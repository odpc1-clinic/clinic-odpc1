{{-- Popup ประวัติย้อนหลังของช่องข้อความคลินิก + ปุ่มดึงข้อความเดิมมาใช้ --}}
{{-- ใส่ครั้งเดียวต่อหน้า ปุ่มที่เรียกใช้คือ .js-fh-open (มี data-url และ data-target = id ของ textarea) --}}
<div class="modal fade" id="fieldHistoryModal" tabindex="-1" aria-labelledby="fieldHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="fieldHistoryLabel">
                    <i class="fas fa-clock-rotate-left me-1"></i> ข้อความเดิมที่เคยบันทึก
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="fieldHistoryBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <div class="mt-2">กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    if (window.__fieldHistoryInit) return;
    window.__fieldHistoryInit = true;

    var ctrl = null, targetId = null;

    document.addEventListener('click', function (e) {

        // 1) กดปุ่มแว่นขยายข้างชื่อช่อง -> โหลดประวัติของช่องนั้น
        var opener = e.target.closest('.js-fh-open');
        if (opener) {
            targetId = opener.dataset.target;
            var body = document.getElementById('fieldHistoryBody');
            if (!body) return;
            body.innerHTML = '<div class="text-center text-muted py-4">'
                + '<i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2">กำลังโหลด...</div></div>';

            if (ctrl) ctrl.abort();
            ctrl = new AbortController();

            fetch(opener.dataset.url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: ctrl.signal
            })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) { body.innerHTML = html; })
            .catch(function (err) {
                if (err.name === 'AbortError') return;
                body.innerHTML = '<div class="alert alert-danger mb-0">'
                    + '<i class="fas fa-triangle-exclamation me-1"></i> โหลดประวัติไม่สำเร็จ กรุณาลองใหม่</div>';
            });
            return;
        }

        // 2) กด "ใช้แทน" / "ต่อท้าย" -> เติมข้อความลงช่องจริง
        var use = e.target.closest('.js-fh-use');
        if (!use || !targetId) return;

        var el = document.getElementById(targetId);
        if (!el) return;

        var text = use.dataset.text || '';
        if (use.dataset.mode === 'append' && el.value.trim() !== '') {
            el.value = el.value.replace(/\s*$/, '') + '\n' + text;
        } else {
            el.value = text;
        }

        // ให้ตัวยืด textarea กลาง (partials/textarea_autogrow) ปรับความสูงตามข้อความใหม่
        el.dispatchEvent(new Event('input', { bubbles: true }));

        // แจ้ง autosave ว่ามีการเปลี่ยนแปลง (input ที่ยิงเองมี isTrusted=false ตัว autosave จะไม่นับให้)
        document.dispatchEvent(new CustomEvent('visit:dirty'));

        // ปิด popup แล้วชี้ให้เห็นว่าข้อความไปลงช่องไหน
        var modalEl = document.getElementById('fieldHistoryModal');
        var closeBtn = modalEl ? modalEl.querySelector('[data-bs-dismiss="modal"]') : null;
        if (closeBtn) closeBtn.click();

        el.focus();
        el.classList.add('fh-flash');
        setTimeout(function () { el.classList.remove('fh-flash'); }, 1200);
    });
})();
</script>

<style>
    .fh-flash { animation: fhFlash 1.2s ease-out; }
    @keyframes fhFlash {
        0%   { background-color: #fef08a; }
        100% { background-color: transparent; }
    }
</style>
