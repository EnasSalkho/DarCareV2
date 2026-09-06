<?php

use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use App\Policies\ConversationPolicy;
use Illuminate\Support\Facades\Broadcast;
use App\Modules\ServiceRequests\Models\ServiceRequest;

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    $conversation = Conversation::query()->find($conversationId);

    if (! $conversation) {
        return false;
    }

    $policy = app(ConversationPolicy::class);

    if ($policy->view($user, $conversation)) {
        return true;
    }

    if ($user instanceof User && $user->isAdmin()) {
        return $policy->adminInspect($user, $conversation) || $conversation->isSupport();
    }

    return false;
});

Broadcast::channel('user.user.{userId}', function ($user, int $userId) {
    return $user instanceof User && (int) $user->id === (int) $userId;
});

Broadcast::channel('user.provider.{providerId}', function ($user, int $providerId) {
    return $user instanceof Provider && (int) $user->id === (int) $providerId;
});

Broadcast::channel('service-request.{id}', function ($user, $id) {
    $serviceRequest = ServiceRequest::find($id);
    
    if (!$serviceRequest) {
        return false;
    }

    // السماح فقط للمستخدم صاحب الطلب أو مزود الخدمة بالاستماع لهذه القناة
    return $user->id === $serviceRequest->user_id || $user->id === $serviceRequest->provider_id;
});
