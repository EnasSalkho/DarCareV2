<?php
// app/Modules/Notifications/Contracts/NotificationServiceInterface.php

namespace App\Modules\Notifications\Contracts;

interface NotificationServiceInterface
{
    public function sendToUser(int $userId, string $type, array $data): void;

    public function sendToProvider(int $providerId, string $type, array $data): void;

    /**
     * @return array{
     *     success: bool,
     *     message: string,
     *     batch_id?: string,
     *     recipients?: int,
     *     devices_attempted?: int,
     *     sent?: int,
     *     failed?: int,
     *     invalidated?: int,
     *     database_stored?: int
     * }
     */
    public function sendBulkNotification(
        string $target,
        string $title,
        string $message,
        ?int $recipientId = null,
        string $recipientType = 'user'
    ): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeDatabaseNotification(object $notifiable, string $type, array $data, array $extra = []): object;

    /**
     * @param  list<string>  $tokens
     * @param  array<string, mixed>  $data
     * @return array{attempted: int, sent: int, failed: int, invalidated: int}
     */
    public function sendFcmToTokens(array $tokens, string $title, string $body, array $data = []): array;

    /**
     * Send FCM push immediately (no queue) without creating another database notification.
     *
     * @param  array<string, mixed>  $data
     * @return array{attempted: int, sent: int, failed: int, invalidated: int}
     */
    public function sendPushImmediately(object $notifiable, string $type, array $data): array;

    public function hasFirebaseCredentials(): bool;
}
