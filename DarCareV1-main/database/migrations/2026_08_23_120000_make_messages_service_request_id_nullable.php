<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make messages.service_request_id nullable.
 *
 * Request conversations still store the related service request id.
 * Support conversations (support_customer / support_provider) have no
 * service request, so MessageService copies a null conversation
 * service_request_id onto each support message.
 *
 * Rollback refuses to restore NOT NULL when any support (null) rows exist.
 * Existing messages are never deleted or rewritten with fake request ids.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('service_request_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $nullCount = DB::table('messages')->whereNull('service_request_id')->count();

        if ($nullCount > 0) {
            throw new \RuntimeException(
                "Cannot revert messages.service_request_id to NOT NULL: {$nullCount} message(s) "
                .'belong to support conversations and have a null service_request_id. '
                .'Those rows are not deleted or reassigned automatically.'
            );
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('service_request_id')->nullable(false)->change();
        });
    }
};
