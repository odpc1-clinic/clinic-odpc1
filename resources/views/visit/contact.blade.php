<div class="row">

    <div class="col-md-12">
        <div class="card">
            <div class="card-header">Contact Person for Visit #{{ $visit->id }}</div>
            <div class="card-body">
                {{-- คนไข้ไม่ยินยอมให้ศูนย์ฯ ติดต่อคู่สัมผัสเอง (PDPA 2569-10-29) — บันทึกข้อมูลได้ แต่ห้ามโทร/ติดต่อแทน --}}
                @unless(\App\Models\PatientConsent::allows((int) $visit->patient_id, 'contact'))
                    <div class="alert py-2 mb-2" style="background:#fef2f2; border:2px solid #dc2626; color:#7f1d1d;">
                        <b><i class="fa fa-ban me-1"></i>ไม่มีความยินยอมให้ศูนย์ฯ ติดต่อคู่สัมผัส (PDPA)</b>
                        @if(($__ctAge = \App\Models\PatientConsent::ageOf(\App\Models\Patient::find($visit->patient_id))) !== null && $__ctAge < 20)
                            <span class="small">· ผู้เยาว์ ต้องให้ผู้ปกครองยินยอม</span>
                        @endif — ห้ามโทร/ติดต่อคู่สัมผัสแทนคนไข้ ให้บัตรแนะนำคนไข้นำไปให้เอง
                    </div>
                @endunless
                <a href="{{ route('contact-person.create',['visit' => $visit->id,'page'=>request('page', 1)]) }}" class="btn btn-success btn-sm" title="Add New ContactPerson">
                    <i class="fa fa-plus" aria-hidden="true"></i> Add New
                </a>

                {{-- ดึงคู่สัมผัสเดิมของคนไข้รายนี้มาใช้ ไม่ต้องคีย์ใหม่ทุกครั้ง (พยาบาลขอ 2569-09-08) --}}
                <button type="button" class="btn btn-outline-primary btn-sm js-ch-open"
                        data-bs-toggle="modal" data-bs-target="#contactHistoryModal"
                        data-url="{{ route('contact-person.history', ['visit' => $visit->id, 'page' => request('page', 1)]) }}"
                        title="ดูคู่สัมผัสที่เคยบันทึกของคนไข้รายนี้ แล้วดึงมาใช้">
                    <i class="fas fa-clock-rotate-left"></i> คู่สัมผัสเดิม
                </button>
                <br />
                <br />
                @include ('visit.table_contact_person', ['contactperson' => $visit->contactPerson()])

            </div>
        </div>
    </div>
</div>

{{-- Popup คู่สัมผัสเดิม: โหลดเนื้อหาด้วย AJAX ตอนกด, ปุ่มดึงเป็นฟอร์ม POST ธรรมดา (โหลดหน้าใหม่กลับมาที่แท็บเดิม) --}}
<div class="modal fade" id="contactHistoryModal" tabindex="-1" aria-labelledby="contactHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contactHistoryLabel"><i class="fas fa-user-group me-1"></i> คู่สัมผัสเดิมของคนไข้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="contactHistoryBody">
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
    if (window.__contactHistoryInit) return;
    window.__contactHistoryInit = true;

    // ปุ่ม "ดึงทั้งชุด" / "ดึง" ใน popup — ต้องผูกจากหน้าหลักเท่านั้น
    // เนื้อหา popup มาทาง AJAX แล้วยัดด้วย innerHTML ซึ่งเบราว์เซอร์จะไม่รัน <script> ที่ติดมาด้วย
    // และต้องสร้าง <form> ต่อกับ body เพราะตารางนี้อยู่ในฟอร์มหลักของหน้าตรวจ วางฟอร์มซ้อนไม่ได้
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-cp-copy');
        if (!btn || btn.disabled) return;
        e.preventDefault();

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังดึง...';

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.url;
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                    + '<input type="hidden" name="page" value="' + (btn.dataset.page || '1') + '">'
                    + '<input type="hidden" name="' + btn.dataset.field + '" value="' + btn.dataset.value + '">';
        document.body.appendChild(f);
        f.submit();
    });

    var ctrl = null;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-ch-open');
        if (!btn) return;

        var body = document.getElementById('contactHistoryBody');
        if (!body) return;
        body.innerHTML = '<div class="text-center text-muted py-4">'
            + '<i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2">กำลังโหลด...</div></div>';

        if (ctrl) ctrl.abort();
        ctrl = new AbortController();

        fetch(btn.dataset.url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function (err) {
            if (err.name === 'AbortError') return;
            body.innerHTML = '<div class="alert alert-danger mb-0">'
                + '<i class="fas fa-triangle-exclamation me-1"></i> โหลดคู่สัมผัสเดิมไม่สำเร็จ กรุณาลองใหม่</div>';
        });
    });
})();
</script>
