<?php
// database/migrations/2024_01_01_000009_create_favorites_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
                                                                                            