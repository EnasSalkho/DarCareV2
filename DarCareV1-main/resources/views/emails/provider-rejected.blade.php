<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>بخصوص طلب انضمامك</title>
</head>
<body style="margin:0;padding:24px;background-color:#f1f1f1;font-family:Tahoma,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background-color:#ffffff;border-radius:8px;">
        <tr>
            <td style="padding:24px;background-color:#406450;border-radius:8px 8px 0 0;">
                <h1 style="margin:0;font-size:20px;color:#ffffff;">DarCare</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:24px;">
                <p style="margin:0 0 16px;font-size:16px;">مرحباً {{ $name }},</p>

                <p style="margin:0 0 16px;font-size:16px;line-height:1.8;">
                    نشكرك على اهتمامك بالانضمام إلى منصة DarCare. بعد مراجعة بياناتك،
                    نأسف لإبلاغك أنه <strong>لم يتم قبول طلبك</strong> في الوقت الحالي.
                </p>

                @if (!empty($reason))
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                        <tr>
                            <td style="padding:16px;background-color:#f9fafb;border-right:4px solid #406450;border-radius:4px;">
                                <p style="margin:0 0 8px;font-size:14px;color:#6b7280;">سبب الرفض:</p>
                                <p style="margin:0;font-size:16px;line-height:1.8;">{{ $reason }}</p>
                            </td>
                        </tr>
                    </table>
                @endif

                <p style="margin:0 0 16px;font-size:16px;line-height:1.8;">
                    يمكنك تصحيح البيانات المطلوبة وإعادة التقديم، وسيسعدنا مراجعة طلبك من جديد.
                </p>

                <p style="margin:24px 0 0;font-size:14px;color:#6b7280;">
                    تحياتنا،<br>
                    فريق DarCare
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
