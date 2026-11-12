@extends('layouts.app')

@section('content')
<style>
    .labedit-hero { background: linear-gradient(135deg,#0f766e 0%,#0891b2 100%); border-radius:16px; color:#fff; }
    .labedit-hero .id-chip { display:inline-block; background:rgba(255,255,255,.2); border:1px solid rgba(255,255,255,.32);
                             border-radius:8px; padding:2px 12px; font-weight:600; font-size:.85rem; }
    .lab-card { border:0; border-radius:14px; }
    .lab-card .card-header { background:#fff; border-bottom:1px solid #eef2f7; border-radius:14px 14px 0 0; font-weight:700; }
    .lab-card .card-header .sec-ico { width:34px; height:34px; border-radius:9px; display:inline-flex; align-items:center; justify-content:center;
                                      color:#fff; margin-right:8px; }
</style>

<div class="container-fluid py-4">

    {{-- ===== Hero: ระบุตัวคนไข้ ===== --}}
    <div class="labedit-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <div class="opacity-75 small"><i class="fas fa-vials me-1"></i> ลงผลตรวจทางห้องปฏิบัติการ</div>
            <h3 class="mb-2 fw-bold text-white">{{ $patient->name ?? '-' }} {{ $patient->surname ?? '' }}</h3>
            <div class="d-flex flex-wrap gap-2">
                <span class="id-chip"><i class="fas fa-id-card-clip me-1"></i> HN {{ $patient->code ?? '-' }}</span>
                @if(!empty($lab->LN))<span class="id-chip"><i class="fas fa-barcode me-1"></i> LN {{ $lab->LN }}</span>@endif
                <span class="id-chip">Lab #{{ $lab->id }}</span>
            </div>
        </div>
        <div class="d-flex flex-column gap-2">
            @if ($backUrl)
                <a href="{{ $backUrl }}" class="btn btn-light fw-bold shadow-sm"><i class="fa fa-arrow-left me-1"></i> กลับหน้า Visit</a>
            @endif
            <a href="{{ route('lab.index') }}" class="btn btn-outline-light"><i class="fas fa-vials me-1"></i> กลับหน้า Lab</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        {{-- ===== ซ้าย: ข้อมูล/รายงาน Lab ===== --}}
        <div class="col-xl-4">
            {{-- ===== ขั้นตอน Lab (ISO 15189) — ชื่อ/เวลาประทับจากปุ่ม = คนที่ login + เวลาจริง (flow ใหม่ 2569-09-30) =====
                 แต่ละปุ่มเป็นฟอร์มของตัวเอง วางนอกฟอร์ม "ข้อมูลรายงาน Lab" (ห้ามฟอร์มซ้อน) --}}
            @php
                $__uname = fn ($id) => $id ? (optional(\App\Models\User::find($id))->name ?? $id) : null;
                $__rawLab = \App\Models\Lab::find($lab->id);   // $lab ถูกแปลงวันที่ไว้แสดงในฟอร์มแล้ว ใช้ค่าดิบแยก
                $__thdt = function ($date, $time = null) {
                    if (! $date) { return null; }
                    $d = \Carbon\Carbon::parse($date);
                    $y = $d->year < 2500 ? $d->year + 543 : $d->year;
                    return $d->format('d/m/') . $y . ($time ? ' ' . substr((string) $time, 0, 5) : '');
                };
            @endphp
            <div class="card lab-card shadow-sm mb-3">
                <div class="card-header py-3 d-flex align-items-center">
                    <span class="sec-ico" style="background:#6366f1;"><i class="fas fa-list-check"></i></span>
                    ขั้นตอน Lab
                </div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div><b>1. ส่ง Lab</b> (เก็บสิ่งส่งตรวจ)
                            <div class="text-muted">{{ $__thdt($__rawLab->collected_date, $__rawLab->collected_at) ?: '—' }}</div></div>
                        <span class="badge {{ $__rawLab->collected_date ? 'bg-success' : 'bg-secondary' }}">{{ $__rawLab->collected_date ? '✓' : 'ยัง' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div><b>2. รับ Lab</b>
                            <div class="text-muted">
                                @if($__rawLab->received_at){{ $__uname($__rawLab->received_by) }} · {{ $__thdt($__rawLab->received_at, \Carbon\Carbon::parse($__rawLab->received_at)->format('H:i')) }}@else — @endif
                            </div></div>
                        @if($__rawLab->received_at)
                            <span class="badge bg-success">✓</span>
                        @else
                            <form method="POST" action="{{ route('lab.receive', $lab->id) }}">@csrf
                                <button class="btn btn-sm btn-primary"><i class="fas fa-inbox"></i> รับ Lab</button></form>
                        @endif
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div><b>3. Tested</b>
                            <div class="text-muted">{{ $__uname($__rawLab->tested_by) ?: '—' }}{{ $__rawLab->tested_date ? ' · ' . $__thdt($__rawLab->tested_date) : '' }}</div>
                            <div class="text-muted" style="font-size:.75rem;">เลือกในกล่อง "ข้อมูลรายงาน Lab" ด้านล่าง</div></div>
                        <span class="badge {{ $__rawLab->tested_by ? 'bg-success' : 'bg-secondary' }}">{{ $__rawLab->tested_by ? '✓' : 'ยัง' }}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div><b>4. Report</b>
                            <div class="text-muted">
                                @if($__rawLab->report_by){{ $__uname($__rawLab->report_by) }} · {{ $__thdt($__rawLab->report_date, $__rawLab->report_at) }}@else — @endif
                            </div></div>
                        <form method="POST" action="{{ route('lab.reportResult', $lab->id) }}">@csrf
                            <button class="btn btn-sm {{ $__rawLab->report_by ? 'btn-outline-secondary' : 'btn-primary' }}"
                                    @if($__rawLab->report_by) onclick="return confirm('Report ซ้ำ? ชื่อและเวลาจะเป็นของคุณ ณ ตอนนี้')" @endif>
                                <i class="fas fa-file-signature"></i> {{ $__rawLab->report_by ? 'Report ซ้ำ' : 'Report' }}
                            </button></form>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div><b>5. Approve</b>
                            <div class="text-muted">
                                @if($__rawLab->approve_by){{ $__uname($__rawLab->approve_by) }} · {{ $__thdt($__rawLab->approve_date, $__rawLab->approve_at) }}@else — @endif
                            </div></div>
                        {{-- ปุ่มเดียวกับกล่อง "อนุมัติผล" ใต้ตารางลงผล — วางไว้ตรงนี้ด้วยให้หาเจอ (คลินิกหาไม่เจอ 2569-09-30) --}}
                        {{-- Approve ซ้ำได้เหมือน Report (ผลออกเพิ่มทีหลัง) — ชื่อ/เวลาเป็นของคนที่กดล่าสุด
                             ของเก่าอยู่ในบันทึกการแก้ไขของระบบ ผลยังให้พยาบาล/หมอเห็นตลอด ไม่ต้องถอนก่อน (2569-09-30) --}}
                        <form method="POST" action="{{ route('lab.approve', $lab->id) }}">@csrf
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm {{ $__rawLab->approve_by ? 'btn-outline-success' : 'btn-success' }}"
                                    @if($__rawLab->approve_by) onclick="return confirm('Approve ซ้ำ? ชื่อและเวลาจะเป็นของคุณ ณ ตอนนี้')" @endif>
                                <i class="fas fa-circle-check"></i> {{ $__rawLab->approve_by ? 'Approve ซ้ำ' : 'Approve' }}
                            </button></form>
                    </div>
                    <div class="text-muted mt-2" style="font-size:.75rem;">ชื่อและเวลาใช้คนที่ login และเวลาจริงเสมอ · Report กับ Approve เป็นคนเดียวกันได้</div>
                </div>
            </div>

            <div class="card lab-card shadow-sm">
                <div class="card-header py-3 d-flex align-items-center">
                    <span class="sec-ico" style="background:#0891b2;"><i class="fas fa-file-signature"></i></span>
                    ข้อมูลรายงาน Lab
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('lab.update', ['lab' => $lab->id, 'page' => request('page', 1)]) }}"
                        accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" autocomplete="off">
                        {{ method_field('PATCH') }}
                        {{ csrf_field() }}

                        @include ('lab.form', ['formMode' => 'edit'])
                    </form>
                </div>
            </div>
        </div>

        {{-- ===== ขวา: ลงผลตรวจ ===== --}}
        <div class="col-xl-8">
            <div class="card lab-card shadow-sm">
                <div class="card-header py-3 d-flex align-items-center">
                    <span class="sec-ico" style="background:#0f766e;"><i class="fas fa-microscope"></i></span>
                    ลงผลตรวจ (Result Entry)
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('lab-sub.updateAllSub', ['id' => $lab->id, 'page' => request('page', 1)]) }}"
                        accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" autocomplete="off">
                        {{ csrf_field() }}

                        @include ('lab-sub.table', ['addResult' => 1, 'labsub' => $lab->labSub()])

                        <div class="d-flex justify-content-end mt-2">
                            <input class="btn btn-primary px-4" type="submit" value="บันทึกผลตรวจ">
                        </div>
                    </form>

                    {{-- อนุมัติผล: พยาบาล/หมอจะเห็นผลก็ต่อเมื่ออนุมัติแล้วเท่านั้น --}}
                    <div class="mt-3 p-3 rounded-3 border {{ $lab->isApproved() ? 'border-success' : 'border-warning' }}"
                         style="background: {{ $lab->isApproved() ? '#f0fdf4' : '#fffbeb' }};">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                @if($lab->isApproved())
                                    <b class="text-success"><i class="fas fa-circle-check"></i> อนุมัติผลแล้ว</b>
                                    <div class="small text-muted">
                                        โดย {{ optional(\App\Models\User::find($lab->approve_by))->name ?? $lab->approve_by }}
                                        @if($lab->approve_at) · {{ \Carbon\Carbon::parse($lab->approve_at)->format('d/m/Y H:i') }} น. @endif
                                        — พยาบาล/หมอเห็นผลนี้แล้ว
                                    </div>
                                @else
                                    <b class="text-warning-emphasis"><i class="fas fa-hourglass-half"></i> ยังไม่อนุมัติผล</b>
                                    <div class="small text-muted">พยาบาล/หมอจะ <b>ยังไม่เห็นผล</b> จนกว่าจะกดอนุมัติ</div>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('lab.approve', $lab->id) }}">
                                {{ csrf_field() }}
                                @if($lab->isApproved())
                                    <input type="hidden" name="action" value="unapprove">
                                    <button class="btn btn-outline-secondary"
                                            onclick="return confirm('ถอนอนุมัติเพื่อแก้ไขผล?\nพยาบาล/หมอจะไม่เห็นผลนี้ชั่วคราว')">
                                        <i class="fas fa-rotate-left"></i> ถอนอนุมัติ (แก้ผล)
                                    </button>
                                @else
                                    <input type="hidden" name="action" value="approve">
                                    <button class="btn btn-success btn-lg fw-bold px-4">
                                        <i class="fas fa-circle-check"></i> อนุมัติผล
                                    </button>
                                @endif
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@include('sweetalert::alert')
@endsection
