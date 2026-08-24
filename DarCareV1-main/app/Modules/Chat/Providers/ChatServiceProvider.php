<?php
// app/Modules/Chat/Providers/ChatServiceProvider.php

namespace App\Modules\Chat\Providers;

use App\Modules\Chat\Contracts\ChatServiceInterface;
use App\Modules\Chat\Contracts\ConversationServiceInterface;
use App\Modules\Chat\Contracts\MessageNotificationServiceInterface;
use App\Modules\Chat\Contracts\MessageServiceInterface;
use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Models\Conversation;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Services\ChatService;
use App\Modules\Chat\Services\ConversationService;
use App\Modules\Chat\Services\MessageNotificationService;
use App\Modules\Chat\Services\MessageService;
use App\Modules\Chat\Services\UnreadMessageService;
use App\Policies\ConversationPolicy;
use App\Policies\MessagePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ChatServiceInterface::class, ChatService::class);
        $this->app->bind(ConversationServiceInterface::class, ConversationService::class);
        $this->app->bind(MessageServiceInterface::class, MessageService::class);
        $this->app->bind(UnreadMessageServiceInterface::class, UnreadMessageService::class);
        $this->app->bind(MessageNotificationServiceInterface::class, MessageNotificationService::class);
    }

    public function boot(): void
    {
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
    }
}
