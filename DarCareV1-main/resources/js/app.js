import './bootstrap'; 

window.addEventListener('DOMContentLoaded', () => {
    if (window.Echo) {
        window.Echo.channel('notifications.1')
            .listen('.notification.sent', (e) => {
                console.log('✅ وصل الإشعار يا وحش!', e);
                alert('إشعار جديد: ' + e.data.title);
            });
        console.log('📡 بدأت التسمّع على قناة notifications.1');
    } else {
        console.error('❌ Echo غير معرف، تأكد من إعدادات Vite');
    }
});