<?php
// app/Modules/ServiceRequests/Services/ServiceRequestService.php

namespace App\Modules\ServiceRequests\Services;

use App\Enums\RequestStatusEnum;
use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Contracts\ServiceRequestServiceInterface;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use App\Services\LocationClient;
use App\Modules\ServiceRequests\Events\RequestStatusUpdated;
use Throwable;

class ServiceRequestService implements ServiceRequestServiceInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
        private readonly ConversationServiceInterface $conversationService,
        private readonly LocationClient $locationClient
    ) {}

    public function createRequest(int $userId, array $data): object
    {
        $request = DB::transaction(function () use ($userId, $data) {
            return ServiceRequest::create([
                'user_id' => $userId,
                'provider_id' => $data['provider_id'],
                'category_id' => $data['category_id'],
                'address_id' => $data['address_id'] ?? null,
                'description' => $data['description'],
                'urgency' => $data['urgency'] ?? 'normal',
                'image' => $data['image'] ?? null,
                'status' => 'pending',
                'temp_latitude' => $data['temp_latitude'] ?? null,
                'temp_longitude' => $data['temp_longitude'] ?? null,
                'temp_label' => $data['temp_label'] ?? null,
            ]);
        });

        $this->notifySafely(function () use ($data, $request) {
            $this->notificationService->sendToProvider(
                (int) $data['provider_id'],
                'new_request',
                [
                    'title' => 'New service request',
                    'body' => 'You have a new service request',
                    'message' => 'You have a new service request',
                    'request_id' => $request->id,
                    'service_request_id' => $request->id,
                    'route' => 'service_request',
                ]
            );
        }, 'new_request', $request->id);

        return $request;
    }

    public function getUserRequests(int $userId): mixed
    {
        $requests = ServiceRequest::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $requests->getCollection()->transform(function ($request) {
            $request->address = $request->address_id
                ? $this->locationClient->getAddress($request->address_id)
                : null;

            return $request;
        });

        return $requests;
    }

    public function getProviderRequests(int $providerId): mixed
    {
        $requests = ServiceRequest::where('provider_id', $providerId)
            ->orderByDesc('created_at')
            ->paginate(15);

        $requests->getCollection()->transform(function ($request) {
            $request->address = $request->address_id
                ? $this->locationClient->getAddress($request->address_id)
                : null;

            return $request;
        });

        return $requests;
    }

    public function updateStatus(int $requestId, int $providerId, string $status, ?string $scheduledAt): object
    {
        $request = ServiceRequest::where('id', $requestId)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        if ((string) $request->status === $status) {
            return $request;
        }

        $request = DB::transaction(function () use ($request, $status, $scheduledAt) {
            $request->update([
                'status' => $status,
                'scheduled_at' => $scheduledAt,
            ]);

            $request = $request->fresh();

            $statusEnum = RequestStatusEnum::tryFrom($status);

            if ($statusEnum?->isFinal()) {
                $this->conversationService->setRequestConversationReadOnly($request);
            }
            broadcast(new RequestStatusUpdated($request));

            return $request;
        });

        $this->notifySafely(function () use ($request, $status) {
            $this->notificationService->sendToUser(
                (int) $request->user_id,
                'request_'.$status,
                [
                    'title' => 'Request updated',
                    'body' => "Your request has been {$status}",
                    'message' => "Your request has been {$status}",
                    'request_id' => $request->id,
                    'service_request_id' => $request->id,
                    'status' => $status,
                    'route' => 'service_request',
                ]
            );
        }, 'request_'.$status, $request->id);

        return $request;
    }

    public function find(int $requestId): object
    {
        $request = ServiceRequest::findOrFail($requestId);

        $request->address = $request->address_id
            ? $this->locationClient->getAddress($request->address_id)
            : null;

        return $request;
    }

    public function getAllRequestsForAdmin(?string $status, ?string $urgency): mixed
    {
        $requests = ServiceRequest::with([
            'user:id,name,phone',
            'provider:id,name,phone',
            'category:id,name',
        ]);

        if ($status) {
            $requests->where('status', $status);
        }

        if ($urgency) {
            $requests->where('urgency', $urgency);
        }

        $requests = $requests
            ->orderByDesc('created_at')
            ->paginate(15);

        $requests->getCollection()->transform(function ($request) {
            $request->address = $request->address_id
                ? $this->locationClient->getAddress($request->address_id)
                : null;

            return $request;
        });

        return $requests;
    }

    public function getRequestDetailsForAdmin(int $requestId): object
    {
        $request = ServiceRequest::with([
            'user',
            'provider',
            'category',
        ])->findOrFail($requestId);

        // جلب العنوان من Location Service
        $request->address = $request->address_id
            ? $this->locationClient->getAddress($request->address_id)
            : null;

        return $request;
    }

    public function reassignProvider(int $requestId, int $providerId): object
    {
        $request = ServiceRequest::query()->findOrFail($requestId);

        Provider::query()->findOrFail($providerId);

        if ((int) $request->provider_id === $providerId) {
            $request->load([
                'user',
                'provider',
                'category',
            ]);

            // جلب العنوان من Location Service
            $request->address = $request->address_id
                ? $this->locationClient->getAddress($request->address_id)
                : null;

            return $request;
        }

        if ($request->isFinalStatus()) {
            throw ValidationException::withMessages([
                'provider_id' => 'Cannot reassign a request that is already finalized.',
            ]);
        }

        $oldProviderId = $request->provider_id
            ? (int) $request->provider_id
            : null;

        $request = DB::transaction(function () use (
            $request,
            $providerId,
            $oldProviderId
        ) {
            $request->update([
                'provider_id' => $providerId
            ]);

            $request = $request->fresh();

            $this->conversationService->handleProviderReassignment(
                $request,
                $oldProviderId,
                $providerId
            );

            return $request;
        });

        $this->notifySafely(function () use ($providerId, $request) {
            $this->notificationService->sendToProvider(
                $providerId,
                'request_reassigned',
                [
                    'title' => 'Request assigned',
                    'body' => 'A service request has been assigned to you',
                    'message' => 'A service request has been assigned to you',
                    'request_id' => $request->id,
                    'service_request_id' => $request->id,
                    'route' => 'service_request',
                ]
            );
        }, 'request_reassigned', $request->id);

        if ($oldProviderId) {
            $this->notifySafely(function () use ($oldProviderId, $request) {
                $this->notificationService->sendToProvider(
                    $oldProviderId,
                    'request_unassigned',
                    [
                        'title' => 'Request reassigned',
                        'body' => 'A service request was reassigned to another provider',
                        'message' => 'A service request was reassigned to another provider',
                        'request_id' => $request->id,
                        'service_request_id' => $request->id,
                        'route' => 'service_request',
                    ]
                );
            }, 'request_unassigned', $request->id);
        }

        $request->load([
            'user',
            'provider',
            'category',
        ]);

        // جلب العنوان من Location Service
        $request->address = $request->address_id
            ? $this->locationClient->getAddress($request->address_id)
            : null;

        return $request;
    }

    private function notifySafely(callable $callback, string $type, int $requestId): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            Log::warning('Service request notification failed after commit', [
                'service' => 'notifications',
                'type' => $type,
                'service_request_id' => $requestId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
}
