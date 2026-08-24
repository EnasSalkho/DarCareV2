<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->index('created_at');
            $table->index('category_id');
            $table->index(['created_at', 'status']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('service_requests', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['created_at', 'status']);
        });

        Schema::table('ratings', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
