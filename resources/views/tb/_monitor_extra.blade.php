{{-- ตรวจสายตา + คัดกรองซึมเศร้า (บัตร TB 01 แท็บ "ติดตาม") — 2569-10-05
     ถูกสลับด้วย innerHTML หลังบันทึก — ⚠️ ห้ามมี <script> ห้ามมี <form>
     ปุ่มบันทึกเปิด popup ท้ายหน้า (tb/_monitor_modals) · ปุ่มลบทำงานผ่าน tb/_monitor_js
     ตัวแปร: $reg --}}
@php
    $__TM = \App\Services\TbMonitor\TbMonitor::class;
    $__m  = $__TM::safe($reg);
    $__v  = $__m['vision'] ?? null;
    $__d  = $__m['depression'] ?? null;
    $__vReady = $__m['ready']['vision'] ?? false;
    $__dReady = $__m['ready']['depression'] ?? false;
@endphp
<div class="row g-3">
    {{-- ---------- ตรวจสายตา ---------- --}}
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="fa fa-eye me-1"></i> ตรวจสายตา (VA + ตาบอดสี)</h6>
                <div class="small text-muted mb-2">
                    คลินิกตรวจทุกรายก่อนเริ่มยา · ระหว่างได้ Ethambutol / Linezolid ตรวจทุกเดือน
                    @if($__v && $__v['drugs'])<br><b style="color:#b45309;">ตอนนี้ได้ {{ implode(' / ', $__v['drugs']) }}</b>@endif
                </div>
                @if($__v && $__v['advice'])
                    <div class="alert alert-warning py-1 px-2 small mb-2"><i class="fa fa-circle-info me-1"></i>{{ $__v['advice'] }}</div>
                @endif

                @if(! $__vReady)
                    <div class="alert alert-light border py-2 small mb-0"><i class="fa fa-hourglass-half me-1"></i><b>รอเปิดใช้</b> — ผู้ดูแลระบบยังไม่ได้สร้างตารางผลตรวจสายตา</div>
                @else
                    <button type="button" class="btn btn-sm btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#tbVisionModal">
                        <i class="fa fa-plus me-1"></i> บันทึกผลตรวจสายตา
                    </button>
                    @if($__v['tests']->isEmpty())
                        <div class="text-muted small">ยังไม่มีผล</div>
                    @else
                        <table class="table table-sm table-bordered small mb-0 align-middle">
                            <thead class="table-light"><tr><th>วันที่</th><th>VA ขวา</th><th>VA ซ้าย</th><th>ตาบอดสี</th><th style="width:2rem"></th></tr></thead>
                            <tbody>
                            @foreach($__v['tests'] as $__t)
                                <tr @if($loop->first) style="background:#f0fdf4;" @endif>
                                    <td class="text-nowrap">{{ \App\Utillity::db2thDate($__t->test_date) }}@if($loop->first) <span class="badge bg-success">ล่าสุด</span>@endif</td>
                                    <td>{{ $__t->va_right ?: '—' }}</td>
                                    <td>{{ $__t->va_left ?: '—' }}</td>
                                    <td @if($__t->color_result === 'abnormal') class="text-danger fw-bold" @endif>{{ $__t->colorText() }}
                                        @if($__t->note)<div class="text-muted">{{ $__t->note }}</div>@endif</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-danger js-tbm-del" title="ลบ"
                                                data-url="{{ route('tb.monitor.vision.delete', [$reg->id, $__t->id]) }}"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif
            </div>
        </div>
    </div>

    {{-- ---------- คัดกรองซึมเศร้า ---------- --}}
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h6 class="fw-bold mb-2"><i class="fa fa-face-frown-open me-1"></i> คัดกรองซึมเศร้า (2Q / 9Q / 8Q)</h6>
                <div class="small text-muted mb-2">
                    ประเมินตอนเริ่มรักษา · สัปดาห์ที่ 2-4 · ทุกครั้งที่มารับยา (ทุก 1-2 เดือน) · และทุกครั้งที่มีสัญญาณเตือน
                    (ไม่กินยา/ขาดนัด ท้อแท้ นอนไม่หลับ เบื่ออาหาร พูดถึงความตาย)
                </div>
                @if($__d && $__d['advice'])
                    <div class="alert alert-warning py-1 px-2 small mb-2"><i class="fa fa-circle-info me-1"></i>{{ $__d['advice'] }}</div>
                @endif

                @if(! $__dReady)
                    <div class="alert alert-light border py-2 small mb-0"><i class="fa fa-hourglass-half me-1"></i><b>รอเปิดใช้</b> — ผู้ดูแลระบบยังไม่ได้สร้างตารางผลคัดกรองซึมเศร้า</div>
                @else
                    <button type="button" class="btn btn-sm btn-primary mb-2" data-bs-toggle="modal" data-bs-target="#tbDepressionModal">
                        <i class="fa fa-plus me-1"></i> ประเมินซึมเศร้า
                    </button>
                    @if($__d['rows']->isEmpty())
                        <div class="text-muted small">ยังไม่เคยประเมิน</div>
                    @else
                        <table class="table table-sm table-bordered small mb-0 align-middle">
                            <thead class="table-light"><tr><th>วันที่</th><th>2Q</th><th>9Q</th><th>8Q</th><th>แปลผล</th><th style="width:2rem"></th></tr></thead>
                            <tbody>
                            @foreach($__d['rows'] as $__s)
                                @php $__bad = ($__s->q9_score ?? 0) >= 7 || ($__s->q8_score ?? 0) >= 1; @endphp
                                <tr @if($loop->first) style="background:#f0fdf4;" @endif>
                                    <td class="text-nowrap">{{ \App\Utillity::db2thDate($__s->screen_date) }}@if($loop->first) <span class="badge bg-success">ล่าสุด</span>@endif</td>
                                    <td>{{ $__s->q2_positive ? 'บวก' : 'ลบ' }}</td>
                                    <td>{{ $__s->q9_score ?? '—' }}</td>
                                    <td>{{ $__s->q8_score ?? '—' }}</td>
                                    <td @if($__bad) class="text-danger fw-bold" @endif>{{ $__s->interpretation }}
                                        @if($__s->referred)<span class="badge bg-danger">ส่งต่อแล้ว</span>@endif
                                        @if($__s->note)<div class="text-muted fw-normal">{{ $__s->note }}</div>@endif</td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-danger js-tbm-del" title="ลบ"
                                                data-url="{{ route('tb.monitor.depression.delete', [$reg->id, $__s->id]) }}"><i class="fa fa-trash"></i></button>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>
