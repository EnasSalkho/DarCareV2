<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const ADMIN_SENT_TYPES = ['admin_bulk', 'admin_direct'];

    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // الإرسالة الواحدة بتولّد سطر لكل مستلم؛ الـ batch_id بيجمعهم كرسالة وحدة.
            $table->uuid('batch_id')->nullable()->after('id');
            $table->string('audience')->nullable()->after('type');

            $table->index(['type', 'batch_id']);
        });

        $this->backfillExistingBatches();
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['type', 'batch_id']);
            $table->dropColumn(['batch_id', 'audience']);
        });
    }

    /**
     * الإشعارات القديمة ما إلها batch_id، فمنجمّعها حسب (النوع + المحتوى + وقت الإنشاء)
     * لأن كل مستلمي الإرسالة الوحدة بيتشاركوا نفس القيم الثلاثة.
     */
    private function backfillExistingBatches(): void
    {
        DB::table('notifications')
            ->whereIn('type', self::ADMIN_SENT_TYPES)
            ->orderBy('created_at')
            ->get(['id', 'type', 'data', 'created_at'])
            ->groupBy(fn ($row) => $row->type.'|'.md5((string) $row->data).'|'.$row->created_at)
            ->each(function ($group) {
                DB::table('notifications')
                    ->whereIn('id', $group->pluck('id'))
                    ->update([
                        'batch_id' => (string) Str::uuid(),
                        'audience' => $group->first()->type === 'admin_direct' ? 'specific' : 'all',
                    ]);
            });
    }
};
