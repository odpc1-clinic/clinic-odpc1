@extends('layouts.app')
<?php
// หน้าตรวจของคลินิก TB: ผล HIV ทุกจุดในหน้าปิดไว้ กดดูแล้วบันทึกว่าใครดู (partials/hiv_mask · 2569-10-06) — STI/TMC เหมือนเดิม
$__hivMaskPage = (int) ($visit->clinic ?? 0) === 2 && $visit->patient_id;
$visits = App\Models\Visit::where('patient_id', $visit->patient_id)
    ->orderBy('date', 'asc') // เรียงเก่า -> ใหม่ ให้ครั้งล่าสุดอยู่ล่างสุด (ตามที่พยาบาลคุ้นเคย)
    ->orderBy('id', 'asc')   // วันเดียวกันหลายครั้ง ให้เรียงตามลำดับที่เกิดจริง
    ->with('certificates')
    ->get();

// แบ่งหน้าตารางประวัติในการ์ด
// ค่าเริ่มต้น = ล่าสุดขึ้นก่อน (desc) · ?vsort=asc = เก่า->ใหม่ แล้วเปิดหน้าสุดท้ายให้อัตโนมัติ
// ใช้ชื่อ 'vpage' แยกจาก ?page= ที่หน้านี้ใช้สลับแท็บอยู่แล้ว (กันชนกัน)
// หมายเหตุ: $visits ต้องคง asc ไว้เสมอ เพราะสรุปข้อมูลด้านบนใช้ ->reverse()->first() หาค่าล่าสุด
$vhSort    = request('vsort') === 'asc' ? 'asc' : 'desc';
$vhOrdered = $vhSort === 'asc' ? $visits : $visits->reverse()->values();

$vhPerPage = 10;
$vhLast    = max(1, (int) ceil($vhOrdered->count() / $vhPerPage));
$vhPage    = (int) request('vpage');
$vhPage    = $vhPage >= 1 ? min($vhPage, $vhLast) : ($vhSort === 'asc' ? $vhLast : 1);

$visitsPage = new \Illuminate\Pagination\LengthAwarePaginator(
    $vhOrdered->forPage($vhPage, $vhPerPage)->values(),
    $vhOrdered->count(),
    $vhPerPage,
    $vhPage,
    ['path' => request()->url(), 'query' => array_merge(request()->query(), ['vsort' => $vhSort]), 'pageName' => 'vpage']
);

// สรุปข้อมูลสำคัญด้านบน popup ประวัติ: วินิจฉัยล่าสุด / ยาปัจจุบัน / ผล lab ล่าสุด
$latestDiagnosisVisit = $visits->reverse()->first(function ($v) {
    return trim($v->diagnosiAllString()) !== '';
});

$latestMedicineVisit = $visits->reverse()->first(function ($v) {
    return $v->visitMedicines()->exists();
});
$currentMedicines = $latestMedicineVisit
    ? $latestMedicineVisit->visitMedicines()->with('medicine')->get()
    : collect();

$latestLab = \App\Models\Lab::whereIn('visit_id', $visits->pluck('id'))
    ->whereHas('labSubs', function ($q) {
        $q->whereNotNull('result')->where('result', '!=', '');
    })
    ->with(['labSubs' => function ($q) {
        $q->whereNotNull('result')->where('result', '!=', '');
    }])
    ->latest('id')
    ->first();
?>
    {{-- ========================================== --}}
    {{-- กลุ่มป้ายลอยมุมขวาบน: คิว / ประวัติ / โน้ต / LINE (จัดกลุ่มเดียวให้เนียน)
         ย้ายจากกึ่งกลางขอบขวามาไว้ขวาบนตามที่คลินิกขอ 2569-09-23
         top เว้นความสูงแถบเมนูไว้ (แถบเมนูเลื่อนตามหน้า ไม่ได้ปักหมุด) ป้ายจะได้ไม่ทับกัน --}}
    {{-- ========================================== --}}
    <div class="position-fixed float-tabs" id="float-tabs" style="top: 88px; right: 0; z-index: 1042;">
        <div class="float-tab bg-warning text-dark"
             onclick="editQueue({{ $visit->id }}, '{{ $visit->queue }}')" title="คลิกเพื่อแก้ไขเลขคิว">
            <div class="ft-label">QUEUE</div>
            <div class="ft-value"><i class="fas fa-ticket-alt"></i> <span id="display-queue">{{ $visit->queue ?? '-' }}</span></div>
        </div>

        <div class="float-tab bg-info text-white"
             data-bs-toggle="modal" data-bs-target="#historyModal" title="ดูประวัติการรับบริการ">
            <div class="ft-label">HISTORY</div>
            <div class="ft-value"><i class="fas fa-history"></i> {{ $visits->count() }}</div>
        </div>

        <div class="float-tab bg-secondary text-white position-relative"
             data-bs-toggle="modal" data-bs-target="#noteModal" id="noteModalTrigger" title="Note คนไข้">
            <div class="ft-label">NOTE</div>
            <div class="ft-value"><i class="fas fa-sticky-note"></i> {{ $patientNotes->count() }}</div>
            @if($unreadNoteCount > 0)
                <span id="note-unread-badge" class="position-absolute top-0 start-0 translate-middle badge rounded-pill bg-danger">
                    {{ $unreadNoteCount }}
                </span>
            @endif
        </div>

        <div class="float-tab bg-success text-white"
             onclick="generateLineLinkCode({{ $visit->patient_id }}, {{ $visit->patient2->line_user_id ? 'true' : 'false' }}, '{{ $visit->patient2->line_linked_at ? \Carbon\Carbon::parse($visit->patient2->line_linked_at)->format('d/m/Y H:i') : '' }}')"
             title="สร้างรหัสผูก LINE แจ้งเตือนนัดหมาย">
            <div class="ft-label">LINE นัด</div>
            <div class="ft-value"><i class="fab fa-line"></i></div>
        </div>
    </div>
    <script>
    // แถบเมนูไม่ได้ปักหมุด และความสูงเปลี่ยนตามความกว้างจอ (ปุ่มห้องตัดบรรทัด)
    // จึงคำนวณตำแหน่งป้ายจากความสูงจริงของแถบเมนู ไม่ใช้ค่าคงที่ ไม่งั้นจอบางขนาดป้ายจะทับเมนู
    (function () {
        var tabs = document.getElementById('float-tabs');
        if (!tabs) return;
        function place() {
            var nav = document.querySelector('nav.navbar');
            var navBottom = nav ? nav.getBoundingClientRect().bottom : 0;
            tabs.style.top = Math.max(8, navBottom + 8) + 'px';
        }
        place();
        window.addEventListener('scroll', place, { passive: true });
        window.addEventListener('resize', place);
    })();
    </script>
