<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Future cleanup (do not run in this release):
 * - Drop messages.service_request_id after all clients use conversation APIs
 * - Drop users.fcm_token and providers.fcm_token after device_tokens adoption
 * - Make messages.conversation_id non-nullable
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally empty placeholder documenting future cleanup.
        // See docs/CHAT_AND_NOTIFICATIONS_2026-08-23.md
    }

    public function down(): void
    {
        //
    }
};
