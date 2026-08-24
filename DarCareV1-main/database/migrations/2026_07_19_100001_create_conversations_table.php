<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // request, support_customer, support_provider
            $table->foreignId('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->string('status')->default('open'); // open, closed, read_only
            $table->nullableMorphs('created_by');
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('last_message_at');
            $table->unique(['type', 'service_request_id'], 'conversations_request_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