@section('content')
<style>
    .visit-hero { background: linear-gradient(135deg,#4f46e5 0%,#0ea5e9 100%); border-radius:14px; color:#fff; }
    /* ป้ายห้อง: พื้นขาว ตัวอักษรสีประจำห้อง (สีกำหนดแบบ inline ตามสถานะ visit) */
    .room-badge { display:inline-block; margin-top:4px; padding:5px 18px; border-radius:999px;
                  background:#fff; font-weight:800; font-size:1.12rem; letter-spacing:.02em;
                  border:1px solid #fff; box-shadow:0 2px 10px rgba(0,0,0,.22); }
    /* แถบแพ้ยา/โรคประจำตัว: ปักหมุดไว้บนสุดของจอ ให้หมอ/พยาบาลเห็นตลอดแม้เลื่อนหน้าลง */
    .alert-allergy { border:2px solid #dc3545; background:#fff5f5; color:#b91c1c; border-radius:12px;
                     position:sticky; top:8px; z-index:1040; }
    .alert-allergy .allergy-name { font-weight:800; }
    /* กระพริบเบาๆ เรียกสายตา ครั้งแรกที่เข้าหน้า */
    @keyframes allergyPulse { 0%{box-shadow:0 0 0 0 rgba(220,53,69,.45);} 100%{box-shadow:0 0 0 14px rgba(220,53,69,0);} }
    .alert-allergy.pulse { animation:allergyPulse 1.2s ease-out 3; }
    /* กลุ่มป้ายลอยขอบขวา (QUEUE/HISTORY/NOTE/LINE) */
    .float-tabs { display:flex; flex-direction:column; gap:6px; }
    .float-tab { border-radius:12px 0 0 12px; padding:8px 14px; cursor:pointer; text-align:center; min-width:78px;
                 box-shadow:-2px 2px 10px rgba(0,0,0,.18); transition:padding-right .2s ease, transform .15s ease; }
    .float-tab:hover { padding-right:24px; transform:translateX(-2px); }
    .float-tab .ft-label { font-size:.62rem; font-weight:700; letter-spacing:.04em; opacity:.9; }
    .float-tab .ft-value { font-size:1.1rem; font-weight:800; line-height:1.15; }
</style>
    <div class="container container-wide">

        <div class="row">
            {{-- @include('admin.sidebar') --}}

            <div class="col-md-12">
                @php
                    $pt = $visit->patient2;
                    $noVal = ['', '-', 'ปฏิเสธ', 'ไม่มี', 'ไม่ระบุ'];
                    $allergyRaw = trim($pt->drug_allergy ?? '');
                    $congenRaw = trim($pt->congenitaldisease ?? '');
                    $hasAllergy = !in_array($allergyRaw, $noVal, true);
                    $hasCongen = !in_array($congenRaw, $noVal, true);
                    // ชื่อ + สีประจำห้องตามสถานะ visit (ให้ตรงกับ dropdown ด้านล่างฟอร์ม)
                    // สีต่างกันชัดเจน เพื่อให้เห็นแวบเดียวว่าคนไข้อยู่ห้องไหน
                    $roomStyles = [
                        2 => ['name' => 'ห้องตรวจชาย 4',       'grad' => 'linear-gradient(135deg,#1d4ed8 0%,#3b82f6 100%)', 'ink' => '#1d4ed8', 'icon' => 'fa-mars'],
                        8 => ['name' => 'ห้องตรวจหญิง 5',      'grad' => 'linear-gradient(135deg,#be185d 0%,#ec4899 100%)', 'ink' => '#be185d', 'icon' => 'fa-venus'],
                        3 => ['name' => 'ห้องเจาะเลือด/ฉีดยา', 'grad' => 'linear-gradient(135deg,#b91c1c 0%,#f97316 100%)', 'ink' => '#b91c1c', 'icon' => 'fa-syringe'],
                        7 => ['name' => 'ห้องตรวจ 7 (TMC)',    'grad' => 'linear-gradient(135deg,#6d28d9 0%,#a855f7 100%)', 'ink' => '#6d28d9', 'icon' => 'fa-plane-departure'],
                        10 => ['name' => 'ห้องตรวจวัณโรค 2',  'grad' => 'linear-gradient(135deg,#b45309 0%,#f59e0b 100%)', 'ink' => '#b45309', 'icon' => 'fa-lungs'],
                        9 => ['name' => 'ห้องการเงิน',         'grad' => 'linear-gradient(135deg,#a16207 0%,#facc15 100%)', 'ink' => '#a16207', 'icon' => 'fa-cash-register'],
                        5 => ['name' => 'ห้องยา',              'grad' => 'linear-gradient(135deg,#047857 0%,#10b981 100%)', 'ink' => '#047857', 'icon' => 'fa-pills'],
                        6 => ['name' => 'กลับบ้าน',            'grad' => 'linear-gradient(135deg,#475569 0%,#94a3b8 100%)', 'ink' => '#475569', 'icon' => 'fa-house-chimney'],
                    ];
                    $rs = $roomStyles[(int) $visit->status]
                        ?? ['name' => 'ห้องตรวจ', 'grad' => 'linear-gradient(135deg,#4f46e5 0%,#0ea5e9 100%)', 'ink' => '#4f46e5', 'icon' => 'fa-door-open'];
                    $roomName = $rs['name'];
                @endphp

                {{-- Hero header: ระบุตัวคนไข้ชัดเจน + ชื่อห้องเด่นๆ --}}
                <div class="visit-hero p-3 mb-3 shadow-sm" style="background: {{ $rs['grad'] }};">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <div class="opacity-75 small"><i class="fas fa-notes-medical me-1"></i> หน้าตรวจ / บันทึกการรักษา</div>
                            <span class="room-badge" style="background:#fff; color:{{ $rs['ink'] }}; border-color:#fff;">
                                <i class="fas {{ $rs['icon'] }} me-1"></i> {{ $roomName }}
                            </span>
                            <h4 class="mb-0 fw-bold mt-1 text-white">{{ $pt->name ?? '-' }} {{ $pt->surname ?? '' }}</h4>
                            <div class="opacity-90 mt-1 small">
                                <span class="me-3"><i class="fas fa-id-card-clip me-1"></i> HN {{ $pt->code ?? '-' }}</span>
                                <span class="me-3">{{ isset($pt->sex) && isset(\App\Models\Patient::$sexOption[$pt->sex]) ? \App\Models\Patient::$sexOption[$pt->sex] : '-' }}</span>
                                <span><i class="fas fa-cake-candles me-1"></i> {{ ($pt && method_exists($pt,'age')) ? $pt->age() : '-' }} ปี</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <a href="{{ route('patient.edit', $visit->patient_id) }}" class="btn btn-light btn-sm shadow-sm"><i class="fa fa-arrow-left"></i> กลับหน้าคนไข้</a>
                            <a href="{{ route('activity-log.patient', $visit->patient_id) }}" target="_blank" class="btn btn-outline-light btn-sm shadow-sm"><i class="fa fa-clock-rotate-left"></i> ประวัติการแก้ไข</a>
                            <div class="opacity-75 small mt-1">Visit #{{ $visit->id }}</div>
                        </div>
                    </div>
                </div>

                {{-- แถบเตือนแพ้ยา/โรคประจำตัว ติดค้างตลอด (ความปลอดภัยผู้ป่วย — ไม่ใช่แค่ popup เด้ง) --}}
                @if($hasAllergy || $hasCongen)
                    <div class="alert-allergy pulse p-3 mb-3 shadow-sm d-flex align-items-center gap-3">
                        <i class="fas fa-triangle-exclamation fa-2x"></i>
                        <div>
                            @if($hasAllergy)
                                <div style="font-size:1.05rem;">⚠️ แพ้ยา: <span class="allergy-name">{{ $allergyRaw }}</span></div>
                            @endif
                            @if($hasCongen)
                                <div class="small">โรคประจำตัว: <b>{{ $congenRaw }}</b></div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- ส่งเวรค้าง (ระดับคนไข้ ไม่ต้องมีนัด) — เห็นทันทีที่เปิด visit ถัดไป (2569-10-27) --}}
                @include('visit._handover_js')
                @include('visit._handover', ['hoMode' => 'banner'])
                @include('visit._handover_appt', ['hoApptVisit' => $visit])

                {{-- ความยินยอม PDPA — อยู่นอกฟอร์มหลัก (2569-10-29) --}}
                @if($__pdpaPt = \App\Models\Patient::find($visit->patient_id))
                    @include('partials.consent_modal', ['cPatient' => $__pdpaPt])
                @endif

                {{-- TMC: เคยฉีดไข้เหลืองแล้ว (ที่นี่หรือที่อื่น) = รับรองตลอดชีพ — โชว์ใต้หัวหน้าตรวจ ไม่ต้องเลื่อนหาในโซนวัคซีน (2569-10-07) --}}
                @if((int) $visit->clinic === 3)
                    @include('partials.yellow_fever_badge', ['yfShots' => \App\Models\VisitVaccine::yellowFeverShots((int) $visit->patient_id)])
                @endif

                <div class="card border-0 shadow-sm">
                    <div class="card-body">

                        <div class="card mb-3 shadow-sm">
                            <div class="card-body p-3">
                                <h6 class="text-muted mb-2"><i class="fas fa-bullhorn"></i> ระบบเรียกคิวกลาง (STI/TB/TMC)</h6>
                                @if($linkedQueue)
                                    <div class="d-flex align-items-center gap-2 flex-wrap">
                                        <span class="badge bg-primary" style="font-size: 1rem;">คิวที่ {{ $linkedQueue->queue_number }}</span>
                                        <span class="badge bg-secondary">ห้อง {{ $linkedQueue->room_number }}</span>
                                        <span id="cq-status">
                                        @if($linkedQueue->status === 'called')
                                            <span class="badge bg-success">เรียกแล้ว</span>
                                        @elseif($linkedQueue->status === 'completed')
                                            <span class="badge bg-danger">เสร็จแล้ว</span>
                                        @else
                                            <span class="badge bg-warning text-dark">รอเรียก</span>
                                        @endif
                                        </span>
                                        <span id="cq-count" class="text-muted small">
                                            @if($linkedQueue->call_count > 0)เรียกไปแล้ว {{ $linkedQueue->call_count }} ครั้ง@endif
                                        </span>
                                        {{-- เรียกผ่าน AJAX (script ท้ายหน้า) ไม่โหลดหน้าใหม่ ข้อมูลที่กรอกค้างไว้ไม่หาย
                                             ถ้า JS ไม่ทำงาน ฟอร์มนี้ยัง submit แบบเดิมได้ --}}
                                        <form id="cq-form" action="{{ route('visit.callQueue', $visit->id) }}" method="POST" class="d-inline ms-2"
                                              data-wait="{{ (int) ($queueCallWait ?? 0) }}"
                                              data-confirm="ยืนยันเรียกคิว {{ $linkedQueue->queue_number }} ห้อง {{ $linkedQueue->room_number }}?">
                                            @csrf
                                            <button type="submit" id="cq-btn" class="btn btn-danger btn-sm">
                                                <i class="fa fa-bullhorn"></i> เรียกคิว
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-muted small">ยังไม่ได้ล็อกคิวสำหรับ visit นี้ (เลือกคิวได้ที่หน้าลงทะเบียนผู้ป่วย)</span>
                                @endif
                            </div>
                        </div>

                        @if ($errors->any())
                            <ul class="alert alert-danger">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
<div class="accordion" id="accordionExample">
    <div class="card mb-3 shadow-sm">
    <div class="card-body p-3">
        <h6 class="text-muted mb-3"><i class="fas fa-history"></i> เส้นทางการรับบริการ (Patient Journey)</h6>
        
        <div class="d-flex justify-content-between text-center position-relative">
            
            {{-- เส้นเชื่อม (Background Line) --}}
            <div style="position: absolute; top: 15px; left: 0; width: 100%; height: 4px; background: #e9ecef; z-index: 0;"></div>

            {{-- 1. ลงทะเบียน --}}
            <div style="z-index: 1; background: #fff; padding: 0 10px;">
                {{-- เช็คว่ามีคนลงทะเบียน หรือ มีเวลาลงทะเบียน อย่างใดอย่างหนึ่ง --}}
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 {{ ($visit->created_at || $visit->registrationUser) ? 'bg-success text-white' : 'bg-light text-muted border' }}" style="width: 35px; height: 35px;">
                    <i class="fas fa-user-plus"></i>
                </div>
                <small class="fw-bold d-block">ลงทะเบียน</small>
                
                {{-- ✅ แก้ไข: ให้แสดงถ้ามี User (แม้เวลาจะไม่มีก็ตาม) --}}
                @if($visit->registrationUser)
                    {{-- ถ้ามีเวลา ให้โชว์เวลา ถ้าไม่มีให้เว้นว่างไว้ --}}
                    <small class="text-success d-block" style="font-size: 0.75rem;">
                        {{ $visit->created_at ? $visit->created_at->format('H:i') : '' }}
                    </small>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                        <i class="fas fa-user-circle"></i> {{ $visit->registrationUser->name }}
                    </small>
                @elseif($visit->created_at)
                     {{-- กรณีมีแต่เวลา (แต่ User ถูกลบไปแล้ว) --}}
                    <small class="text-success d-block" style="font-size: 0.75rem;">{{ $visit->created_at->format('H:i') }}</small>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">-</small>
                @else
                    <small class="text-muted" style="font-size: 0.75rem;">-</small>
                @endif
            </div>

            {{-- 2. ซักประวัติ/คัดกรอง --}}
            <div style="z-index: 1; background: #fff; padding: 0 10px;">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 {{ $visit->screening_at ? 'bg-success text-white' : 'bg-light text-muted border' }}" style="width: 35px; height: 35px;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <small class="fw-bold d-block">ซักประวัติ</small>

                {{-- ✅ แสดงเวลา + ชื่อผู้บันทึก --}}
                @if($visit->screening_at)
                    <small class="text-success d-block" style="font-size: 0.75rem;">{{ $visit->screening_at->format('H:i') }}</small>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                        <i class="fas fa-user-nurse"></i> {{ $visit->nurseUser->name ?? '-' }}
                    </small>
                @else
                    <small class="text-muted" style="font-size: 0.75rem;">-</small>
                @endif
            </div>

            {{-- 3. พบแพทย์ --}}
            <div style="z-index: 1; background: #fff; padding: 0 10px;">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 {{ $visit->doctor_at ? 'bg-success text-white' : 'bg-light text-muted border' }}" style="width: 35px; height: 35px;">
                    <i class="fas fa-user-md"></i>
                </div>
                <small class="fw-bold d-block">พบแพทย์</small>

                {{-- ✅ แสดงเวลา + ชื่อผู้บันทึก --}}
                @if($visit->doctor_at)
                    <small class="text-success d-block" style="font-size: 0.75rem;">{{ $visit->doctor_at->format('H:i') }}</small>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                        <i class="fas fa-user-md"></i> {{ $visit->doctorUser->name ?? '-' }}
                    </small>
                @else
                    <small class="text-muted" style="font-size: 0.75rem;">-</small>
                @endif
            </div>

            {{-- 4. เสร็จสิ้น (ใช้ handledByUser) --}}
            <div style="z-index: 1; background: #fff; padding: 0 10px;">
                <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1 {{ ($visit->status == 5 || $visit->finished_at) ? 'bg-success text-white' : 'bg-light text-muted border' }}" style="width: 35px; height: 35px;">
                    <i class="fas fa-check"></i>
                </div>
                <small class="fw-bold d-block">เสร็จสิ้น</small>

                {{-- ✅ แสดงเวลา + ชื่อผู้บันทึก --}}
                @if($visit->finished_at)
                    <small class="text-success d-block" style="font-size: 0.75rem;">{{ $visit->finished_at->format('H:i') }}</small>
                    <small class="text-muted d-block" style="font-size: 0.7rem;">
                        <i class="fas fa-check-circle"></i> {{ $visit->handledByUser->name ?? '-' }}
                    </small>
                @else
                    <small class="text-muted" style="font-size: 0.75rem;">-</small>
                @endif
            </div>

        </div>
    </div>
</div>
                        {{-- แถบบัตรวัณโรค TB 01 ย้ายเข้าไปอยู่ใต้ข้อมูลคนไข้ใน visit/form/index แล้ว (2569-10-02) --}}
                        <form autocomplete="off" method="POST" id="visit_edit_form"
                            {{-- request('page', 2) กัน notice "Undefined array key page" ตอนเปิดหน้าโดยไม่มี ?page= (เขียน log ~30KB ทุกครั้ง) --}}
                            action="{{ route('visit.update', ['visit' => $visit->id, 'page' => request('page', 2)]) }}"
                            accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" autocomplete="off">
                            
                            {{ csrf_field() }}

                            @include('visit.form.index', ['formMode' => 'edit'])

                            {{ method_field('PATCH') }}
                            {{-- @include('visit.form.save') --}}

<div class="row align-items-center g-2" id="visit-action-bar">
    <div>
        <a href="{{ route('patient.edit', $visit->patient_id) }}" class="btn btn-warning btn-sm me-2" title="Back">
            <i class="fa fa-arrow-left"></i> Back
        </a>

        <a href="{{ route('visit.print', $visit->id) }}" target="_blank" class="btn btn-secondary btn-sm" title="Print Medical Record">
            <i class="fa fa-print"></i> Print Medical Record
        </a>

       @if(isset($visit) && $visit->id)
    <a href="{{ route('visit.medicalCertificate', $visit->id) }}" target="_blank" class="btn btn-info btn-sm">
        <i class="fas fa-file-medical"></i> พิมพ์ใบรับรองแพทย์
    </a>
    <a href="{{ route('visit.medicalCertificateEn', $visit->id) }}" target="_blank" class="btn btn-outline-info btn-sm">
        <i class="fas fa-file-medical"></i> Medical Cert (EN)
    </a>
    {{-- TMC: ใบนัดวัคซีนรวมนัดทั่วไปไว้แล้ว พิมพ์รอบเดียว (คลินิกขอ 2569-10-08) — ไม่มีปุ่มใบนัดทั่วไปแยก --}}
    @if((int)$visit->clinic === 3)
    <a href="{{ route('visit.appointmentCard', ['id' => $visit->id, 'type' => 'vaccine']) }}" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-calendar-check"></i> ใบนัด (วัคซีน + นัดอื่น)
    </a>
    @else
    <a href="{{ route('visit.appointmentCard', ['id' => $visit->id]) }}" target="_blank" class="btn btn-success btn-sm">
        <i class="fas fa-calendar-check"></i> ใบนัดทั่วไป
    </a>
    @endif
@endif
    </div>
  {{-- ส่งต่อคลินิก --}}
  <div class="col-auto">
    <label class="visually-hidden" for="clinic">Clinic</label>
    <select class="form-select" id="clinic" name="clinic" required>
      <option value="1" {{ (int)$visit->clinic === 1 ? 'selected' : '' }}>STI Clinic</option>
      <option value="2" {{ (int)$visit->clinic === 2 ? 'selected' : '' }}>TB Clinic</option>
      <option value="3" {{ (int)$visit->clinic === 3 ? 'selected' : '' }}>TMC Clinic</option>
    </select>
  </div>

  {{-- ห้อง/สถานะ --}}
  <div class="col-auto">
    <label class="visually-hidden" for="status">Status</label>
    {{-- ห้องที่คนไข้อยู่ตอนเปิดหน้านี้ — ใช้เช็คตอนกดบันทึกว่ามีห้องอื่นย้ายคนไข้ไปก่อนแล้วหรือยัง
         (เคสจริง: ห้อง 4 ส่งไป 6 แล้วเปิดหน้าค้าง ห้อง 6 ส่งกลับ 4 แต่ห้อง 4 ไม่รู้ กดส่งไป 6 ซ้ำ 2569-09-14) --}}
    <input type="hidden" name="_orig_status" value="{{ (int) $visit->status }}">
    <input type="hidden" name="_orig_room_at" value="{{ optional($visit->room_entered_at)->format('Y-m-d H:i:s') }}">
    <select class="form-select" id="status" name="status" required>
      {{-- data-clinics = ห้องนี้เป็นของคลินิกไหน — ช่องนี้โชว์เฉพาะห้องของคลินิกที่เลือกอยู่ (ห้องที่คนไข้อยู่ตอนนี้โชว์เสมอ)
           เดิมโชว์ทุกห้อง พยาบาล STI เลือก "ห้องตรวจ 7 (TMC)" แทนการกดปุ่มส่งไป TMC → คนไข้ไม่โผล่ฝั่ง TMC (แจ้ง 2569-11-09) --}}
      <option value="2" data-clinics="1" {{ (int)$visit->status === 2 ? 'selected' : '' }}>ห้องตรวจชาย 4</option>
      <option value="8" data-clinics="1" {{ (int)$visit->status === 8 ? 'selected' : '' }}>ห้องตรวจหญิง 5</option>
      <option value="3" data-clinics="1 2 3" {{ (int)$visit->status === 3 ? 'selected' : '' }}>ห้องเจาะเลือด/ฉีดยา</option>
      <option value="7" data-clinics="3" {{ (int)$visit->status === 7 ? 'selected' : '' }}>ห้องตรวจ 7 (TMC)</option>
      {{-- คลินิก TB เดินห้อง 2 -> 6 -> 10 (พี่กานต์ยืนยัน 2569-09-18) --}}
      <option value="10" data-clinics="2" {{ (int)$visit->status === 10 ? 'selected' : '' }}>ห้องตรวจวัณโรค 2</option>
      {{-- ส่งเข้า "ห้องยา" — ห้องยาจ่ายยาและเก็บเงินเอง (กองคลังแจ้งว่าออกใบเสร็จจากคอมไม่ได้ 2569-09-11)
           ห้องการเงินยังเปิดจากเมนูได้ แต่ไม่อยู่ในเส้นทางเดินของคนไข้แล้ว จึงเหลือไว้เฉพาะเคสที่ค้างสถานะเดิม --}}
      <option value="5" {{ (int)$visit->status === 5 ? 'selected' : '' }}>ห้องยา (จ่ายยา/เก็บเงิน)</option>
      @if((int)$visit->status === 9)
      <option value="9" selected>ห้องการเงิน (เคสค้างจากระบบเดิม)</option>
      @endif
      <option value="6" {{ (int)$visit->status === 6 ? 'selected' : '' }}>กลับบ้าน</option>
      @if((int) $visit->clinic !== 3)
      {{-- ไม่ใช่ห้อง — เลือกแล้วทำงานเหมือนกดปุ่ม "ส่งไป TMC" (เปิด visit TMC ใหม่) แล้วช่องกลับเป็นห้องเดิม --}}
      <option value="__send_tmc" data-clinics="1 2" style="color:#6d28d9;font-weight:bold;">✈ ส่งไป TMC (เปิด visit ใหม่)</option>
      @endif
    </select>
  </div>
  @php
      // Refer (คลินิก TB ขอ 2569-10-08) — แยกจากช่องเลือกห้อง: ติ๊ก Refer แล้วค่อยกรอก รพ. · ไม่ติ๊ก = ช่องเป็นสีเทา
      // เก็บแค่ refer_hospital (มีค่า = refer) · checkbox ไม่มี name ไม่ไปกับฟอร์ม
      $__referOn = (int) $visit->clinic === 2 && \App\Models\Visit::referReady();
      $__isRefer = $__referOn && trim((string) $visit->refer_hospital) !== '';
  @endphp
  @if($__referOn)
  <div class="col-auto d-flex align-items-center gap-2" id="refer-hospital-wrap">
    <div class="form-check mb-0">
      <input class="form-check-input" type="checkbox" id="refer-check" {{ $__isRefer ? 'checked' : '' }}>
      <label class="form-check-label fw-bold" for="refer-check">Refer</label>
    </div>
    {{-- ไม่ใช้ disabled — ช่อง disabled ไม่ถูกส่ง ตัวเทียบ _touched จะนึกว่าช่องหาย · ใช้ readonly + สีเทาแทน --}}
    <input type="text" class="form-control" name="refer_hospital" id="refer_hospital" maxlength="255" list="refer-hospital-list"
           value="{{ $visit->refer_hospital }}" placeholder="Refer ไป รพ. ไหน" style="min-width:15rem;"
           {{ $__isRefer ? 'required' : 'readonly' }}>
    <datalist id="refer-hospital-list">
        @foreach(\App\Models\Visit::whereNotNull('refer_hospital')->where('refer_hospital', '!=', '')->select('refer_hospital')->groupBy('refer_hospital')->orderByRaw('COUNT(*) DESC')->limit(30)->pluck('refer_hospital') as $__rh)
            <option value="{{ $__rh }}">
        @endforeach
    </datalist>
  </div>
  <style>#refer_hospital[readonly] { background:#e9ecef; color:#6c757d; cursor:not-allowed; }</style>
  <script>
  (function () {
      var cb = document.getElementById('refer-check'), inp = document.getElementById('refer_hospital');
      if (!cb || !inp) return;
      cb.addEventListener('change', function () {
          inp.readOnly = !cb.checked;
          inp.required = cb.checked;
          if (cb.checked) { inp.focus(); }
          else if (inp.value !== '') { inp.value = ''; inp.dispatchEvent(new Event('input', { bubbles: true })); }
      });
  })();
  </script>
  @endif
  <div class="col-auto">
    <button type="submit" class="btn btn-primary btn-save-th px-4" id="visit-save-btn"><i class="fa fa-save me-1"></i> บันทึก</button>
  </div>

  {{-- ส่งคนไข้ไป TMC โดยเปิด visit ใหม่ให้เลย — พยาบาลส่งได้เอง ไม่ต้องให้คนไข้เดินกลับเวชระเบียน
       ต้องเป็น type="button" + ส่งด้วย JS (ห้ามซ้อน <form> ในฟอร์มหลัก) --}}
  @if((int) $visit->clinic !== 3)
  {{-- ซ่อนปุ่ม ใช้ตัวเลือก "ส่งไป TMC" ในช่องห้องแทน (คลินิกขอ 2569-11-10) — ปุ่มยังต้องอยู่ เพราะตัวเลือกนั้นสั่งกดปุ่มนี้ --}}
  <div class="col-auto d-none">
    <button type="button" class="btn" id="btn-send-tmc" style="border:1px solid #6d28d9;color:#6d28d9;background:#f5f3ff;"
            data-action="{{ route('visit.sendToClinic', $visit->id) }}"
            data-token="{{ csrf_token() }}"
            data-page="{{ request('page', $_GET['page'] ?? 2) }}"
            title="เปิด visit ของคลินิก TMC ให้คนไข้รายนี้ (visit เดิมยังอยู่ตามปกติ)">
      <i class="fa-solid fa-plane-departure me-1"></i> ส่งไป TMC
    </button>
  </div>
  @endif

</div>

{{-- เตือนเมื่อเลือกห้อง 7 แต่คลินิกยังไม่ใช่ TMC — กันคีย์แล้วคนไข้หายไปจากสายตา TMC --}}
<div class="row mt-2" id="tmc-room-warn" style="display:none;">
  <div class="col-12">
    <div class="alert alert-warning d-flex align-items-center justify-content-between flex-wrap gap-2 mb-0 py-2">
      <span>
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong>ห้องตรวจ 7 เป็นห้องของคลินิก TMC</strong> —
        ถ้าไม่เปลี่ยนคลินิกเป็น TMC ด้วย เจ้าหน้าที่ TMC จะมองไม่เห็นคนไข้รายนี้ และรายงานจะนับเป็นงานคลินิกเดิม
      </span>
      <span class="d-flex gap-2">
        <button type="button" class="btn btn-warning btn-sm" id="tmc-warn-yes">ใช่ เปลี่ยนเป็น TMC ให้เลย</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="tmc-warn-no">ไม่ ใช้คลินิกเดิม</button>
      </span>
    </div>
  </div>
</div>

<script>
(function () {
    if (window.__tmcRoomWarnInit) return;
    window.__tmcRoomWarnInit = true;

    var st = document.getElementById('status');
    var cl = document.getElementById('clinic');
    var warn = document.getElementById('tmc-room-warn');
    if (!st || !cl || !warn) return;

    // ช่องห้องโชว์เฉพาะห้องของคลินิกที่เลือก — ห้องที่คนไข้อยู่ตอนนี้ (ตอนเปิดหน้า) โชว์เสมอ กันค่าเดิมหาย
    var origStatus = st.value, prevStatus = st.value;
    function filterRooms(pickFirst) {
        var c = cl.value, firstOk = null;
        Array.prototype.forEach.call(st.options, function (o) {
            var tag = o.getAttribute('data-clinics');
            var ok = !tag || (' ' + tag + ' ').indexOf(' ' + c + ' ') >= 0 || (o.value === origStatus && o.value !== '__send_tmc');
            o.hidden = !ok; o.disabled = !ok;
            if (ok && firstOk === null && tag === c) firstOk = o.value;   // ห้องตรวจหลักของคลินิก (ป้ายเดียว)
        });
        // เปลี่ยนคลินิกแล้วห้องเดิมไม่ใช่ของคลินิกนั้น → ไปห้องแรกของคลินิกใหม่ (เช่น STI→TB ไปห้องวัณโรค 2)
        var cur = st.options[st.selectedIndex];
        var curTag = cur ? cur.getAttribute('data-clinics') : null;
        if (pickFirst && curTag && (' ' + curTag + ' ').indexOf(' ' + c + ' ') < 0 && firstOk !== null) { st.value = firstOk; prevStatus = firstOk; }
    }
    filterRooms(false);
    cl.addEventListener('change', function () { filterRooms(true); });

    // เลือก "ส่งไป TMC" ในช่องห้อง = กดปุ่มส่งไป TMC · ช่องกลับเป็นห้องเดิมก่อน (ไม่ให้ค่านี้ไปกับฟอร์ม)
    st.addEventListener('change', function (e) {
        if (st.value === '__send_tmc') {
            e.stopImmediatePropagation();
            st.value = prevStatus;
            var b = document.getElementById('btn-send-tmc');
            if (b) b.click();
            return;
        }
        prevStatus = st.value;
    });

    var dismissed = false;
    function sync() {
        var mismatch = st.value === '7' && cl.value !== '3';
        warn.style.display = (mismatch && !dismissed) ? '' : 'none';
    }
    st.addEventListener('change', function () { dismissed = false; sync(); });
    cl.addEventListener('change', sync);

    document.getElementById('tmc-warn-yes').addEventListener('click', function () {
        cl.value = '3';
        dismissed = true;
        sync();
    });
    document.getElementById('tmc-warn-no').addEventListener('click', function () {
        dismissed = true;
        sync();
    });
    sync();

    // ส่งไป TMC = เปิด visit ใหม่ (ฟอร์มชั่วคราวต้องอยู่นอกฟอร์มหลัก)
    var btn = document.getElementById('btn-send-tmc');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('เปิด visit ของคลินิก TMC ให้คนไข้รายนี้?\n(visit เดิมยังอยู่ตามปกติ ไม่ถูกย้าย)')) return;
            var f = document.createElement('form');
            f.method = 'POST';
            f.action = btn.dataset.action;
            f.style.display = 'none';
            [['_token', btn.dataset.token], ['clinic', '3'], ['page', btn.dataset.page]].forEach(function (kv) {
                var i = document.createElement('input');
                i.type = 'hidden'; i.name = kv[0]; i.value = kv[1];
                f.appendChild(i);
            });
            document.body.appendChild(f);
            f.submit();
        });
    }
})();
</script>

                            </div>

                        </form>

                        @if((int) $visit->clinic === 2)
                            {{-- จุดวางแถบ Save/ส่งห้องของหน้า TB (ล่างสุด) — แถบบัตร TB 01 ย้ายขึ้นไปบนสุดแล้ว (2569-10-01) --}}
                            <div id="tb-action-anchor"></div>

                            {{-- พยาบาลขอให้ปุ่ม Save/ส่งห้อง อยู่ "ล่างสุด" ของหน้า TB (2569-09-29)
                                 ย้ายด้วย JS แล้วผูกช่องกลับเข้าฟอร์มหลักด้วย attribute form=
                                 (ถ้าย้ายใน Blade ต้องรื้อโครงฟอร์มทั้งหน้า ซึ่งกระทบคลินิกอื่นด้วย) --}}
                            <script>
                            (function moveActionBarToBottom() {
                                var bar  = document.getElementById('visit-action-bar');
                                var zone = document.getElementById('tb-action-anchor');
                                if (!bar || !zone) { return; }

                                bar.querySelectorAll('select, input, button, textarea').forEach(function (el) {
                                    if (!el.getAttribute('form')) { el.setAttribute('form', 'visit_edit_form'); }
                                });

                                zone.parentNode.insertBefore(bar, zone.nextSibling);
                                bar.classList.add('mt-3', 'pt-3', 'border-top');
                            })();
                            </script>
                        @endif

