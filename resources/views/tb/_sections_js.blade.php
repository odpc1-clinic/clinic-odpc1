{{-- สคริปต์ของกล่องงานในบัตร TB 01 — อยู่คู่กับ partial เพื่อให้ทำงานทั้งในหน้าบัตรและในหน้าตรวจที่ฝังบัตรไว้
     (ห้ามย้ายกลับไปไว้เฉพาะหน้าบัตร ไม่งั้นปุ่มในหน้าตรวจจะกดไม่ทำงาน — เจอมาแล้ว 2569-09-24) --}}
<script>
// ตัวคำนวณขนาดยา "เติมลงตาราง" และการเตือนขนาดยาในตาราง ย้ายไปอยู่ในกล่อง "สั่งยาครั้งนี้" แล้ว (tb/_order_js — 2569-10-02)

// 4FDC กับ 2FDC ใส่ได้ตัวเดียวต่อรอบ (คลินิกแจ้ง 2569-10-02: 4FDC เดือน 1-2 · 2FDC เดือน 3-6)
// ใส่ตัวหนึ่งแล้ว อีกช่องในแถวเดียวกันล็อกเป็นอ่านอย่างเดียว · ลบค่าออกแล้วปลดล็อก
// ใช้ readonly ไม่ใช้ disabled — ช่อง disabled ไม่ถูกส่งไปกับฟอร์ม ตรวจฝั่งเซิร์ฟเวอร์ซ้ำอยู่แล้ว
(function () {
    var PAIR = { '4FDC': '2FDC', '2FDC': '4FDC' };
    function syncRow(tr) {
        var a = tr.querySelector('input[data-drug="4FDC"]'), b = tr.querySelector('input[data-drug="2FDC"]');
        if (!a || !b) return;
        [[a, b], [b, a]].forEach(function (p) {
            var lock = p[1].value.trim() !== '' && p[0].value.trim() === '';
            p[0].readOnly = lock;
            // แตะสีพื้นเฉพาะที่ตัวเองใส่ ไม่ลบสีเหลือง "เพิ่งเติม" ของปุ่มเติมลงตาราง
            if (lock) { p[0].style.backgroundColor = '#e9ecef'; p[0].dataset.fdcLock = '1'; }
            else if (p[0].dataset.fdcLock) { p[0].style.backgroundColor = ''; delete p[0].dataset.fdcLock; }
            p[0].placeholder = lock ? 'ใช้ ' + p[1].dataset.drug + ' แล้ว' : '';
            p[0].title = lock ? 'รอบนี้ใช้ ' + p[1].dataset.drug + ' อยู่ — ลบออกก่อนถ้าจะเปลี่ยน' : '';
        });
    }
    function syncAll() { document.querySelectorAll('#tb-dose-table tr.js-dose-row').forEach(syncRow); }
    document.addEventListener('input', function (e) {
        if (e.target.matches('input[data-drug]') && PAIR[e.target.dataset.drug]) { syncRow(e.target.closest('tr')); }
    });
    document.addEventListener('tb:fdc-sync', syncAll);
    syncAll();
})();

// ชื่อแถวในตารางขนาดยาที่คนอ่าน ("เดือนที่ 1" · "2 สัปดาห์" · ชื่อนอกรอบที่พิมพ์)
function rowName(tr) {
    var lb = tr.querySelector('.js-dose-label');
    if (lb) { return (lb.value || 'นอกรอบ').trim() + ' (เดือน ' + tr.querySelector('.js-dose-month').value + ')'; }
    // แถวปกติเรียกตามคอลัมน์ "รอบ" ของตาราง ไม่ใช่ "เดือนที่" — คนไข้บางคนหมอนัดถี่กว่าเดือนละครั้ง
    // เรียกว่าเดือนแล้วพยาบาลงงว่าตรงกับแถวไหน (คลินิกขอ 2569-10-02)
    if (/^\d+$/.test(tr.dataset.label || '')) { return 'รอบ ' + tr.dataset.label; }
    return tr.dataset.text || '';
}

