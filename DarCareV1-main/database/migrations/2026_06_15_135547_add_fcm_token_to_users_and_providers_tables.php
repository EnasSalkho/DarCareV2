<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // إضافة العمود لجدول المستخدمين
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('email');
            });
        }

        // إضافة العمود لجدول الفنيين / مزودي الخدمة
        if (Schema::hasTable('providers') && !Schema::hasColumn('providers', 'fcm_token')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->text('fcm_token')->nullable()->after('email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'fcm_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }

        if (Schema::hasColumn('providers', 'fcm_token')) {
            Schema::table('providers', function (Blueprint $table) {
                $table->dropColumn('fcm_token');
            });
        }
    }
};