{{-- สคริปต์ปุ่มลบแถว (วินิจฉัย/ยา) — ต้องอยู่ "นอก" ฟอร์มหลัก --}}
@include('partials.row-delete')

</div>
                    </div>
                </div>

            </div>
        </div>

<style type="text/css">
    .current-visit {
    background-color: #ffd700 !important; /* สีทอง */
    font-weight: bold;
}
</style>

{{-- ประวัติการรับบริการ: การ์ดแสดงตลอดเวลาแบบเดิม (เอากลับมาแล้ว ตามที่ขอ) --}}
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">Visit History</div>
            <div class="card-body">
                {{-- ครอบด้วย #visit-history-inline เพื่อให้ AJAX สลับเฉพาะส่วนนี้
                     (ห้ามรีโหลดหน้า เพราะหน้าตรวจอาจมีข้อมูลที่ยังไม่ได้ Save)
                     แถบเครื่องมือต้องอยู่ "ข้างใน" ด้วย ป้ายลำดับ/เลขหน้าจะได้อัปเดตตามหลังสลับ --}}
                <div id="visit-history-inline" style="transition:opacity .15s;">
                <div class="d-flex justify-content-end align-items-center gap-2 mb-2">
                    {{-- ปุ่มสลับลำดับ: บางคนชอบล่าสุดบน บางคนชอบล่าสุดล่าง เลยให้เลือกเอง --}}
                    <a href="{{ request()->fullUrlWithQuery(['vsort' => $vhSort === 'asc' ? 'desc' : 'asc', 'vpage' => null]) }}"
                       class="btn btn-outline-secondary btn-sm py-0 px-2 fw-normal"
                       title="สลับลำดับการแสดงประวัติ">
                        <i class="fas {{ $vhSort === 'asc' ? 'fa-arrow-down-1-9' : 'fa-arrow-down-9-1' }}"></i>
                        {{ $vhSort === 'asc' ? 'เก่า → ใหม่' : 'ล่าสุดขึ้นก่อน' }}
                    </a>
                    <span class="badge bg-secondary fw-normal">
                        ทั้งหมด {{ $visitsPage->total() }} ครั้ง
                        @if($visitsPage->lastPage() > 1)
                            · หน้า {{ $visitsPage->currentPage() }}/{{ $visitsPage->lastPage() }}
                        @endif
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th><th>วันที่</th><th>ผ่านมาแล้ว</th><th>คลินิก</th><th>วินิจฉัย</th><th>สถานะโรค</th><th>HIV</th><th>Sy</th><th>RPR</th><th>วันนัด</th><th>เหตุผล</th><th>ใบรับรองแพทย์</th><th></th>
                            </tr>
                        </thead>
                        @php
                            // ทุกนัดของทุกครั้งที่คนไข้มา (query เดียว) — ต้องเห็นครบ ทั้งที่ปิดแล้วและยังไม่ปิด
                            // visits.appointment เก็บได้วันเดียว พอปิดนัดแรก ประวัติเลยดูเหมือนนัดแรกหายไป (พี่กานต์แจ้ง 2569-09-16)
                            $__vhAppts = \App\Models\VisitAppointment::whereIn('visit_id', $visits->pluck('id'))   // ทุกครั้งของคนไข้ ไม่ใช่แค่หน้านี้ (ตารางล่างไล่ทุก visit)
                                ->orderBy('appointment_date')->orderBy('id')->get()->groupBy('visit_id');
                        @endphp
                        <tbody>
                        @foreach($visitsPage as $item)
                            <tr @if($item->id === $visit->id) class="current-visit" @endif>
                                {{-- เลขลำดับนับต่อเนื่องข้ามหน้า ไม่ใช่เริ่ม 1 ใหม่ทุกหน้า --}}
                                <td>{{ $visitsPage->firstItem() + $loop->index }}</td>
                                <td nowrap="1">{{ $item->date }}</td>
                                {{-- ผ่านมาแล้วกี่วัน/เดือน/ปี นับจากวันนี้ (หมอขอไว้ดูระยะห่างการมาแต่ละครั้ง) --}}
                                <td nowrap="1">
                                    <span class="badge {{ $item->elapsedBadgeClass() }}">{{ $item->elapsedText() }}</span>
                                </td>
                                <td>@include('partials.clinic-badge', ['clinic' => $item->clinic])</td>
                                <td>{{ $item->diagnosiAllString() }}</td>
                                <td>{{ $item->diseaseStateString() }}</td>
                                {{-- ผล HIV / ซิฟิลิส ของ visit นั้น ๆ (ผลแล็บโชว์เฉพาะที่ห้องแล็บอนุมัติแล้ว) --}}
                                @php $__hiv = $item->hivResultText(); $__sy = $item->syphilisResultText(); @endphp
                                <td nowrap="1">
                                    @if($__hiv !== '' && $__hivMaskPage)
                                        @include('partials.hiv_mask', ['hmPatient' => $visit->patient_id, 'hmKind' => 'visit', 'hmRef' => $item->id,
                                            'hmVisit' => $visit->id, 'hmWhere' => 'หน้าตรวจ TB · ประวัติการมา'])
                                    @elseif($__hiv !== '')<span class="badge {{ \App\Models\Visit::resultBadgeClass($__hiv) }}">{{ $__hiv }}</span>@endif
                                </td>
                                <td nowrap="1">
                                    @if($__sy !== '')<span class="badge {{ \App\Models\Visit::resultBadgeClass($__sy) }}">{{ $__sy }}</span>@endif
                                </td>
                                <td>{{ $item->RPR() }}</td>
                                @php
                                    $__vhRows = $__vhAppts[$item->id] ?? collect();
                                    // แผนวัคซีนลงเป็นนัดในตารางเองตั้งแต่ 2569-10-28 — วันที่มีนัดแล้วไม่ต้องโชว์แผนซ้ำ (TMC แจ้งว่าขึ้นสองชุด 2569-11-09)
                                    // เหลือโชว์แผนเฉพาะวันที่ไม่ได้ลงตาราง (วันที่ผ่านไปแล้วตอนวางแผน / visit ก่อนมีระบบลงนัดให้)
                                    $__vhDates = $__vhRows->map(fn ($a) => (string) $a->appointment_date)->all();
                                    $__vhPlan = array_filter(\App\Models\VisitVaccine::normalizePlan($item->tmc_vaccine_plan),
                                        fn ($p) => $p['date'] !== '' && ! in_array(\App\Utillity::th2dbDate($p['date']), $__vhDates, true));
                                @endphp
                                <td nowrap="1">
                                    @forelse($__vhRows as $__va)
                                        <div @if($__va->closed_at) style="text-decoration:line-through;color:#6c757d;" title="ปิดนัดแล้ว ({{ \App\Models\VisitAppointment::closedReasonText($__va->closed_reason) }})" @elseif(str_starts_with((string) $__va->reason, \App\Models\VisitAppointment::PLAN_PREFIX)) style="color:#6d28d9;" @endif>{{ $__va->appointment_date }}</div>
                                    @empty
                                        {{ $item->appointment }}
                                    @endforelse
                                    {{-- นัดฉีดวัคซีนจากแผน (TMC) — คลินิกขอให้ประวัติบอกว่านัดครั้งหน้าวันไหน มาทำอะไร (2569-10-05) --}}
                                    @foreach($__vhPlan as $__pr)
                                        <div style="color:#6d28d9;">{{ $__pr['date'] }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    @forelse($__vhRows as $__va)
                                        @php $__vaPlan = str_starts_with((string) $__va->reason, \App\Models\VisitAppointment::PLAN_PREFIX); @endphp
                                        <div @if($__va->closed_at) style="color:#6c757d;" @elseif($__vaPlan) style="color:#6d28d9;" @endif>@if($__vaPlan)<i class="fa-solid fa-syringe"></i> @endif{{ $__va->reason }}@if($__va->closed_at) <span class="badge bg-secondary fw-normal">ปิดนัดแล้ว</span>@endif</div>
                                    @empty
                                        {{ $item->appointment_reason }}
                                    @endforelse
                                    @foreach($__vhPlan as $__pr)
                                        <div style="color:#6d28d9;"><i class="fa-solid fa-syringe"></i> ฉีดวัคซีน: {{ \App\Models\VisitVaccine::planItemsText($__pr['items']) ?: '—' }}{{ \App\Models\VisitVaccine::planSiteText($__pr) }}</div>
                                    @endforeach
                                    {{-- Refer ไป รพ. ไหน (คลินิก TB ขอ 2569-10-19 ให้ประวัติบอกด้วย) --}}
                                    @if(\App\Models\Visit::referReady() && trim((string) $item->refer_hospital) !== '')
                                        <div><span class="badge bg-danger">Refer</span> {{ $item->refer_hospital }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->certificates->count())
                                        <a href="{{ route('visit.medicalCertificate.view', $item->certificates->first()->id) }}" target="_blank" class="btn btn-outline-info btn-sm" title="ดูใบรับรองแพทย์ที่เคยออก">
                                            <i class="fas fa-file-medical"></i>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('visit.edit',['visit'=>$item->id,'page'=>1])}}" title="Edit Visit"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i></button></a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                @if($visitsPage->hasPages())
                    <div class="d-flex justify-content-center mt-2">
                        {{ $visitsPage->onEachSide(1)->links() }}
                    </div>
                @endif
                </div>{{-- /#visit-history-inline --}}

                <script>
                (function () {
                    if (window.__visitHistoryPageInit) return;
                    window.__visitHistoryPageInit = true;

                    // สลับหน้าแบบ AJAX เปลี่ยนเฉพาะ #visit-history-inline
                    // (delegate บน document เพื่อให้ปุ่มหน้าใหม่หลังสลับยังใช้ได้)
                    document.addEventListener('click', function (e) {
                        // ครอบทั้งลิงก์แบ่งหน้า (vpage) และปุ่มสลับลำดับ (vsort)
                        var link = e.target.closest('#visit-history-inline a[href*="vpage="], #visit-history-inline a[href*="vsort="]');
                        if (!link) return;
                        e.preventDefault();

                        var box = document.getElementById('visit-history-inline');
                        if (!box) return;
                        box.style.opacity = '.5';

                        fetch(link.href, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin'
                        })
                        .then(function (r) { return r.text(); })
                        .then(function (html) {
                            var fresh = new DOMParser().parseFromString(html, 'text/html')
                                            .getElementById('visit-history-inline');
                            if (fresh) box.innerHTML = fresh.innerHTML;
                            box.style.opacity = '1';
                            // อัปเดต URL ไว้ กด F5 แล้วยังอยู่หน้าเดิม (ไม่เพิ่มประวัติ back)
                            try { history.replaceState(null, '', link.href); } catch (err) {}
                        })
                        .catch(function () { box.style.opacity = '1'; });
                    });
                })();
                </script>
            </div>
        </div>
    </div>
</div>

{{-- ป้าย HISTORY ลอยขวาจอ + popup ยังอยู่เหมือนเดิม เผื่อใครอยากดูแบบสรุปเร็วๆ --}}
<div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="historyModalLabel"><i class="fas fa-history"></i> ประวัติการรับบริการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3 p-3 bg-light rounded border">
                    <h6 class="text-muted mb-2"><i class="fas fa-notes-medical"></i> สรุปข้อมูลสำคัญ</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="small text-muted fw-bold">วินิจฉัยล่าสุด</div>
                            @if($latestDiagnosisVisit)
                                <div>{{ $latestDiagnosisVisit->diagnosiAllString() }}</div>
                                <div class="small text-muted">{{ $latestDiagnosisVisit->diseaseStateString() }} · {{ $latestDiagnosisVisit->date }}</div>
                            @else
                                <div class="text-muted">ไม่มีข้อมูล</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted fw-bold">ยาปัจจุบัน</div>
                            @if($currentMedicines->count())
                                <ul class="mb-0 ps-3">
                                    @foreach($currentMedicines as $vm)
                                        <li>{{ $vm->medicine->name ?? '-' }}@if($vm->dose) ({{ $vm->dose }})@endif</li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="text-muted">ไม่มีข้อมูล</div>
                            @endif
                        </div>
                        <div class="col-md-4">
                            <div class="small text-muted fw-bold">ผล Lab ล่าสุด</div>
                            @if($latestLab && $latestLab->labSubs->count())
                                @if($latestLab->isResultVisible())
                                    <ul class="mb-0 ps-3">
                                        @foreach($latestLab->labSubs as $ls)
                                            @if($__hivMaskPage && (int) $ls->method === 6 && trim((string) $ls->result) !== '')
                                                <li>{{ $ls->method() }}: @include('partials.hiv_mask', ['hmPatient' => $visit->patient_id, 'hmKind' => 'sub', 'hmRef' => $ls->id,
                                                    'hmVisit' => $visit->id, 'hmWhere' => 'หน้าตรวจ TB · ผล Lab ล่าสุด'])</li>
                                            @else
                                                <li>{{ $ls->method() }}: {{ $ls->result }}</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-hourglass-half me-1"></i> รอห้อง Lab อนุมัติผล
                                    </span>
                                @endif
                            @else
                                <div class="text-muted">ไม่มีข้อมูล</div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>#</th><th>วันที่</th><th>ผ่านมาแล้ว</th><th>คลินิก</th><th>วินิจฉัย</th><th>สถานะโรค</th><th>HIV</th><th>Sy</th><th>RPR</th><th>วันนัด</th><th>เหตุผล</th><th>ใบรับรองแพทย์</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                        {{-- popup นี้แสดงครบทุกครั้งในหน้าเดียว เรียงล่าสุดขึ้นก่อน
                             ($visits ตัวจริงต้องคง asc ไว้ เพราะกล่องสรุปด้านบนใช้ ->reverse()->first() หาค่าล่าสุด) --}}
                        @foreach($visits->reverse() as $item)
                            <tr @if($item->id === $visit->id) class="current-visit" @endif>
                                <td>{{ $visits->count() - $loop->index }}</td>
                                <td nowrap="1">{{ $item->date }}</td>
                                {{-- ผ่านมาแล้วกี่วัน/เดือน/ปี นับจากวันนี้ (หมอขอไว้ดูระยะห่างการมาแต่ละครั้ง) --}}
                                <td nowrap="1">
                                    <span class="badge {{ $item->elapsedBadgeClass() }}">{{ $item->elapsedText() }}</span>
                                </td>
                                <td>@include('partials.clinic-badge', ['clinic' => $item->clinic])</td>
                                <td>{{ $item->diagnosiAllString() }}</td>
                                <td>{{ $item->diseaseStateString() }}</td>
                                {{-- ผล HIV / ซิฟิลิส ของ visit นั้น ๆ (ผลแล็บโชว์เฉพาะที่ห้องแล็บอนุมัติแล้ว) --}}
                                @php $__hiv = $item->hivResultText(); $__sy = $item->syphilisResultText(); @endphp
                                <td nowrap="1">
                                    @if($__hiv !== '' && $__hivMaskPage)
                                        @include('partials.hiv_mask', ['hmPatient' => $visit->patient_id, 'hmKind' => 'visit', 'hmRef' => $item->id,
                                            'hmVisit' => $visit->id, 'hmWhere' => 'หน้าตรวจ TB · ประวัติการมา'])
                                    @elseif($__hiv !== '')<span class="badge {{ \App\Models\Visit::resultBadgeClass($__hiv) }}">{{ $__hiv }}</span>@endif
                                </td>
                                <td nowrap="1">
                                    @if($__sy !== '')<span class="badge {{ \App\Models\Visit::resultBadgeClass($__sy) }}">{{ $__sy }}</span>@endif
                                </td>
                                <td>{{ $item->RPR() }}</td>
                                @php
                                    $__vhRows = $__vhAppts[$item->id] ?? collect();
                                    // แผนวัคซีนลงเป็นนัดในตารางเองตั้งแต่ 2569-10-28 — วันที่มีนัดแล้วไม่ต้องโชว์แผนซ้ำ (TMC แจ้งว่าขึ้นสองชุด 2569-11-09)
                                    // เหลือโชว์แผนเฉพาะวันที่ไม่ได้ลงตาราง (วันที่ผ่านไปแล้วตอนวางแผน / visit ก่อนมีระบบลงนัดให้)
                                    $__vhDates = $__vhRows->map(fn ($a) => (string) $a->appointment_date)->all();
                                    $__vhPlan = array_filter(\App\Models\VisitVaccine::normalizePlan($item->tmc_vaccine_plan),
                                        fn ($p) => $p['date'] !== '' && ! in_array(\App\Utillity::th2dbDate($p['date']), $__vhDates, true));
                                @endphp
                                <td nowrap="1">
                                    @forelse($__vhRows as $__va)
                                        <div @if($__va->closed_at) style="text-decoration:line-through;color:#6c757d;" title="ปิดนัดแล้ว ({{ \App\Models\VisitAppointment::closedReasonText($__va->closed_reason) }})" @elseif(str_starts_with((string) $__va->reason, \App\Models\VisitAppointment::PLAN_PREFIX)) style="color:#6d28d9;" @endif>{{ $__va->appointment_date }}</div>
                                    @empty
                                        {{ $item->appointment }}
                                    @endforelse
                                    {{-- นัดฉีดวัคซีนจากแผน (TMC) — คลินิกขอให้ประวัติบอกว่านัดครั้งหน้าวันไหน มาทำอะไร (2569-10-05) --}}
                                    @foreach($__vhPlan as $__pr)
                                        <div style="color:#6d28d9;">{{ $__pr['date'] }}</div>
                                    @endforeach
                                </td>
                                <td>
                                    @forelse($__vhRows as $__va)
                                        @php $__vaPlan = str_starts_with((string) $__va->reason, \App\Models\VisitAppointment::PLAN_PREFIX); @endphp
                                        <div @if($__va->closed_at) style="color:#6c757d;" @elseif($__vaPlan) style="color:#6d28d9;" @endif>@if($__vaPlan)<i class="fa-solid fa-syringe"></i> @endif{{ $__va->reason }}@if($__va->closed_at) <span class="badge bg-secondary fw-normal">ปิดนัดแล้ว</span>@endif</div>
                                    @empty
                                        {{ $item->appointment_reason }}
                                    @endforelse
                                    @foreach($__vhPlan as $__pr)
                                        <div style="color:#6d28d9;"><i class="fa-solid fa-syringe"></i> ฉีดวัคซีน: {{ \App\Models\VisitVaccine::planItemsText($__pr['items']) ?: '—' }}{{ \App\Models\VisitVaccine::planSiteText($__pr) }}</div>
                                    @endforeach
                                    {{-- Refer ไป รพ. ไหน (คลินิก TB ขอ 2569-10-19 ให้ประวัติบอกด้วย) --}}
                                    @if(\App\Models\Visit::referReady() && trim((string) $item->refer_hospital) !== '')
                                        <div><span class="badge bg-danger">Refer</span> {{ $item->refer_hospital }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if($item->certificates->count())
                                        <a href="{{ route('visit.medicalCertificate.view', $item->certificates->first()->id) }}" target="_blank" class="btn btn-outline-info btn-sm" title="ดูใบรับรองแพทย์ที่เคยออก">
                                            <i class="fas fa-file-medical"></i>
                                        </a>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('visit.edit',['visit'=>$item->id,'page'=>1])}}" title="Edit Visit"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i></button></a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Note คนไข้: ผูกกับ patient ติดตัวไปทุก visit --}}
