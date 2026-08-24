<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('token');
            $table->string('platform'); // android, ios, web
            $table->string('device_name')->nullable();
            $table->string('device_identifier')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique('token');
            $table->index(['tokenable_type', 'tokenable_id', 'invalidated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