// ใบสรุปการรักษา: ลงรอบย้อนหลัง (แถวมาตรฐานที่ยังว่าง / นอกรอบตั้งชื่อเอง) — แถวที่เพิ่มเปิดแก้ได้ทันที (2569-10-02)
// รอบใหม่ของวันนี้สร้างจากกล่อง "สั่งยาครั้งนี้" · ตรงนี้ไว้ลงรอบที่สั่งก่อนมีระบบ
(function () {
    var table = document.getElementById('tb-dose-table');
    if (!table) return;
    var tbody   = table.tBodies[0];
    var pick    = document.getElementById('tb-dose-add-pick');
    var lblIn   = document.getElementById('tb-dose-add-label');
    var monIn   = document.getElementById('tb-dose-add-month');
    var addBtn  = document.getElementById('tb-dose-add-btn');
    if (!pick || !addBtn) return;

    function enable(tr) { if (window.tbDoseEnableRow) window.tbDoseEnableRow(tr); }
    function unfold(tr) {
        tr.classList.remove('d-none', 'js-dose-folded');
        var opt = pick.querySelector('option[value="' + tr.dataset.label + '"]');
        if (opt) opt.remove();
        enable(tr);
    }

    // ตำแหน่งตามเวลา — ชุดเดียวกับ TbMonthlyDose::position()
    function position(label, month) {
        label = String(label || '').trim();
        var m;
        if (label === '' || /^\d+$/.test(label)) return label === '' ? month : parseFloat(label);
        if ((m = label.match(/^(\d+)\s*(?:wk|สัปดาห์)$/i))) return parseFloat(m[1]) / 4.345;
        if ((m = label.match(/(?:วันที่|day|d)\s*(\d+)/i))) return Math.max(month, parseFloat(m[1]) / 30.44);
        return month + 0.5;
    }

    pick.addEventListener('change', function () {
        var extra = pick.value === '__extra';
        lblIn.classList.toggle('d-none', !extra);
        monIn.classList.toggle('d-none', !extra);
        if (extra) lblIn.focus();
    });

    var seq = 0;
    addBtn.addEventListener('click', function () {
        var v = pick.value;
        if (!v) { pick.focus(); return; }
        var tr;
        if (v !== '__extra') {
            // เลขรอบซ้ำกันได้ข้ามช่วงนับรอบ — เอาแถวว่างที่พับไว้ ไม่ใช่แถวของช่วงก่อน (2569-10-26)
            tr = tbody.querySelector('tr.js-dose-row.js-dose-folded[data-label="' + v + '"]') || tbody.querySelector('tr.js-dose-row[data-label="' + v + '"]');
            if (tr) unfold(tr);
        } else {
            var label = lblIn.value.trim();
            if (!label) { lblIn.focus(); return; }
            var month = parseInt(monIn.value, 10) || 0;
            var html = document.getElementById('tb-dose-row-tpl').innerHTML.replace(/__I__/g, 'n' + Date.now() + (seq++));
            var holder = document.createElement('tbody');
            holder.innerHTML = html;
            tr = holder.querySelector('tr');
            tr.querySelector('.js-dose-label').value = label;
            tr.querySelector('.js-dose-month').value = month;
            tr.querySelector('.js-dose-month-text').textContent = 'เดือน ' + month;
            var pos = position(label, month);
            tr.dataset.pos = pos;
            // แทรกตามเวลา: ก่อนแถวแรกที่อยู่หลังจากนี้
            // แทรกในช่วงนับรอบล่าสุดเท่านั้น (ช่วงก่อนมีเลขเดือนซ้อนกันได้)
            var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr.js-dose-row'));
            var lastSeg = rows.reduce(function (m, r) { return Math.max(m, parseInt(r.dataset.seg || '0', 10)); }, 0);
            tr.dataset.seg = lastSeg;
            var before = rows.find(function (r) { return parseInt(r.dataset.seg || '0', 10) === lastSeg && parseFloat(r.dataset.pos) > pos; });
            tbody.insertBefore(tr, before || null);
            enable(tr);
            lblIn.value = '';
        }
        if (tr && window.jQuery && jQuery.fn.datepicker) {
            jQuery(tr).find('.tb-date').not('[data-tb-bound]').attr('data-tb-bound', '1').datepicker({ language: 'th-th', autoclose: true, todayHighlight: true });
        }
        pick.value = '';
        lblIn.classList.add('d-none'); monIn.classList.add('d-none');
        if (tr) { var w = tr.querySelector('[name$="[weight]"]'); if (w) w.focus(); }
    });
    lblIn.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addBtn.click(); } });
})();

// เลือกสูตร/ติ๊กยาทำในกล่อง "สั่งยาครั้งนี้" แล้ว — ประวัติสูตรยาเหลือแค่แก้/ลบช่วง (2569-10-02)

