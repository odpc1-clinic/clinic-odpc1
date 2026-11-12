{{-- ไฮไลต์ช่องที่กรอกแล้ว — หน้างานวัณโรคมีช่องเยอะมาก พยาบาลหลงว่ากรอกอะไรไปแล้วบ้าง
     ทำด้วยสีพื้นอ่อน ๆ ไม่ใช่กรอบหนา จะได้ไม่ตีกับสีเตือน (เหลือง/แดง) ที่มีอยู่แล้ว (คลินิกขอ 2569-09-29) --}}
<style>
    .tb-filled { background-color: #e7f6ec !important; }           /* ช่องที่มีค่าแล้ว */
    .tb-filled-check { background-color: #e7f6ec; border-radius: .25rem; }
    /* ผลที่ต้องสะดุดตา เช่น HIV Positive · AFB พบเชื้อ · ดื้อยา — ต้องเห็นทันทีที่เปิดหน้า */
    .tb-alert-value {
        background-color: #ffe3e3 !important;
        color: #b02a37 !important;
        font-weight: 700;
        border-color: #f1aeb5 !important;
    }
</style>
<script>
(function () {
    if (window.__tbFilledBound) { return; }
    window.__tbFilledBound = true;

    // ครอบทั้งหน้าตรวจของคลินิกวัณโรค (ฟอร์มหลัก + โซนคัดกรอง) และทุกกล่องในบัตร TB 01
    // ไฟล์นี้ถูก include เฉพาะหน้างานวัณโรค คลินิกอื่นจึงไม่โดนทาสีไปด้วย
    var SCOPE = '#tbScreening, #visit_edit_form, form[action*="/tb/"]';
    var SKIP  = ['hidden', 'submit', 'button', 'file', 'search'];

    function fields() {
        var out = [];
        document.querySelectorAll(SCOPE).forEach(function (box) {
            box.querySelectorAll('input, select, textarea').forEach(function (el) { out.push(el); });
        });
        return out;
    }

    function paint(el) {
        if (SKIP.indexOf(el.type) !== -1) { return; }
        // ช่องที่ระบบเติมให้เอง (เช่น เลขเดือน/รอบ) ไม่ต้องทาสี ไม่ใช่สิ่งที่พยาบาลกรอก
        if (el.hasAttribute('data-no-highlight')) { return; }

        if (el.type === 'checkbox' || el.type === 'radio') {
            var wrap = el.closest('.form-check') || el.closest('.input-group-text');
            if (wrap) { wrap.classList.toggle('tb-filled-check', el.checked); }
            return;
        }

        var has = String(el.value || '').trim() !== '';

        // ค่าที่ต้องเตือน: ผลบวก / พบเชื้อ / ดื้อยา
        // อ่านจากข้อความที่พยาบาลเห็นจริง (ถ้าเป็น dropdown ใช้ข้อความของตัวเลือก ไม่ใช่ค่ารหัส)
        var shown = el.tagName === 'SELECT'
            ? (el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : '')
            : String(el.value || '');
        shown = shown.trim().toLowerCase();

        var alert = has && (
            /^(positive|pos|reactive)\b/.test(shown)
            || /ผลบวก|พบเชื้อ|ดื้อยา/.test(shown)
            || /^(1\+|2\+|3\+|scanty)$/.test(shown)
            || /\bmtb detected\b/.test(shown)
            || /rif resistance detected/.test(shown)
            || /^growth\b/.test(shown)            // Culture โตขึ้น = พบเชื้อ (NG = no growth ไม่เข้าเงื่อนไขนี้)
        );

        el.classList.toggle('tb-filled', has && ! alert);
        el.classList.toggle('tb-alert-value', alert);
    }

    function paintAll() { fields().forEach(paint); }

    // ทาสีตอนเปิดหน้า แล้วอัปเดตทุกครั้งที่พิมพ์/เลือก (รวมแถวที่กดเพิ่มทีหลัง)
    ['input', 'change'].forEach(function (ev) {
        document.addEventListener(ev, function (e) {
            if (e.target.closest && e.target.closest(SCOPE)) { paint(e.target); }
        });
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest && e.target.closest('.js-tb-add-row, .js-tb-insert-row')) {
            setTimeout(paintAll, 50);
        }
    });

    paintAll();
    setTimeout(paintAll, 800);   // เผื่อช่องที่สคริปต์อื่นเติมค่าให้ทีหลัง (เช่น ปุ่มคำนวณขนาดยา)
})();
</script>
