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

// القناة الخاصة بالعميل العادي (Client)
Broadcast::channel('client.{id}', function ($user, int $id) {
    return $user instanceof User && (int) $user->id === (int) $id;
});

// القناة الخاصة بمزود الخدمة (Provider)
Broadcast::channel('user.{id}', function ($user, int $id) {
    return $user instanceof Provider && (int) $user->id === (int) $id;
});

Broadcast::channel('service-request.{id}', function ($user, $id) {
    $serviceRequest = ServiceRequest::find($id);
    
    if (!$serviceRequest) {
        return false;
    }

    // السماح فقط للمستخدم صاحب الطلب أو مزود الخدمة بالاستماع لهذه القناة.
    // لا بد من التحقق من النوع أيضاً: بدونه يستطيع مزود رقمه 5 الاشتراك في قناة
    // طلب يعود للمستخدم رقم 5.
    if ($user instanceof User) {
        return (int) $user->id === (int) $serviceRequest->user_id;
    }

    if ($user instanceof Provider) {
        return $serviceRequest->provider_id !== null
            && (int) $user->id === (int) $serviceRequest->provider_id;
    }

    return false;
});
