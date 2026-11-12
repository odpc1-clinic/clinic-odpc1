{{-- ของประดับตามเทศกาล/ฤดู ข้างชื่อระบบ (2569-11-03) — ดู App\Support\SeasonFx
     ไอคอนอยู่ตลอดช่วง · ของลอยจาง ๆ เฉพาะใกล้วันเทศกาล วิ่งราว 12 วินาทีแล้วหายเอง ไม่เกินครั้งละ 30 นาทีต่อเครื่อง
     ไม่บังงาน: ทะลุคลิกได้ อยู่ใต้ popup · ไม่พิมพ์ · ปิดเองถ้าเครื่องตั้งลดการเคลื่อนไหว · กดที่ไอคอนเพื่อปิด/เปิดของลอยเฉพาะเครื่องนี้ --}}
@php $__sfx = \App\Support\SeasonFx::today(null, request('sfx')); @endphp
{{-- null = แอดมินปิดไว้ (ทั้งระบบ หรือปิดฤดูนั้น) — ตั้งค่าที่ /backend/season-fx --}}
@if($__sfx)
<button type="button" class="sfx-badge" id="sfx-badge"
        data-key="{{ $__sfx['key'] }}" data-fx="{{ $__sfx['fx'] }}" data-force="{{ request('sfx') ? '1' : '' }}"
        data-opacity="{{ $__sfx['opacity'] / 100 }}" data-seconds="{{ $__sfx['seconds'] }}" data-gap="{{ $__sfx['gap'] }}"
        title="{{ $__sfx['label'] }}{{ $__sfx['fx'] ? ' — กดเพื่อปิด/เปิดของลอยบนหน้าจอ (เฉพาะเครื่องนี้)' : '' }}"
        aria-label="{{ $__sfx['label'] }}">{{ $__sfx['icon'] }}</button>
