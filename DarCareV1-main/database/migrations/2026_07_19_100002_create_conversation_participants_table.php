<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('muted_at')->nullable();
            $table->timestamps();

            $table->index(['participant_type', 'participant_id'], 'conversation_participants_participant_index');
            $table->index(['conversation_id', 'left_at']);
            $table->unique(
                ['conversation_id', 'participant_type', 'participant_id'],
                'conversation_participants_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
