<?php
// app/Enums/RequestStatusEnum.php

namespace App\Enums;

enum RequestStatusEnum: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
    case Rejected  = 'rejected';
    case ON_THE_WAY = 'on_the_way'; // تمت إضافتها
    case IN_PROGRESS = 'in_progress'; // تمت إضافتها
    case Delayed   = 'delayed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::Completed,
            self::Cancelled,
        ], true);
    }

    /**
     * الطلبات التي يكون مزود الخدمة قد التزم بها فعلاً وما زالت قيد التنفيذ.
     *
     * تستثني pending لأن الطلب المعلّق لم يُقبل بعد — يستطيع المزود رفضه، ولا
     * معنى لعبارة "أنهِ طلبك" بالنسبة له. وهذه نفس المجموعة التي يعتبرها
     * التطبيق طلباً نشطاً في OrderPresentationUtils.isActiveRequest.
     */
    public static function activeValues(): array
    {
        return [
            self::Accepted->value,
            self::ON_THE_WAY->value,
            self::IN_PROGRESS->value,
            self::Delayed->value,
        ];
    }

    public static function finalValues(): array
    {
        return [
            self::Rejected->value,
            self::Completed->value,
            self::Cancelled->value,
        ];
    }
}
