@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="row">


            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">Visit</div>
                    <div class="card-body">

                        {{-- @include ('status') --}}

                        <!-- <a href="{{ route('visit.create') }}" class="btn btn-success btn-sm" title="Add New Visit">
                                <i class="fa fa-plus" aria-hidden="true"></i> Add New
                            </a> -->
<style>
/* แถบสีบอกเพศทั้งแถว — พยาบาลขอให้กวาดตาหาเคสได้เร็วโดยไม่ต้องอ่านคอลัมน์เพศทีละบรรทัด
   ใช้สีอ่อนมาก เพราะในแถวมีตัวหนังสือสีเขียว/แดง/น้ำเงินอยู่แล้ว ถ้าพื้นเข้มจะอ่านไม่ออก
   ตัวแยกจริงคือแถบหนา 5px ด้านซ้าย ส่วนพื้นเป็นแค่ตัวช่วยรอง */
.appt-table tbody tr.sex-m  { background-color: #eef4fd; }
.appt-table tbody tr.sex-f  { background-color: #fdeef4; }
.appt-table tbody tr.sex-na { background-color: #f4f4f4; }
.appt-table tbody tr.sex-m:hover  { background-color: #dce9fb; }
.appt-table tbody tr.sex-f:hover  { background-color: #fbdce9; }
.appt-table tbody tr.sex-na:hover { background-color: #e8e8e8; }
/* ใช้ inset shadow แทน border-left เพราะเส้นขอบบน <tr> เบราว์เซอร์ไม่วาดให้ */
.appt-table tbody tr.sex-m  > td:first-child { box-shadow: inset 5px 0 0 0 #1d6fd0; }
.appt-table tbody tr.sex-f  > td:first-child { box-shadow: inset 5px 0 0 0 #d0357f; }
.appt-table tbody tr.sex-na > td:first-child { box-shadow: inset 5px 0 0 0 #9aa0a6; }
/* ป้ายเพศในคอลัมน์ของมันเอง — เผื่อพิมพ์ขาวดำหรือคนตาบอดสีจะได้ยังแยกออก */
.sex-tag { display:inline-block; min-width:1.6em; text-align:center; font-weight:600;
           border-radius:3px; padding:0 4px; color:#fff; }
.sex-tag.m  { background:#1d6fd0; }
.sex-tag.f  { background:#d0357f; }
.sex-tag.na { background:#9aa0a6; }
@media print {
    /* ไม่ใส่บรรทัดนี้ เบราว์เซอร์จะตัดสีพื้นทิ้งตอนพิมพ์ แถบสีก็หายหมด */
    .appt-table tbody tr, .sex-tag { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

{{-- แท็บแยกคลินิก (คลินิกขอ 2569-10-27) — ตัวเลข = จำนวนนัดตามเงื่อนไขค้นหาเดียวกัน --}}
@php
    $__q = request()->except(['clinic', 'page']);
    $__tabs = [];
    foreach (\App\Models\Visit::$ClinicShortName as $__k => $__n) { $__tabs[$__k] = $__n; }
    $__tabs['all'] = 'ทุกคลินิก';
@endphp
<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach($__tabs as $__k => $__n)
        @php
            $__on  = $__k === 'all' ? $clinic === null : $clinic === $__k;
            $__col = \App\Models\Visit::$ClinicColor[$__k] ?? '#475569';
            $__cnt = $__k === 'all' ? $clinicCounts->sum() : (int) ($clinicCounts[$__k] ?? 0);
        @endphp
        <a href="{{ route('visit.appointment', $__q + ['clinic' => $__k]) }}"
           class="btn fw-bold px-3" style="border:2px solid {{ $__col }}; {{ $__on ? 'background:'.$__col.';color:#fff;' : 'background:#fff;color:'.$__col.';' }}">
            {{ $__n }} <span class="badge ms-1" style="{{ $__on ? 'background:#fff;color:'.$__col : 'background:'.$__col.';color:#fff' }}">{{ $__cnt }}</span>
        </a>
    @endforeach
</div>

<form method="GET" action="{{ route('visit.appointment') }}" accept-charset="UTF-8" autocomplete="off" class="p-3 border rounded">
    <input type="hidden" name="clinic" value="{{ $clinic ?? 'all' }}">
    <fieldset>
        <legend class="mb-3 font-weight-bold text-primary">ค้นหาการนัดหมาย</legend>
        
        <div class="row align-items-end">
            <!-- HN -->
            <div class="col-md-2">
                <label for="hn" class="form-label">HN</label>
                <input type="text" class="form-control" id="hn" name="hn" placeholder="HN" value="{{ request('hn') }}">
            </div>
            
            <!-- เพศ -->
            <div class="col-md-1">
                <label for="sex" class="form-label">เพศ</label>
                <select class="form-control" id="sex" name="sex">
                    <option value="">ทั้งหมด</option>
                    <option value="1" {{ request('sex') === '1' ? 'selected' : '' }}>ช</option>
                    <option value="2" {{ request('sex') === '2' ? 'selected' : '' }}>ญ</option>
                </select>
            </div>

            <!-- ประเภทผู้รับบริการ -->
            <div class="col-md-2">
                <label for="status" class="form-label">ประเภท</label>
                <select class="form-control" id="status" name="status">
                    <option value="">ทั้งหมด</option>
                    @foreach (\App\Models\Patient::$statusOption as $key => $label)
                        @if ($key !== '')
                            <option value="{{ $key }}" {{ request('status') === (string) $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            <!-- เหตุผลการนัดหมาย -->
            <div class="col-md-2">
                <label for="reason" class="form-label">เหตุผลการนัดหมาย</label>
                @php
                    $reasonOptions = \App\Models\Visit::$reasonAppointmentOption;
                @endphp
                <select class="form-control" id="reason" name="reason">
                    <option value="">-- เลือกเหตุผล --</option>
                    @foreach ($reasonOptions as $key => $label)
                        <option value="{{ $label }}" {{ request('reason') == $label ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>
            
            <!-- วันที่เริ่มต้น -->
            <div class="col-md-2">
                <label for="start" class="form-label">วันที่เริ่มต้น (วันนัด)</label>
                <input type="text" class="form-control date" id="start" name="start" placeholder="เลือกวันที่เริ่มต้น" value="{{ request('start') }}" required>
            </div>
            
            <!-- วันที่สิ้นสุด -->
            <div class="col-md-2">
                <label for="end" class="form-label">วันที่สิ้นสุด (วันนัด)</label>
                <input type="text" class="form-control date" id="end" name="end" placeholder="เลือกวันที่สิ้นสุด" value="{{ request('end') }}" required>
            </div>
            
            <!-- ปุ่มค้นหา -->
            <div class="col-md-1 text-right">
                <label class="form-label d-block">&nbsp;</label>
                <button class="btn btn-primary w-100" type="submit">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </div>
    </fieldset>
</form>
 <div class="card-body">
                        {{-- จำนวนผู้ป่วยที่ค้นพบ --}}
                        <p>จำนวนผู้ป่วยที่ค้นพบ: {{ $totalVisits }}</p>
                        <a href="{{ route('visit.export', request()->query()) }}" class="btn btn-success mb-3">
    <i class="fa fa-download"></i> Export to Excel
</a>

                        <div class="table-responsive">
                        <table class="table appt-table">
    <thead>
        <tr>
            <th>#</th>
            @if($clinic === null)<th>คลินิก</th>@endif
            <th>NH</th>
            <th>ชื่อ</th>
            <th>นามสกุล</th>
            <th>เพศ</th>
             <th>ประเภท</th>
            <th>วันที่มา</th>
            <th>diag</th>
            <th>วันนัด</th>
            <th class="col-4">เหตุผล</th>
            <th></th> <!-- แสดงผลวันที่ถัดไป -->
            <th>สถานะการติดตาม</th> 
            <th>โทรศัพท์</th> <!-- แสดงผลวันที่ถัดไป -->
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    @foreach ($visit as $item)
        @php
            $p = $item->patient2 ?: $item->patient();
            // ในฐานข้อมูลจริงมีเพศ=0 และประเภท=0/ว่าง ซึ่งไม่มีในตารางตัวเลือก
            // ของเดิมเปิดอาร์เรย์ตรง ๆ เลยขึ้น warning และช่องว่างเปล่า ไม่รู้ว่าข้อมูลขาดหรือระบบพัง
            $sexKey  = $p->sex ?? null;
            $sexRow  = $sexKey == 1 ? 'sex-m' : ($sexKey == 2 ? 'sex-f' : 'sex-na');
            $sexAbbr = $sexKey == 1 ? 'ช' : ($sexKey == 2 ? 'ญ' : '?');
        @endphp
        <tr class="{{ $sexRow }}">
            <td>{{ $visit->firstItem() + $loop->index }}</td>
            @if($clinic === null)
                <td><span class="badge" style="background:{{ \App\Models\Visit::$ClinicColor[$item->clinic] ?? '#64748b' }};">{{ \App\Models\Visit::$ClinicShortName[$item->clinic] ?? '?' }}</span></td>
            @endif
            <td>{{ $p->code }}</td>
            <td nowrap="1">{{ $p->name }}</td>
            <td>{{ $p->surname }}</td>
            <td><span class="sex-tag {{ str_replace('sex-', '', $sexRow) }}"
                     title="{{ $sexKey == 1 ? 'ชาย' : ($sexKey == 2 ? 'หญิง' : 'ยังไม่ได้ระบุเพศ') }}">{{ $sexAbbr }}</span></td>
            <td>{{ App\Models\Patient::$statusOption[$p->status] ?? '-' }}</td>
            <td style="color: gray;" nowrap="1">{{ $item->date }}</td>
            <td>
    @if ($item->diagnoses->isNotEmpty())
        @foreach ($item->diagnoses as $diag)
            <!-- แสดง Disease -->
            @if (array_key_exists($diag->disease, \App\Models\Diagnosi::$diseaseOption))
                <span class="badge bg-info">
                    {{ \App\Models\Diagnosi::$diseaseOption[$diag->disease] }}
                </span><br>
            @else
                <span style="color: red;">ไม่ทราบโรค</span><br>
            @endif

            <!-- แสดง Term Syphilis -->
            @if (!empty($diag->term_syphilis) && array_key_exists($diag->term_syphilis, \App\Models\Diagnosi::$termSyphilisOption))
                <span class="text-primary">
                    Term Syphilis: {{ \App\Models\Diagnosi::$termSyphilisOption[$diag->term_syphilis] }}
                </span><br>
            @endif

            <!-- แสดง Disease State -->
            @if (!empty($diag->disease_state) && array_key_exists($diag->disease_state, \App\Models\Diagnosi::$diseaseStateOption))
                <span class="text-success">
                    State: {{ \App\Models\Diagnosi::$diseaseStateOption[$diag->disease_state] ?? '-' }}
                </span><br>
            @endif
        @endforeach
    @else
        <span style="color: red;">ไม่มีการวินิจฉัย</span>
    @endif
</td>

            <td style="color: green;" nowrap="1">{{ $item->appointment }}</td>
            <td>
     <a href="{{ url('/visit/' . $item->id . '/edit') . '?page=' . request('page') }}">
        {{ $item->appointment_reason }}
    </a>
    @php
        $remarks = $item->followups;
    @endphp
    @foreach ($remarks as $index => $remark) 
    @php
        $isSuccess = $remark->status === 'สำเร็จ'; // ตรวจสอบว่า status เป็น 'สำเร็จ' หรือไม่
    @endphp
    <br><medium class="text-muted">
        <span class="badge {{ $isSuccess ? 'bg-success' : 'bg-warning text-dark' }}">
            ติดตาม: {{ $index + 1 }}
        </span> 
        {{ $remark->remark }}
    </medium>
@endforeach
</td>

            <td style="color: green;" nowrap="1">
    @if ($item->next_visit)
        @php
            $nextVisitDate = \Carbon\Carbon::parse($item->next_visit);
            $appointmentDate = \Carbon\Carbon::parse($item->appointment);
        @endphp

        @if ($nextVisitDate->lt($appointmentDate))
            {{--  ❌ ของเดิม: ใช้ $item->date (วันที่สร้างนัด) --}}
            {{-- <span style="color: blue;">วันที่มา {{ \Carbon\Carbon::parse($item->date)->format('Y-m-d') }}</span><br> --}}

            {{--  ✅ แก้ไขเป็น: ใช้ $item->next_visit (วันที่มาจริง) --}}
            <span style="color: blue;">วันที่มา {{ \Carbon\Carbon::parse($item->next_visit)->format('Y-m-d') }}</span><br>
            
            <a href="{{ url('/visit/' . $item->id . '/edit') . '?page=' . request('page') }}">
                <span style="color: red;">มาก่อนนัด</span><br>
                <span style="color: red;">{{ $item->appointment }}</span><br>
            </a>
        @else
            {{ $nextVisitDate->format('Y-m-d') }}
        @endif
    @else
        <span style="color: red;">ยังไม่มา</span>
    @endif
</td>
            <td>
    @php
        $followupCount = $item->followups->count();
        $successCount = $item->followups->where('status', 'สำเร็จ')->count();
        
        // นับจำนวนการติดตามแต่ละช่องทาง
        $lineFollowups = $item->followups->where('method', 'ไลน์')->count();
        $phoneFollowups = $item->followups->where('method', 'โทรศัพท์')->count();
    @endphp

    <span class="badge bg-primary">ติดตาม {{ $followupCount }} ครั้ง</span><br>
    
    @if ($successCount > 0)
        <span class="badge bg-success">สำเร็จแล้ว {{ $successCount }} ครั้ง</span><br>
    @else
        <span class="badge bg-danger">ยังไม่ได้ตาม</span><br>
    @endif

    <!-- แสดงการติดตามตามช่องทาง -->
    <span class="badge bg-info">ไลน์: {{ $lineFollowups }} ครั้ง</span><br>
    <span class="badge bg-warning text-dark">โทรศัพท์: {{ $phoneFollowups }} ครั้ง</span><br>
</td>

            <td style="color: blue;" nowrap="1">{{ $p->tel }}</td>
            <td>
    <a href="{{ url('/visit/' . $item->id . '/edit') . '?page=' . request('page') }}">
        <button class="btn btn-primary btn-sm">
            <i class="fa fa-pencil" aria-hidden="true"></i> 
        </button>
    </a>
</td>

        </tr>
    @endforeach
</tbody>

</table>
                            <div class="pagination-wrapper">
    {{ $visit->appends(request()->query())->links() }}
</div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="/js/bootstrap-datepicker.js"></script>
    <script src="/js/bootstrap-datepicker-thai.js"></script>
    <script src="/js/bootstrap-datepicker.th.js"></script>

    <script type="text/javascript">
        $(function() {

            $('.date').datepicker({
                language: 'th-th',
            });

        });
    </script>

@endsection