<div class="modal fade" id="noteModal" tabindex="-1" aria-labelledby="noteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="noteModalLabel"><i class="fas fa-sticky-note"></i> Note คนไข้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="{{ route('patient.notes.store', $visit->patient_id) }}" method="POST" class="mb-3">
                    @csrf
                    <div class="input-group">
                        <textarea name="body" class="form-control" rows="2" placeholder="เพิ่ม note ใหม่ เช่น แพ้ยา, ข้อควรระวัง..." required></textarea>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> บันทึก</button>
                    </div>
                </form>

                @forelse($patientNotes as $note)
                    <div class="border rounded p-2 mb-2 {{ $note->is_resolved ? 'bg-light text-muted' : 'bg-white' }}">
                        <div style="white-space: pre-wrap;" class="{{ $note->is_resolved ? 'text-decoration-line-through' : '' }}">{{ $note->body }}</div>
                        <div class="small text-muted d-flex justify-content-between align-items-center mt-1">
                            <span><i class="fas fa-user-circle"></i> {{ $note->author->name ?? '-' }} · {{ $note->created_at->format('d/m/Y H:i') }}</span>
                            @if($note->is_resolved)
                                <span class="text-success"><i class="fas fa-check-circle"></i> ปิดโดย {{ $note->resolvedByUser->name ?? '-' }}</span>
                            @else
                                <form action="{{ route('patient.notes.resolve', $note->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">ปิด note นี้</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-muted">ยังไม่มี note สำหรับคนไข้รายนี้</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

        @include('sweetalert::alert')
    </div>

