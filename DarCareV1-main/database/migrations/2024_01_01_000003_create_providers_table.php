<?php
// database/migrations/2024_01_01_000002_create_providers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedInteger('years_of_experience')->default(0);
            $table->text('bio')->nullable();
            $table->string('profile_image');
            $table->string('status')->default('available'); // ProviderStatusEnum
            $table->decimal('rating_avg', 3, 2)->default(0.00);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
