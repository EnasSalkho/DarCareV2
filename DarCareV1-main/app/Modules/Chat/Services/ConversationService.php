<?php

namespace App\Modules\Chat\Services;

use App\Enums\ConversationStatusEnum;
use App\Enums\ConversationTypeEnum;
use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ConversationService implements ConversationServiceInterface
{
    public function __construct(
        private readonly UnreadMessageServiceInterface $unreadMessageService
    ) {}

    public function openRequestConversation(ServiceRequest $request, object $actor): Conversation
    {
        Gate::forUser($actor)->authorize('view', $request);

        return DB::transaction(function () use ($request, $actor) {
            $conversation = Conversation::query()
                ->where('type', ConversationTypeEnum::Request)
                ->where('service_request_id', $request->id)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'type' => ConversationTypeEnum::Request,
                    'service_request_id' => $request->id,
                    'status' => $request->isFinalStatus()
                        ? ConversationStatusEnum::ReadOnly
                        : ConversationStatusEnum::Open,
                    'created_by_type' => ActorHelper::morphType($actor),
                    'created_by_id' => ActorHelper::morphId($actor),
                ]);
            } elseif ($request->isFinalStatus() && $conversation->status !== ConversationStatusEnum::ReadOnly) {
                $conversation->update(['status' => ConversationStatusEnum::ReadOnly]);
            }

            $customer = $request->relationLoaded('user')
                ? $request->user
                : $request->user()->first();

            if ($customer) {
                $this->ensureParticipant($conversation, $customer);
            }

            if ($request->provider_id) {
                $provider = $request->relationLoaded('provider')
                    ? $request->provider
                    : $request->provider()->first();

                if ($provider) {
                    $this->ensureParticipant($conversation, $provider);
                }
            }

            return $conversation->fresh(['lastMessage', 'participants', 'serviceRequest']);
        });
    }

    public function openCustomerSupport(User $user): Conversation
    {
        if (! $user->isCustomer()) {
            throw new AuthorizationException('Only customers can open customer support conversations.');
        }

        return DB::transaction(function () use ($user) {
            $existing = Conversation::query()
                ->where('type', ConversationTypeEnum::SupportCustomer)
                ->whereHas('participants', function ($query) use ($user) {
                    $query->whereNull('left_at')
                        ->where('participant_type', 'user')
                        ->where('participant_id', $user->id);
                })
                ->first();

            if ($existing) {
                return $existing->load(['lastMessage', 'participants', 'serviceRequest']);
            }

            $conversation = Conversation::create([
                'type' => ConversationTypeEnum::SupportCustomer,
                'service_request_id' => null,
                'status' => ConversationStatusEnum::Open,
                'created_by_type' => ActorHelper::morphType($user),
                'created_by_id' => ActorHelper::morphId($user),
            ]);

            $this->ensureParticipant($conversation, $user);

            return $conversation->fresh(['lastMessage', 'participants', 'serviceRequest']);
        });
    }

    public function openProviderSupport(Provider $provider): Conversation
    {
        return DB::transaction(function () use ($provider) {
            $existing = Conversation::query()
                ->where('type', ConversationTypeEnum::SupportProvider)
                ->whereHas('participants', function ($query) use ($provider) {
                    $query->whereNull('left_at')
                        ->where('participant_type', 'provider')
                        ->where('participant_id', $provider->id);
                })
                ->first();

            if ($existing) {
                return $existing->load(['lastMessage', 'participants', 'serviceRequest']);
            }

            $conversation = Conversation::create([
                'type' => ConversationTypeEnum::SupportProvider,
                'service_request_id' => null,
                'status' => ConversationStatusEnum::Open,
                'created_by_type' => ActorHelper::morphType($provider),
                'created_by_id' => ActorHelper::morphId($provider),
            ]);

            $this->ensureParticipant($conversation, $provider);

            return $conversation->fresh(['lastMessage', 'participants', 'serviceRequest']);
        });
    }

    public function handleProviderReassignment(
        ServiceRequest $request,
        ?int $oldProviderId,
        ?int $newProviderId
    ): void {
        DB::transaction(function () use ($request, $oldProviderId, $newProviderId) {
            $conversation = Conversation::query()
                ->where('type', ConversationTypeEnum::Request)
                ->where('service_request_id', $request->id)
                ->first();

            if (! $conversation) {
                return;
            }

            if ($oldProviderId) {
                $this->leaveParticipant($conversation, 'provider', $oldProviderId);
            }

            if ($newProviderId) {
                $provider = Provider::query()->find($newProviderId);
                if ($provider) {
                    $this->ensureParticipant($conversation, $provider);
                }
            }
        });
    }

    public function setRequestConversationReadOnly(ServiceRequest $request): void
    {
        Conversation::query()
            ->where('type', ConversationTypeEnum::Request)
            ->where('service_request_id', $request->id)
            ->update([
                'status' => ConversationStatusEnum::ReadOnly,
            ]);
    }

    public function closeSupport(Conversation $conversation): void
    {
        if (! $conversation->isSupport()) {
            throw ValidationException::withMessages([
                'conversation' => 'Only support conversations can be closed.',
            ]);
        }

        $conversation->update([
            'status' => ConversationStatusEnum::Closed,
            'closed_at' => now(),
        ]);
    }

    public function reopenSupport(Conversation $conversation): void
    {
        if (! $conversation->isSupport()) {
            throw ValidationException::withMessages([
                'conversation' => 'Only support conversations can be reopened.',
            ]);
        }

        $conversation->update([
            'status' => ConversationStatusEnum::Open,
            'closed_at' => null,
        ]);
    }

    public function listForActor(object $actor, array $filters = []): CursorPaginator
    {
        $query = Conversation::query()
            ->with(['lastMessage', 'participants', 'serviceRequest'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($actor instanceof User && $actor->isAdmin()) {
            $query->whereIn('type', [
                ConversationTypeEnum::SupportCustomer,
                ConversationTypeEnum::SupportProvider,
            ]);
        } else {
            $query->whereHas('participants', function ($q) use ($actor) {
                $q->whereNull('left_at')
                    ->where('participant_type', ActorHelper::morphType($actor))
                    ->where('participant_id', ActorHelper::morphId($actor));
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        /** @var CursorPaginator $paginator */
        $paginator = $query->cursorPaginate(max(1, min($perPage, 50)));

        $paginator->getCollection()->transform(function (Conversation $conversation) use ($actor) {
            $conversation->setAttribute(
                'unread_count',
                $this->unreadMessageService->unreadForConversation($conversation, $actor)
            );

            return $conversation;
        });

        return $paginator;
    }

    public function findForActor(int $id, object $actor): Conversation
    {
        $conversation = Conversation::query()
            ->with(['lastMessage', 'participants', 'serviceRequest'])
            ->findOrFail($id);

        Gate::forUser($actor)->authorize('view', $conversation);

        $conversation->setAttribute(
            'unread_count',
            $this->unreadMessageService->unreadForConversation($conversation, $actor)
        );

        return $conversation;
    }

    private function ensureParticipant(Conversation $conversation, object $actor): void
    {
        $participant = ConversationParticipant::query()->firstOrNew([
            'conversation_id' => $conversation->id,
            'participant_type' => ActorHelper::morphType($actor),
            'participant_id' => ActorHelper::morphId($actor),
        ]);

        $participant->left_at = null;
        $participant->joined_at = $participant->joined_at ?? now();
        $participant->save();
    }

    private function leaveParticipant(Conversation $conversation, string $type, int $id): void
    {
        ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('participant_type', $type)
            ->where('participant_id', $id)
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }
}
