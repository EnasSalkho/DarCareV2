<?php

namespace App\Modules\Chat\Http\Controllers;

use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Chat\Contracts\MessageServiceInterface;
use App\Modules\Chat\Http\Requests\SendChatMessageRequest;
use App\Modules\Chat\Http\Resources\MessageResource;
use App\Modules\Chat\Models\Message;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

class ChatController extends Controller
{
    use ApiResponseTrait, AuthorizesRequests;

    public function __construct(
        private readonly ConversationServiceInterface $conversations,
        private readonly MessageServiceInterface $messages
    ) {}

    public function index(Request $request, int $requestId): JsonResponse
    {
        $serviceRequest = ServiceRequest::query()->findOrFail($requestId);
        Gate::authorize('view', $serviceRequest);

        $conversation = $this->conversations->openRequestConversation($serviceRequest, $request->user());

        $paginator = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'message' => 'Success',
            'data' => MessageResource::collection($paginator),
            'errors' => null,
            'meta' => [
                'deprecated' => true,
                'replacement_endpoint' => '/api/v1/chat/conversations/'.$conversation->id.'/messages',
                'conversation_id' => $conversation->id,
            ],
        ]);
    }

    public function send(SendChatMessageRequest $request, int $requestId): JsonResponse
    {
        $serviceRequest = ServiceRequest::query()->findOrFail($requestId);
        Gate::authorize('view', $serviceRequest);

        $conversation = $this->conversations->openRequestConversation($serviceRequest, $request->user());

        $message = $this->messages->send(
            $conversation,
            $request->user(),
            $request->validated('body'),
            $request->validated('reply_to_message_id'),
            $request->validated('client_message_id')
        );

        return response()->json([
            'success' => true,
            'message' => 'Created successfully',
            'data' => new MessageResource($message->load('sender')),
            'errors' => null,
            'meta' => [
                'deprecated' => true,
                'replacement_endpoint' => '/api/v1/chat/conversations/'.$conversation->id.'/messages',
                'conversation_id' => $conversation->id,
            ],
        ], 201);
    }
}
