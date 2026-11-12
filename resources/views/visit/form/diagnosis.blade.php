@php
    // หน้า TMC บางทีเปิดมาโดยไม่มี ?page= -> อ่าน $_GET ตรง ๆ จะไม่มีคีย์ ทำให้ขึ้น notice
    $__dgPage = $_GET['page'] ?? request('page', 2);
@endphp
<div>
@php
    // TB: หมอกรอกวินิจฉัยช่องเดียวที่ ICD-10 ด้านบน (คลินิกขอ 2569-10-09) — ตารางนี้เหลือไว้ลง "ให้คำปรึกษา HIV" (Pre/Post-test)
    // ซึ่งทะเบียน RIHIS-VCT นับจากตาราง diagnoses · ลงแบบเดิมยังทำได้ (ปุ่มพับไว้) เผื่อเคสอื่นที่ต้องใช้
    $__dgTb = (int) ($visit->clinic ?? 1) === 2;
    $__dgAll = $visit->diagnosi();
    $__dgHas = fn ($d) => $__dgAll->contains(fn ($x) => (int) $x->disease === $d);
    $__dgOther = $__dgTb ? $__dgAll->reject(fn ($x) => in_array((int) $x->disease, [13, 14], true))->values() : $__dgAll;
@endphp
@if($__dgTb)
    {{-- TB: ให้คำปรึกษา HIV ย้ายไปต่อบันทึกทางการพยาบาล · วินิจฉัยแบบเดิมรวมเข้าตาราง ICD-10 ด้านบนแล้ว (คลินิกขอ 2569-10-14) --}}
@else
    <a href="{{ route('diagnosis.create',['visit' => $visit->id, 'page'=>$__dgPage]) }}" class="btn btn-success btn-sm" title="Add New Diagnosis">
        <i class="fa fa-plus" aria-hidden="true"></i> Add New
    </a>

    {{-- ดึงวินิจฉัยเดิมของคนไข้รายนี้ (เคสติดตามอาการมักวินิจฉัยเดิม) --}}
    <button type="button" class="btn btn-outline-primary btn-sm js-dh-open"
            data-bs-toggle="modal" data-bs-target="#diagHistoryModal"
            data-url="{{ route('diagnosis.history', ['visit' => $visit->id, 'page' => $__dgPage]) }}"
            title="ดูวินิจฉัยเดิมของคนไข้รายนี้ แล้วดึงมาใช้">
        <i class="fas fa-clock-rotate-left"></i> วินิจฉัยเดิม
    </button>

    @include ('diagnosis.table', ['diagnosis' => $__dgOther])
@endif



</div>
{{-- Popup วินิจฉัยเดิม (โครงเดียวกับ popup ยาเดิม) --}}
<div class="modal fade" id="diagHistoryModal" tabindex="-1" aria-labelledby="diagHistoryLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="diagHistoryLabel"><i class="fas fa-stethoscope me-1"></i> วินิจฉัยเดิมของคนไข้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="diagHistoryBody">
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
    if (window.__diagHistoryInit) return;
    window.__diagHistoryInit = true;

    var ctrl = null;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-dh-open');
        if (!btn) return;

        var body = document.getElementById('diagHistoryBody');
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
                + '<i class="fas fa-triangle-exclamation me-1"></i> โหลดวินิจฉัยเดิมไม่สำเร็จ กรุณาลองใหม่</div>';
        });
    });
})();
</script>
