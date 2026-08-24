<?php
// database/migrations/2024_01_01_000005_create_category_provider_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('category_provider', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_provider');
    }
};
