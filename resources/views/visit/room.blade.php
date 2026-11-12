@extends('layouts.app')

@section('content')

{{-- Import Font & Custom CSS --}}
<link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Prompt', sans-serif; background-color: #f3f4f7; }
    
    /* Modern Card */
    .card-modern {
        border: none;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        background: #fff;
        overflow: hidden;
    }

    /* Timer Bar Styling */
    .timer-container {
    position: relative;
    height: 15px; /* 🌟 ปรับตรงนี้ครับ จาก 6px ลองเปลี่ยนเป็น 12px หรือ 15px */
    background: #f1f1f1;
    width: 100%;
}
    #progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #36b9cc, #4e73df); /* ฟ้าไปน้ำเงิน */
        border-radius: 0 4px 4px 0;
        width: 100%;
        transition: width 1s linear;
    }
    .timer-text-overlay {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 0.8rem;
        color: #858796;
        font-weight: 500;
    }

    /* Table Styling */
    .table-modern thead th {
        border-top: none;
        border-bottom: 2px solid #eef2f7;
        color: #a0aec0;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        padding: 15px;
        white-space: nowrap;
    }
    .table-modern tbody td {
        vertical-align: middle;
        padding: 15px;
        border-bottom: 1px solid #f4f5f7;
        color: #5a5c69;
        font-size: 0.95rem;
    }
    .table-modern tbody tr:hover {
        background-color: #f8f9fc;
    }

    /* คนไข้รอเกิน 30 นาทีแล้วยังไม่มีใครกด "รับคนไข้" — เปลี่ยนสีแถวแทนป็อปอัพเตือนซ้ำ
       (ป็อปอัพแจ้งครั้งเดียวตอนครบ 30 นาทีก็พอ ที่เหลือดูจากสีแถวนี้แทน ผู้ใช้ขอ 2569-08-07) */
    .table-modern tbody tr.row-waiting      { background-color: #fff8e6; }
    .table-modern tbody tr.row-waiting:hover{ background-color: #fff1cc; }
    .table-modern tbody tr.row-waiting-long { background-color: #fdeceb; }
    .table-modern tbody tr.row-waiting-long:hover { background-color: #fbdad8; }
    .wait-badge { font-size: .72rem; font-weight: 700; padding: .25em .55em; border-radius: 6px; }

    /* ผลแล็บออกแล้ว — ย้ายจากป็อปอัพมาไว้ค้างหลังชื่อคนไข้ตรงนี้แทน (พยาบาลอาวุโสมองป็อปอัพไม่ทัน, 2569-08-11)
       ทำให้เด่นชัดเจนกว่า wait-badge เพราะเป็นเรื่องที่ต้องรีบตามงานต่อ ไม่ใช่แค่สถานะรอ */
    .lab-badge {
        display: inline-flex; align-items: center; gap: .3em;
        font-size: .8rem; font-weight: 700; color: #fff; background: #198754;
        padding: .3em .65em; border-radius: 999px; margin-left: .4em;
        box-shadow: 0 0 0 rgba(25,135,84,.5); animation: labPulse 1.8s ease-in-out infinite;
    }
    @keyframes labPulse {
        0%   { box-shadow: 0 0 0 0 rgba(25,135,84,.55); }
        70%  { box-shadow: 0 0 0 8px rgba(25,135,84,0); }
        100% { box-shadow: 0 0 0 0 rgba(25,135,84,0); }
    }

    /* Badges */
    .badge-soft {
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.75rem;
    }
    .badge-soft-primary { background-color: #e3f2fd; color: #1976d2; }
    .badge-soft-success { background-color: #e8f5e9; color: #2e7d32; }
    .badge-soft-warning { background-color: #fff8e1; color: #fbc02d; }
    .badge-soft-info    { background-color: #e1f5fe; color: #0288d1; }
    .badge-soft-secondary { background-color: #f5f5f5; color: #616161; }

    /* Queue Number */
    .queue-badge {
        font-size: 1.1rem;
        font-weight: 600;
        color: #e74a3b;
        background: #fff5f5;
        padding: 5px 12px;
        border-radius: 8px;
        border: 1px dashed #e74a3b;
    }

    /* Action Button */
    .btn-action {
        width: 35px; 
        height: 35px; 
        display: inline-flex; 
        align-items: center; 
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s;
    }
    .btn-action:hover { transform: translateY(-2px); }
</style>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-md-12">
            
            {{-- Header Actions --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0 fw-bold text-gray-800"><i class="fas fa-user-md me-2 text-primary"></i> รายการตรวจ (Room Visit)</h4>
                
                {{-- ปุ่มเปิดหน้าเรียกคิว (ปรับให้ดูดีขึ้น) --}}
                <a href="{{ url('/queue/index.php') }}" class="btn btn-primary shadow-sm" target="_blank">
                    <i class="fa fa-list me-1" aria-hidden="true"></i> เปิดจอเรียกคิว
                </a>
            </div>

            <div class="card card-modern">
                {{-- Progress Bar ส่วนหัว --}}
                <div class="timer-container">
                    <div id="progress-bar"></div>
                </div>
                {{-- เพิ่มตัววิ่งตรงนี้ (เปลี่ยน Emoji ได้ตามชอบ เช่น 🐜, 🏃, 🦸‍♂️) --}}
        <div id="running-bug" style="position: absolute; top: -5px; left: 0%; transition: left 1s linear; font-size: 18px; z-index: 0;">
            🏃
        </div>
    </div>
                <div class="timer-text-overlay">
                    <i class="fas fa-clock me-1"></i> <span id="timer-text">Refreshing in 20s</span>
                </div>

                <div class="card-body p-0" id="table-data-container">
                    @include ('status')

                    <div class="table-responsive">
                        <table class="table table-modern w-100 mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" width="5%">#</th>
                                    <th>HN</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th class="text-center">เพศ</th>
                                    <th class="text-center">คลินิก</th>
                                    <th class="text-center" style="width: 80px;">คิว</th>
                                    <th class="text-center">Service</th>
                                    <th>ผู้บันทึกข้อมูล</th>
                                    <th class="text-center" width="8%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($visit as $item)
                                @php
                                    $p = $item->patient2;
                                    // ยังไม่ถูกกด "รับคนไข้" = screening_at ว่าง — คำนวณเวลารอเพื่อไล่สีแถว
                                    // (แทนป็อปอัพเตือนซ้ำทุก 15 นาที เหลือแค่เตือนครั้งเดียวตอนครบ 30 นาที)
                                    $__waitMin = null;
                                    if (!$item->screening_at) {
                                        $__since = $item->room_entered_at ?: $item->created_at;
                                        if ($__since) { $__waitMin = (int) now()->diffInMinutes(\Carbon\Carbon::parse($__since), true); }
                                    }
                                    $__rowCls = $__waitMin === null ? '' : ($__waitMin >= 30 ? 'row-waiting-long' : ($__waitMin >= 15 ? 'row-waiting' : ''));
                                @endphp
                                <tr class="{{ $__rowCls }}">
                                    <td class="text-center fw-bold text-muted">{{ $loop->iteration + ($visit->currentPage()-1)*$visit->perPage() }}</td>

                                    <td>
    {{-- ✅ แก้ไข: ลิงก์ไปหน้าแก้ไขผู้ป่วย (Patient Edit) --}}
    <a href="{{ route('patient.edit', $p->id) }}" target="_blank" class="fw-bold text-primary text-decoration-none" title="แก้ไขข้อมูลผู้ป่วย">
        {{ $p->code ?? '-' }}
    </a>
    {{-- เวลารอในห้องนี้ โชว์ทุกแถว (คลินิกขอ 2569-10-05 — เดิมโชว์เฉพาะรอเกิน 15 นาที) นับจากเวลาเข้าห้อง
         ยังไม่ "รับคนไข้" = สีตามเกณฑ์ 15/30 นาทีเหมือนเดิม · รับแล้ว = สีเทา (อยู่ในห้องมานานเท่าไร)
         ตัวเลขเดินเองทุก 30 วิ (สคริปต์ท้ายหน้า) ไม่ต้องรีเฟรช --}}
    @php $__since = $item->room_entered_at ?: $item->created_at; @endphp
    @if($__since)
        <div>
            <span class="wait-badge js-wait {{ $__waitMin === null ? 'bg-light text-secondary border' : ($__waitMin >= 30 ? 'bg-danger text-white' : ($__waitMin >= 15 ? 'bg-warning text-dark' : 'bg-light text-dark border')) }}"
                  data-since="{{ \Carbon\Carbon::parse($__since)->timestamp }}" data-received="{{ $item->screening_at ? 1 : 0 }}"
                  title="{{ $item->screening_at ? 'รับคนไข้แล้ว — อยู่ในห้องนี้มา' : 'รอรับคนไข้มา' }}">
                <i class="fas fa-hourglass-half me-1"></i><span class="js-wait-text">{{ $item->screening_at ? 'อยู่ห้อง' : 'รอ' }} {{ (int) now()->diffInMinutes(\Carbon\Carbon::parse($__since), true) }} น.</span>
            </span>
        </div>
    @endif
</td>
                                    
                                    <td>
                                        <div class="fw-bold">
                                            {{ $p->name ?? '-' }} {{ $p->surname ?? '' }}
                                            @if($item->lab_approved)
                                                <span class="lab-badge" title="ผลแล็บออกและอนุมัติแล้ว">
                                                    <i class="fas fa-vial-circle-check"></i> ผลแล็บออกแล้ว
                                                </span>
                                            @endif
                                        </div>
                                        {{-- จับเวลาสังเกตอาการ 30 นาที — ห้องเจาะเลือด/ฉีดยาใช้หน้านี้เป็นหลัก
                                             จึงอยู่ในแถวคนไข้เลย (ป้ายลอยจะเด้งเฉพาะตอนครบเวลา) --}}
                                        @include('partials.obs_button', [
                                            'obsPatientId' => $p->id ?? '',
                                            'obsVisitId'   => $item->id,
                                            'obsName'      => trim(($p->name ?? '') . ' ' . ($p->surname ?? '')) ?: ('HN ' . ($p->code ?? '')),
                                        ])
                                    </td>
                                    
                                    <td class="text-center">
                                        {{ isset($p->sex) && isset(\App\Models\Patient::$sexOption[$p->sex]) ? \App\Models\Patient::$sexOption[$p->sex] : '-' }}
                                    </td>
                                    
                                    {{-- รายการนี้แสดงทุกคลินิก ป้ายนี้จึงเป็นตัวบอกว่าคนไข้มาจากคลินิกไหน
                                         ใช้สีชุดกลาง (Visit::$ClinicColor) ให้ตรงกับแถบบนและป้ายที่อื่น --}}
                                    <td class="text-center">
                                        @include('partials.clinic-badge', ['clinic' => $item->clinic])
                                    </td>
                                    {{-- ✅ เพิ่มบรรทัดนี้ (แสดงเลขคิว) --}}
        <td class="text-center">
    @if($item->queue)
        {{-- กรณีมีเลขคิวแล้ว: โชว์สีเหลือง + คลิกแก้ได้ --}}
        <span class="badge bg-warning text-dark shadow-sm" 
              style="font-size: 1em; cursor: pointer; transition: transform 0.2s;" 
              onmouseover="this.style.transform='scale(1.1)'"
              onmouseout="this.style.transform='scale(1)'"
              onclick="editQueue({{ $item->id }}, '{{ $item->queue }}')"
              title="คลิกเพื่อแก้ไขเลขคิว">
            {{ $item->queue }} <i class="fas fa-pen" style="font-size: 0.6em; opacity: 0.5;"></i>
        </span>
    @else
        {{-- กรณีไม่มีเลขคิว: โชว์ปุ่มให้กดเพิ่ม --}}
        <span class="badge bg-light text-secondary border" 
              style="cursor: pointer;"
              onclick="editQueue({{ $item->id }}, '')"
              title="คลิกเพื่อระบุเลขคิว">
            <i class="fas fa-plus-circle"></i> ระบุคิว
        </span>
    @endif
</td>
    <td class="text-center">
                                        <span class="queue-badge">
                                            {{ $item->queue_no ?? $item->other_from }}
                                        </span>
                                    </td>

                                    {{-- ส่วนแสดงผู้บันทึก --}}
                                    @php
                                        $entries = collect();
                                        if ($item->registrationUser) { $entries->push(['role' => 'ทะเบียน', 'user' => $item->registrationUser]); }
                                        if ($item->nurseUser)        { $entries->push(['role' => 'พยาบาล', 'user' => $item->nurseUser]); }
                                        if ($item->doctorUser)       { $entries->push(['role' => 'แพทย์', 'user' => $item->doctorUser]); }
                                        if ($item->pharmacyUser)     { $entries->push(['role' => 'สั่งยา', 'user' => $item->pharmacyUser]); }
                                        if ($item->labUser)          { $entries->push(['role' => 'ส่งแล็บ', 'user' => $item->labUser]); }

                                        $grouped = $entries->groupBy(fn($r) => $r['user']->id);

                                        $roleBadgeColor = [
                                            'ทะเบียน'  => 'secondary',
                                            'พยาบาล'   => 'info',
                                            'แพทย์'    => 'primary',
                                            'สั่งยา'     => 'success',
                                            'ส่งแล็บ'   => 'warning',
                                        ];
                                    @endphp
                                    <td>
                                        @if($grouped->isEmpty())
                                            <span class="text-muted small">-</span>
                                        @else
                                            @foreach($grouped as $rows)
                                                @php $name = $rows->first()['user']->name; @endphp
                                                <div class="mb-1" style="line-height: 1.4;">
                                                    <span class="text-dark small me-1">{{ $name }}</span>
                                                    @foreach($rows as $row)
                                                        <span class="badge badge-soft-{{ $roleBadgeColor[$row['role']] ?? 'secondary' }}">
                                                            {{ $row['role'] }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        @endif
                                    </td>

                                    <td class="text-center">
    
    {{-- ปุ่ม 1: แก้ไขข้อมูล (สีน้ำเงิน) --}}
    <a href="{{ $item->roomLink() }}" class="btn btn-primary btn-sm btn-action shadow-sm me-1" title="แก้ไขข้อมูล / ลงผลตรวจ">
        <i class="fa fa-pencil"></i>
    </a>

    {{-- ปุ่ม 2: พิมพ์สติ๊กเกอร์ (สีฟ้า) --}}
    {{-- ลิงก์ไปหน้าค้นหาผู้ป่วย เพื่อกดปุ่ม Print Sticker / OPD Card ได้เลย --}}
    <a href="{{ route('patient.index', ['search' => $p->code]) }}" target="_blank" class="btn btn-info btn-sm btn-action shadow-sm text-white" title="พิมพ์สติ๊กเกอร์ / ทำบัตร">
        <i class="fa fa-print"></i>
    </a>

    {{-- ปุ่ม 3: สร้างรหัสผูก LINE แจ้งเตือนนัดหมาย (ให้คนไข้ add ต่อหน้าพยาบาลได้เลย) --}}
    <button type="button" class="btn btn-success btn-sm btn-action shadow-sm" onclick="generateLineLinkCode({{ $p->id }}, {{ $p->line_user_id ? 'true' : 'false' }}, '{{ $p->line_linked_at ? \Carbon\Carbon::parse($p->line_linked_at)->format('d/m/Y H:i') : '' }}')" title="สร้างรหัสผูก LINE แจ้งเตือนนัดหมาย">
        <i class="fab fa-line"></i>
    </button>

</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                
                {{-- Pagination Styling --}}
                @if($visit->hasPages())
                <div class="card-footer bg-white border-0 py-3 d-flex justify-content-center">
                    {!! $visit->appends(['search'=>Request::get('search')])->render() !!}
                </div>
                @endif
            </div>

        </div>
    </div>
</div>
@push('scripts') {{-- หรือใส่ใน <script> ธรรมดาถ้าไม่ได้ใช้ stack --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
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
                    cancelButtonColor: '#6c757d',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'กรุณาเลือกคิว!'
                        }
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
                                    title: 'บันทึกเรียบร้อย',
                                    text: 'เลือกคิว ' + (chosen ? chosen.queue_number : '') + ' แล้ว',
                                    timer: 1000,
                                    showConfirmButton: false
                                }).then(() => {
                                    reloadTableData();
                                });
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
@endpush
{{-- Script Timer — รีเฟรชทุก 10 วิ (เดิม 20) ให้ชื่อคนไข้ที่เพิ่งถูกส่งมาขึ้นเร็วขึ้น --}}
<script>
  var timeLeft = 10;
  var totalTime = 10;
  var progressBar = document.getElementById("progress-bar");
  var timerText = document.getElementById("timer-text");
  var runningBug = document.getElementById("running-bug"); // ดึง element ตัววิ่งมา

  function reloadTableData() {
      timerText.innerHTML = "<i class='fas fa-spinner fa-spin'></i> Loading...";
      
      // ให้ตัววิ่งวิ่งทะลุขอบไปเลยตอนโหลด
      runningBug.style.left = "110%"; 

      $.ajax({
          url: window.location.href,
          type: 'GET',
          cache: false,   // ต่อ timestamp กันเบราว์เซอร์เสิร์ฟหน้าเก่า -> จอไม่อัปเดตทั้งที่ข้อมูลใหม่แล้ว
          success: function(response) {
      var newContent = $(response).find('#table-data-container').html();
      // ถ้า response ไม่ใช่หน้าห้อง (เช่นได้หน้า error/ล็อกอินกลับมา) จะไม่เจอ container
      // อย่าเอา null ไปทับ ไม่งั้นตารางที่มีอยู่จะถูกล้างจนคนไข้หายทั้งจอ
      if (newContent != null) {
          $('#table-data-container').html(newContent);
      }

      // ต่อ Reverb ติด = คนไข้ย้ายห้องขึ้นจอทันทีอยู่แล้ว รอบนี้เหลือไว้เก็บตก (รับเข้าห้อง/ผลแล็บ) จึงห่างได้
      timeLeft = totalTime = (window.ClinicRT && ClinicRT.connected) ? 30 : 10;
      progressBar.style.background = "linear-gradient(90deg, #36b9cc, #4e73df)";
      
      // ดึงซุปเปอร์แมนกลับมาขวาสุด (100%) ตอนโหลดเสร็จ
      runningBug.style.transition = "none"; 
      runningBug.style.left = "100%"; 
      setTimeout(() => { runningBug.style.transition = "left 1s linear"; }, 50); 
  },
          error: function() {
              timerText.innerHTML = "<span class='text-danger'>Connection Error!</span>";
              timeLeft = 5;
          }
      });
  }

  function updateProgress() {
      if (timeLeft <= 0) {
          reloadTableData();
      } else {
          var percentage = (timeLeft / totalTime) * 100;
          progressBar.style.width = percentage + "%";
          
          // 🌟 1. ให้ตำแหน่งซ้าย (left) เท่ากับความกว้างของแถบพลังเป๊ะๆ
          // หักออก 25px เพื่อให้ตัวการ์ตูนไม่ล้นขอบปลายหลอด
          runningBug.style.left = "calc(" + percentage + "% - 25px)"; 
          
          // 🌟 2. สั่งให้หันหน้าไปทางซ้าย (ทิศที่แถบพลังกำลังลด) ด้วย scaleX(-1)
          if(percentage < 30) {
              progressBar.style.background = "#e74a3b";
              // หันซ้าย + ขยายร่างตอนใกล้หมดเวลา
              runningBug.style.transform = "scaleX(-1.2) scaleY(1.2)"; 
          } else {
              // หันซ้าย ขนาดปกติ
              runningBug.style.transform = "scaleX(-1)"; 
          }

          timerText.innerHTML = "Refreshing in " + timeLeft + "s";
          timeLeft--;
      }
  }

  var timerId = setInterval(updateProgress, 1000);

  // Reverb: คนไข้ถูกส่งเข้า/ออกจากห้องนี้ → ดึงตารางใหม่ทันที (หน่วง 0.8 วิ รวมสัญญาณที่มาติด ๆ กัน)
  (function () {
      var room = {{ (int) $status }}, wait = null;
      ClinicRT.on('visit', function (d) {
          if (d.from !== room && d.to !== room) return;
          clearTimeout(wait);
          wait = setTimeout(reloadTableData, 800);
      });
      ClinicRT.onState(function (up) { if (!up) { totalTime = 10; if (timeLeft > 10) timeLeft = 10; } });
  })();
</script>

<script>
// เวลารอในแถวเดินเองระหว่างรอหน้ารีเฟรช — เทียบกับนาฬิกาเซิร์ฟเวอร์ (นาฬิกาเครื่องพยาบาลอาจเพี้ยน)
(function () {
    var skew = {{ now()->timestamp }} - Math.floor(Date.now() / 1000);
    function tick() {
        var now = Math.floor(Date.now() / 1000) + skew;
        document.querySelectorAll('.js-wait').forEach(function (b) {
            var min = Math.max(0, Math.floor((now - (+b.dataset.since)) / 60));
            var received = b.dataset.received === '1';
            b.querySelector('.js-wait-text').textContent = (received ? 'อยู่ห้อง ' : 'รอ ') + min + ' น.';
            if (!received) {
                b.className = 'wait-badge js-wait ' + (min >= 30 ? 'bg-danger text-white' : (min >= 15 ? 'bg-warning text-dark' : 'bg-light text-dark border'));
            }
        });
    }
    setInterval(tick, 30000);
})();
</script>

{{-- เวลานับถอยหลังอยู่ในแถวคนไข้แล้ว ป้ายลอยจึงเด้งเฉพาะตอนครบเวลา
     (ปุ่ม/ป้ายและ logic ทั้งหมดอยู่ที่ partials/obs_button + partials/obs_timer) --}}

@endsection
