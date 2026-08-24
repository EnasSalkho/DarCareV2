<?php

namespace App\Modules\Chat\Services;

use App\Modules\Chat\Contracts\MessageNotificationServiceInterface;
use App\Modules\Chat\Contracts\UnreadMessageServiceInterface;
use App\Modules\Chat\Events\UnreadCountUpdated;
use App\Modules\Chat\Models\Message;
use App\Modules\Chat\Support\ActorHelper;
use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class MessageNotificationService implements MessageNotificationServiceInterface
{
    public function __construct(
        private readonly NotificationServiceInterface $notificationService,
        private readonly UnreadMessageServiceInterface $unreadMessageService
    ) {}

    public function notifyRecipientsImmediately(Message $message): array
    {
        $message = $message->fresh(['conversation.participants.participant', 'sender']);

        $firebaseResult = ['attempted' => 0, 'sent' => 0, 'failed' => 0, 'invalidated' => 0];

        if (! $message || ! $message->conversation) {
            return [
                'firebase' => $firebaseResult,
            ];
        }

        $conversation = $message->conversation;
        $sender = $message->sender;
        $preview = $this->previewBody((string) $message->body);

        $data = [
            'title' => 'New message',
            'body' => $preview,
            'type' => 'chat_message',
            'conversation_id' => (string) $conversation->id,
            'message_id' => (string) $message->id,
            'service_request_id' => $conversation->service_request_id !== null
                ? (string) $conversation->service_request_id
                : null,
            'route' => 'chat',
        ];

        if ($data['service_request_id'] === null) {
            unset($data['service_request_id']);
        }

        foreach ($this->resolveRecipients($conversation, $sender) as $recipient) {
            if (! ($recipient instanceof User || $recipient instanceof Provider)) {
                continue;
            }

            try {
                $channel = ActorHelper::channelName($recipient);

                $unread = $this->unreadMessageService->totalUnread($recipient);

                broadcast(new UnreadCountUpdated($channel, [
                    'conversation_id' => $conversation->id,
                    'unread' => $unread,
                    'conversation_unread' => $this->unreadMessageService->unreadForConversation(
                        $conversation,
                        $recipient
                    ),
                ]));
            } catch (Throwable $e) {
                Log::warning('Chat recipient realtime broadcast failed', [
                    'service' => 'pusher',
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'recipient_type' => ActorHelper::morphType($recipient),
                    'recipient_id' => ActorHelper::morphId($recipient),
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $push = $this->notificationService->sendPushImmediately(
                    $recipient,
                    'chat_message',
                    $data
                );
                $firebaseResult['attempted'] += $push['attempted'];
                $firebaseResult['sent'] += $push['sent'];
                $firebaseResult['failed'] += $push['failed'];
                $firebaseResult['invalidated'] += $push['invalidated'];
            } catch (Throwable $e) {
                $firebaseResult['failed']++;
                Log::warning('Chat Firebase notification failed', [
                    'service' => 'firebase',
                    'conversation_id' => $conversation->id,
                    'message_id' => $message->id,
                    'recipient_type' => ActorHelper::morphType($recipient),
                    'recipient_id' => ActorHelper::morphId($recipient),
                    'exception' => $e::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'firebase' => $firebaseResult,
        ];
    }

    private function previewBody(string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if (mb_strlen($body) <= 80) {
            return $body;
        }

        return mb_substr($body, 0, 77).'...';
    }

    /**
     * @return Collection<int, User|Provider>
     */
    private function resolveRecipients(object $conversation, mixed $sender): Collection
    {
        $recipients = collect();

        if ($conversation->isSupport()) {
            $senderIsAdmin = $sender instanceof User && $sender->isAdmin();

            if ($senderIsAdmin) {
                foreach ($conversation->participants as $participantRow) {
                    if ($participantRow->left_at !== null || $participantRow->isMuted()) {
                        continue;
                    }

                    $participant = $participantRow->participant;

                    if (! $participant) {
                        continue;
                    }

                    if ($participant instanceof User && $participant->isAdmin()) {
                        continue;
                    }

                    if (ActorHelper::isSameActor($sender, $participant)) {
                        continue;
                    }

                    $recipients->push($participant);
                }
            } else {
                $recipients = $recipients->merge(
                    User::query()->where('role', 'admin')->get()
                );
            }

            return $recipients
                ->filter(fn ($r) => $r instanceof User || $r instanceof Provider)
                ->unique(fn ($r) => ActorHelper::morphType($r).':'.ActorHelper::morphId($r))
                ->values();
        }

        foreach ($conversation->participants as $participantRow) {
            if ($participantRow->left_at !== null || $participantRow->isMuted()) {
                continue;
            }

            $participant = $participantRow->participant;

            if (! $participant || ActorHelper::isSameActor($sender, $participant)) {
                continue;
            }

            $recipients->push($participant);
        }

        return $recipients
            ->filter(fn ($r) => $r instanceof User || $r instanceof Provider)
            ->unique(fn ($r) => ActorHelper::morphType($r).':'.ActorHelper::morphId($r))
            ->values();
    }
}
