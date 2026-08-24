<?php

namespace App\Modules\Notifications\Services;

use App\Modules\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DeviceTokenService
{
    public function register(
        Model $actor,
        string $token,
        string $platform,
        ?string $deviceName = null,
        ?string $deviceIdentifier = null
    ): DeviceToken {
        return DB::transaction(function () use ($actor, $token, $platform, $deviceName, $deviceIdentifier) {
            $existing = DeviceToken::query()->where('token', $token)->lockForUpdate()->first();

            if ($existing) {
                $existing->forceFill([
                    'tokenable_type' => $actor->getMorphClass(),
                    'tokenable_id' => (int) $actor->getKey(),
                    'platform' => $platform,
                    'device_name' => $deviceName,
                    'device_identifier' => $deviceIdentifier,
                    'last_used_at' => now(),
                    'invalidated_at' => null,
                ])->save();

                return $existing->refresh();
            }

            return DeviceToken::create([
                'tokenable_type' => $actor->getMorphClass(),
                'tokenable_id' => $actor->getKey(),
                'token' => $token,
                'platform' => $platform,
                'device_name' => $deviceName,
                'device_identifier' => $deviceIdentifier,
                'last_used_at' => now(),
                'invalidated_at' => null,
            ]);
        });
    }

    public function remove(Model $actor, string $token): bool
    {
        $deviceToken = DeviceToken::query()
            ->where('token', $token)
            ->where('tokenable_type', $actor->getMorphClass())
            ->where('tokenable_id', $actor->getKey())
            ->first();

        if (! $deviceToken) {
            return false;
        }

        return (bool) $deviceToken->delete();
    }

    public function invalidateToken(string $token): void
    {
        DeviceToken::query()
            ->where('token', $token)
            ->whereNull('invalidated_at')
            ->update(['invalidated_at' => now()]);
    }
}