// ลบช่วงสูตรยา — สร้างฟอร์มต่อกับ body (ห้ามฟอร์มซ้อน)
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.js-regimen-delete');
    if (!btn) return;
    if (!confirm('ลบช่วงสูตรยานี้? ประวัติช่วงนี้จะหายไป')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = btn.dataset.url;
    f.style.display = 'none';
    f.innerHTML = '<input type="hidden" name="_method" value="DELETE">'
                + '<input type="hidden" name="_token" value="{{ csrf_token() }}">';
    document.body.appendChild(f);
    f.submit();
});

// แก้ช่วงสูตรยา (คลินิกขอ 2569-10-02) — ประวัติสูตรไม่ใช่ฟอร์ม ปุ่มบันทึกส่งด้วยฟอร์มที่สร้างต่อ body
(function () {
    function editRow(id) { return document.querySelector('.js-regimen-editrow[data-rc="' + id + '"]'); }
    function viewRow(id) { return document.querySelector('.js-regimen-view[data-rc="' + id + '"]'); }
    function close(er) {
        // ยกเลิก = คืนค่าเดิมทุกช่อง เปิดใหม่จะได้ไม่เห็นค่าที่พิมพ์ค้างไว้
        er.querySelectorAll('[data-f]').forEach(function (i) { i.value = i.defaultValue; });
        er.classList.add('d-none');
        var vr = viewRow(er.dataset.rc); if (vr) vr.classList.remove('d-none');
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-regimen-edit, .js-regimen-cancel, .js-regimen-save');
        if (!b) return;
        e.preventDefault();

        if (b.classList.contains('js-regimen-edit')) {
            var er = editRow(b.dataset.rc);
            if (!er) return;
            er.classList.remove('d-none');
            b.closest('tr').classList.add('d-none');
            var first = er.querySelector('[data-f="regimen"]'); if (first) first.focus();
            return;
        }

        var row = b.closest('.js-regimen-editrow');
        if (!row) return;
        if (b.classList.contains('js-regimen-cancel')) { close(row); return; }

        var val = function (f) { var i = row.querySelector('[data-f="' + f + '"]'); return i ? i.value.trim() : ''; };
        if (val('regimen') === '') { alert('กรุณาระบุสูตรยา'); return; }
        var dateOk = /^\d{1,2}\/\d{1,2}\/\d{4}$/;
        if (val('start_date') !== '' && !dateOk.test(val('start_date'))) { alert('วันที่เริ่มต้องเป็น วว/ดด/ปปปป'); return; }
        if (val('end_date') !== '' && !dateOk.test(val('end_date'))) { alert('วันที่สิ้นสุดต้องเป็น วว/ดด/ปปปป'); return; }

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = row.dataset.url;
        f.style.display = 'none';
        var add = function (n, v) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i);
        };
        add('_method', 'PUT');
        add('_token', '{{ csrf_token() }}');
        ['regimen', 'start_month', 'start_date', 'end_date', 'reason'].forEach(function (n) { add(n, val(n)); });
        // ยาเป็นข้อความคั่นจุลภาค → ส่งเป็น drugs[] เหมือนตอนเพิ่มสูตร
        val('drugs').split(/[,+;·]/).map(function (k) { return k.trim(); })
            .filter(function (k) { return k !== ''; })
            .forEach(function (k) { add('drugs[]', k.slice(0, 20)); });
        document.body.appendChild(f);   // ต่อกับ body เท่านั้น — ห้ามฟอร์มซ้อน
        f.submit();
    });

    // Enter ในช่องแก้ไข ห้ามไปกดบันทึกฟอร์มเพิ่มสูตรใหม่ (ฟอร์มที่ครอบอยู่)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' || !e.target.closest || !e.target.closest('.js-regimen-editrow')) return;
        e.preventDefault();
        var s = e.target.closest('.js-regimen-editrow').querySelector('.js-regimen-save');
        if (s) s.click();
    });
})();

