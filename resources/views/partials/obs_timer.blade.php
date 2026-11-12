{{-- นาฬิกาสังเกตอาการ 30 นาทีหลังฉีดวัคซีน (TMC) — แบะติดขอบขวาเหมือนแท็บ QUEUE/HISTORY/NOTE (localStorage, ตามทุกหน้า) --}}
{{-- มุมขวาล่างมีของหลายชิ้น ต้องไม่ทับกัน (เรียงจากล่างขึ้นบน):
     bottom 16px = ป้าย "ยังไม่ได้ Save" (visit/edit) · 90px = ป้ายสมุดเหลือง · 150px = นาฬิกานี้ --}}
<div id="obs-timer-wrap" style="position:fixed; right:0; bottom:150px; z-index:1055; display:none;"></div>
<style>
    #obs-timer-wrap { display:flex; flex-direction:column; gap:6px; align-items:flex-end; }
    /* เป็น <a> (คลิกเข้า visit ได้) จึงต้องล็อกสีขาวทุกสถานะ ไม่งั้น Bootstrap ทับเป็นสีลิงก์ตอน hover จนอ่านไม่ออก */
    .obs-tab, .obs-tab:link, .obs-tab:visited, .obs-tab:hover, .obs-tab:focus, .obs-tab:active {
        color:#fff; text-decoration:none;
    }
    .obs-tab, .obs-tab:hover *, .obs-tab * { color:#fff; }
    /* ห้ามเปลี่ยนขนาด/ตำแหน่งตอน hover: ปุ่มปิดจะหนีเมาส์ -> หลุด hover -> เด้งกลับ -> วนไม่จบ กดปิดไม่ได้
       เปลี่ยนแค่สีพื้นเป็น feedback พอ */
    .obs-tab { display:block; position:relative;
               border-radius:12px 0 0 12px; padding:7px 34px 7px 16px; text-align:center; min-width:130px;
               background:#0d6efd; cursor:pointer; box-shadow:-2px 2px 10px rgba(0,0,0,.2);
               transition:background .15s ease; }
    .obs-tab:hover { background:#0b5ed7; }
    .obs-tab.obs-done { background:#dc3545; animation:obsFlash 1s ease-in-out infinite; }
    .obs-tab.obs-done:hover { background:#bb2d3b; }
    /* กะพริบด้วย box-shadow เท่านั้น (ไม่กิน layout) จึงไม่ทำให้ปุ่มขยับ */
    @keyframes obsFlash { 0%,100%{ box-shadow:-2px 2px 10px rgba(220,53,69,.5);} 50%{ box-shadow:-2px 2px 18px rgba(220,53,69,.95);} }
    .obs-tab .obs-lbl   { font-size:.62rem; font-weight:700; letter-spacing:.04em; opacity:.95; }
    .obs-tab .obs-name  { font-size:.75rem; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; opacity:.95; }
    .obs-tab .obs-count { font-size:1.25rem; font-weight:800; line-height:1.15; font-variant-numeric:tabular-nums; letter-spacing:1px; }
    /* ปุ่มปิด: ตรึงมุมขวาบน ไม่ขึ้นกับ padding/ความยาวข้อความ + วงกลมกดง่าย */
    .obs-tab .obs-x { position:absolute; top:4px; right:6px; width:24px; height:24px;
                      line-height:22px; text-align:center; border-radius:50%;
                      background:rgba(0,0,0,.28); font-size:17px; font-weight:700; cursor:pointer; }
    .obs-tab .obs-x:hover { background:rgba(0,0,0,.55); }
</style>
<script>
(function () {
    if (window.__obsTimerInit) return;
    window.__obsTimerInit = true;
    var KEY = 'tmc_obs_timers';
    var wrap = document.getElementById('obs-timer-wrap');
    var beeped = {};

    function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
    function save(list) { localStorage.setItem(KEY, JSON.stringify(list)); }

    // ให้หน้าอื่น (เช่นตารางห้องเจาะเลือด) อ่านนาฬิกาตัวเดียวกันได้ ไม่ต้องรู้ชื่อ key เอง
    window.obsTimerFor = function (id) {
        return load().filter(function (t) { return String(t.id) === String(id); })[0] || null;
    };
    window.obsTimerFmt = function (ms) { return fmt(ms); };

    /** ยกเลิกการจับเวลาของคนไข้รายนั้น (ตารางห้องเรียกใช้ — ถามยืนยันมาก่อนแล้ว) */
    window.stopObsTimer = function (id) {
        save(load().filter(function (t) { return String(t.id) !== String(id); }));
        delete beeped[String(id)];
        render();
    };

    /** ตั้งเวลาครบแบบระบุเวลาจบตรง ๆ ไม่ถาม — โซนวัคซีน TMC ใช้ (เข็มสุดท้าย + 30 นาที) ให้ทุกเข็มครบพร้อมกัน */
    window.setObsTimerEnd = function (id, name, endMs, visitId) {
        var list = load().filter(function (t) { return String(t.id) !== String(id); });
        list.push({ id: String(id), name: name || '', visitId: String(visitId || ''), end: +endMs });
        delete beeped[String(id)];
        save(list);
        render();
    };

    // เปิดให้ปุ่มในหน้าฉีดวัคซีน/ห้องยาเรียกใช้
    window.startObsTimer = function (id, name, minutes, visitId) {
        var mins = minutes || 30;
        var list = load();
        var old  = list.filter(function (t) { return String(t.id) === String(id); })[0];

        // กดซ้ำคนเดิม = จะรีเซ็ตเวลาที่นับไปแล้วทิ้ง ต้องถามก่อน ไม่งั้นเผลอกดแล้วเวลาเดินใหม่โดยไม่รู้ตัว
        if (old) {
            var left = old.end - Date.now();
            var msg  = left > 0
                ? 'กำลังจับเวลา ' + (old.name || 'คนไข้รายนี้') + ' อยู่แล้ว เหลืออีก ' + fmt(left) + ' นาที\n\nเริ่มนับใหม่ ' + mins + ' นาทีใช่ไหม?'
                : (old.name || 'คนไข้รายนี้') + ' ครบเวลาแล้ว\n\nเริ่มนับใหม่ ' + mins + ' นาทีใช่ไหม?';
            if (!confirm(msg)) { return; }
        }

        list = list.filter(function (t) { return String(t.id) !== String(id); });
        list.push({ id: String(id), name: name || '', visitId: String(visitId || ''), end: Date.now() + mins * 60000 });
        delete beeped[String(id)];   // เริ่มรอบใหม่ ต้องให้เตือนเสียงได้อีกครั้ง
        save(list);
        render();
    };

    function beep() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var o = ctx.createOscillator(), g = ctx.createGain();
            o.connect(g); g.connect(ctx.destination);
            o.type = 'sine'; o.frequency.value = 880; g.gain.value = 0.15;
            o.start(); o.stop(ctx.currentTime + 0.6);
        } catch (e) {}
    }

    function fmt(ms) {
        if (ms < 0) ms = 0;
        var s = Math.floor(ms / 1000), m = Math.floor(s / 60);
        s = s % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function render() {
        var list = load();
        // หน้าที่แสดงเวลาในตารางเองอยู่แล้ว (ห้องเจาะเลือด) ตั้ง __obsHideFloat ไว้ ไม่ต้องมีป้ายลอยซ้ำ
        if (window.__obsHideFloat) {
            wrap.style.display = 'none'; wrap.innerHTML = '';
            list.forEach(function (t) { if (t.end - Date.now() <= 0 && !beeped[t.id]) { beeped[t.id] = true; beep(); } });
            document.dispatchEvent(new CustomEvent('obs:tick'));
            return;
        }
        document.dispatchEvent(new CustomEvent('obs:tick'));   // ให้ตารางอัปเดตพร้อมกัน

        // เสียงเตือนต้องดังแม้ยังไม่ได้แสดงป้าย (นับอยู่เบื้องหลังทุกหน้า)
        list.forEach(function (t) { if (t.end - Date.now() <= 0 && !beeped[t.id]) { beeped[t.id] = true; beep(); } });

        // ระหว่างนับไม่ต้องขึ้นป้าย — เกะกะจอโดยไม่มีอะไรให้ทำ
        // เด้งเฉพาะตอนครบเวลา ซึ่งเป็นจังหวะที่ต้องไปดูคนไข้จริง (ผู้ใช้สั่ง 2026-08-03)
        var due = list.filter(function (t) { return t.end - Date.now() <= 0; });
        if (!due.length) { wrap.style.display = 'none'; wrap.innerHTML = ''; return; }

        wrap.style.display = 'flex';
        var html = '';
        due.forEach(function (t) {
            var over = Date.now() - t.end;     // เลยมานานแค่ไหนแล้ว
            var visitLink = t.visitId ? '/visit/' + t.visitId + '/edit?page=2' : 'javascript:void(0)';
            html += '<a href="' + visitLink + '" class="obs-tab obs-done" title="' + (t.name || '') + ' (คลิกเข้า visit)">'
                + '<span class="obs-x" data-id="' + t.id + '" onclick="event.preventDefault(); event.stopPropagation();" title="รับทราบ/ปิด">&times;</span>'
                + '<div class="obs-lbl"><i class="fa-solid fa-bell me-1"></i>ครบ 30 นาทีแล้ว</div>'
                + '<div class="obs-name">' + (t.name || '') + '</div>'
                + '<div class="obs-lbl">เลยมา ' + fmt(over) + '</div>'
                + '</a>';
        });
        wrap.innerHTML = html;
        wrap.querySelectorAll('.obs-x').forEach(function (x) {
            x.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var id = this.dataset.id;
                // ป้ายนี้ขึ้นเฉพาะตอนครบเวลาแล้ว กดปิด = รับทราบ ไม่ต้องถามยืนยัน
                save(load().filter(function (t) { return String(t.id) !== String(id); }));
                delete beeped[id];
                render();
            });
        });
    }

    // ===== ปุ่ม/ป้ายในหน้า (partials/obs_button) — จัดการที่เดียว ใช้ได้ทุกหน้า =====
    // เดิม logic นี้อยู่ในหน้า room หน้าเดียว ทำให้หน้า visit กดแล้วไม่มีอะไรตอบสนอง
    function paintCells() {
        document.querySelectorAll('.obs-cell').forEach(function (cell) {
            var pid   = cell.dataset.pid;
            var t     = pid ? (load().filter(function (x) { return String(x.id) === String(pid); })[0] || null) : null;
            var start = cell.querySelector('.obs-start');
            var live  = cell.querySelector('.obs-live');
            var badge = cell.querySelector('.obs-badge');
            if (!start || !live || !badge) return;

            if (!t) {                                   // ยังไม่ได้จับเวลา -> โชว์ปุ่ม
                start.classList.remove('d-none');
                live.classList.add('d-none');
                live.classList.remove('d-inline-flex', 'd-flex');
                return;
            }
            var left = t.end - Date.now();
            var done = left <= 0;
            start.classList.add('d-none');
            live.classList.remove('d-none');
            live.classList.add(cell.classList.contains('d-grid') ? 'd-flex' : 'd-inline-flex');
            // ต้องบอกด้วยว่าเป็นเวลาอะไร ไม่งั้นเห็นแค่ตัวเลขลอย ๆ ไม่รู้ว่านับอะไรอยู่
            badge.innerHTML = '<i class="fa-solid fa-stopwatch me-1"></i>สังเกตอาการ '
                            + (done ? 'ครบ 30 น.' : fmt(left));
            badge.className = badge.className.replace(/\bbg-\w+\b/g, '') + ' ' + (done ? 'bg-danger' : 'bg-primary');
        });
    }

    document.addEventListener('click', function (e) {
        var startBtn = e.target.closest('.obs-start');
        if (startBtn) {
            var cell = startBtn.closest('.obs-cell');
            if (!cell || !cell.dataset.pid) return;
            window.startObsTimer(cell.dataset.pid, cell.dataset.name, 30, cell.dataset.vid);
            return;
        }
        var stopBtn = e.target.closest('.obs-stop');
        if (stopBtn) {
            e.preventDefault();
            var c = stopBtn.closest('.obs-cell');
            if (!c) return;
            var cur = window.obsTimerFor(c.dataset.pid);
            if (cur && (cur.end - Date.now() > 0) && !confirm('ยังไม่ครบ 30 นาที — ยกเลิกการจับเวลาสังเกตอาการ?')) return;
            window.stopObsTimer(c.dataset.pid);
        }
    });

    document.addEventListener('obs:tick', paintCells);

    render();
    paintCells();
    setInterval(render, 1000);
    // ซิงก์ข้ามแท็บ
    window.addEventListener('storage', function (e) { if (e.key === KEY) render(); });
})();
</script>
