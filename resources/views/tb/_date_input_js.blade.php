{{--
    จัดรูปแบบช่องวันที่ของงานวัณโรคตอนพิมพ์เสร็จ (2569-10-05) — คลินิกขอให้วันที่เป็นแบบเดียวกันหมด รายงานจะได้ถูก
    กติกาเดียวกับฝั่งเซิร์ฟเวอร์ App\Support\ThaiDate (แก้ที่หนึ่งต้องแก้อีกที่):
      - รับ วว/ดด/ปปปป ตัวคั่น / - . · เติม 0 ให้ครบ 2 หลัก
      - ปี 1900-2399 = ค.ศ. → บวก 543 ให้ แล้วบอกใต้ช่อง
      - เดือนเกิน 12 / วันไม่มีจริง / ปี 2 หลัก = กรอบแดง + บอกเหตุผล (เซิร์ฟเวอร์จะไม่บันทึกด้วย)
    ผูกที่ document — ใช้ได้กับแถวที่กดเพิ่มทีหลัง · ช่องที่สนใจ: .tb-date หรือช่องที่มี placeholder "วว/ดด/ปปปป"
    ใน scope ที่ระบุ (บัตร TB 01 ทั้งหน้า / ส่วน TB ในหน้าตรวจ)
--}}
<style>
    .tbd-note { font-size: .75rem; margin-top: 2px; }
    .tbd-note.ok { color: #0f766e; }
    .tbd-note.err { color: #b91c1c; }
</style>
<script>
(function () {
    if (window.__tbDateInput) return;
    window.__tbDateInput = true;
    var SCOPE = @json($scope ?? 'body');

    function isDateInput(el) {
        if (!el || el.tagName !== 'INPUT' || el.type === 'hidden') return false;
        if (!el.closest(SCOPE)) return false;
        return el.classList.contains('tb-date') || el.classList.contains('tmc-hist-date') || el.classList.contains('tmc-plan-date') || /วว\/ดด\/ปปปป/.test(el.getAttribute('placeholder') || '');
    }
    function note(el, text, cls) {
        var n = el.parentNode.querySelector(':scope > .tbd-note');
        if (!text) { if (n) n.remove(); return; }
        if (!n) { n = document.createElement('div'); n.className = 'tbd-note'; el.insertAdjacentElement('afterend', n); }
        n.className = 'tbd-note ' + cls; n.textContent = text;
    }
    function check(el) {
        var raw = (el.value || '').trim();
        el.classList.remove('is-invalid'); el.title = '';
        if (raw === '') { note(el, ''); return; }
        var m = raw.match(/^(\d{1,2})\s*[\/\-.]\s*(\d{1,2})\s*[\/\-.]\s*(\d+)$/);
        var bad = function (msg) { el.classList.add('is-invalid'); el.title = msg; note(el, msg, 'err'); };
        if (!m) return bad('รูปแบบต้องเป็น วว/ดด/ปปปป (พ.ศ.)');
        var d = +m[1], mo = +m[2], ys = m[3];
        if (ys.length !== 4) return bad('ปีต้องพิมพ์ครบ 4 หลัก (พ.ศ.)');
        var y = +ys, conv = false;
        if (y < 1900) return bad('ปี ' + ys + ' ไม่ถูกต้อง');
        if (y < 2400) { y += 543; conv = true; }
        if (mo < 1 || mo > 12) return bad('เดือนต้องเป็น 1-12 (อาจสลับวันกับเดือน)');
        var dt = new Date(y - 543, mo - 1, d);
        if (dt.getFullYear() !== y - 543 || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return bad('ไม่มีวันที่ ' + d + ' ในเดือน ' + mo);
        var nowY = new Date().getFullYear() + 543;
        if (y < nowY - 30 || y > nowY + 2) return bad('ปี ' + y + ' อยู่นอกช่วงที่รับได้');
        var out = ('0' + d).slice(-2) + '/' + ('0' + mo).slice(-2) + '/' + y;
        if (el.value !== out) { el.value = out; }
        note(el, conv ? 'แปลงปี ค.ศ. ' + ys + ' เป็น พ.ศ. ' + y + ' แล้ว' : '', 'ok');
    }
    // datepicker ตั้งค่าด้วย change · พิมพ์เองจบด้วย blur (focusout)
    document.addEventListener('focusout', function (e) { if (isDateInput(e.target)) check(e.target); });
    document.addEventListener('change', function (e) { if (isDateInput(e.target)) check(e.target); });
})();
</script>
