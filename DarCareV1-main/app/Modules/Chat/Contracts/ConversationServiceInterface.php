<?php

namespace App\Modules\Chat\Contracts;

use App\Modules\Chat\Models\Conversation;
use App\Modules\Providers\Models\Provider;
use App\Modules\ServiceRequests\Models\ServiceRequest;
use App\Modules\Users\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

interface ConversationServiceInterface
{
    public function openRequestConversation(ServiceRequest $request, object $actor): Conversation;

    public function openCustomerSupport(User $user): Conversation;

    public function openProviderSupport(Provider $provider): Conversation;

    public function handleProviderReassignment(
        ServiceRequest $request,
        ?int $oldProviderId,
        ?int $newProviderId
    ): void;

    public function setRequestConversationReadOnly(ServiceRequest $request): void;

    public function closeSupport(Conversation $conversation): void;

    public function reopenSupport(Conversation $conversation): void;

    public function listForActor(object $actor, array $filters = []): CursorPaginator;

    public function findForActor(int $id, object $actor): Conversation;
}