{{-- ปุ่มบันทึกเป็นช่วง ๆ (คลินิกขอ 2569-10-06 — แทนปุ่มลอยที่ลองใช้ไปรอบแรก)
     ใส่ท้ายแต่ละหมวด (กล่องพับได้) เฉพาะหมวดที่มีช่องต้องกด Save — หมวดที่บันทึกเองทั้งหมดไม่ต้องมีปุ่ม
     (อาการ/ตรวจร่างกาย/การรักษา/สัญญาณชีพ ที่ autosave · แผนนัดวัคซีน · กล่องที่บันทึกด้วยตัวเอง [data-no-dirty])
     กดแล้ว = กดปุ่ม Save เดิมท้ายหน้า (ผ่านตัวเช็ค _touched / confirm เหมือนเดิม) · บันทึกเสร็จเลื่อนกลับที่เดิม --}}
<div id="section-save-toast" class="d-none d-print-none"></div>
<style>
    .section-save { display: flex; justify-content: flex-end; margin-top: .75rem; }
    /* ปุ่มบันทึกทุกตัวในหน้าตรวจหน้าตาเดียวกัน (คลินิกขอ 2569-10-07 — เดิมมีทั้ง Save อังกฤษกับ บันทึก) */
    .btn-save-th { border-radius: 2rem; font-weight: 700; padding: .35rem 1.2rem; }
    #section-save-toast { position: fixed; left: 18px; bottom: 18px; z-index: 1040; background: #198754; color: #fff; border-radius: 2rem;
                          padding: .45rem 1rem; font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,.2); }
    #section-save-toast.warn { background: #dc3545; }
