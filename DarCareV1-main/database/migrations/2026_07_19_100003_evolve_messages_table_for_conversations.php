<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('id')->constrained('conversations')->nullOnDelete();
            $table->string('type')->default('text')->after('sender_id');
            $table->foreignId('reply_to_message_id')->nullable()->after('body')->constrained('messages')->nullOnDelete();
            $table->string('client_message_id', 100)->nullable()->after('reply_to_message_id');
            $table->json('metadata')->nullable()->after('client_message_id');
            $table->timestamp('edited_at')->nullable()->after('metadata');
            $table->softDeletes();
        });

        // Normalize legacy morph class names to morph map aliases.
        DB::table('messages')
            ->whereIn('sender_type', [
                'App\\Models\\User',
                'App\\Modules\\Users\\Models\\User',
            ])
            ->update(['sender_type' => 'user']);

        DB::table('messages')
            ->whereIn('sender_type', [
                'App\\Modules\\Providers\\Models\\Provider',
                'App\\Models\\Provider',
            ])
            ->update(['sender_type' => 'provider']);

        $requestIds = DB::table('messages')
            ->whereNotNull('service_request_id')
            ->distinct()
            ->pluck('service_request_id');

        foreach ($requestIds as $requestId) {
            $request = DB::table('service_requests')->where('id', $requestId)->first();

            if (! $request) {
                continue;
            }

            $existing = DB::table('conversations')
                ->where('type', 'request')
                ->where('service_request_id', $requestId)
                ->first();

            if ($existing) {
                $conversationId = $existing->id;
            } else {
                $conversationId = DB::table('conversations')->insertGetId([
                    'type' => 'request',
                    'service_request_id' => $requestId,
                    'status' => in_array($request->status, ['rejected', 'completed', 'cancelled'], true)
                        ? 'read_only'
                        : 'open',
                    'created_by_type' => 'user',
                    'created_by_id' => $request->user_id,
                    'last_message_id' => null,
                    'last_message_at' => null,
                    'closed_at' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $customerExists = DB::table('conversation_participants')
                ->where('conversation_id', $conversationId)
                ->where('participant_type', 'user')
                ->where('participant_id', $request->user_id)
                ->exists();

            if (! $customerExists) {
                DB::table('conversation_participants')->insert([
                    'conversation_id' => $conversationId,
                    'participant_type' => 'user',
                    'participant_id' => $request->user_id,
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($request->provider_id) {
                $providerExists = DB::table('conversation_participants')
                    ->where('conversation_id', $conversationId)
                    ->where('participant_type', 'provider')
                    ->where('participant_id', $request->provider_id)
                    ->exists();

                if (! $providerExists) {
                    DB::table('conversation_participants')->insert([
                        'conversation_id' => $conversationId,
                        'participant_type' => 'provider',
                        'participant_id' => $request->provider_id,
                        'joined_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('messages')
                ->where('service_request_id', $requestId)
                ->whereNull('conversation_id')
                ->update(['conversation_id' => $conversationId]);

            $last = DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->first();

            if ($last) {
                DB::table('conversations')->where('id', $conversationId)->update([
                    'last_message_id' => $last->id,
                    'last_message_at' => $last->created_at,
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('messages', function (Blueprint $table) {
            $table->unique(
                ['conversation_id', 'sender_type', 'sender_id', 'client_message_id'],
                'messages_client_message_unique'
            );
            $table->index(['conversation_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique('messages_client_message_unique');
            $table->dropIndex(['conversation_id', 'created_at', 'id']);
            $table->dropConstrainedForeignId('reply_to_message_id');
            $table->dropConstrainedForeignId('conversation_id');
            $table->dropColumn([
                'type',
                'client_message_id',
                'metadata',
                'edited_at',
                'deleted_at',
            ]);
        });
    }
};