// ปฏิทินกินยา: กดวนสัญลักษณ์ในช่องวัน (ไม่ต้องเปิด dropdown ทีละวัน พยาบาลกรอกทั้งเดือนเร็วกว่า)
(function () {
    var ORDER = ['', 'taken', 'self', 'start', 'dispensed', 'missed'];
    var CHAR  = { '': '', taken: '✓', self: '⌂', start: '✗', dispensed: '–', missed: '0' };
    var CLASS = { '': 'btn-outline-secondary', taken: 'btn-success', self: 'btn-info', start: 'btn-primary',
                  dispensed: 'btn-secondary', missed: 'btn-danger' };

    function paint(btn, mark) {
        // เขียนตัวอักษรลง span ข้างใน — ถ้าเขียน textContent ทั้งปุ่ม มุมส้ม (มีหมายเหตุ) จะหายไป
        var ch = btn.querySelector('.js-dot-char');
        var missing = mark === '' && btn.dataset.missing === '1';
        var st = mark === '' ? (btn.dataset.state || '') : '';
        (ch || btn).textContent = missing ? '✗' : (st === 'self' ? '⌂' : (st === 'pending' ? '⏱' : (CHAR[mark] || '')));
        Object.keys(CLASS).forEach(function (k) { btn.classList.remove(CLASS[k]); });
        btn.classList.add(CLASS[mark] || CLASS['']);
        btn.classList.toggle('tb-dot-missing', missing);
        btn.classList.toggle('tb-dot-pending', st === 'pending');
        btn.classList.toggle('tb-dot-self', st === 'self');
    }
    function setDay(cell, mark) {
        var input = cell.parentNode.querySelector('.js-dot-input');
        input.value = mark;
        paint(cell, mark);
    }

    document.addEventListener('click', function (e) {
        var cell = e.target.closest('.js-dot-cell');
        if (!cell) return;
        // ปฏิทินล็อกอยู่ (ต้องกด "แก้ไขปฏิทิน" ก่อน) — กันหมอกดโดนระหว่างดูบัตร
        // ยกเว้นกากบาทแดง "ไม่ได้บันทึก": กดแล้วแก้เป็น ✓ และปลดล็อกให้กดบันทึกได้เลย (พยาบาลขอ 2569-09-30)
        var lockedForm = cell.closest('#tb-dot[data-locked="1"]');
        if (lockedForm) {
            // กากบาท / รอเช็ค / วันหยุด (ยังไม่มีใครลง) กดแก้เป็น ✓ ได้เลยแม้ปฏิทินล็อก
            if (['missed', 'pending', 'self'].indexOf(cell.dataset.state || '') === -1) return;
            if (!confirm('คนไข้กินยาวันที่ ' + cell.dataset.day + ' แล้ว แต่ยังไม่ได้ลง — แก้เป็น ✓ กินยา?')) return;
            lockedForm.setAttribute('data-locked', '0');
            setDay(cell, 'taken');
            return;
        }
        var input = cell.parentNode.querySelector('.js-dot-input');
        var next = ORDER[(ORDER.indexOf(input.value || '') + 1) % ORDER.length];
        setDay(cell, next);
    });

    var fill = document.getElementById('tb-dot-fill');
    var clear = document.getElementById('tb-dot-clear');
    if (fill) fill.addEventListener('click', function () {
        document.querySelectorAll('.js-dot-cell').forEach(function (c) { setDay(c, 'taken'); });
    });
    if (clear) clear.addEventListener('click', function () {
        if (!confirm('ล้างสัญลักษณ์ทั้งเดือนนี้?')) return;
        document.querySelectorAll('.js-dot-cell').forEach(function (c) { setDay(c, ''); });
    });

    // ลงสีตามค่าที่โหลดมาตอนเปิดหน้า
    document.querySelectorAll('.js-dot-cell').forEach(function (c) {
        paint(c, c.parentNode.querySelector('.js-dot-input').value || '');
    });

    // ปลดล็อกวันก่อนวันเริ่มยา เมื่อพยาบาลติ๊กว่าจะลงย้อนหลัง (เคส refer มา)
    var unlock = document.getElementById('tb-dot-unlock');
    if (unlock) unlock.addEventListener('change', function () {
        var on = this.checked;
        document.querySelectorAll('#tb-dot-grid button[data-day]').forEach(function (b) {
            if (!b.classList.contains('js-dot-locked')) return;
            b.disabled = !on;
            b.style.background = on ? '' : '#f1f3f5';
            b.style.borderColor = on ? '' : '#e9ecef';
            b.classList.toggle('js-dot-cell', on);
            if (on) { paint(b, b.parentNode.querySelector('.js-dot-input').value || ''); }
        });
    });

    // เปลี่ยนเดือน = โหลดหน้าใหม่พร้อม dot_month (ไม่ submit ฟอร์มบันทึก)
    var sel = document.getElementById('tb-dot-month');
    if (sel) sel.addEventListener('change', function () {
        window.location = this.dataset.base + '?dot_month=' + encodeURIComponent(this.value);
    });
})();