</style>
<script>
(function () {
    var real = document.getElementById('visit-save-btn');
    if (!real || !real.form) return;
    var form = real.form;
    var KEY = 'visitScroll:{{ $visit->id }}';

    // ช่องที่บันทึกเองอยู่แล้ว (ดู URL_AUTOSAVE ในหน้านี้ + แผนนัดวัคซีน) — ไม่นับว่า "ต้องกด Save"
    var AUTO = /^(symptom|body_check|body_check_marks|treatment|weight|height|temperature|pulse|systolic|diastolic|respiratory_rate|spo2|dtx|qtcf|tmc_vaccine_plan.*|_token|_method|_touched)$/;
    function needsSave(box) {
        return Array.prototype.some.call(box.querySelectorAll('input[name], select[name], textarea[name]'), function (el) {
            if (el.form !== form || el.type === 'hidden' || el.disabled) return false;
            if (el.closest('[data-no-dirty]')) return false;
            return !AUTO.test(el.name);
        });
    }
    document.querySelectorAll('[data-tabtoggle][href^="#"]').forEach(function (t) {
        var box = document.querySelector(t.getAttribute('href'));
        if (!box || box.querySelector('.section-save')) return;
        // ปุ่มบันทึกเดิมที่ฝังกลางหมวด (บางหมวดอยู่บนสุด) เอาออก — ทุกหมวดมีปุ่มที่เดียวคือ "ท้ายหมวด ชิดขวา"
        // เดิมหมวดหนึ่งปุ่มอยู่บน อีกหมวดอยู่ล่าง สองปุ่มมาชิดกันจนงง · คีย์ข้อมูลไล่ลงไปแล้วกดบันทึกท้ายหมวดได้เลย (คลินิกขอ 2569-10-07)
        var had = false;
        box.querySelectorAll('button[type=submit].btn-save-th, input[type=submit]').forEach(function (b) {
            if (b.form !== form || b.id === 'visit-save-btn') return;
            had = true; b.remove();
        });
        if (!had && !needsSave(box)) return;
        var wrap = document.createElement('div');
        wrap.className = 'section-save d-print-none';
        wrap.innerHTML = '<button type="button" class="btn btn-primary btn-save-th"><i class="fa fa-save me-1"></i> บันทึก</button>';
        wrap.querySelector('button').addEventListener('click', function () { real.click(); });
        (box.querySelector('.card-body') || box).appendChild(wrap);
    });

    // จำตำแหน่งตอนกด Save (ปุ่มไหนก็ได้) — capture ให้จำก่อนตัวจัดการอื่น
    form.addEventListener('submit', function () {
        try { sessionStorage.setItem(KEY, JSON.stringify({ y: window.scrollY, t: Date.now() })); } catch (e) {}
    }, true);

    // กลับมาหลังบันทึก (ภายใน 2 นาที) -> เลื่อนกลับที่เดิม
    var saved = null;
    try { saved = JSON.parse(sessionStorage.getItem(KEY) || 'null'); sessionStorage.removeItem(KEY); } catch (e) {}
    if (!saved || Date.now() - saved.t > 120000) return;
    var toast = document.getElementById('section-save-toast');
    // บันทึกไม่ผ่าน (มีข้อความผิดพลาด) = อยู่บนสุดให้เห็นข้อความ ไม่เลื่อนหนี
    // นับเฉพาะกล่องเตือนที่ "มองเห็นจริง" — หน้ามีกล่อง alert-danger ซ่อน (d-none) รอไว้ใช้ทีหลัง เช่น ตัวเลือกแล็บส่งนอก
    // เดิมเจอตัวซ่อนแล้วขึ้น "บันทึกไม่สำเร็จ" ทั้งที่บันทึกผ่าน (แจ้ง 2569-10-09)
    var __errShown = Array.prototype.some.call(document.querySelectorAll('.alert-danger, .invalid-feedback.d-block, .is-invalid'), function (el) {
        return !el.closest('.d-none') && el.getClientRects().length > 0;
    });
    if (__errShown) {
        toast.className = 'warn d-print-none'; toast.textContent = 'บันทึกไม่สำเร็จ — ดูข้อความด้านบน';
    } else {
        window.addEventListener('load', function () { window.scrollTo(0, saved.y); });
        setTimeout(function () { window.scrollTo(0, saved.y); }, 300);   // หน้าที่โหลดช้า (รูป/ตาราง) เลื่อนซ้ำอีกครั้ง
        var now = new Date();
        toast.className = 'd-print-none'; toast.textContent = '✓ บันทึกแล้ว ' + ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2) + ' น.';
    }
    setTimeout(function () { toast.className = 'd-none'; }, 6000);
})();
</script>
@endsection
@push('scripts')
@include('visit._draft_popup_js')
@if($__hivMaskPage)
    {{-- ตัวจัดการ "กดดู" ผล HIV (กันทำงานซ้ำในตัว — แผง Lab ในคลินิก/ตาราง Lab ก็ include ตัวเดียวกัน) --}}
    @include('partials.hiv_mask_js')
@endif
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
/* ปุ่มเรียกคิว (2569-09-14)
   เดิมกดแล้วโหลดหน้าใหม่ ข้อมูลที่กรอกค้างไว้ (ห้อง/นัด/ยา/บิล) หาย -> ส่งเบื้องหลังแทน
   กดแล้วล็อกปุ่ม 10 วินาที กันกดซ้ำตอนเสียงยังเรียกอยู่
   คิวที่เพิ่งออกไม่ถึง 60 วิ ล็อกปุ่มไว้ก่อน ให้เวลาพิมพ์บัตรคิว (เซิร์ฟเวอร์กันซ้ำอีกชั้น) */
(function () {
    var form = document.getElementById('cq-form');
    if (!form) return;
    var btn = document.getElementById('cq-btn');
    var btnHtml = btn.innerHTML;
    var COOLDOWN = 10;

    function cooldown(seconds, label) {
        var left = seconds;
        btn.disabled = true;
        (function tick() {
            if (left <= 0) { btn.disabled = false; btn.innerHTML = btnHtml; return; }
            btn.innerHTML = '<i class="fa fa-hourglass-half"></i> ' + label + ' ' + left + ' วิ';
            left--;
            setTimeout(tick, 1000);
        })();
    }

    var initialWait = parseInt(form.dataset.wait || '0', 10);
    if (initialWait > 0) cooldown(initialWait, 'รอพิมพ์บัตรคิว');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (btn.disabled) return;
        if (!confirm(form.dataset.confirm)) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> กำลังเรียก…';

        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: new FormData(form)
        })
        .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (res.d.status === 'wait') {   // นาฬิกาเครื่องนี้เดินเร็วกว่าเซิร์ฟเวอร์ -> นับต่อตามที่เซิร์ฟเวอร์บอก
                cooldown(res.d.wait, 'รอพิมพ์บัตรคิว');
                return;
            }
            if (!res.ok || res.d.status !== 'success') {
                btn.disabled = false; btn.innerHTML = btnHtml;
                alert('เรียกคิวไม่สำเร็จ: ' + (res.d.message || 'ลองใหม่อีกครั้ง หรือกด Ctrl+F5'));
                return;
            }
            document.getElementById('cq-status').innerHTML = '<span class="badge bg-success">เรียกแล้ว</span>';
            document.getElementById('cq-count').textContent = 'เรียกไปแล้ว ' + res.d.call_count + ' ครั้ง';
            if (window.Swal) {
                Swal.fire({ toast: true, position: 'top-end', icon: 'success', timer: 2500, showConfirmButton: false,
                            title: 'เรียกคิว ' + res.d.queue + ' ห้อง ' + res.d.room + ' แล้ว' });
            }
            cooldown(COOLDOWN, 'เรียกได้อีกใน');
        })
        .catch(function () {
            btn.disabled = false; btn.innerHTML = btnHtml;
            alert('เรียกคิวไม่สำเร็จ (เชื่อมต่อเซิร์ฟเวอร์ไม่ได้) ลองใหม่อีกครั้ง');
        });
    });
})();
</script>
<script>
/* เซฟเฉพาะช่องที่แก้จริง (2569-09-14)
   หลายห้องเปิดหน้าตรวจคนไข้คนเดียวกันค้างไว้ คนที่กด Save ทีหลังเคยเอาค่าเก่าในหน้าตัวเองไปทับของอีกห้อง
   ตอนเปิดหน้า: จำค่าของทุกช่องไว้ · ตอนกด Save: เทียบแล้วส่งรายชื่อช่องที่เปลี่ยนไปใน _touched
   ฝั่งเซิร์ฟเวอร์จะเขียนเฉพาะช่องพวกนั้น (ดู VisitController@update) */