<style>
    .sfx-badge { background: none; border: 0; padding: 0 .25rem; margin-left: -.6rem; margin-right: .5rem; font-size: 1.15rem; line-height: 1;
                 cursor: {{ $__sfx['fx'] ? 'pointer' : 'default' }}; filter: drop-shadow(0 1px 1px rgba(0,0,0,.25)); }
    .sfx-badge:focus-visible { outline: 2px solid #fff; outline-offset: 2px; border-radius: 4px; }
    .sfx-badge.sfx-off { opacity: .55; }
    #sfx-canvas { position: fixed; inset: 0; width: 100vw; height: 100vh; pointer-events: none; z-index: 1030; transition: opacity 1.2s; }
    @media print { .sfx-badge, #sfx-canvas { display: none !important; } }
</style>
@if($__sfx['fx'])
<script>
(function () {
    var badge = document.getElementById('sfx-badge');
    if (!badge) return;
    var FX = badge.dataset.fx, KEY = 'sfx-off', LAST = 'sfx-last';
    // ค่าจากหน้าตั้งค่า: ความเข้ม · ลอยนานกี่วินาที · ขึ้นซ้ำได้ทุกกี่นาที
    var ALPHA = parseFloat(badge.dataset.opacity) || 0.55;
    var SECS = parseInt(badge.dataset.seconds, 10) || 10;
    var GAP = (parseInt(badge.dataset.gap, 10) || 0) * 60 * 1000;
    var store = {
        get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
        set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} },
        del: function (k) { try { localStorage.removeItem(k); } catch (e) {} }
    };
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var running = null;

    function paintBadge() { badge.classList.toggle('sfx-off', store.get(KEY) === '1'); }
    paintBadge();

    badge.addEventListener('click', function () {
        if (store.get(KEY) === '1') { store.del(KEY); paintBadge(); start(true); }
        else { store.set(KEY, '1'); paintBadge(); stop(); }
    });

    // ของลอยแต่ละชนิด — สีเข้มพอเห็นบนพื้นขาว แต่จางทั้งชุด
    var COLORS = {
        snow: ['#9cc3e6', '#b9d6f0', '#8fb3d9'],
        lantern: ['#f59e0b', '#fb923c', '#fbbf24'],
        confetti: ['#ef4444', '#f59e0b', '#10b981', '#3b82f6', '#a855f7'],
        petal: ['#ef4444', '#f87171', '#fb7185'],
        drop: ['#60a5fa', '#38bdf8', '#93c5fd'],
        rain: ['#7aa7d6', '#93b8e0', '#6b9ccf'],         // ฤดูฝน: สายฝนเฉียง
        cassia: ['#facc15', '#eab308', '#fde047']        // ฤดูร้อน: ดอกคูน (ราชพฤกษ์) บาน มี.ค.–พ.ค.
    };
    function make(w, h, initial) {
        var up = FX === 'lantern', rain = FX === 'rain';
        return {
            x: Math.random() * (rain ? w + 120 : w),
            y: initial ? Math.random() * h : (up ? h + 20 : -20),
            r: FX === 'lantern' ? 5 + Math.random() * 4 : (FX === 'drop' ? 3 + Math.random() * 2 : (rain ? 10 + Math.random() * 8 : 2 + Math.random() * 3)),
            vy: (up ? -1 : 1) * (rain ? 5 + Math.random() * 2.5 : (FX === 'drop' ? 1.6 + Math.random() * 1.2 : 0.35 + Math.random() * 0.6)),
            vx: rain ? -1.2 : (Math.random() - 0.5) * 0.4,
            rot: Math.random() * Math.PI, vr: (Math.random() - 0.5) * 0.04,
            ph: Math.random() * Math.PI * 2,
            c: COLORS[FX][Math.floor(Math.random() * COLORS[FX].length)]
        };
    }
    function draw(ctx, p, t) {
        ctx.save();
        ctx.translate(p.x, p.y);
        ctx.fillStyle = p.c;
        if (FX === 'snow') {
            ctx.beginPath(); ctx.arc(0, 0, p.r, 0, Math.PI * 2); ctx.fill();
        } else if (FX === 'lantern') {
            // โคมลอยยี่เป็ง: ตัวโคม + แสงเรือง กะพริบเบา ๆ
            var flick = 0.75 + 0.25 * Math.sin(t / 220 + p.ph);
            ctx.globalAlpha *= flick;
            var g = ctx.createRadialGradient(0, 0, 0, 0, 0, p.r * 2.4);
            g.addColorStop(0, 'rgba(251,191,36,.55)'); g.addColorStop(1, 'rgba(251,191,36,0)');
            ctx.fillStyle = g; ctx.beginPath(); ctx.arc(0, 0, p.r * 2.4, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = p.c; ctx.fillRect(-p.r * 0.6, -p.r * 0.8, p.r * 1.2, p.r * 1.6);
        } else if (FX === 'confetti') {
            ctx.rotate(p.rot); ctx.fillRect(-p.r, -p.r / 2.5, p.r * 2, p.r / 1.25);
        } else if (FX === 'rain') {
            ctx.strokeStyle = p.c; ctx.lineWidth = 1.2; ctx.lineCap = 'round';
            ctx.beginPath(); ctx.moveTo(0, 0); ctx.lineTo(p.vx * 3, -p.r); ctx.stroke();
        } else if (FX === 'petal' || FX === 'cassia') {
            ctx.rotate(p.rot); ctx.beginPath(); ctx.ellipse(0, 0, p.r * 1.4, p.r * 0.7, 0, 0, Math.PI * 2); ctx.fill();
        } else if (FX === 'drop') {
            ctx.beginPath(); ctx.moveTo(0, -p.r * 1.8);
            ctx.quadraticCurveTo(p.r, 0, 0, p.r); ctx.quadraticCurveTo(-p.r, 0, 0, -p.r * 1.8); ctx.fill();
        }
        ctx.restore();
    }

    function stop() {
        if (!running) return;
        var cv = running.cv; running.stop = true; running = null;
        cv.style.opacity = '0'; setTimeout(function () { cv.remove(); }, 1300);
    }

    // manual = กดเปิดเอง → เล่นทันทีไม่สนรอบ 30 นาที
    function start(manual) {
        if (running || reduce || store.get(KEY) === '1') return;
        var now = Date.now(), last = parseInt(store.get(LAST) || '0', 10);
        if (!manual && !badge.dataset.force && now - last < GAP) return;
        store.set(LAST, String(now));

        var cv = document.createElement('canvas');
        cv.id = 'sfx-canvas'; cv.setAttribute('aria-hidden', 'true');
        document.body.appendChild(cv);
        var ctx = cv.getContext('2d'), dpr = Math.min(window.devicePixelRatio || 1, 2), w, h;
        function size() { w = window.innerWidth; h = window.innerHeight; cv.width = w * dpr; cv.height = h * dpr; ctx.setTransform(dpr, 0, 0, dpr, 0, 0); }
        size(); window.addEventListener('resize', size);

        var N = FX === 'drop' ? 26 : 22, parts = [];
        for (var i = 0; i < N; i++) parts.push(make(w, h, true));
        var t0 = performance.now(), SPAWN = SECS * 1000, END = SPAWN + 3000;
        running = { cv: cv, stop: false };
        var me = running;

        function frame(t) {
            if (me.stop) return;
            if (document.hidden) { requestAnimationFrame(frame); return; }   // แท็บไม่ได้เปิดดู ไม่ต้องวาด
            var el = t - t0;
            ctx.clearRect(0, 0, w, h);
            ctx.globalAlpha = ALPHA;
            for (var i = 0; i < parts.length; i++) {
                var p = parts[i];
                p.y += p.vy; p.x += p.vx + Math.sin(t / 900 + p.ph) * 0.3; p.rot += p.vr;
                var out = p.vy > 0 ? p.y > h + 25 : p.y < -25;
                if (out) { if (el < SPAWN) parts[i] = make(w, h, false); else { parts.splice(i, 1); i--; continue; } }
                draw(ctx, p, t);
            }
            if (el > END || !parts.length) { window.removeEventListener('resize', size); stop(); return; }
            requestAnimationFrame(frame);
        }
        requestAnimationFrame(frame);
    }

    start(false);
})();
</script>
@endif
@endif