// กดบันทึกแล้วกลับมาที่กล่องเดิม — ถ้าอยู่ในหน้าตรวจ กล่องบัตรถูกพับไว้ ต้องกางให้ก่อนแล้วค่อยเลื่อนไปหา
(function () {
    var id = (window.location.hash || '').replace('#', '');
    if (!id) return;
    var el = document.getElementById(id);
    if (!el) return;
    // ประวัติสูตรยาพับไว้ (2569-10-02) — กลับมาจากการแก้ช่วงสูตร ต้องกางให้เห็นแถวที่เพิ่งแก้
    if (id === 'tb-regimen') { var rb = document.getElementById('tb-regimen-body'); if (rb) rb.classList.add('show'); }

    for (var p = el.parentElement; p; p = p.parentElement) {
        if (p.classList && p.classList.contains('collapse')) {
            p.classList.add('show');
            var tab = document.querySelector('[data-tabtoggle][href="#' + p.id + '"]');
            if (tab) { tab.setAttribute('aria-expanded', 'true'); }
        }
    }

    // ข้อความ "บันทึกแล้ว" อยู่หัวหน้า พอเลื่อนลงมาที่กล่องจะมองไม่เห็น — ลอยซ้ำให้ตรงมุมล่างขวา
    var flash = document.querySelector('#flash-msg .alert');
    if (flash) {
        var toast = flash.cloneNode(true);
        toast.style.cssText = 'position:fixed; bottom:16px; right:16px; z-index:1080; max-width:26rem;'
                            + ' box-shadow:0 .5rem 1rem rgba(0,0,0,.25); margin:0;';
        document.body.appendChild(toast);
        setTimeout(function () { toast.remove(); }, 5000);
    }

    // เลื่อนหลังหน้าโหลดเสร็จ แล้วย้ำอีกครั้ง — หน้าตรวจยาวมาก ตำแหน่งจะนิ่งหลังรูป/ตารางวางตัวแล้ว
    //
    // ต้องสั่งครั้งเดียวด้วยตำแหน่งสัมบูรณ์ + behavior:'auto'
    // เพราะ Bootstrap ตั้ง :root{scroll-behavior:smooth} ไว้ การสั่ง scrollIntoView แล้วตาม scrollBy
    // จะกลายเป็นยกเลิกการเลื่อนอันแรกแล้วเลื่อนจากตำแหน่งปัจจุบัน -80 = ค้างอยู่หัวหน้าเสมอ (2569-09-24)
    function go() {
        var top = el.getBoundingClientRect().top + (window.scrollY || window.pageYOffset || 0) - 80;
        window.scrollTo({ top: top < 0 ? 0 : top, behavior: 'auto' });
    }

    // หน้าตรวจยาวมากและสูงขึ้นเรื่อย ๆ ระหว่างโหลด (รูป ตาราง ปฏิทิน) ตำแหน่งที่คำนวณตอนแรกจึงเพี้ยน
    // จึงจัดตำแหน่งซ้ำจนนิ่ง แล้วหยุดทันทีที่พยาบาลเลื่อนจอเอง จะได้ไม่แย่งกัน
    var tries = 0, stop = false;
    ['wheel', 'touchmove', 'keydown', 'mousedown'].forEach(function (ev) {
        window.addEventListener(ev, function () { stop = true; }, { passive: true });
    });
    function settle() {
        if (stop) return;
        var off = Math.abs(el.getBoundingClientRect().top - 80);
        if (off > 2) { go(); }
        if (++tries < 25) { setTimeout(settle, 200); }
    }
    settle();                                   // เริ่มจัดตำแหน่งทันที ไม่รอรูปโหลดครบ
    window.addEventListener('load', settle);    // แล้วย้ำอีกรอบตอนโหลดเสร็จ
})();