(function () {
    var form = document.getElementById('visit_edit_form');
    if (!form) return;

    var snap = null, snapAppt = null;

    function topKey(name) { return String(name).split('[')[0]; }

    // ค่าของทุกช่องจัดกลุ่มตามชื่อระดับบนสุด (appt_date[], services[3][use] ฯลฯ รวมเป็นก้อนเดียวต่อชื่อ)
    function canonical() {
        var groups = {};
        new FormData(form).forEach(function (v, name) {
            var k = topKey(name);
            if (k === '_token' || k === '_method' || k === '_touched' || k === '_orig_appt') return;
            if (typeof File !== 'undefined' && v instanceof File) { v = v.name + ':' + v.size; }
            (groups[k] = groups[k] || []).push(name + '=' + v);
        });
        // checkbox ที่ติ๊กออกหมดจะไม่มีคีย์ใน FormData — เก็บชื่อจาก element ไว้ด้วย จะได้รู้ว่ามันเปลี่ยน
        Array.prototype.forEach.call(form.elements, function (el) {
            if (!el.name) return;
            var k = topKey(el.name);
            if (!(k in groups) && k !== '_token' && k !== '_method' && k !== '_touched' && k !== '_orig_appt') { groups[k] = []; }
        });
        var out = {};
        Object.keys(groups).forEach(function (k) { out[k] = groups[k].join(''); });
        return out;
    }

    function apptRows() {
        var rows = {};
        form.querySelectorAll('#appt-table tr.appt-row').forEach(function (tr) {
            var id = (tr.querySelector('input[name="appt_id[]"]') || {}).value;
            if (!id) return;
            rows[id] = {
                date:   ((tr.querySelector('input[name="appt_date[]"]')   || {}).value || '').trim(),
                reason: ((tr.querySelector('input[name="appt_reason[]"]') || {}).value || '').trim(),
                note:   ((tr.querySelector('input[name="appt_note[]"]')   || {}).value || '').trim(),
                // ช่องของคลินิกวัณโรค (2569-09-29) — ต้องจำด้วย ไม่งั้นแก้แค่เวลา/ก่อนพบแพทย์
                // ฝั่งเซิร์ฟเวอร์จะเห็นว่าแถวนี้ "ไม่ได้แก้" แล้วข้ามไม่บันทึก
                time:   ((tr.querySelector('select[name="appt_time[]"]')   || {}).value || '').trim(),
                pre:    ((tr.querySelector('input[name="appt_pre[]"]')     || {}).value || '').trim(),
                doctor: ((tr.querySelector('select[name="appt_doctor[]"]') || {}).value || '').trim()
            };
        });
        return rows;
    }

    function hidden(name) {
        var el = form.querySelector('input[type=hidden][name="' + name + '"]');
        if (!el) { el = document.createElement('input'); el.type = 'hidden'; el.name = name; form.appendChild(el); }
        return el;
    }

    // จำค่าหลังสคริปต์อื่นในหน้าตั้งค่าเสร็จ (ปฏิทิน / body map ฯลฯ) ไม่งั้นจะนึกว่าช่องพวกนั้นถูกแก้
    window.addEventListener('load', function () {
        setTimeout(function () { snap = canonical(); snapAppt = apptRows(); }, 400);
    });

    // ใช้ capture เพื่อให้เติมค่าก่อน listener อื่น (ถ้า listener อื่นยกเลิก submit ก็ไม่เสียหาย)
    form.addEventListener('submit', function () {
        if (!snap) return;   // ยังจำค่าไม่ทัน (กด Save เร็วมาก) -> ไม่ส่ง _touched = เซฟทั้งฟอร์มแบบเดิม
        var now = canonical(), touched = [];
        Object.keys(now).forEach(function (k) { if (now[k] !== snap[k]) touched.push(k); });
        Object.keys(snap).forEach(function (k) { if (!(k in now)) touched.push(k); });
        hidden('_touched').value = JSON.stringify(touched);
        hidden('_orig_appt').value = JSON.stringify(snapAppt || {});
    }, true);
})();

    // เตือนก่อนถ้ากด Save พร้อมเลือก "กลับบ้าน" (status=6) ทั้งที่ยังมีคิวค้างอยู่
    // เพราะ Visit::booted() จะปิดคิวในระบบเรียกคิวกลางให้อัตโนมัติทันทีที่ status เปลี่ยนเป็น 6
    // (สาเหตุที่พยาบาลบ่นว่า "กดเรียกคิวแล้วคิวหายไปเป็นปิดงาน" คือดันไปกด Save ฟอร์มนี้ตอน dropdown ยังค้างเป็น "กลับบ้าน" อยู่)
    document.getElementById('visit_edit_form').addEventListener('submit', function (e) {
        var statusSelect = document.getElementById('status');
        var wasStatus6 = {{ (int) $visit->status === 6 ? 'true' : 'false' }};
        var hasActiveQueue = {{ ($linkedQueue && $linkedQueue->status !== 'completed') ? 'true' : 'false' }};

        if (statusSelect && statusSelect.value === '6' && !wasStatus6 && hasActiveQueue) {
            var confirmed = confirm('เลือก "กลับบ้าน" จะปิดคิวที่ผูกไว้ (คิว {{ $linkedQueue->queue_number ?? "" }}) ทันที\n\nยืนยันว่าคนไข้เสร็จสิ้นการรับบริการและกลับบ้านแล้วใช่ไหม?');
            if (!confirmed) {
                e.preventDefault();
            }
        }
    });

    // สร้างรหัส OTP ให้เจ้าหน้าที่อ่านให้คนไข้ฟัง/ให้ดู แล้วคนไข้พิมพ์เข้า LINE เอง (ไม่ใช้ HN ตรงๆ กันสวมสิทธิ์)
    function generateLineLinkCode(patientId, alreadyLinked, linkedAt) {
        function doGenerate() {
            fetch("{{ url('/patient') }}/" + patientId + "/line-link-code", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                }
            })
                .then(res => res.json())
                .then(data => {
                    Swal.fire({
                        title: '📱 รหัสผูก LINE',
                        html: @include('partials.line_link_code_html'),
                        icon: null,
                        confirmButtonText: 'ปิด'
                    });
                })
                .catch(() => {
                    Swal.fire('เกิดข้อผิดพลาด', 'ไม่สามารถสร้างรหัสได้ กรุณาลองใหม่', 'error');
                });
        }

        if (alreadyLinked) {
            Swal.fire({
                title: 'คนไข้เชื่อมต่อ LINE ไว้แล้ว',
                html: 'ผูกไว้ตั้งแต่ ' + linkedAt + '<br>ต้องการสร้างรหัสใหม่เพื่อเปลี่ยนบัญชีที่ผูกไว้หรือไม่?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'สร้างรหัสใหม่',
                cancelButtonText: 'ยกเลิก',
                confirmButtonColor: '#d33'
            }).then(function (result) {
                if (result.isConfirmed) doGenerate();
            });
        } else {
            doGenerate();
        }
    }

    // Note: mark เป็นอ่านแล้วเฉพาะตอนเปิด popup จริงๆ ไม่ใช่ตอนโหลดหน้าเฉยๆ
    // เพื่อให้ badge เลขที่ยังไม่อ่านนับแยกตามคนอ่านจริง (ดู PatientNoteController::markRead)
    document.getElementById('noteModal').addEventListener('shown.bs.modal', function () {
        fetch("{{ route('patient.notes.markRead', $visit->patient_id) }}", {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}",
                'Accept': 'application/json'
            }
        }).then(function () {
            var badge = document.getElementById('note-unread-badge');
            if (badge) badge.style.display = 'none';
        });
    });

    $(document).ready(function() {
        // เงื่อนไขใหม่:
        // 1. เป็นพยาบาล
        // 2. ยังไม่มีเวลาซักประวัติ
        // 3. ✅ สถานะต้องไม่ใช่ "กลับบ้าน" (5) และไม่ใช่ "เสร็จสิ้น" (6)
        {{-- 9 = ห้องการเงิน ก็ไม่ต้องถามรับคนไข้แล้ว เหมือน 5 (ห้องยา) และ 6 (กลับบ้าน) --}}
        @if(auth()->user()->hasRole('Nurse') && empty($visit->screening_at) && !in_array($visit->status, [9, 5, 6]))
            
            Swal.fire({
                title: 'รับคนไข้รายนี้หรือไม่?',
                text: "คนไข้: {{ $visit->patient2->name }} {{ $visit->patient2->surname }}",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: '✅ รับคนไข้ (ลงเวลา)',
                cancelButtonText: 'ยังไม่รับ',
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "{{ route('visit.receive', $visit->id) }}",
                        type: "POST",
                        data: {
                            _token: "{{ csrf_token() }}"
                        },
                        success: function(response) {
                            Swal.fire(
                                'บันทึกสำเร็จ!',
                                'ลงเวลาซักประวัติเรียบร้อยแล้ว (' + response.time + ')',
                                'success'
                            ).then(() => {
                                location.reload();
                            });
                        },
                        error: function() {
                            Swal.fire('Error', 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้', 'error');
                        }
                    });
                }
            });

        @endif
    });
    // เปลี่ยนจากพิมพ์เลขคิวเป็น text ฟรีๆ มาเป็น dropdown เลือกจากคิวที่ "รอเรียก" จริงในระบบเรียกคิวกลาง (สดๆ ตอนเปิด)
    // ผูกกับ queue_system_id ผ่าน route เดิม (visit.selectQueue) แทน update-queue เดิมที่แก้แค่ตัวเลขที่แสดง
    // ไม่ได้ผูกกับคิวจริง ทำให้กดเรียกคิวแล้วไปเรียกคิว/ห้องเดิมที่ผูกไว้ ไม่ตรงกับเลขที่แก้ไว้
    function editQueue(visitId, currentQueue) {
        fetch("{{ route('queue.available') }}?visit_id=" + visitId)
            .then(res => res.json())
            .then(queues => {
                if (!queues.length) {
                    Swal.fire('ไม่มีคิวที่รอเรียกอยู่ตอนนี้', 'กรุณาไปเพิ่มคิวที่หน้า Add Queue ก่อน', 'info');
                    return;
                }
                const options = {};
                queues.forEach(q => {
                    options[q.id] = 'คิว ' + q.queue_number + ' (ห้อง ' + q.room_number + ')';
                });
                Swal.fire({
                    title: '📝 เลือกคิว',
                    input: 'select',
                    inputOptions: options,
                    inputPlaceholder: '-- เลือกคิว --',
                    showCancelButton: true,
                    confirmButtonText: 'บันทึก',
                    cancelButtonText: 'ยกเลิก',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    inputValidator: (value) => {
                        if (!value) { return 'กรุณาเลือกคิว!'; }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const chosen = queues.find(q => String(q.id) === String(result.value));
                        $.ajax({
                            url: "{{ url('/visit') }}/" + visitId + "/select-queue",
                            type: "POST",
                            data: {
                                _token: "{{ csrf_token() }}",
                                queue_system_queue_id: result.value
                            },
                            success: function() {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'เรียบร้อย!',
                                    text: 'เลือกคิว ' + (chosen ? chosen.queue_number : '') + ' แล้ว',
                                    timer: 1500,
                                    showConfirmButton: false
                                }).then(() => location.reload());
                            },
                            error: function(xhr) {
                                // 422 = คิวถูกจองซ้ำ (มีข้อความเฉพาะจาก server)
                                const msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'เกิดข้อผิดพลาด ไม่สามารถบันทึกได้';
                                Swal.fire('ไม่สำเร็จ', msg, 'error');
                            }
                        });
                    }
                });
            });
    }
