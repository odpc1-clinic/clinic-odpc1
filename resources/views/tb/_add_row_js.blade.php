{{-- ปุ่ม "เพิ่มช่อง" ของตารางที่จำนวนแถวไม่ตายตัว
     ใช้ทั้งในบัตร TB 01 (ผลเอกซเรย์ · ผลความไวต่อยา) และในใบคัดกรอง (ผู้สัมผัสร่วมบ้าน)
     จึงแยกเป็นไฟล์เดียวแล้ว include ทั้งสองที่ — มีตัวกันผูกซ้ำเผื่อหน้าไหน include ทั้งคู่ --}}
<script>
(function () {
    if (window.__tbAddRowBound) { return; }
    window.__tbAddRowBound = true;

    document.addEventListener('click', function (e) {
        // ปุ่มท้ายตาราง = ต่อแถวข้างล่างสุด · ปุ่ม + ท้ายแถว = แทรกต่อจากแถวนั้นเลย
        var btn    = e.target.closest('.js-tb-add-row');
        var insert = e.target.closest('.js-tb-insert-row');
        if (!btn && !insert) { return; }

        var tbody, last;
        if (insert) {
            last  = insert.closest('tr');
            tbody = last && last.parentNode;
        } else {
            var table = document.getElementById(btn.dataset.table);
            tbody = table && table.querySelector('tbody');
            var rows0 = tbody ? tbody.querySelectorAll('tr') : [];
            last = rows0[rows0.length - 1];
        }
        if (!tbody || !last) { return; }

        var rows = tbody.querySelectorAll('tr');

        // เลขแถวใหม่ = เลขสูงสุดที่มีอยู่ + 1 (กันชนกับแถวที่เซิร์ฟเวอร์ส่งมา)
        var maxIndex = -1;
        tbody.querySelectorAll('[name]').forEach(function (el) {
            var m = el.name.match(/\[(\d+)\]/);
            if (m) { maxIndex = Math.max(maxIndex, parseInt(m[1], 10)); }
        });

        var clone = last.cloneNode(true);

        // ของที่เป็นของแถวเดิมเท่านั้น (ป้ายท่า X-ray ที่เลือกไว้, ข้อความค่าเดิมที่ซ่อนช่องไปแล้ว,
        // ตัวเลือก Culture ค่าเก่า) — ถ้าติดไปแถวใหม่จะดูเหมือนมีข้อมูล ทั้งที่ยังไม่ได้กรอก
        clone.querySelectorAll('[data-remove-on-clone], option[data-legacy]').forEach(function (el) { el.remove(); });
        clone.querySelectorAll('[data-hide-on-clone]').forEach(function (el) { el.classList.add('d-none'); });
        clone.querySelectorAll('.js-xv-add option').forEach(function (o) { o.disabled = false; o.hidden = false; });

        clone.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.name) { el.name = el.name.replace(/\[(\d+)\]/, '[' + (maxIndex + 1) + ']'); }
            el.removeAttribute('id');

            // แทรกต่อจากแถวเดิม = ยังอยู่รอบเดียวกัน ช่องที่ติดป้ายไว้จึงคงค่าเดิม (เช่น เดือน/รอบ)
            // ส่วนปุ่มด้านล่างตารางเป็นการต่อแถวใหม่ ล้างค่าทั้งแถวเหมือนเดิม
            if (insert && el.hasAttribute('data-keep-on-insert')) { return; }

            if (el.tagName === 'SELECT') { el.selectedIndex = 0; }
            else if (el.type === 'checkbox' || el.type === 'radio') { el.checked = false; }
            else { el.value = ''; }
            el.removeAttribute('data-tb-bound');          // ให้ปฏิทิน พ.ศ. มาผูกใหม่
            el.classList.remove('hasDatepicker');
        });
        clone.querySelectorAll('a').forEach(function (a) { a.remove(); });   // ลิงก์ของแถวเดิม (เช่น "ดูประวัติ")

        // ช่องลำดับที่ (คอลัมน์แรก) — เขียนเลขใหม่ให้ ถ้าคอลัมน์นั้นเป็นตัวเลขล้วน
        var firstCell = clone.querySelector('td');
        if (firstCell && firstCell.childNodes[0] && /^\s*\d+\s*$/.test(firstCell.childNodes[0].nodeValue || '')) {
            firstCell.childNodes[0].nodeValue = ' ' + (rows.length + 1) + ' ';
        }

        // แทรกต่อจากแถวที่กด (ถ้ากดปุ่ม + ท้ายแถว) หรือต่อท้ายตาราง (ถ้ากดปุ่มด้านล่าง)
        if (insert) { last.parentNode.insertBefore(clone, last.nextSibling); }
        else { tbody.appendChild(clone); }

        // รอภาษาไทยโหลดก่อนเหมือน bindTbDate — ไม่งั้นแถวใหม่ได้ปฏิทินอังกฤษ วันที่เป็น ค.ศ.
        (function bindClone() {
            if (!window.jQuery || !jQuery.fn.datepicker || !(jQuery.fn.datepicker.dates || {})['th-th']) { return setTimeout(bindClone, 200); }
            jQuery(clone).find('.tb-date, .date').datepicker({ language: 'th-th', autoclose: true, todayHighlight: true });
        })();

        var first = clone.querySelector('input:not([type=hidden]), select, textarea');
        if (first) { first.focus(); }
    });
})();
</script>
