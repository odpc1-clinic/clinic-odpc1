@extends('layouts.app')

@section('content')
<div class="container">

    {{-- ========================================================= --}}
    {{-- PART 1: Logic คำนวณอายุ (แปลงเป็น Blade PHP เพื่อความสะอาด) --}}
    {{-- ========================================================= --}}
    @php
        $age_text = "ไม่ระบุวันเกิด";
        if($patient->birth_date) {
            $birth_date_parts = explode("/", $patient->birth_date);
            if(count($birth_date_parts) == 3) {
                $birth_day = $birth_date_parts[0];
                $birth_month = $birth_date_parts[1];
                $birth_year_be = $birth_date_parts[2];
                $birth_year_ad = $birth_year_be - 543;

                $age_year = date("Y") - $birth_year_ad;
                $age_month = date("m") - $birth_month;
                $age_day = date("d") - $birth_day;

                if ($age_month < 0) { $age_year--; $age_month += 12; }
                if ($age_day < 0) { $age_month--; $age_day += 31; }
                
                $age_text = "อายุ " . $age_year . " ปี";
            }
        }
    @endphp

    {{-- แจ้งเตือนนัดที่ห้องบัตร (พยาบาลแจ้ง 2569-08-26 ว่าคนไข้นัดมาแต่หน้านี้ไม่บอก)
         ตัวเตือนเดิมอยู่ที่หน้าห้องตรวจเท่านั้น ซึ่งกว่าจะถึงตรงนั้นก็ลงทะเบียนไปแล้ว --}}
    @php $__appt = $patient->appointmentAlert(); @endphp
    @if ($__appt['level'] !== 'none')
        @php
            $__style = [
                'ontime'  => ['alert-success', '✅', 'คนไข้มีนัดวันนี้'],
                'overdue' => ['alert-danger',  '⚠️', 'คนไข้ขาดนัด — ยังไม่ได้มาตามนัด'],
                'future'  => ['alert-info',    '⏳', 'คนไข้มาก่อนวันนัด — ถามก่อนว่ามาเรื่องที่นัดไว้หรือเปล่า'],
            ][$__appt['level']];
        @endphp
        <div class="alert {{ $__style[0] }} d-flex align-items-start gap-2 mb-3" style="font-size:1.02rem">
            <span style="font-size:1.4rem;line-height:1">{{ $__style[1] }}</span>
            <div>
                <b>{{ $__style[2] }}</b>
                @foreach ($__appt['items'] as $__i)
                    @php $__p = explode('-', $__i['date']); @endphp
                    <div class="mt-1">
                        <span class="badge {{ $__i['status'] === 'ontime' ? 'bg-success' : ($__i['status'] === 'overdue' ? 'bg-danger' : 'bg-info') }}">
                            {{ ['ontime' => 'นัดวันนี้', 'overdue' => 'เลยนัด', 'future' => 'ยังไม่ถึง'][$__i['status']] }}
                        </span>
                        <b>{{ count($__p) === 3 ? $__p[2].'/'.$__p[1].'/'.$__p[0] : $__i['date'] }}</b>
                        — {{ $__i['reason'] }}
                    </div>
                @endforeach
                @if ($__appt['level'] === 'future')
                    {{-- ให้ห้องบัตรถามคำถามที่ตอบง่าย แทนคำถามกว้าง ๆ ว่า "มาทำอะไร"
                         คนไข้มาก่อนนัดเพราะวันนัดไม่ว่างบ่อยมาก (209 ครั้งใน 12 เดือน) --}}
                    <div class="small mt-2">
                        ถามคนไข้ว่า <b>"วันนี้มา{{ $__appt['items'][0]['reason'] }}ใช่ไหมคะ"</b> —
                        ถ้าใช่ ให้พยาบาลห้องตรวจกดปิดนัดเดิมตอนบันทึก จะได้ไม่ส่ง LINE เตือนซ้ำ
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- Toast แสดงอายุ (มุมขวาบน) --}}
    <div class="toast show position-fixed end-0 top-0 m-3 text-bg-primary border-0" role="alert" style="z-index: 1050;">
        <div class="d-flex">
            <div class="toast-body">
                <i class="fas fa-birthday-cake me-2"></i> {{ $age_text }}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- PART 2: ข้อมูลผู้ป่วย (Edit Patient) --}}
    {{-- ========================================================= --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="m-0 font-weight-bold text-dark">
                        <i class="fas fa-user-edit me-2"></i> แก้ไขข้อมูลผู้ป่วย #{{ $patient->id }}
                    </h5>
                    <div class="d-flex gap-2">
                        <a href="{{ route('activity-log.patient', $patient->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm shadow-sm">
                            <i class="fa fa-clock-rotate-left"></i> ประวัติการแก้ไข
                        </a>
                        <a href="{{ route('patient.index') }}" class="btn btn-warning btn-sm shadow-sm">
                            <i class="fa fa-arrow-left"></i> ย้อนกลับ
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    {{-- ความยินยอม PDPA — นอกฟอร์มแก้ข้อมูล (2569-10-29) --}}
                    @include('partials.consent_modal', ['cPatient' => $patient])

                    {{-- ส่วนแสดงรูปภาพ --}}
                    <div class="d-flex align-items-start mb-4 p-3 bg-light rounded border">
                        <div class="me-3">
                            @if($patient->photo)
                                <img src="{{ route('patient.showPhoto', $patient->id) }}" class="rounded shadow-sm" alt="รูปผู้ป่วย" style="height: 120px; width: auto; object-fit: cover;" />
                                <div class="mt-2 text-center">
                                    <form action="{{ route('patient.deletePhoto', $patient->id) }}" method="POST" onsubmit="return confirm('ยืนยันลบรูปภาพ?');">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm py-0" style="font-size: 0.8rem;">
                                            <i class="fas fa-trash"></i> ลบรูป
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded" style="height: 120px; width: 120px;">
                                    <i class="fas fa-user fa-3x"></i>
                                </div>
                                <div class="mt-2 text-center small text-muted">ไม่มีรูปภาพ</div>
                            @endif
                        </div>
                        <div class="flex-fill">
                            {{-- ฟอร์มแก้ไขข้อมูล --}}
                            <form method="POST" action="{{ route('patient.update', $patient->id) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data" autocomplete="off">
                                {{ method_field('PATCH') }}
                                {{ csrf_field() }}

                                @if ($errors->any())
                                    <div class="alert alert-danger p-2 mb-3 small">
                                        <ul class="mb-0 ps-3">
                                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @include('patient.form', ['formMode' => 'edit'])
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- PART 3: ที่อยู่ (Address) --}}
    {{-- ========================================================= --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-secondary"><i class="fas fa-map-marker-alt me-2"></i> ที่อยู่ (Address)</h6>
                </div>
                <div class="card-body">
                    @if (!$patient->address())
                        <div class="alert alert-warning d-flex align-items-center justify-content-between m-0">
                            <span>ยังไม่มีข้อมูลที่อยู่</span>
                            <a href="{{ route('address.create', ['patient' => $patient->id]) }}" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus"></i> เพิ่มที่อยู่ใหม่
                            </a>
                        </div>
                    @else
                        @include('address.table', ['address' => $patient->address()])
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ========================================================= --}}
    {{-- PART 4: เปิดงาน Visit ใหม่ (แบบ Grid Layout สวยงาม) --}}
    {{-- ========================================================= --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-0 border-top-primary">
                <div class="card-header bg-white py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-file-medical-alt me-1"></i> เปิดงาน / ส่งตัว (Visit for Patient #{{ $patient->id }})
                    </h6>
                </div>

                <div class="card-body bg-light">

                    {{-- โน้ตติดตัวคนไข้ (พยาบาล ↔ ห้องบัตร) — โชว์เด่นก่อนเปิดงาน --}}
                    @php $openNotes = $patientNotes->where('is_resolved', false); @endphp
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="m-0 fw-bold text-warning-emphasis">
                                <i class="fas fa-sticky-note text-warning"></i> โน้ตถึงห้องบัตร
                                @if($openNotes->count())
                                    <span class="badge bg-danger">{{ $openNotes->count() }}</span>
                                @endif
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#addNoteBox">
                                <i class="fas fa-plus"></i> เพิ่มโน้ต
                            </button>
                        </div>

                        {{-- ฟอร์มเพิ่มโน้ต (พับไว้ กดเพื่อเปิด) --}}
                        <div class="collapse {{ $openNotes->count() ? '' : '' }} mb-2" id="addNoteBox">
                            <form action="{{ route('patient.notes.store', $patient->id) }}" method="POST">
                                @csrf
                                <div class="input-group">
                                    <textarea name="body" class="form-control" rows="2" placeholder="พิมพ์โน้ต เช่น นัดฟังผลเลือด, ขอเบอร์ติดต่อใหม่, เตือนเรื่อง..." required></textarea>
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> บันทึก</button>
                                </div>
                            </form>
                        </div>

                        {{-- รายการโน้ตที่ยังไม่ปิด (โชว์เด่นสีเหลือง) --}}
                        @forelse($openNotes as $note)
                            <div class="alert alert-warning d-flex justify-content-between align-items-start py-2 px-3 mb-2">
                                <div>
                                    <div style="white-space: pre-wrap;">{{ $note->body }}</div>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-user-circle"></i> {{ $note->author->name ?? '-' }} · {{ $note->created_at->format('d/m/Y H:i') }}
                                    </div>
                                </div>
                                <form action="{{ route('patient.notes.resolve', $note->id) }}" method="POST" class="ms-2">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success btn-sm" title="ทำเสร็จ/รับทราบแล้ว"><i class="fas fa-check"></i> ปิด</button>
                                </form>
                            </div>
                        @empty
                            <div class="text-muted small mb-0"><i class="fas fa-check-circle text-success"></i> ไม่มีโน้ตค้างสำหรับคนไข้รายนี้</div>
                        @endforelse

                        {{-- โน้ตที่ปิดแล้ว (พับไว้ดูย้อนหลังได้) --}}
                        @php $doneNotes = $patientNotes->where('is_resolved', true); @endphp
                        @if($doneNotes->count())
                            <a class="small text-muted" data-bs-toggle="collapse" href="#doneNotesBox" role="button">
                                <i class="fas fa-clock-rotate-left"></i> โน้ตที่ปิดแล้ว ({{ $doneNotes->count() }})
                            </a>
                            <div class="collapse mt-1" id="doneNotesBox">
                                @foreach($doneNotes as $note)
                                    <div class="border rounded p-2 mb-1 bg-white text-muted small">
                                        <span class="text-decoration-line-through">{{ $note->body }}</span>
                                        <div class="text-muted"><i class="fas fa-user-circle"></i> {{ $note->author->name ?? '-' }} · ปิดโดย {{ $note->resolvedByUser->name ?? '-' }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    <hr class="my-3">

                    {{-- Form เริ่มต้น --}}
                    {{-- กดแล้วล็อกปุ่มทันที กันดับเบิลคลิกเปิดงานซ้อน 2 อัน (ฝั่ง server กันซ้ำอีกชั้น) --}}
                    <form method="POST" action="{{ route('patient.createVisit') }}"
                          onsubmit="if (this.dataset.sent) return false; this.dataset.sent = 1; var b = this.querySelector('button[type=submit]'); if (b) { b.disabled = true; b.innerHTML = '<span class=&quot;spinner-border spinner-border-sm me-1&quot;></span> กำลังเปิดงาน...'; }">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">

                        <div class="row g-3">
                            {{-- ROW 1: ข้อมูลหลัก --}}
                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">คลินิก (Clinic)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-clinic-medical text-primary"></i></span>
                                    {{-- ตั้งค่าเริ่มต้นเป็นคลินิกที่เลือกอยู่บน navbar กันลงผิดคลินิก --}}
                                    <select class="form-select" id="clinic" name="clinic" required>
                                        @foreach(\App\Models\Visit::$ClinicOption as $__cid => $__cname)
                                            <option value="{{ $__cid }}" {{ old('clinic', \App\Models\Visit::activeClinic()) == $__cid ? 'selected' : '' }}>{{ $__cname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">ส่งไปที่ (Send to)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-door-open text-info"></i></span>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="">-- เลือกห้อง --</option>
                                        <option value="2">ห้องตรวจชาย 4</option>
                                        <option value="8">ห้องตรวจหญิง 5</option>
                                        <option value="3">ห้องเจาะเลือด/ฉีดยา</option>
                                        <option value="7">ห้องตรวจ 7 (TMC)</option>
                                        {{-- คลินิก TB เริ่มที่ห้องตรวจวัณโรค 2 (2569-09-18) --}}
                                        <option value="10">ห้องตรวจวัณโรค 2</option>
                                        <option value="5">ห้องยา</option>
                                        <option value="6">กลับบ้าน</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small text-muted mb-1">เลขคิว (จากระบบเรียกคิวกลาง)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="fas fa-sort-numeric-down"></i></span>
                                    <select class="form-select" name="queue_system_queue_id" id="queue_system_queue_id">
                                        <option value="">-- ไม่ล็อกคิว --</option>
                                        @foreach($availableQueues as $q)
                                            <option value="{{ $q->id }}">คิว {{ $q->queue_number }} (ห้อง {{ $q->room_number }})</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- ROW 2: รายละเอียด + ปุ่มกด --}}
                            <div class="col-md-9">
                                <label class="form-label small fw-bold text-danger mb-1" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#other_from_modal">
                                    <i class="fas fa-hand-pointer me-1"></i> รายละเอียด (คลิกเพื่อเลือก)
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-danger" style="cursor: pointer;" data-bs-toggle="modal" data-bs-target="#other_from_modal">
                                        <i class="fas fa-list"></i>
                                    </span>
                                    <input type="text" class="form-control" id="other_from" name="other_from" 
                                           value="{{ isset($patient->other_from) ? $patient->other_from : '' }}" 
                                           placeholder="พิมพ์รายละเอียดเพิ่มเติม หรือคลิกไอคอนด้านหน้า">
                                </div>
                            </div>

                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-success w-100 shadow-sm fw-bold" style="height: 38px;">
                                    <i class="fas fa-plus-circle me-1"></i> เปิดงาน (Add Visit)
                                </button>
                            </div>
                        </div>
                    </form>

                    {{-- แจ้งเตือนนัดหมายทาง LINE: สร้างรหัส OTP ให้เจ้าหน้าที่อ่านให้คนไข้พิมพ์เข้า LINE เอง (ไม่ใช้ HN ตรงๆ กันคนอื่นสวมสิทธิ์) --}}
                    <div class="alert {{ $patient->line_user_id ? 'alert-success' : 'alert-light border' }} d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fab fa-line {{ $patient->line_user_id ? 'text-success' : 'text-muted' }}"></i>
                            @if($patient->line_user_id)
                                เชื่อมต่อ LINE แล้ว (ผูกเมื่อ {{ \Carbon\Carbon::parse($patient->line_linked_at)->format('d/m/Y H:i') }})
                            @else
                                ยังไม่ได้เชื่อมต่อ LINE สำหรับรับแจ้งเตือนนัดหมาย
                            @endif
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="generateLineLinkCode({{ $patient->id }})">
                            <i class="fas fa-qrcode"></i> {{ $patient->line_user_id ? 'สร้างรหัสผูกใหม่' : 'สร้างรหัสผูก LINE' }}
                        </button>
                    </div>

                    <hr class="my-4">

                    {{-- ตารางประวัติ Visit --}}
                    <div class="mt-3">
                        {{-- ครอบด้วย #visit-history เพื่อให้ AJAX สลับเฉพาะส่วนนี้ (ไม่รีโหลดหน้า/ไม่เด้งขึ้นบน) --}}
                        <div id="visit-history" style="transition:opacity .15s;">
                            <h6 class="text-secondary mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span><i class="fas fa-history me-1"></i> ประวัติการรักษา (History)</span>
                                @isset($visitHistory)
                                    <span class="d-flex align-items-center gap-2">
                                        {{-- ปุ่มสลับลำดับ: บางคนชอบล่าสุดบน บางคนชอบล่าสุดล่าง เลยให้เลือกเอง --}}
                                        @php $hsort = request('hsort') === 'asc' ? 'asc' : 'desc'; @endphp
                                        <a href="{{ request()->fullUrlWithQuery(['hsort' => $hsort === 'asc' ? 'desc' : 'asc', 'hpage' => null]) }}"
                                           class="btn btn-outline-secondary btn-sm py-0 px-2 fw-normal"
                                           title="สลับลำดับการแสดงประวัติ">
                                            <i class="fas {{ $hsort === 'asc' ? 'fa-arrow-down-1-9' : 'fa-arrow-down-9-1' }}"></i>
                                            {{ $hsort === 'asc' ? 'เก่า → ใหม่' : 'ล่าสุดขึ้นก่อน' }}
                                        </a>
                                        <span class="badge bg-secondary fw-normal">
                                            ทั้งหมด {{ $visitHistory->total() }} ครั้ง
                                            @if($visitHistory->lastPage() > 1)
                                                · หน้า {{ $visitHistory->currentPage() }}/{{ $visitHistory->lastPage() }}
                                            @endif
                                        </span>
                                    </span>
                                @endisset
                            </h6>

                            @include('visit.table', ['visit' => $visitHistory ?? $patient->visits(), 'hasMethod' => $hasMethod ?? false])

                            @isset($visitHistory)
                                @if($visitHistory->hasPages())
                                    <div class="d-flex justify-content-center mt-2">
                                        {{ $visitHistory->onEachSide(1)->links() }}
                                    </div>
                                @endif
                            @endisset
                        </div>

                        <script>
                        (function () {
                            // แบ่งหน้าประวัติแบบ AJAX — สลับเฉพาะ #visit-history
                            // (delegate บน document เพื่อให้ปุ่มหน้าใหม่หลังสลับยังใช้ได้)
                            document.addEventListener('click', function (e) {
                                // ครอบทั้งลิงก์แบ่งหน้า (hpage) และปุ่มสลับลำดับ (hsort)
                                var link = e.target.closest('#visit-history a[href*="hpage="], #visit-history a[href*="hsort="]');
                                if (!link) return;
                                e.preventDefault();

                                var box = document.getElementById('visit-history');
                                if (!box) return;
                                box.style.opacity = '.45';

                                fetch(link.href, {
                                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                                    credentials: 'same-origin'
                                })
                                .then(function (r) { return r.ok ? r.text() : null; })
                                .then(function (html) {
                                    if (!html) { box.style.opacity = '1'; return; }
                                    var fresh = new DOMParser().parseFromString(html, 'text/html')
                                                    .getElementById('visit-history');
                                    if (fresh) box.innerHTML = fresh.innerHTML;
                                    box.style.opacity = '1';
                                    // อัปเดต URL ให้รีเฟรชแล้วยังอยู่หน้าเดิม (ไม่ทำให้จอเลื่อน)
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
    </div>
    
    {{-- SweetAlert --}}
    @include('sweetalert::alert')

</div> {{-- End Container --}}


{{-- ========================================================= --}}
{{-- MODAL & SCRIPTS (วางไว้นอกสุด เพื่อความเป็นระเบียบ) --}}
{{-- ========================================================= --}}

{{-- Modal เลือกรายละเอียด --}}
<div class="modal fade" id="other_from_modal" tabindex="-1" aria-labelledby="otherFromLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="otherFromLabel"><i class="fas fa-list-ol"></i> เลือกรายการ (Queue Option)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @foreach (App\Models\Visit::$QueueOption as $item)
                    <div class="other_from_item p-2 border-bottom" 
                         style="cursor: pointer; transition: background-color 0.2s;"
                         onmouseover="this.style.backgroundColor='#f8f9fa'; this.style.color='#dc3545';" 
                         onmouseout ="this.style.backgroundColor='white'; this.style.color='black';">
                         <i class="fas fa-angle-right text-muted me-2"></i> {{ $item }}
                    </div>
                @endforeach
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // สร้างรหัส OTP ให้เจ้าหน้าที่อ่านให้คนไข้ฟัง/ให้ดู แล้วคนไข้พิมพ์เข้า LINE เอง (ไม่ใช้ HN ตรงๆ กันสวมสิทธิ์)
    function generateLineLinkCode(patientId) {
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

    // สคริปต์สำหรับ Modal เลือกรายการ
    document.addEventListener("DOMContentLoaded", function() {
        // ใช้ jQuery หรือ Vanilla JS ก็ได้ (อันนี้ใช้ jQuery ตามเดิม)
        $('.other_from_item').click(function() {
            let selectedText = $(this).text().trim();
            let currentVal = $('#other_from').val();

            if (currentVal !== '') {
                $('#other_from').val(currentVal + ', ' + selectedText);
            } else {
                $('#other_from').val(selectedText);
            }
        });
    });

    // รีเฟรชรายการคิวที่รอเรียกทุก 5 วิ กันเลือกคิวที่คนอื่นเลือกไปแล้ว/ที่หมดอายุ
    // ยังคงค่าที่เลือกไว้อยู่ถ้าคิวนั้นยังอยู่ในลิสต์ ไม่ทำให้ค่าที่เลือกหายไปเฉยๆ ระหว่างกรอกฟอร์ม
    (function pollAvailableQueues() {
        const select = document.getElementById('queue_system_queue_id');
        if (!select) return;
        const clinicSel = document.getElementById('clinic');
        const statusSel = document.getElementById('status');

        // คำขอที่ยิงทีหลังต้องชนะเสมอ — poll ทุก 5 วิ กับตอนเปลี่ยนคลินิก/ห้อง ยิงซ้อนกันได้
        // ถ้าคำตอบของคำขอเก่า (คลินิกเดิม) มาถึงทีหลัง จะทับรายการใหม่จนดูเหมือนคิวหาย
        let seq = 0;
        function refresh() {
            const my = ++seq;
            const currentValue = select.value;
            // คิวตามคลินิกที่เลือกในฟอร์ม (เลือก TMC -> เห็นคิวห้อง 7)
            // และตามห้องที่จะส่งไป (เลือกห้องตรวจวัณโรค 2 -> เห็นคิวห้อง 2 แม้คลินิกในฟอร์มยังเป็น STI)
            const clinic = clinicSel ? clinicSel.value : '';
            const status = statusSel ? statusSel.value : '';
            fetch("{{ route('queue.available') }}?clinic=" + encodeURIComponent(clinic)
                  + "&status=" + encodeURIComponent(status))
                .then(res => res.json())
                .then(queues => {
                    if (my !== seq) return; // มีคำขอใหม่กว่าแล้ว ทิ้งคำตอบนี้
                    let html = '<option value="">-- ไม่ล็อกคิว --</option>';
                    let stillExists = false;
                    queues.forEach(q => {
                        if (String(q.id) === String(currentValue)) stillExists = true;
                        var where = String(q.room_number) === '1' ? 'จองไว้ที่เวชระเบียน' : 'ห้อง ' + q.room_number;
                        html += '<option value="' + q.id + '">คิว ' + q.queue_number + ' (' + where + ')</option>';
                    });
                    select.innerHTML = html;
                    if (stillExists) select.value = currentValue;
                })
                .catch(() => {}); // เงียบไว้ ไม่ต้องรบกวนผู้ใช้ถ้า poll พลาดบางรอบ
        }

        // สลับคลินิก -> โหลดคิวใหม่ทันที · หน่วงหนึ่งจังหวะให้ตัวล็อกห้อง TMC (ด้านล่าง) ปรับช่องห้องเสร็จก่อน
        // ไม่งั้นจะดึงคิวด้วยห้องค่าเก่า
        if (clinicSel) clinicSel.addEventListener('change', function () { setTimeout(refresh, 0); });
        if (statusSel) statusSel.addEventListener('change', refresh); // เปลี่ยนห้องที่จะส่งไป -> โหลดคิวของห้องนั้น
        refresh();                 // โหลดครั้งแรกตามคลินิกในฟอร์ม
        setInterval(refresh, 5000);
    })();

    // ล็อกห้องเป็น TMC (ห้องตรวจ 7) อัตโนมัติเมื่อเลือกคลินิก TMC — กันส่ง TMC เข้าห้อง STI ผิด
    (function lockTmcRoom() {
        var clinic = document.getElementById('clinic');
        var status = document.getElementById('status');
        if (!clinic || !status) return;
        var TMC = '3', TMC_ROOM = '7';
        var hidden = null; // disabled select ไม่ส่งค่า จึงใช้ hidden ส่ง status=7 แทน

        function lock() {
            status.value = TMC_ROOM;
            status.setAttribute('disabled', 'disabled');
            status.classList.add('bg-light');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'status';
                clinic.form.appendChild(hidden);
            }
            hidden.value = TMC_ROOM;
        }
        function unlock(reset) {
            status.removeAttribute('disabled');
            status.classList.remove('bg-light');
            if (hidden) { hidden.remove(); hidden = null; }
            if (reset) status.value = '';
        }
        function sync(reset) {
            if (String(clinic.value) === TMC) lock(); else unlock(reset);
        }
        clinic.addEventListener('change', function () { sync(true); });
        sync(false); // ตอนโหลด: ถ้าคลินิกที่ใช้อยู่คือ TMC ให้ล็อกทันที
    })();

    // กดย้อนกลับมาหน้านี้ (เบราว์เซอร์จำหน้าเดิมไว้) ปุ่มเปิดงานจะค้างสถานะ "กำลังเปิดงาน" — ปลดล็อกคืน
    window.addEventListener('pageshow', function (e) {
        if (!e.persisted) return;
        var f = document.querySelector('form[action="{{ route('patient.createVisit') }}"]');
        if (!f || !f.dataset.sent) return;
        delete f.dataset.sent;
        var b = f.querySelector('button[type=submit]');
        if (b) { b.disabled = false; b.innerHTML = '<i class="fas fa-plus-circle me-1"></i> เปิดงาน (Add Visit)'; }
    });
</script>

@endsection