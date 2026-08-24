<?php

namespace App\Modules\Chat\Http\Resources;

use App\Modules\Chat\Support\ActorHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $participant = $this->relationLoaded('participant') ? $this->participant : null;

        return [
            'type' => $this->participant_type,
            'id' => $this->participant_id,
            'display_role' => $participant ? ActorHelper::displayRole($participant) : $this->participant_type,
            'name' => $participant->name ?? null,
            'joined_at' => optional($this->joined_at)?->toIso8601String(),
            'left_at' => optional($this->left_at)?->toIso8601String(),
            'muted' => $this->muted_at !== null,
            'last_read_at' => optional($this->last_read_at)?->toIso8601String(),
        ];
    }
}
