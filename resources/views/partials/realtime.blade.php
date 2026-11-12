{{-- จอรีเฟรชทันทีผ่าน Laravel Reverb (2569-10-20)
     หน้าไหนอยากรู้ว่ามีอะไรเปลี่ยน เรียก ClinicRT.on('visit' | 'lab', fn) — ตัวรับคิวไว้ถูกตั้งใน <head> ของ layouts/app แล้ว
     จึงเรียกได้จากสคริปต์ในหน้า ก่อนไฟล์นี้โหลด
     ต่อไม่ติด (Reverb ดับ / Apache ยังไม่ตั้ง proxy) = ไม่มีอะไรพัง หน้าเดิม poll ตามรอบเดิม
     ClinicRT.connected = true เมื่อต่อติด — หน้าที่ poll อยู่ใช้ยืดรอบสำรองให้ห่างขึ้นได้ --}}
@if(\App\Support\Realtime::enabled())
<script src="{{ asset('js/vendor/pusher-8.4.0.min.js') }}"></script>
<script src="{{ asset('js/vendor/echo-2.1.6.iife.js') }}"></script>
<script>
(function () {
    var RT = window.ClinicRT;
    if (!RT || !window.Echo || !window.Pusher) return;
    var https = location.protocol === 'https:';
    var port = +location.port || (https ? 443 : 80);
    try {
        var echo = new Echo.default({
            broadcaster: 'reverb',
            key: @json(config('broadcasting.connections.reverb.key')),
            // ต่อกลับมาที่เว็บเดียวกับหน้านี้ — Apache ส่ง /app/ ต่อให้ Reverb ในเครื่อง (ไม่ต้องเปิดพอร์ตเพิ่ม ใบรับรองเดียวกับหน้าเว็บ)
            wsHost: location.hostname, wsPort: port, wssPort: port, forceTLS: https,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: @json(url('/broadcasting/auth')),
            auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } }
        });
        echo.connector.pusher.connection.bind('state_change', function (s) {
            var up = s.current === 'connected';
            if (up === RT.connected) return;
            RT.connected = up;
            RT._s.forEach(function (f) { try { f(up); } catch (e) {} });
        });
        echo.private('clinic').listen('.signal', function (e) {
            (RT._h[e.type] || []).forEach(function (f) { try { f(e.data || {}); } catch (err) {} });
        });
    } catch (e) { /* ต่อไม่ได้ = ใช้ poll ตามเดิม */ }
})();
</script>
@endif
