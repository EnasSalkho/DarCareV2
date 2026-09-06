<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Enums\ConversationTypeEnum;
use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Chat\Contracts\MessageServiceInterface;
use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Http\Requests\MarkReadRequest;
use App\Modules\Chat\Http\Requests\MuteConversationRequest;
use App\Modules\Chat\Http\Requests\OpenConversationRequest;
use App\Modules\Chat\Http\Requests\SendChatMessageRequest;
use App\Modules\Chat\Http\Resources\ConversationResource;
use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\ConversationParticipant;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ConversationController extends Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    public function __construct(
        private readonly ConversationServiceInterface $conversations,
        private readonly MessageServiceInterface $messages,
        private readonly UnreadMessageServiceInterface $unread
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->conversations->listForActor($request->user(), $request->only([
            'type', 'status', 'search',
        ]));

        $actor = $request->user();
        $items = collect($paginator->items())->map(function (Conversation $conversation) use ($actor) {
            $conversation->unread_count = $this->unread->unreadForConversation($conversation, $actor);

            return $conversation;
        });

        return $this->success([
            'data' => ConversationResource::collection($items),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function store(OpenConversationRequest $request): JsonResponse
    {
        $actor = $request->user();
        
        $type = $request->validated('type');

        $conversation = match ($type) {
            'request' => $this->openRequest($actor, (int) $request->validated('service_request_id')),
            'support_customer' => $this->openCustomerSupport($actor),
            'support_provider' => $this->openProviderSupport($actor),
            'direct' => $this->openDirectChat($actor, (int) $request->validated('receiver_id')), 
        };

        $conversation->unread_count = $this->unread->unreadForConversation($conversation, $actor);

        return $this->success(new ConversationResource($conversation), 'Conversation opened successfully.');
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $this->ensureParticipant($conversation, $actor);

        $conversation->load(['lastMessage', 'participants.participant', 'serviceRequest']);
        $conversation->unread_count = $this->unread->unreadForConversation($conversation, $actor);

        return $this->success(new ConversationResource($conversation));
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();
        $this->ensureParticipant($conversation, $actor);

        $paginator = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return $this->success([
            'data' => MessageResource::collection($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function send(SendChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        // التحقق من الصلاحيات يتم الآن داخل الـ MessageService الذي قمنا بتعديله في الخطوة السابقة
        $message = $this->messages->send(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $request->validated('reply_to_message_id'),
            $request->validated('client_message_id')
        );

        $message->load('sender');

        return $this->created(new MessageResource($message), 'Message sent successfully.');
    }

    public function markRead(MarkReadRequest $request, Conversation $conversation): JsonResponse
    {
        $this->unread->markRead(
            $conversation,
            $request->user(),
            $request->validated('last_message_id')
        );

        return $this->success(null, 'Conversation marked as read.');
    }

    public function mute(MuteConversationRequest $request, Conversation $conversation): JsonResponse
    {
        $actor = $request->user();

        $participant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->first();

        if (! $participant) {
            abort(403, 'Unauthorized. You are not a participant.');
        }

        $participant->update([
            'muted_at' => $request->boolean('muted') ? now() : null,
        ]);

        return $this->success([
            'muted' => $participant->muted_at !== null,
        ], 'Mute preference updated.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success(
            $this->unread->totalUnread($request->user()),
            'Unread count retrieved successfully.'
        );
    }

    private function openRequest(object $actor, int $serviceRequestId): Conversation
    {
        $serviceRequest = ServiceRequest::query()->findOrFail($serviceRequestId);

        return $this->conversations->openRequestConversation($serviceRequest, $actor);
    }

    private function openCustomerSupport(object $actor): Conversation
    {
        if (! $actor instanceof User || ! $actor->isCustomer()) {
            abort(403, 'Only customers can open customer support conversations.');
        }

        return $this->conversations->openCustomerSupport($actor);
    }

    private function openProviderSupport(object $actor): Conversation
    {
        if (! $actor instanceof Provider) {
            abort(403, 'Only providers can open provider support conversations.');
        }

        return $this->conversations->openProviderSupport($actor);
    }

    private function openDirectChat(object $actor, int $receiverId): Conversation
    {
        return $this->conversations->openDirectConversation($actor, $receiverId);
    }

    /**
     * دالة مساعدة للتحقق من أن المستخدم مشارك في المحادثة بدلاً من Gate
     */
    private function ensureParticipant(Conversation $conversation, object $actor): void
    {
        $isParticipant = $conversation->participants()
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->whereNull('left_at')
            ->exists();

        if (! $isParticipant) {
            abort(403, 'This action is unauthorized. You are not a participant.');
        }
    }
}