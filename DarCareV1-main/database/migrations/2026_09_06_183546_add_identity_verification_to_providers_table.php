<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->string('identity_image')->nullable()->after('profile_image');

            $table->string('verification_status')
                ->default('pending')
                ->after('status');

            $table->text('rejection_reason')->nullable()
                ->after('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropColumn([
                'identity_image',
                'verification_status',
                'rejection_reason',
            ]);
        });
    }
};
