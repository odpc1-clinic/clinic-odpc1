{{--
    การติดตามผู้ป่วยของคนไข้วัณโรค — ย้ายมาจากหน้าตรวจ (OPD) มาไว้ในบัตร TB 01 (คลินิกขอ 2569-10-01)
    ข้อมูลยังเก็บเป็นรายครั้งที่มา (visit_followups ผูกกับ visit) — ตรงนี้รวมทุกครั้งของคนไข้ในคลินิกวัณโรค
    เพิ่ม/แก้ใช้หน้าเดิมของระบบ แล้วเด้งกลับมาที่บัตร (back_tb) · ไม่มี <form> ในกล่องนี้ ลบด้วย fetch
--}}
@php
    $__fuVisits = \App\Models\Visit::where('patient_id', $reg->patient_id)->where('clinic', 2)
        ->orderByDesc('date')->orderByDesc('id')->get(['id', 'date']);
    $__fus = \App\Models\VisitFollowup::whereIn('visit_id', $__fuVisits->pluck('id'))
        ->orderByDesc('created_at')->get();
    $__fuVisitDate = $__fuVisits->pluck('date', 'id');
    // เพิ่มการติดตามผูกกับครั้งล่าสุดที่มาคลินิกวัณโรค (วันนี้ถ้ามา)
    $__fuLatest = $__fuVisits->first();
@endphp
<div class="card border-0 shadow-sm mb-3" id="tb-followup">
    <div class="card-body">
        <h6 class="fw-bold mb-2"><i class="fa fa-phone-volume me-1"></i> การติดตามผู้ป่วย</h6>
        @if($__fus->isEmpty())
            <div class="text-muted small mb-2">ยังไม่มีการติดตาม</div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-2">
                    <thead class="table-light">
                        <tr>
                            <th style="width:9rem;">วันที่ติดตาม</th>
                            <th style="width:7rem;">ช่องทาง</th>
                            <th style="width:6rem;">ครั้งที่</th>
                            <th style="width:9rem;">สถานะ</th>
                            <th>หมายเหตุ</th>
                            <th style="width:8rem;">ของการมาวันที่</th>
                            <th style="width:8.5rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($__fus as $__f)
                        <tr>
                            <td>{{ optional($__f->created_at)->format('d/m/') }}{{ optional($__f->created_at)->year ? $__f->created_at->year + 543 : '' }} {{ optional($__f->created_at)->format('H:i') }}</td>
                            <td>{{ $__f->method }}</td>
                            <td>{{ $__f->followup_count }}</td>
                            <td>{{ $__f->status }}</td>
                            <td>{{ $__f->remark }}</td>
                            <td class="small">{{ \App\Utillity::db2thDate($__fuVisitDate[$__f->visit_id] ?? '') }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('followups.edit', ['followup' => $__f->id, 'back_tb' => $reg->id]) }}" class="btn btn-warning btn-sm">แก้ไข</a>
                                <button type="button" class="btn btn-outline-danger btn-sm js-tb-fu-del" data-url="{{ url('followups/' . $__f->id) }}">ลบ</button>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @if($__fuLatest)
            <a href="{{ route('followups.create', ['visit' => $__fuLatest->id, 'back_tb' => $reg->id]) }}" class="btn btn-success btn-sm">
                <i class="fa fa-plus"></i> เพิ่มการติดตาม
            </a>
            <span class="small text-muted ms-2">บันทึกไว้กับการมาวันที่ {{ \App\Utillity::db2thDate($__fuLatest->date) }}</span>
        @else
            <div class="small text-muted">คนไข้ยังไม่มีการมาคลินิกวัณโรคในระบบ จึงยังเพิ่มการติดตามไม่ได้</div>
        @endif
    </div>
</div>
<script>
document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('.js-tb-fu-del');
    if (!b || !confirm('ยืนยันการลบการติดตามนี้?')) return;
    fetch(b.dataset.url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j.success) { location.hash = 'tb-followup'; location.reload(); } else { alert('ลบไม่สำเร็จ'); } })
        .catch(function () { alert('ลบไม่สำเร็จ'); });
});
</script>