</script>

{{-- ===== Autosave ช่องคลินิก + เตือน "มีข้อมูลยังไม่ได้ Save" (ช่องอื่น) =====
     - ช่องคลินิก (อาการ/ตรวจร่างกาย/การรักษา + body map) -> autosave เงียบๆ (endpoint แยก saveQuietly ไม่แตะ status/คิว/เวลา/บิล)
     - ช่องอื่น (status/วันนัด/ยา/บิล ฯลฯ) -> ไม่ autosave แต่จะเตือนถ้าจะออกหน้าโดยยังไม่กด Save
     - ป้ายเดียว: แดง=ต้อง Save เอง (สำคัญสุด) / เหลือง=กำลัง autosave / เขียว=autosave แล้ว --}}
<script>
(function () {
    var form = document.getElementById('visit_edit_form');
    if (!form) return;

    var URL_AUTOSAVE = "{{ route('visit.autosave', $visit->id) }}";
    var TOKEN = (document.querySelector('meta[name="csrf-token"]') || {}).content
             || (document.querySelector('#visit_edit_form input[name=_token]') || {}).value;

    var AUTO_FIELDS = ['symptom', 'body_check', 'treatment']; // textarea (+ body_check_marks hidden)
    // สัญญาณชีพ TMC (การ์ด visit/form/tmc) — autosave เหมือนช่องคลินิกอื่น
    // การฉีดวัคซีนไม่รวมที่นี่ เพราะมันตัดสต็อก/คิดเงิน มี endpoint ของตัวเองที่กด "เพิ่ม" แล้วบันทึกทันที
    ['weight','height','temperature','pulse','systolic','diastolic','respiratory_rate','spo2','dtx','qtcf']
        .forEach(function (f) { if (document.getElementById(f)) AUTO_FIELDS.push(f); });
    var MARKS_ID = 'body_check_marks';

    // ---- สถานะ ----
    var autosavePending = false; // ช่องคลินิกเปลี่ยน แต่ autosave ยังไม่สำเร็จ
    var dirtyOther = false;      // ช่องอื่นเปลี่ยน แต่ยังไม่กด Save
    var saving = false;          // กำลัง submit (Save) จริง
    var timer = null, inflight = null, hideTimer = null;
    var lastSaved = null, initialMarks = '';

    // ---- ป้ายสถานะเดียว (มุมล่างขวา) ----
    var badge = document.createElement('div');
    badge.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:1080;font-size:14px;font-weight:600;'
        + 'padding:8px 14px;border-radius:20px;box-shadow:0 2px 8px rgba(0,0,0,.2);display:none;transition:opacity .3s;';

    function show(text, bg, color, autohide) {
        clearTimeout(hideTimer);
        badge.textContent = text;
        badge.style.background = bg;
        badge.style.color = color || '#fff';
        badge.style.display = 'block';
        badge.style.opacity = '1';
        if (autohide) hideTimer = setTimeout(function () { badge.style.opacity = '0'; }, 2500);
    }
    // แดง (ต้อง Save เอง) สำคัญสุด; ถ้าไม่มีก็ปล่อยให้สถานะ autosave คุมป้ายเอง
    function refresh() {
        if (dirtyOther) { show('● มีข้อมูลยังไม่ได้ Save — กด Save ด้วย', '#dc3545', '#fff', false); }
        else if (!autosavePending) { badge.style.display = 'none'; }
    }

    // ---- autosave (ช่องคลินิก) ----
    function snapshot() {
        var s = {};
        AUTO_FIELDS.forEach(function (f) { var el = document.getElementById(f); s[f] = el ? el.value : null; });
        var m = document.getElementById(MARKS_ID); s[MARKS_ID] = m ? m.value : null;
        return s;
    }
    function doSave() {
        var cur = snapshot(), payload = null;
        Object.keys(cur).forEach(function (k) {
            if (!lastSaved || cur[k] !== lastSaved[k]) { if (!payload) payload = {}; payload[k] = cur[k]; }
        });
        if (!payload) { autosavePending = false; refresh(); return; }

        var body = new FormData();
        Object.keys(payload).forEach(function (k) { body.append(k, payload[k] == null ? '' : payload[k]); });
        body.append('_token', TOKEN);

        if (!dirtyOther) show('กำลังบันทึกอัตโนมัติ…', '#ffc107', '#212529', false);
        if (inflight) inflight.abort();
        inflight = new AbortController();

        fetch(URL_AUTOSAVE, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': TOKEN, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin', body: body, signal: inflight.signal
        })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (res) {
            lastSaved = cur; autosavePending = false;
            if (!dirtyOther) show('✓ บันทึกอัตโนมัติ ' + (res.saved_at || ''), '#198754', '#fff', true);
            else refresh();
        })
        .catch(function (e) {
            if (e && e.name === 'AbortError') return;   // ถูกยกเลิกเพราะพิมพ์ต่อ
            // autosavePending ยัง true อยู่ -> beforeunload จะเตือน
            if (!dirtyOther) show('⚠ บันทึกอัตโนมัติไม่สำเร็จ (กด Save เอง)', '#dc3545', '#fff', false);
        });
    }
    function scheduleAuto() { autosavePending = true; clearTimeout(timer); timer = setTimeout(doSave, 1500); }

    function isAutoField(t) { return t && (AUTO_FIELDS.indexOf(t.id) !== -1 || AUTO_FIELDS.indexOf(t.name) !== -1); }

    function init() {
        document.body.appendChild(badge);
        lastSaved = snapshot();
        initialMarks = lastSaved[MARKS_ID];

        // input ในฟอร์ม: ช่องคลินิก -> autosave, ช่องอื่น -> ตั้งธงต้อง Save เอง
        // [data-no-dirty] = ส่วนที่บันทึกเองด้วย fetch ไม่ผ่านปุ่ม Save (เช่น การ์ด Lab ส่งตรวจภายนอก)
        // ถ้านับเป็น "ยังไม่ได้ Save" พยาบาลจะเจอกล่อง "ออกจากหน้านี้?" ทั้งที่ไม่มีอะไรค้างจริง
        function ignored(t) { return t && t.closest && t.closest('[data-no-dirty]'); }
        form.addEventListener('input', function (e) {
            if (!e.isTrusted || ignored(e.target)) return;
            if (isAutoField(e.target)) scheduleAuto();
            else { dirtyOther = true; refresh(); }
        });
        form.addEventListener('change', function (e) {
            if (!e.isTrusted || ignored(e.target)) return;
            if (isAutoField(e.target)) return; // ช่องคลินิกใช้ input พอ
            dirtyOther = true; refresh();
        });
        // visit:dirty = การเปลี่ยนที่ไม่ได้เกิดจากการพิมพ์ (body map ยิงจาก saveMarks,
        // ปุ่ม "ใช้ข้อความเดิม" ยิงหลังเติมข้อความ) -> เทียบกับค่าที่บันทึกล่าสุด กัน false ตอนโหลด
        document.addEventListener('visit:dirty', function (e) {
            if (e.detail && e.detail.other) { dirtyOther = true; refresh(); return; }
            var cur = snapshot();
            var changed = Object.keys(cur).some(function (k) {
                return !lastSaved || cur[k] !== lastSaved[k];
            });
            if (changed) scheduleAuto();
        });
        // กด Save จริง -> เคลียร์ทุกธง ไม่ต้องเตือน
        form.addEventListener('submit', function () {
            saving = true; dirtyOther = false; autosavePending = false;
            clearTimeout(timer); if (inflight) inflight.abort();
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();

    // เตือนตอนจะออก/ปิด/รีเฟรช ถ้ามีของค้าง (ช่องอื่นยังไม่ Save หรือ autosave ยังไม่สำเร็จ)
    window.addEventListener('beforeunload', function (e) {
        // window.__visitDraftStashed = จำข้อมูลที่พิมพ์ค้างไว้ในเครื่องแล้ว (visit/_draft_popup_js) กลับมาจะเติมให้ ไม่ต้องเตือน
        if ((dirtyOther || autosavePending) && !saving && !window.__visitDraftStashed
            && !(window.__visitDraftStash && window.__visitDraftStash())) { e.preventDefault(); e.returnValue = ''; }
    });
})();
</script>

{{-- ===== หลังกด Save: กลับมาที่เดิม =====
     ฟอร์มนี้ต้อง submit จริง (เพราะ update() คิดบิล/ลงเวลา/ปิดคิว แล้ว render ค่าใหม่)
     จึงจำ "แท็บที่เปิดไว้ + ตำแหน่งจอ" ก่อน submit แล้วคืนให้หลังโหลดเสร็จ
     -> หมอ/พยาบาลไม่ต้องเลื่อนหาจุดเดิมใหม่ทุกครั้งที่บันทึก --}}
<script>
(function () {
    var KEY  = 'visitEditState:{{ $visit->id }}';
    var form = document.getElementById('visit_edit_form');

    function openSections() {
        return Array.prototype.slice.call(document.querySelectorAll('.collapse.show'))
            .map(function (el) { return el.id; })
            .filter(function (id) { return !!id; });
    }

    if (form) {
        form.addEventListener('submit', function () {
            try {
                sessionStorage.setItem(KEY, JSON.stringify({
                    y: window.scrollY || window.pageYOffset || 0,
                    open: openSections()
                }));
            } catch (e) {}
        });
    }

    function restore() {
        var raw = null;
        try { raw = sessionStorage.getItem(KEY); } catch (e) {}
        if (!raw) return;
        try { sessionStorage.removeItem(KEY); } catch (e) {}

        var st;
        try { st = JSON.parse(raw); } catch (e) { return; }

        // เปิดแท็บที่เคยเปิดไว้กลับมาก่อน (ให้ความสูงหน้าเท่าเดิม)
        (st.open || []).forEach(function (id) {
            var el = document.getElementById(id);
            if (el && !el.classList.contains('show')) {
                el.classList.add('show');
                var toggler = document.querySelector('[data-bs-target="#' + id + '"], [href="#' + id + '"]');
                if (toggler) toggler.setAttribute('aria-expanded', 'true');
            }
        });

        // แล้วค่อยเลื่อนกลับตำแหน่งเดิม (หน่วงให้ layout/ป็อปอัพนิ่งก่อน)
        setTimeout(function () { window.scrollTo(0, st.y || 0); }, 120);
    }

    if (document.readyState === 'complete') restore();
    else window.addEventListener('load', restore);
})();
</script>
@endpush