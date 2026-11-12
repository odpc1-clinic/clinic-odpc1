@extends('layouts.app')

{{-- เปิดบัตร TB 01 ใหม่ — เข้ามาได้ 2 ทาง: จากหน้าคนไข้ (มี patient_id) หรือค้นคนไข้จากหน้านี้ --}}

@section('content')
<div class="container">
    <div class="p-3 mb-3 shadow-sm" style="background:linear-gradient(135deg,#b45309 0%,#f59e0b 100%);color:#fff;border-radius:.75rem;">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="opacity-75 small"><i class="fa-solid fa-lungs me-1"></i> คลินิกวัณโรค</div>
                <h4 class="mb-0 fw-bold">เปิดบัตร TB 01 / บัตรวัณโรคแฝง (LTBI) ใหม่</h4>
                <div class="small opacity-90">เลือก "ประเภททะเบียน" = วัณโรคแฝง แล้วหน้าบัตรเปลี่ยนเป็นแบบ LTBI ให้เอง</div>
            </div>
            <a href="{{ route('tb.index') }}" class="btn btn-light btn-sm"><i class="fa fa-arrow-left"></i> กลับทะเบียน</a>
        </div>
    </div>

    @if ($errors->any())
        <ul class="alert alert-danger">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif

    @if(! $patient)
        {{-- ขั้นที่ 1: เลือกคนไข้ก่อน --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="fw-bold mb-3"><i class="fa fa-user me-1"></i> เลือกคนไข้</h6>
                <form method="GET" action="{{ route('tb.create') }}" class="row g-2 align-items-end mb-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted mb-1">ค้นหาคนไข้</label>
                        <input type="text" name="q" value="{{ $q }}" class="form-control" autofocus
                               placeholder="HN / ชื่อ / สกุล / เลขบัตรประชาชน">
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-primary"><i class="fa fa-magnifying-glass"></i> ค้นหา</button>
                    </div>
                </form>

                @if($q !== '' && $found->isEmpty())
                    <div class="alert alert-warning mb-0">
                        ไม่พบคนไข้ที่ตรงกับ "{{ $q }}" —
                        ถ้าเป็นคนไข้ใหม่ ให้ลงทะเบียนที่หน้า
                        <a href="{{ route('patient.index') }}">ทะเบียนคนไข้</a> ก่อน แล้วค่อยกลับมาเปิดบัตร
                    </div>
                @elseif($found->isNotEmpty())
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light"><tr><th style="width:8rem">HN</th><th>ชื่อ-สกุล</th><th style="width:6rem">เพศ</th><th style="width:8rem"></th></tr></thead>
                            <tbody>
                            @foreach($found as $p)
                                <tr>
                                    <td>{{ $p->code }}</td>
                                    <td>{{ $p->name }} {{ $p->surname }}</td>
                                    <td>{{ \App\Models\Patient::$sexOption[$p->sex] ?? '-' }}</td>
                                    <td>
                                        <a href="{{ route('tb.create', ['patient_id' => $p->id]) }}" class="btn btn-sm btn-primary">
                                            เลือกคนนี้
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    @else
        {{-- ขั้นที่ 2: กรอกบัตร --}}
        @if($openOne)
            <div class="alert alert-warning">
                <i class="fa fa-triangle-exclamation me-1"></i>
                คนไข้รายนี้มีบัตรที่ยังรักษาอยู่แล้ว ({{ $openOne->tb_no ?: 'ยังไม่ใส่เลขทะเบียน' }})
                — <a href="{{ route('tb.show', $openOne->id) }}">เปิดบัตรเดิม</a>
                หรือเปิดบัตรใหม่ต่อได้ถ้าเป็นการรักษาคนละคอร์สจริง ๆ
            </div>
        @endif

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-2">
                <span class="fw-bold">{{ $patient->name }} {{ $patient->surname }}</span>
                <span class="text-muted ms-2">HN {{ $patient->code }}</span>
                <span class="text-muted ms-2">{{ \App\Models\Patient::$sexOption[$patient->sex] ?? '-' }}</span>
                <span class="text-muted ms-2">{{ method_exists($patient, 'age') ? $patient->age() . ' ปี' : '' }}</span>
                <a href="{{ route('tb.create') }}" class="btn btn-sm btn-outline-secondary ms-2">เปลี่ยนคนไข้</a>
            </div>
        </div>

        {{-- กดบันทึกแล้วปิดปุ่มทันที — เคยกดเบิ้ลจนได้บัตรซ้ำ 2 ใบในวินาทีเดียวกัน (2569-10-22) --}}
        <form method="POST" action="{{ route('tb.store') }}" autocomplete="off"
              onsubmit="var b=this.querySelector('.js-tb-create-btn'); if(b){ b.disabled=true; b.innerHTML='กำลังบันทึก…'; }">
            @csrf
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
            @if(request('from_screening'))<input type="hidden" name="from_screening" value="{{ request('from_screening') }}">@endif
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    @if($screening)
                        <div class="alert alert-success py-2 px-3">
                            <i class="fa fa-wand-magic-sparkles me-1"></i>
                            เติมข้อมูลจากใบคัดกรองของวันที่ {{ \App\Utillity::db2thDate(optional($screening->visit)->date ?? '') ?: '-' }} ให้แล้ว
                            (ผลสรุป: {{ \App\Models\TbScreening::$impressionOption[$screening->impression] ?? '-' }}) — ตรวจทานแล้วแก้ได้
                        </div>
                    @endif
                    @include('tb._form', ['reg' => $prefill])
                </div>
                <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2">
                    @if($openOne)
                        {{-- มีบัตรที่ยังรักษาอยู่ = ต้องยืนยันก่อน (หน้า DOT ขึ้นชื่อซ้ำเพราะเปิดบัตรใบที่ 2 โดยไม่ตั้งใจ) --}}
                        <div class="form-check w-100 mb-1">
                            <input class="form-check-input" type="checkbox" name="confirm_second" value="1" id="tb-confirm-second" required>
                            <label class="form-check-label text-danger fw-semibold" for="tb-confirm-second">
                                ยืนยันว่าเป็นการรักษา<b>คนละคอร์ส</b> — เปิดบัตรใบที่ 2 (บัตรเดิม {{ $openOne->tb_no ?: 'ไม่มีเลขทะเบียน' }} ยังไม่ได้ปิดผลการรักษา)
                            </label>
                        </div>
                    @endif
                    <button class="btn btn-primary js-tb-create-btn"><i class="fa fa-save"></i> เปิดบัตร</button>
                    <a href="{{ route('tb.index') }}" class="btn btn-outline-secondary">ยกเลิก</a>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
{{-- ปฏิทิน พ.ศ. ไม่ได้อยู่ใน layout กลาง แต่ละหน้าต้องดึงเอง (ทำเหมือนหน้าวันหยุด/แล็บ) --}}
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
    // ปฏิทิน พ.ศ. ชุดเดียวกับหน้าอื่นในระบบ (bootstrap-datepicker-thai)
    $(function () { $('.tb-date').datepicker({ language: 'th-th', autoclose: true, todayHighlight: true }); });
</script>
@endpush
