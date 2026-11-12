{{-- ช่องค้นหาสำหรับ <select data-searchable> (TMC ขอ 2569-10-07)
     ชื่อวัคซีนขึ้นต้นด้วยภาษาไทย ("ไข้เหลือง / Yellow Fever (Stamaril)") — กด y ในตัวเลือกปกติจึงกระโดดไม่ถึง
     ตัวนี้เพิ่มช่องพิมพ์เหนือ select: พิมพ์ส่วนไหนของชื่อก็ได้ (ไทย/อังกฤษ ไม่สนตัวพิมพ์เล็กใหญ่) แล้วกด Enter/แตะเลือก
     select ตัวจริงยังอยู่และยังเป็นตัวเก็บค่า (ยิง change ตามปกติ) — ตัวจัดการเดิมของแต่ละหน้าทำงานเหมือนเดิม
     ช่องค้นหาไม่มี name (ไม่ไปกับฟอร์มหลัก) และอยู่ใน [data-no-dirty]
     include ซ้ำได้ (@once) · select ที่เพิ่มทีหลังเรียก window.initSearchableSelects() --}}
@once
<style>
    .ss-wrap { position: relative; margin-bottom: 2px; }
    .ss-wrap .ss-input { font-size: .85rem; padding: .15rem .5rem; background: #fafaff; }
    .ss-menu { position: absolute; left: 0; right: 0; top: 100%; z-index: 1056; max-height: 260px; overflow-y: auto;
               background: #fff; border: 1px solid #c4b5fd; border-radius: .35rem; box-shadow: 0 6px 16px rgba(0,0,0,.15); }
    .ss-menu .ss-item { padding: .35rem .6rem; cursor: pointer; font-size: .9rem; border-bottom: 1px solid #f1f5f9; }
    .ss-menu .ss-item.active, .ss-menu .ss-item:hover { background: #ede9fe; }
    .ss-menu .ss-empty { padding: .35rem .6rem; color: #6b7280; font-size: .85rem; }
</style>
<script>
(function () {
    if (window.initSearchableSelects) return;

    /*SS-START*/
    // คะแนนการค้นหา: -1 = ไม่ตรง · 0 = ทุกคำขึ้นต้นคำในชื่อ (ดีที่สุด) · ยิ่งมากยิ่งตรงน้อย
    // แยกคำค้นด้วยช่องว่าง ทุกคำต้องเจอ ("yel fev" ก็เจอ) · ขึ้นต้นคำ = ต้นข้อความ หรือหลังช่องว่าง / ( ) - , . : +
    var SS = {
        norm: function (s) { return String(s == null ? '' : s).toLowerCase().replace(/\s+/g, ' ').trim(); },
        score: function (text, q) {
            var t = SS.norm(text), toks = SS.norm(q).split(' ').filter(Boolean), sc = 0;
            for (var k = 0; k < toks.length; k++) {
                var tok = toks[k], i = t.indexOf(tok), ws = false;
                if (i < 0) return -1;
                while (i >= 0) {
                    if (i === 0 || /[\s\/()\-,.:+]/.test(t.charAt(i - 1))) { ws = true; break; }
                    i = t.indexOf(tok, i + 1);
                }
                sc += ws ? 0 : 1;
            }
            return sc;
        },
        // opts: [{value, text}] -> ตัวที่ตรง เรียงคะแนนก่อน แล้วตามลำดับเดิม (ข้ามตัวเลือกว่าง "— เลือก —")
        filter: function (opts, q) {
            var out = [];
            opts.forEach(function (o, idx) {
                if (o.value === '' || o.value == null) return;
                var s = SS.score(o.text, q);
                if (s >= 0) out.push({ value: o.value, text: o.text, s: s, i: idx });
            });
            out.sort(function (a, b) { return a.s - b.s || a.i - b.i; });
            return out;
        }
    };
    /*SS-END*/
    window.SearchableSelectMatch = SS;

    function enhance(sel) {
        if (sel.dataset.ssDone) return;
        sel.dataset.ssDone = '1';
        var wrap = document.createElement('div');
        wrap.className = 'ss-wrap d-print-none';
        wrap.setAttribute('data-no-dirty', '');
        var inp = document.createElement('input');
        inp.type = 'search';
        inp.className = 'form-control form-control-sm ss-input';
        inp.placeholder = sel.dataset.searchPlaceholder || '🔍 พิมพ์ค้นหา (ไทย/อังกฤษ) เช่น yel';
        inp.autocomplete = 'off';
        inp.setAttribute('aria-label', 'ค้นหาในรายการ');
        var menu = document.createElement('div');
        menu.className = 'ss-menu d-none';
        wrap.appendChild(inp); wrap.appendChild(menu);
        sel.parentNode.insertBefore(wrap, sel);

        var items = [], active = 0;
        function opts() {
            // ตัวเลือกที่ซ่อน/ปิดอยู่ (เช่น ชื่อวัคซีนประวัติที่อื่น ตอนเลือกฉีดที่นี่) ไม่ขึ้นในผลค้นหา
            return Array.prototype.filter.call(sel.options, function (o) { return !o.disabled && !o.hidden && !(o.parentNode && o.parentNode.hidden); })
                .map(function (o) { return { value: o.value, text: o.textContent.trim() }; });
        }
        function render() {
            var q = inp.value;
            if (!SS.norm(q)) { menu.classList.add('d-none'); items = []; return; }
            items = SS.filter(opts(), q);
            active = 0;
            menu.innerHTML = '';
            if (!items.length) {
                var e = document.createElement('div'); e.className = 'ss-empty'; e.textContent = 'ไม่พบ "' + q + '"';
                menu.appendChild(e);
            }
            items.forEach(function (it, n) {
                var d = document.createElement('div');
                d.className = 'ss-item' + (n === 0 ? ' active' : '');
                d.textContent = it.text;
                d.dataset.n = n;
                menu.appendChild(d);
            });
            menu.classList.remove('d-none');
        }
        function setActive(n) {
            if (!items.length) return;
            active = (n + items.length) % items.length;
            menu.querySelectorAll('.ss-item').forEach(function (d, i) { d.classList.toggle('active', i === active); });
            var a = menu.querySelector('.ss-item.active'); if (a && a.scrollIntoView) a.scrollIntoView({ block: 'nearest' });
        }
        function choose(n) {
            var it = items[n];
            if (!it) return;
            sel.value = it.value;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            inp.value = '';
            menu.classList.add('d-none');
        }
        inp.addEventListener('input', render);
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                // ช่องนี้อยู่ในฟอร์มหลัก — Enter ต้องไม่ไปกด Save ของหน้าตรวจ
                e.preventDefault();
                choose(active);
            } else if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
            else if (e.key === 'Escape') { inp.value = ''; menu.classList.add('d-none'); }
        });
        // mousedown (ไม่ใช่ click) — ให้เลือกได้ก่อน blur ปิดเมนู
        menu.addEventListener('mousedown', function (e) {
            var d = e.target.closest('.ss-item');
            if (!d) return;
            e.preventDefault();
            choose(parseInt(d.dataset.n, 10));
        });
        inp.addEventListener('blur', function () { setTimeout(function () { menu.classList.add('d-none'); }, 150); });
        inp.addEventListener('focus', function () { if (SS.norm(inp.value)) render(); });
    }

    window.initSearchableSelects = function (root) {
        (root || document).querySelectorAll('select[data-searchable]').forEach(enhance);
    };
    window.initSearchableSelects();
    document.addEventListener('DOMContentLoaded', function () { window.initSearchableSelects(); });
})();
</script>
@endonce
