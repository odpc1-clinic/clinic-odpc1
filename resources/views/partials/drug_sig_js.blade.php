@once
{{-- ตัวจัดการช่องวิธีใช้ยา (partials/drug_sig_fields) — ผูกที่ document เพราะแถวยาในหน้าสั่งยาเพิ่มด้วย JS
     ข้อความพรีวิวต้องตรงกับ App\Support\DrugSig::text() ฝั่งเซิร์ฟเวอร์
     เลือกวิธีใช้ครบ (ครั้งละ × ความถี่ × วัน) แล้วเติม "จำนวน" ให้ — ถ้าผู้ใช้พิมพ์จำนวนเองแล้วจะไม่ทับ --}}
<script>
(function () {
    var FREQ   = @json(\App\Support\DrugSig::$freq);
    var ROUTE  = @json(\App\Support\DrugSig::$route);
    var TIMING = @json(\App\Support\DrugSig::$timing);
    var PERIOD = @json(\App\Support\DrugSig::$period);

    function val(box, part) {
        var el = box.querySelector('[name$="[' + part + ']"]');
        return el ? (el.value || '').trim() : '';
    }
    function num(s) {
        var f = /^(\d+)\/(\d+)$/.exec(s);
        if (f && +f[2] > 0) return +f[1] / +f[2];
        return /^\d+(\.\d+)?$/.test(s) ? parseFloat(s) : 0;
    }
    // ตรงกับ DrugSig::qtyText() — 0.5 เม็ด = "ครึ่งเม็ด" · 1.5 เม็ด = "1 เม็ดครึ่ง"
    function qtyText(q, unit) {
        var n = num(q);
        if (unit === 'เม็ด' && n > 0 && Math.abs(n * 2 - Math.round(n * 2)) < 1e-9 && Math.abs(n - Math.round(n)) > 1e-9) {
            var w = Math.floor(n);
            return w === 0 ? 'ครึ่งเม็ด' : w + ' เม็ดครึ่ง';
        }
        return (q + ' ' + unit).trim();
    }

    function checkedPeriods(box) {
        return Array.prototype.slice.call(box.querySelectorAll('[name$="[period][]"]:checked'))
            .map(function (c) { return c.value; });
    }
    // ตรงกับ DrugSig::extraText() — ถ้าเลือก "ก่อนนอน" ในช่องเวลาแล้ว ไม่พูดซ้ำ
    function extra(box) {
        var hs = val(box, 'timing') === 'hs';
        var p = checkedPeriods(box).filter(function (k) { return !(hs && k === 'b'); })
            .map(function (k) { return PERIOD[k]; }).join('-');
        return (p + ' ' + val(box, 'note')).trim();
    }
    // ติ๊กช่วงเวลา n ช่อง = วันละ n ครั้ง (ไม่ยุ่งถ้าตั้งเป็นครั้งเดียว/รายสัปดาห์/เมื่อมีอาการไว้)
    function syncFreq(box) {
        var n = checkedPeriods(box).length;
        var sel = box.querySelector('[name$="[freq]"]');
        var map = {1: 'OD', 2: 'BID', 3: 'TID', 4: 'QID'};
        if (sel && map[n] && ['', 'OD', 'BID', 'TID', 'QID'].indexOf(sel.value) !== -1) { sel.value = map[n]; }
    }

    function text(box) {
        var f = FREQ[val(box, 'freq')];
        var x = extra(box);
        if (!f && !x) return '';
        var q = val(box, 'qty'), d = val(box, 'days');
        return [ROUTE[val(box, 'route')] || '',
                q ? 'ครั้งละ ' + qtyText(q, val(box, 'unit')) : '',
                f ? f[0] : '',
                TIMING[val(box, 'timing')] || '',
                x,
                d && val(box, 'freq') !== 'STAT' ? 'นาน ' + d + ' วัน' : ''
               ].filter(function (s) { return s.trim() !== ''; }).join(' ').replace(/\s+/g, ' ').trim();
    }
    function total(box) {
        var q = num(val(box, 'qty')), code = val(box, 'freq'), f = FREQ[code];
        // ยาเม็ดสั่งเศษ ตัดสต๊อกเต็มเม็ดต่อครั้ง (หักครึ่งที่เหลือทิ้ง) — ตรงกับ DrugSig::dosePerDispense()
        if (val(box, 'unit') === 'เม็ด') q = Math.ceil(q - 1e-9);
        if (q <= 0 || !f) return null;
        if (code === 'STAT') return q;
        var d = parseInt(val(box, 'days'), 10);
        if (!f[1] || !(d >= 1)) return null;
        var times = f[1] >= 1 ? f[1] * d : Math.ceil(f[1] * d - 1e-9);
        return Math.round(q * times * 100) / 100;
    }
    // ช่องจำนวนของยาตัวเดียวกัน — แถวในหน้าสั่งยา หรือทั้งฟอร์มในหน้าแก้ไข
    function amountOf(box) {
        // หน้าสั่งยา: ช่องวิธีใช้อยู่อีกแถว (<tr>) ใต้แถวยา จึงชี้ด้วยเลขแถว
        if (box.dataset.amount) {
            return document.querySelector('tr.rx-row[data-rx="' + box.dataset.amount + '"] .js-rx-amount');
        }
        var scope = box.closest('[data-rx-scope]');
        return scope ? scope.querySelector('.js-rx-amount') : null;
    }

    // ไม่เขียนทับช่อง "จำนวน" แล้ว (รีวิว 2569-10-01) — จำนวนคือหน่วยที่จ่าย/คิดเงิน/ตัดสต๊อก (ขวด, หลอด, เม็ด)
    // แต่ที่คิดจากวิธีใช้เป็นหน่วยของวิธีใช้ (หยด, มล.) เช่น หยอดตา 1 หยด × 4 × 7 = 28 จะกลายเป็น 28 ขวด
    // จำนวนใช้ค่าตั้งต้นจากคลังยา · ยอดที่คิดได้โชว์เป็นคำแนะนำข้างช่องให้คนตัดสินเอง
    function hintFor(amt) {
        var h = amt.parentNode.querySelector('.js-rx-amount-hint');
        if (!h) {
            h = document.createElement('div');
            h.className = 'small text-muted js-rx-amount-hint';
            amt.parentNode.appendChild(h);
        }
        return h;
    }
    function refresh(box) {
        var t = box.querySelector('.js-rx-sig-text');
        if (t) t.textContent = text(box);
        var amt = amountOf(box), n = total(box);
        if (!amt) return;
        var unit = val(box, 'unit');
        hintFor(amt).textContent = n !== null ? 'ตามวิธีใช้ ≈ ' + n + (unit ? ' ' + unit : '') : '';
    }

    function onEdit(e) {
        var box = e.target.closest && e.target.closest('.js-rx-sig');
        if (!box) return;
        if (e.type === 'change' && e.target.name && /\[period\]\[\]$/.test(e.target.name)) { syncFreq(box); }
        refresh(box);
    }
    document.addEventListener('input', onEdit);
    document.addEventListener('change', onEdit);

    // ใช้จากหน้าสั่งยาตอนเลือกยาตัวใหม่: เติมค่าที่เดาจากคลังยา แล้วคิดจำนวนให้
    window.rxSigApply = function (box, sig) {
        sig = sig || {};
        ['route', 'qty', 'unit', 'freq', 'timing', 'days', 'note'].forEach(function (k) {
            var el = box.querySelector('[name$="[' + k + ']"]');
            if (el) el.value = sig[k] || '';
        });
        var per = sig.period || [];
        box.querySelectorAll('[name$="[period][]"]').forEach(function (c) { c.checked = per.indexOf(c.value) !== -1; });
        refresh(box);
    };
    // หน้าแก้ไขยา: โชว์ยอดตามวิธีใช้ตั้งแต่เปิดหน้า
    document.querySelectorAll('.js-rx-sig').forEach(function (b) { refresh(b); });
})();
</script>
@endonce
