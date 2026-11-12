{{-- แถบแจ้งเตือนความเคลื่อนไหวคนไข้ — เลื่อนเข้ามาทางขวาบน แล้วหายเอง
     ทุกเครื่องเห็นเหมือนกัน (poll ทุก 10 วิ) · แสดงแค่ คิว + HN + ห้อง ไม่มีชื่อคนไข้

     เดิมเด้งกลางจอด้านบน แต่ไปทับตารางห้องตรวจ (พี่อ้อแจ้ง 2569-08-05)
     ย้ายมาชิดขวาบนใต้เมนู — ตารางห้องตรวจเริ่มจากซ้าย จึงไม่บังของที่ต้องอ่าน

     แจ้งเฉพาะ "เหตุการณ์" ที่เพิ่งเกิด (คนไข้ใหม่ / ย้ายห้อง) เท่านั้น
     สถานะค้างอย่าง "รอนาน" และ "ผลแล็บออก" ไม่เอามาเด้งป็อปอัพ เพราะพยาบาลอาวุโสมองไม่ทันตอนเด้งแล้วหาย —
     ย้ายไปแสดงเป็นสีแถว/ป้ายค้างในหน้า Room แทน (รอนาน: 2569-08-07, ผลแล็บ: 2569-08-11) --}}
<div id="act-toast-wrap"></div>
<style>
    #act-toast-wrap { position:fixed; top:64px; right:12px; z-index:1090;
                      display:flex; flex-direction:column; align-items:flex-end; gap:8px;
                      pointer-events:none; }
    .act-toast { pointer-events:auto; display:flex; align-items:center; gap:10px;
                 max-width:min(92vw, 440px); padding:9px 16px;
                 border-radius:14px; border:1px solid rgba(0,0,0,.08);
                 background:#fff; box-shadow:0 4px 16px rgba(0,0,0,.18);
                 font-size:1rem; text-decoration:none; color:#212529;
                 transform:translateX(120%); opacity:0;
                 transition:transform .35s cubic-bezier(.2,.8,.3,1), opacity .35s ease; }
    .act-toast.act-in   { transform:translateX(0); opacity:1; }
    .act-toast:hover    { color:#212529; box-shadow:0 6px 20px rgba(0,0,0,.26); }
    .act-toast .act-dot { width:10px; height:10px; border-radius:50%; flex:0 0 auto; }
    .act-toast .act-cl  { font-size:.7rem; font-weight:700; color:#fff; padding:1px 7px; border-radius:999px; flex:0 0 auto; }
    .act-toast .act-tx b { font-weight:800; }
    .act-toast .act-tx  { font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .act-toast .act-ic  { flex:0 0 auto; opacity:.75; }
    /* จอแคบ: เมนูสูงกว่าและพื้นที่แนวนอนน้อย ให้กางเต็มความกว้างแทนการชิดขวา */
    @media (max-width: 767.98px) {
        #act-toast-wrap { left:8px; right:8px; align-items:stretch; }
        .act-toast { max-width:none; }
    }
    @media print { #act-toast-wrap { display:none !important; } }
</style>
<script>
(function () {
    if (window.__actToastInit) return;
    window.__actToastInit = true;

    var URL_  = @json(route('notifications.recent'));
    var wrap  = document.getElementById('act-toast-wrap');
    var since = null;          // cursor เวลาจากเซิร์ฟเวอร์ (ไม่ใช้เวลาเครื่อง จะได้ไม่เพี้ยนถ้านาฬิกาไม่ตรง)
    var seen  = {};            // กัน event เดิมเด้งซ้ำ
    var busy  = false;
    // เหลือ 2 แบบ: คนไข้ใหม่ / ย้ายห้อง
    // (กลับบ้าน + รอนาน + ผลแล็บออก เอาออกหมดแล้ว — ย้ายไปโชว์ค้างในหน้า Room แทน)
    var ICON  = { 'new': 'fa-user-plus', 'move': 'fa-right-left' };
    // นานแค่ไหนก่อนหายเอง (มิลลิวินาที) — เดิม 5 วิ พยาบาลบอกเห็นแค่แว็บเดียว
    var HOLD_MS = { normal: 9000 };

    // เมนูไม่ได้ตรึงหัวจอ (เลื่อนหน้าแล้วหายไป) — ขยับกล่องให้อยู่ใต้เมนูเสมอตอนอยู่บนสุด
    // และหนีบไว้ที่ขอบบนเมื่อเลื่อนลง จะได้ไม่ลอยทับเนื้อหาสูงเกินไป
    var nav = document.querySelector('.navbar');
    function place() {
        var bottom = nav ? nav.getBoundingClientRect().bottom : 56;
        wrap.style.top = Math.max(8, bottom + 8) + 'px';
    }
    place();
    var raf = null;
    ['scroll', 'resize'].forEach(function (ev) {
        window.addEventListener(ev, function () {
            if (raf) return;
            raf = requestAnimationFrame(function () { raf = null; place(); });
        }, { passive: true });
    });

    function toast(ev) {
        if (seen[ev.id]) return;
        seen[ev.id] = 1;

        var a = document.createElement('a');
        a.className = 'act-toast';
        a.href = ev.url || '#';
        a.innerHTML =
            '<span class="act-dot" style="background:' + (ev.color || '#0d6efd') + '"></span>'
            + (ev.clinic ? '<span class="act-cl" style="background:' + (ev.color || '#0d6efd') + '">' + ev.clinic + '</span>' : '')
            + '<span class="act-ic"><i class="fas ' + ICON[ev.type] + '"></i></span>'
            + '<span class="act-tx"></span>';
        var tx = a.querySelector('.act-tx');                // ใช้ textContent กัน HTML แปลกปลอมจากข้อมูล
        if (ev.room && ev.text.indexOf(ev.room) === 0) {
            var b = document.createElement('b');             // ชื่อห้องตัวหนา ขึ้นก่อน
            b.textContent = ev.room;
            tx.appendChild(b);
            tx.appendChild(document.createTextNode(ev.text.slice(ev.room.length)));
        } else {
            tx.textContent = ev.text;
        }
        wrap.appendChild(a);

        requestAnimationFrame(function () { a.classList.add('act-in'); });

        var killed = false;
        function kill() {
            if (killed) return; killed = true;
            a.classList.remove('act-in');
            setTimeout(function () { if (a.parentNode) a.parentNode.removeChild(a); }, 400);
        }
        var t = setTimeout(kill, HOLD_MS[ev.type] || HOLD_MS.normal);
        a.addEventListener('mouseenter', function () { clearTimeout(t); });   // ชี้ค้างไว้อ่านได้ ไม่หายระหว่างอ่าน
        a.addEventListener('mouseleave', function () { t = setTimeout(kill, 3000); });
        a.addEventListener('click', kill);
    }

    function poll() {
        if (busy || document.hidden) return;                 // แท็บที่ไม่ได้ดูอยู่ ไม่ต้องยิง
        busy = true;
        var u = URL_ + (since ? ('?since=' + encodeURIComponent(since)) : '');
        fetch(u, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) {
                busy = false;
                if (!j) return;
                var first = (since === null);
                since = j.now || since;
                if (first) return;                            // ครั้งแรก = แค่ตั้งจุดเริ่ม ไม่เด้งย้อนหลัง
                (j.events || []).forEach(toast);
            })
            .catch(function () { busy = false; });
    }

    poll();
    // ต่อ Reverb ติด: เด้งทันทีที่คนไข้ใหม่/ย้ายห้อง · รอบ 10 วิเหลือเก็บตกทุก 30 วิ
    var tick = 0, rtWait = null;
    setInterval(function () {
        if (window.ClinicRT && ClinicRT.connected && (++tick % 3)) return;
        poll();
    }, 10000);
    if (window.ClinicRT) {
        ClinicRT.on('visit', function () { clearTimeout(rtWait); rtWait = setTimeout(poll, 600); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });
})();
</script>
