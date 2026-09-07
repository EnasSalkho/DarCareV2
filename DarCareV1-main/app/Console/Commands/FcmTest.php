<?php

namespace App\Console\Commands;

use App\Modules\Notifications\Contracts\NotificationServiceInterface;
use App\Modules\Notifications\Models\DeviceToken;
use App\Modules\Providers\Models\Provider;
use App\Modules\Users\Models\User;
use Illuminate\Console\Command;

/**
 * Diagnostics for push notifications.
 *
 * Push failures are mostly silent: when a recipient has no device token,
 * sendPushImmediately() returns without logging anything at all. This command
 * makes the state visible and lets a push be sent by hand.
 */
class FcmTest extends Command
{
    protected $signature = 'fcm:test
        {token? : FCM device token to send to. Omit to only print the current state.}
        {--title=DarCare : Notification title}
        {--body=Test notification : Notification body}';

    protected $description = 'Show push notification state and optionally send a test push';

    public function handle(NotificationServiceInterface $notifications): int
    {
        $this->info('=== Configuration ===');
        $this->line('  FIREBASE_PROJECT_ID : '.config('services.firebase.project_id'));
        $this->line('  credentials file    : '.config('services.firebase.credentials'));

        $ready = $notifications->hasFirebaseCredentials();
        $this->line('  credentials usable  : '.($ready ? '<fg=green>yes</>' : '<fg=red>NO</>'));

        if (! $ready) {
            $this->error('Firebase credentials are missing or the project id is empty.');
            $this->line('Check FIREBASE_PROJECT_ID and FIREBASE_CREDENTIALS in .env, then run: php artisan config:clear');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Registered devices ===');
        $tokens = DeviceToken::query()->orderByDesc('id')->get();

        if ($tokens->isEmpty()) {
            $this->warn('  No device tokens at all. Every push will be skipped silently.');
            $this->line('  A device registers on login, via POST /api/v1/notifications/device-tokens.');
        } else {
            $this->table(
                ['id', 'owner', 'platform', 'token', 'invalidated', 'last used'],
                $tokens->map(fn (DeviceToken $t) => [
                    $t->id,
                    $t->tokenable_type.'#'.$t->tokenable_id,
                    $t->platform,
                    substr($t->token, 0, 14).'...'.substr($t->token, -6),
                    $t->invalidated_at ? 'YES' : '-',
                    (string) $t->last_used_at,
                ])->all()
            );
        }

        $this->newLine();
        $this->info('=== Who can actually receive a push ===');
        foreach ([['user', User::class], ['provider', Provider::class]] as [$label, $model]) {
            foreach ($model::query()->get() as $record) {
                $count = $record->deviceTokens()->valid()->count();
                $this->line(sprintf(
                    '  %s#%-4s %s',
                    $label,
                    $record->getKey(),
                    $count > 0 ? "<fg=green>{$count} device(s)</>" : '<fg=red>no device - push is skipped</>'
                ));
            }
        }

        $token = $this->argument('token');
        if (! $token) {
            $this->newLine();
            $this->comment('Pass a token to send a test push: php artisan fcm:test <FCM_TOKEN>');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('=== Sending ===');
        $result = $notifications->sendFcmToTokens(
            [$token],
            (string) $this->option('title'),
            (string) $this->option('body'),
            ['type' => 'test', 'route' => 'notifications']
        );

        $this->line('  '.json_encode($result));

        if ($result['sent'] > 0) {
            $this->info('  Google accepted the message. It should appear on the device.');

            return self::SUCCESS;
        }

        $this->error('  Send failed. See storage/logs/laravel.log for the FCM error code.');

        return self::FAILURE;
    }
}
