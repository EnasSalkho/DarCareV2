<?php

namespace App\Policies;

use App\Enums\ConversationStatusEnum;
use App\Enums\ConversationTypeEnum;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversationPolicy
{
    public function view(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        if ($this->isAdmin($actor) && $conversation->isSupport()) {
            return true;
        }

        if ($this->isActiveParticipant($conversation, $actor)) {
            return true;
        }

        return $this->canAccessAsRequestParty($conversation, $actor);
    }

    public function sendMessage(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        if ($conversation->status !== ConversationStatusEnum::Open) {
            return false;
        }

        if ($this->isAdmin($actor) && $conversation->isSupport()) {
            return true;
        }

        return $this->isActiveParticipant($conversation, $actor);
    }

    public function markRead(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        if ($this->isAdmin($actor) && $conversation->isSupport()) {
            return true;
        }

        if ($this->isActiveParticipant($conversation, $actor)) {
            return true;
        }

        return $this->canAccessAsRequestParty($conversation, $actor);
    }

    public function mute(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        if ($this->isActiveParticipant($conversation, $actor)) {
            return true;
        }

        return $this->canAccessAsRequestParty($conversation, $actor);
    }

    public function close(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        return $this->isAdmin($actor) && $conversation->isSupport();
    }

    public function reopen(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        return $this->isAdmin($actor) && $conversation->isSupport();
    }

    /**
     * Admins may inspect request conversations without being participants.
     */
    public function adminInspect(mixed $actor, Conversation $conversation): bool
    {
        if ($this->isTrashed($actor)) {
            return false;
        }

        return $this->isAdmin($actor)
            && $conversation->type === ConversationTypeEnum::Request;
    }

    private function isTrashed(mixed $actor): bool
    {
        if (! $actor instanceof Model) {
            return false;
        }

        if (! in_array(SoftDeletes::class, class_uses_recursive($actor), true)) {
            return false;
        }

        return method_exists($actor, 'trashed') && $actor->trashed();
    }

    private function isAdmin(mixed $actor): bool
    {
        return $actor instanceof User && $actor->isAdmin();
    }

    private function isActiveParticipant(Conversation $conversation, mixed $actor): bool
    {
        if (! is_object($actor)) {
            return false;
        }

        return $conversation->participants()
            ->whereNull('left_at')
            ->where('participant_type', ActorHelper::morphType($actor))
            ->where('participant_id', ActorHelper::morphId($actor))
            ->exists();
    }

    /**
     * Request conversations: customer owner or assigned provider may access
     * even when participation rows are missing / being checked.
     */
    private function canAccessAsRequestParty(Conversation $conversation, mixed $actor): bool
    {
        if ($conversation->type !== ConversationTypeEnum::Request) {
            return false;
        }

        $request = $conversation->relationLoaded('serviceRequest')
            ? $conversation->serviceRequest
            : $conversation->serviceRequest()->first();

        if (! $request) {
            return false;
        }

        if ($actor instanceof User && $actor->isCustomer()) {
            return (int) $actor->id === (int) $request->user_id;
        }

        if ($actor instanceof Provider) {
            return $request->provider_id !== null
                && (int) $actor->id === (int) $request->provider_id;
        }

        return false;
    }
}
