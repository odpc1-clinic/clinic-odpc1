{{-- ตัวเลขคนไข้แต่ละห้องบนแถบเมนู อัปเดตเอง + ป้ายแดง "ใหม่" เมื่อมีคนไข้เข้าห้องเพิ่ม (คลินิกขอ 2569-11-02)
     เดิมตัวเลขคิดตอนโหลดหน้าเท่านั้น ต้องขยับเมาส์/เปลี่ยนหน้าถึงจะเห็นว่ามีคนไข้เข้า ระหว่างทำงานอื่นเลยไม่รู้
     ถามเมื่อได้สัญญาณ visit (Reverb) และทุก 30 วิ ด้วยตัวจับเวลาใน Web Worker (หน้าที่ถูกย่อ/อยู่หลังแท็บอื่นไม่โดนหน่วง)
     ป้าย "+N ใหม่" ค้างจนกว่าจะกดเข้าห้องนั้น · ชื่อแท็บขึ้น (N) ให้เห็นจากแท็บอื่น --}}
<style>
    .room-new-badge {
        display: inline-block; margin-left: 4px; padding: 1px 6px; border-radius: 10px;
        background: #dc3545; color: #fff; font-size: .72rem; font-weight: 700; line-height: 1.4;
        animation: roomNewPulse 1.2s ease-in-out infinite;
    }
    @keyframes roomNewPulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
    @media (prefers-reduced-motion: reduce) { .room-new-badge { animation: none; } }
</style>
<script>
(function () {
    var URL = @json(route('visit.roomCounts'));
    var badges = document.querySelectorAll('[data-room-count]');
    if (!badges.length) return;

    // ห้องที่กำลังเปิดอยู่ = เห็นคนไข้ใหม่อยู่แล้ว ไม่ต้องติดป้าย
    var here = (location.pathname.match(/\/visit\/room\/(\d+)/) || [])[1] || null;
    var KEY = 'roomCountsSeen';
    var seen = {};
    try { seen = JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e) {}

    var baseTitle = document.title, busy = false;

    function mark(status, plus) {
        document.querySelectorAll('[data-room-count="' + status + '"]').forEach(function (b) {
            var tag = b.parentNode.querySelector('.room-new-badge');
            if (plus > 0) {
                if (!tag) { tag = document.createElement('span'); tag.className = 'room-new-badge'; b.insertAdjacentElement('afterend', tag); }
                tag.textContent = '+' + plus + ' ใหม่';
                tag.title = 'มีคนไข้เข้าห้องนี้เพิ่ม ' + plus + ' ราย ตั้งแต่ที่เปิดดูห้องนี้ครั้งล่าสุด';
            } else if (tag) {
                tag.remove();
            }
        });
    }

    function apply(counts) {
        var total = 0;
        Object.keys(counts).forEach(function (s) {
            var n = +counts[s];
            document.querySelectorAll('[data-room-count="' + s + '"]').forEach(function (b) { b.textContent = n; });
            if (seen[s] === undefined || s === here || n < seen[s]) { seen[s] = n; }   // คนไข้ออกจากห้อง = ฐานลดตาม
            var plus = Math.max(0, n - seen[s]);
            mark(s, plus);
            total += plus;
        });
        try { sessionStorage.setItem(KEY, JSON.stringify(seen)); } catch (e) {}
        document.title = total > 0 ? '(' + total + ') ' + baseTitle : baseTitle;
    }

    function poll() {
        if (busy) return;
        busy = true;
        fetch(URL, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j) apply(j); })
            .catch(function () {})
            .then(function () { busy = false; });
    }

    // กดเข้าห้องไหน = ถือว่าเห็นแล้ว ล้างป้ายห้องนั้น
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href*="/visit/room/"]');
        if (!a) return;
        var s = (a.getAttribute('href').match(/\/visit\/room\/(\d+)/) || [])[1];
        var b = s && document.querySelector('[data-room-count="' + s + '"]');
        if (b) { seen[s] = +b.textContent || 0; try { sessionStorage.setItem(KEY, JSON.stringify(seen)); } catch (err) {} }
    });

    // ค่าตั้งต้นจากตัวเลขที่เซิร์ฟเวอร์วาดมากับหน้า
    var init = {};
    badges.forEach(function (b) { init[b.dataset.roomCount] = +b.textContent || 0; });
    apply(init);

    // สัญญาณเรียลไทม์ (มีคนไข้ย้ายห้อง) — ถามทันที
    if (window.ClinicRT && typeof window.ClinicRT.on === 'function') {
        window.ClinicRT.on('visit', function () { setTimeout(poll, 400); });
    }
    // ตัวจับเวลาใน Worker — ไม่โดนเบราว์เซอร์หน่วงตอนหน้าอยู่หลังแท็บอื่น
    try {
        var w = new Worker(URL_CREATE());
        w.onmessage = poll;
    } catch (e) {
        setInterval(poll, 30000);
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    function URL_CREATE() {
        return window.URL.createObjectURL(new Blob(["setInterval(function(){postMessage('t')},30000);"], { type: 'text/javascript' }));
    }
})();
</script>
