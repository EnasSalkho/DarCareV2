    <!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>اختبار إشعارات DarCare</title>
    <!-- استدعاء مكتبة Pusher -->
    <script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>
    <style>
        body { font-family: Tahoma, Arial; padding: 50px; text-align: center; }
        #messages { margin-top: 20px; text-align: left; background: #f4f4f4; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h2>شاشة الاستماع للإشعارات (Real-Time) 🎧</h2>
    <p>افتحي الـ Console (F12) لمراقبة حالة الاتصال.</p>
    <div id="messages"></div>

    <script>
        // تفعيل الـ Console Logs لتشوفي التفاصيل
        Pusher.logToConsole = true;

        // إعداد الاتصال باستخدام الـ Key الخاص بمشروعك
        var pusher = new Pusher('82364372bfaf8dcdd482', {
            cluster: 'eu'
        });

        // ==========================================
        // 1. الاستماع لقناة الاختبار العامة
        // ==========================================
        var testChannel = pusher.subscribe('test-channel');
        testChannel.bind('test-event', function(data) {
            alert("🔔 وصل حدث عام: " + data.message);
            document.getElementById('messages').innerHTML += `<p><b>حدث عام:</b> ${data.message}</p>`;
        });

        // ==========================================
        // 2. الاستماع لقناة المستخدم (مثلاً المستخدم رقم 1)
        // ==========================================
        // إذا كنتِ تختبرين على مستخدم آخر، غيّري الرقم 1 هنا
        var userChannel = pusher.subscribe('notifications.1');
        userChannel.bind('notification.sent', function(data) {
            // بيانات الإشعار موجودة داخل data.data بناءً على الـ Resource الخاص بك
            let title = data.data.title || 'إشعار جديد';
            let body = data.data.body || data.data.message || '';
            
            alert("🚀 Pop-up!!\nالعنوان: " + title + "\nالتفاصيل: " + body);
            document.getElementById('messages').innerHTML += `<p style="color:blue;"><b>إشعار مستخدم:</b> ${title} - ${body}</p>`;
        });
    </script>
</body>
</html>