// ท่าถ่าย X-ray แบบป้าย + dropdown "เพิ่มท่า" (คลินิกขอ 2569-10-02)
// ผูกที่ document เพราะแถวที่กด "เพิ่มช่อง" ถูกโคลนมาทีหลัง · ค่าที่ส่งยังเป็น xray[i][views][] เหมือนเดิม
(function () {
    // เลขแถวอ่านจากชื่อช่องอื่นในแถวเดียวกัน — แถวที่โคลนมาถูกเปลี่ยนเลขไปแล้ว ใช้ตามนั้นได้เลย
    function rowPrefix(box) {
        var tr = box.closest('tr');
        var any = tr && tr.querySelector('[name^="xray["]');
        var m = any && any.name.match(/^xray\[(\d+)\]/);
        return m ? 'xray[' + m[1] + ']' : null;
    }
    // ตัวเลือกที่เลือกไปแล้วซ่อนจาก dropdown กันเลือกซ้ำ
    function sync(box) {
        var have = {};
        box.querySelectorAll('.js-xv-chip').forEach(function (c) { have[c.dataset.view] = true; });
        box.querySelectorAll('.js-xv-add option').forEach(function (o) {
            if (!o.value || o.value === '__other') { return; }
            o.hidden = o.disabled = !!have[o.value];
        });
    }
    function addChip(box, key, label) {
        var prefix = rowPrefix(box);
        if (!prefix) { return; }
        var chip = document.createElement('span');
        chip.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-1 js-xv-chip';
        chip.style.fontWeight = '500'; chip.style.fontSize = '.8rem';
        chip.setAttribute('data-remove-on-clone', '1');
        chip.dataset.view = key;
        chip.appendChild(document.createTextNode(label + ' '));
        var h = document.createElement('input');
        h.type = 'hidden'; h.name = prefix + '[views][]'; h.value = key;
        chip.appendChild(h);
        var x = document.createElement('button');
        x.type = 'button'; x.className = 'btn-close js-xv-remove'; x.style.fontSize = '.55rem';
        x.title = 'เอาท่านี้ออก'; x.setAttribute('aria-label', 'เอาท่านี้ออก');
        chip.appendChild(x);
        box.querySelector('.js-xv-chips').appendChild(chip);
    }

    document.addEventListener('change', function (e) {
        var sel = e.target.closest && e.target.closest('.js-xv-add');
        if (!sel) { return; }
        var box = sel.closest('.js-xv-box');
        var v = sel.value;
        if (v === '__other') {
            var other = box.querySelector('.js-xv-other');
            other.classList.remove('d-none');
            other.focus();
        } else if (v && !box.querySelector('.js-xv-chip[data-view="' + v + '"]')) {
            addChip(box, v, sel.options[sel.selectedIndex].text);
        }
        sel.value = '';
        sync(box);
    });

    document.addEventListener('click', function (e) {
        var x = e.target.closest && e.target.closest('.js-xv-remove');
        if (!x) { return; }
        var box = x.closest('.js-xv-box');
        x.closest('.js-xv-chip').remove();
        sync(box);
    });

    // แถวที่เพิ่งโคลนมา dropdown อาจยังซ่อนตัวเลือกของแถวต้นแบบไว้ — จัดใหม่ทุกครั้งก่อนเปิด
    document.addEventListener('focusin', function (e) {
        var sel = e.target.closest && e.target.closest('.js-xv-add');
        if (sel) { sync(sel.closest('.js-xv-box')); }
    });

    // ช่อง "ท่าอื่น ๆ" ที่ลบข้อความจนว่าง ซ่อนกลับไปให้เหลือแค่ dropdown
    document.addEventListener('focusout', function (e) {
        var o = e.target.closest && e.target.closest('.js-xv-other');
        if (o && o.value.trim() === '') { o.classList.add('d-none'); }
    });
})();

// ปฏิทิน พ.ศ. ของช่องวันที่ในบัตร — หน้าตรวจโหลดปลั๊กอินไว้อยู่แล้ว รอให้พร้อมก่อนค่อยผูก
(function bindTbDate() {
    // ต้องรอไฟล์ภาษาไทย (th-th) โหลดด้วย ไม่ใช่แค่ตัว datepicker — เดิมบางเครื่องผูกก่อนภาษาไทยมา
    // เลยได้ปฏิทินอังกฤษ วันที่ออกมาเป็น ดด/วว/ค.ศ. (เช่น 08/23/2027) แล้วบันทึกเพี้ยน (เจอ 2569-10-02)
    if (!window.jQuery || !jQuery.fn.datepicker || !(jQuery.fn.datepicker.dates || {})['th-th']) { return setTimeout(bindTbDate, 200); }
    jQuery('.tb-date').not('[data-tb-bound]').attr('data-tb-bound', '1')
        .datepicker({ language: 'th-th', autoclose: true, todayHighlight: true });
})();
</script>

@include('tb._add_row_js')
@include('tb._filled_js')
