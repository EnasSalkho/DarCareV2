<?php

namespace App\Modules\Chat\Http\Controllers\Admin;

use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Chat\Contracts\MessageServiceInterface;
use App\Modules\Chat\Http\Requests\SendChatMessageRequest;
use App\Modules\Chat\Http\Resources\ConversationResource;
use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use App\Enums\ConversationTypeEnum;

class AdminConversationController extends Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    public function __construct(
        private readonly ConversationServiceInterface $conversations,
        private readonly MessageServiceInterface $messages
    ) {}

    public function index(Request $request): JsonResponse { $query = Conversation::query() 
    ->with(['lastMessage', 'participants.participant', 'serviceRequest']) 
    ->whereIn('type', [ ConversationTypeEnum::SupportCustomer, ConversationTypeEnum::SupportProvider, ]) 
    ->orderByDesc('last_message_at') 
    ->orderByDesc('id'); 
    if ($request->filled('status')) { 
        $query->where('status', $request->string('status')); } 
    if ($request->filled('customer')) { 
        $customerId = $request->integer('customer'); 
        $query->whereHas('participants', function ($q) use ($customerId) { $q->whereNull('left_at') 
        ->where('participant_type', 'user') ->where('participant_id', $customerId); }); } 
        if ($request->filled('provider')) { $providerId = $request->integer('provider');
         $query->whereHas('participants', function ($q) use ($providerId) { $q->whereNull('left_at') 
         ->where('participant_type', 'provider') ->where('participant_id', $providerId); }); } 
         if ($request->filled('search')) { $search = '%'.$request->string('search').'%'; 
         $query->whereHas('messages', function ($q) use ($search) { $q->where('body', 'like', $search); }); } 
         $paginator = $query->cursorPaginate(20); 
         return $this->success([ 
            'data' => ConversationResource::collection($paginator->items()), 
            'next_cursor' => $paginator->nextCursor()?->encode(), 
            'has_more' => $paginator->hasMorePages(), ]); }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorizeAdminAccess($conversation);
        $conversation->load(['lastMessage', 'participants.participant', 'serviceRequest']);

        return $this->success(new ConversationResource($conversation));
    }

    public function messages(Conversation $conversation): JsonResponse
    {
        $this->authorizeAdminAccess($conversation);

        $paginator = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return $this->success([
            'data' => MessageResource::collection($paginator->items()),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'has_more' => $paginator->hasMorePages(),
        ]);
    }

    public function send(SendChatMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', $conversation);

        $message = $this->messages->send(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $request->validated('reply_to_message_id'),
            $request->validated('client_message_id')
        );

        return $this->created(new MessageResource($message->load('sender')));
    }

    public function close(Conversation $conversation): JsonResponse
    {
        Gate::authorize('close', $conversation);
        $this->conversations->closeSupport($conversation);

        return $this->success(new ConversationResource($conversation->fresh()), 'Conversation closed.');
    }

    public function reopen(Conversation $conversation): JsonResponse
    {
        Gate::authorize('reopen', $conversation);
        $this->conversations->reopenSupport($conversation);

        return $this->success(new ConversationResource($conversation->fresh()), 'Conversation reopened.');
    }

    private function authorizeAdminAccess(Conversation $conversation): void
    {
        if (! $conversation->isSupport()) {
            abort(403, 'Admins can only access support conversations.');
        }

        Gate::authorize('view', $conversation);
    }
}
