<?php
// database/migrations/2024_01_01_000006_create_service_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories');
            $table->unsignedBigInteger('address_id')->nullable();
            $table->text('description');
            $table->string('urgency')->default('normal'); // urgent, normal
            $table->string('image')->nullable();
            $table->string('status')->default('pending'); // RequestStatusEnum
            $table->decimal('temp_latitude', 10, 8)->nullable();
            $table->decimal('temp_longitude', 11, 8)->nullable();
            $table->string('temp_label')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['provider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
    