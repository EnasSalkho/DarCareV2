<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $actor = $request->user();
        $unread = $this->unread_count ?? null;

        if ($unread === null && $actor && isset($this->resource->_unreadService)) {
            $unread = $this->resource->_unreadService->unreadForConversation($this->resource, $actor);
        }

        $muted = false;
        if ($actor && $this->relationLoaded('participants')) {
            $row = $this->participants->first(function ($p) use ($actor) {
                return $p->participant_type === ActorHelper::morphType($actor)
                    && (int) $p->participant_id === ActorHelper::morphId($actor)
                    && $p->left_at === null;
            });
            $muted = $row?->muted_at !== null;
        }

        return [
            'id' => $this->id,
            'type' => $this->type?->value ?? $this->type,
            'status' => $this->status?->value ?? $this->status,
            'service_request' => $this->when(
                $this->relationLoaded('serviceRequest') && $this->serviceRequest,
                fn () => new ServiceRequestSummaryResource($this->serviceRequest)
            ),
            'participants' => ParticipantResource::collection(
                $this->whenLoaded('participants', fn () => $this->participants->whereNull('left_at')->values())
            ),
            'last_message' => $this->when(
                $this->relationLoaded('lastMessage') && $this->lastMessage,
                fn () => new MessageResource($this->lastMessage)
            ),
            'last_message_at' => optional($this->last_message_at)?->toIso8601String(),
            'unread_count' => $unread ?? 0,
            'muted' => $muted,
            'closed_at' => optional($this->closed_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
