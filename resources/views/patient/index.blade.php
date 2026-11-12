@extends('layouts.app')
@section('content')

    <div class="container-fluid py-4">
        <div class="row">

            <div class="col-md-12">
                <style>
                    .pt-hero { background: linear-gradient(135deg,#4338ca 0%,#6366f1 60%,#0ea5e9 100%); border-radius:16px; color:#fff; }
                    .pt-hero .form-control { border:0; }
                    .pt-card { border:0; border-radius:14px; }
                    #example thead th { background:#f1f5f9; color:#334155; font-size:.8rem; letter-spacing:.02em;
                                        text-transform:uppercase; border-bottom:2px solid #e2e8f0; white-space:nowrap; vertical-align:middle; }
                    #example tbody tr:hover { background:#eef2ff; }
                    #example td { vertical-align:middle; }
                    #example .btn-sm { border-radius:8px; }
                </style>

                {{-- ===== Hero + toolbar ===== --}}
                <div class="pt-hero p-4 mb-4 shadow-sm">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h3 class="mb-1 fw-bold text-white"><i class="fas fa-hospital-user me-2 text-white"></i>ทะเบียนผู้ป่วย (Patients)</h3>
                            <div class="opacity-75">ค้นหาผู้ป่วย · เปิด Visit · พิมพ์สติกเกอร์ / OPD Card</div>
                        </div>
                        <form method="GET" action="{{ url('/patient') }}" accept-charset="UTF-8" role="search" style="min-width:280px;">
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-0"><i class="fa fa-search text-muted"></i></span>
                                <input type="text" class="form-control js-realtime-search" name="search" placeholder="ค้นหา HN, ชื่อ, นามสกุล, เลขบัตร..." value="{{ request('search') }}" autocomplete="off">
                                <button class="btn btn-light fw-bold" type="submit">ค้นหา</button>
                            </div>
                        </form>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('patient.create') }}" class="btn btn-light fw-bold shadow-sm" title="เพิ่มผู้ป่วยใหม่">
                            <i class="fa fa-plus text-success me-1"></i> เพิ่มผู้ป่วยใหม่
                        </a>
                        <a href="{{ url('/queue/index.php') }}" class="btn btn-outline-light" title="เปิดโปรแกรมเรียกคิว" target="_blank">
                            <i class="fa fa-list me-1"></i> เปิดโปรแกรมเรียกคิว
                        </a>
                    </div>
                </div>

                <div class="card pt-card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive" id="rt-results">
                            <table class="table" id="example">
                                <thead>
                                    <tr>
                                        <!-- <th>#</th> -->
                                        <th>วันที่มาล่าสุด</th><th>HN</th><th>ชื่อ-นามสกุล</th><th>เลขบัตร / พาสปอร์ต</th><th>เพศ</th><th>อายุ</th><th>status</th>
                                        
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($patient as $item)

                                    <tr>
                                        <!-- <td>{{ $loop->iteration }}</td> -->
                                        <td>{{ $item->lastVisitDate() }}</td>
                                        <td>{{ $item->code }}</td>
                                        <!-- <td>
                                            
                                            {{ $item->name .' '. $item->surname }}

                                        </td> -->
                                        <td>
            @php
                // ดึงชื่อคำนำหน้าออกมาแสดง
                $pfx = \App\Models\Patient::prefixLabel($item) ?? '';
                $fullName = $pfx .' '. $item->name .' '. $item->surname;
                // ชื่อมี "(L" / "(T" = คนไข้โครงการวิจัยของคลินิก STI → ชื่อสีน้ำเงิน (ไม่เกี่ยวกับวัณโรค — ป้าย TB อยู่ด้านล่าง)
                $color = (str_contains($fullName, '(L') || str_contains($fullName, '(T')) ? 'blue' : 'black';
            @endphp
            <span style="color: {{ $color }};">{{ $fullName }}</span>
            @if($m = ($tbMarks[$item->id] ?? null))
                <a href="{{ route('tb.show', $m['id']) }}" class="badge rounded-pill ms-1 text-decoration-none align-middle"
                   style="background:{{ $m['bg'] }};color:{{ $m['fg'] }};font-size:.78rem;" title="{{ $m['title'] }}">{{ $m['label'] }}</a>
            @endif
        </td>

                                        {{-- แยกช่องพาสปอร์ตแล้ว (2569-10-07) คนต่างชาติเลขบัตรว่าง — โชว์พาสปอร์ตในคอลัมน์เดียวกัน --}}
                                        <td>{{ $item->id_card_number }}@if($item->passport)@if($item->id_card_number)<br>@endif<span class="small text-muted">PP</span> {{ $item->passport }}@endif</td>
                                        <td>{{ App\Models\Patient::$sexOption[$item->sex] }}</td>
                                        <td>{{ $item->age() }}</td>
                                        <td>{{ App\Models\Patient::$statusOption[$item->status] ?? '-' }}</td>
                                        
                                        

                                        <td>
                                            <!-- <a href="{{ url('/patient/' . $item->id) }}" title="View Patient"></a> -->
                                            <!-- <button class="btn btn-info btn-sm" ><i class="fa fa-eye" aria-hidden="true" ></i> </button> -->
                                            <a href="{{ url('/patient/' . $item->id . '/edit') }}" title="Edit Patient"><button class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i> Visit </button></a>

                                            {{-- พิมพ์บัตรคิว: แสดงเฉพาะเมื่อคนไข้มีคิว "วันนี้" → พิมพ์เลขคิวปัจจุบันถูกคนเสมอ --}}
                                            @php
                                                $qVisit = $item->visits2()
                                                    ->whereNotNull('queue')->where('queue', '!=', '')
                                                    ->whereDate('created_at', now()->toDateString())
                                                    ->latest('id')->first();
                                            @endphp
                                            @if($qVisit)
                                                <a href="{{ route('visit.queueTicket', $qVisit->id) }}" target="_blank" class="btn btn-warning btn-sm" title="พิมพ์บัตรคิววันนี้">
                                                    <i class="fa fa-ticket-alt" aria-hidden="true"></i> บัตรคิว {{ $qVisit->queue }}
                                                </a>
                                            @endif
<!--                                             <form method="POST" action="{{ url('/patient' . '/' . $item->id) }}" accept-charset="UTF-8" style="display:inline">
                                                {{ method_field('DELETE') }}
                                                {{ csrf_field() }}
                                                <button type="submit" class="btn btn-danger btn-sm" title="Delete Patient" onclick="return confirm(&quot;คุณจะลบ {{ $item->name .' '. $item->surname }} หรือไม่ delete?&quot;)"><i class="fa fa-trash" aria-hidden="true"></i> ลบผู้ป่วย </button>
                                            </form> -->
                                            <form method="POST" action="{{ url('/patient' . '/' . $item->id) }}" accept-charset="UTF-8" style="display:inline">
                                                {{ method_field('DELETE') }}
                                                {{ csrf_field() }}
                                                <button type="button" class="btn btn-danger btn-sm" onclick="confirmDelete(this, '{{ $item->name }} {{ $item->surname }}')">
                                                    <i class="fa fa-trash" aria-hidden="true"></i> ลบผู้ป่วย
                                                </button>
                                            </form>

                                            {{-- <a href="{{ route('visit.create',['patient' => $item->id]) }}" title="Back"><button class="btn btn-success btn-sm"><i class="fa fa-plus" aria-hidden="true"></i> Visit</button></a> --}}
                                            <button type="button" class="btn btn-info btn-sm btn-print-sticker"
                                                data-hn="{{ $item->code }}"
                                                data-fullname="{{ trim((\App\Models\Patient::prefixLabel($item) ?? '').' '.$item->name.' '.$item->surname) }}"
                                                data-name-en="{{ $item->englishNameLine() }}"
                                                data-age="{{ $item->age() }}"
                                                data-sex="{{ App\Models\Patient::$sexOption[$item->sex] ?? '-' }}"
                                                data-status="{{ App\Models\Patient::$statusOption[$item->status] ?? '' }}"
                                                data-right="{{ App\Models\Patient::$healthRightShort[$item->health_right ?? ''] ?? '' }}">
                                                <i class="fa fa-tag"></i> Sticker
                                            </button>
                                            <button
    class="btn btn-success btn-print-opd btn-sm"
    data-code="{{ $item->code }}"
    data-prefix="{{ \App\Models\Patient::prefixLabel($item) ?? '' }}"
    data-name="{{ $item->name }}"
    data-surname="{{ $item->surname }}"
    data-sex="{{ App\Models\Patient::$sexOption[$item->sex] ?? '-' }}"
    data-idcard="{{ collect([$item->id_card_number, $item->passport])->filter()->implode(' / ') }}"
    data-birth="{{ $item->birth_date }}"
    data-age="{{ $item->age() }}"
    data-tel="{{ $item->tel }}"
    data-email="{{ $item->email }}"
    data-sex="{{ App\Models\Patient::$sexOption[$item->sex] ?? '-' }}"
    data-address="{{ optional($item->address2)->fullAddress() }}"
    data-nationality="{{ $item->nationality }}"
    data-education="{{ App\Models\Patient::$educationOption[$item->education] ?? '-' }}"
    data-marital="{{ App\Models\Patient::$maritalStatusOption[$item->marital_status] ?? '-' }}"
    data-statusid="{{ $item->status }}"
    data-occupationid="{{ $item->occupation }}"
    data-otheroccupation="{{ $item->other_occupation }}"
    data-disease="{{ $item->congenitaldisease }}"
    data-allergy="{{ $item->drug_allergy }}"
    data-organization="{{ $item->organization }}"
>
    OPD CARD
</button>



                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                            <div class="pagination-wrapper"> {!! $patient->appends(['search' => Request::get('search')])->render() !!} </div>
                        </div>
<!-- -------------------------------- Modal 1 -------------------------------- -->

<div class="modal fade" id="myModal" role="dialog" >
    
<div class="modal-dialog">
  <!-- Modal content-->
  <div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title">Patient Details</h4>
    </div>
    <div class="modal-body" id="box" media="print">
<style>
    .vertical{
     transform: rotate(180deg);
     writing-mode: vertical-lr;
     text-align: center;
}

    /* มีชื่ออังกฤษ = เพิ่ม 1 บรรทัด ต้องบีบบรรทัดอื่นให้สูงรวมเท่าเดิม ไม่งั้นบาร์โค้ดล้นสติกเกอร์ (2569-10-07)
       CSS font-size ชนะ attribute size ของ <font> */
    #sticker-table.has-en td { padding-top: 0; padding-bottom: 0; font-size: 10px; }
    #sticker-table.has-en .name-en-row td { line-height: 1; }
    #sticker-table.has-en .hn-top { font-size: 21px; }
    #sticker-table.has-en .name-surname { font-size: 15px; }
    #sticker-table.has-en .name-en { font-size: 11px; }
    #sticker-table.has-en .barcode { font-size: 26px; }
    #sticker-table.has-en .barcode-cell { padding-top: 4px; }

    /* สติกเกอร์ต้องออก 1 ดวง — ดวงจริง (Print STIS / TSC TTP-247 กระดาษ "Stis2") สูง 1.00 x กว้าง 1.94 นิ้ว
       เนื้อหาเดิมสูง ~1.2 นิ้ว เกินดวง เครื่องพิมพ์ขึ้นดวงใหม่ ได้สติกเกอร์เปล่าทิ้ง (คลินิกเจอ 2569-11-05)
       บีบระยะบรรทัด (ขนาดตัวอักษร/บาร์โค้ดเท่าเดิม) ให้สูงรวม ~0.9 นิ้ว
       ห้ามใส่ overflow:hidden ที่ html/body — Chrome ย่อความกว้างให้พอดีดวงไม่ได้ ขวาโดนตัด (เจอ 2569-11-05) */
    #sticker-table td { padding-top: 0; padding-bottom: 0; line-height: 1; }
    #sticker-table td.barcode-cell { line-height: normal; padding-top: 3px; }   /* บาร์โค้ด line-height 0.5 ล้นขึ้นไปทับบรรทัดอายุ — เว้นไว้ */
    #sticker-table font:not(.barcode) { line-height: 1.05 !important; }
    #sticker-table tr:first-child td { padding-top: 5px; }   /* เลื่อนลงจากขอบบน (คลินิกขอ 2569-11-05) + สระบนของ "ชำระเงิน" ไม่โดนตัด */
    #sticker-table .hright { top: 5px !important; }
    @media print {
        html, body { margin: 0 !important; padding: 0 !important; }
        /* ให้แถวกางเต็มความสูงดวง บาร์โค้ดชิดล่าง (คลินิกขอ 2569-11-05 — เดิมกองอยู่ครึ่งบน)
           ตอนพิมพ์ให้บาร์โค้ดเกือบเท่าบรรทัดของมัน (line-height 0.8) ยื่นเลยขอบตารางแค่นิดเดียว — เดิม 0.5 แท่งยื่นลงไป ~8px กางเต็มไม่ได้
           97% เผื่อปัดเศษ ไม่งั้นล้นไปดวงถัดไป */
        html, body { height: 97%; }
        #sticker-table font.barcode { line-height: 0.8 !important; }
        #sticker-table td.barcode-cell { vertical-align: bottom; padding-top: 0 !important; }
        #sticker-table { height: 100%; }
    }
</style>
<table height="100%" width="100%" id="sticker-table">
 <tr>
    <td nowrap="1" style="position:relative;">
        <font class="hn hn-top" size="5"><b><span></span> | </b></font><font class="status" size="2"><b><span></span></b></font>
        {{-- สิทธิการรักษา มุมขวาบน (2569-11-03) — คนที่ยังไม่ได้กรอกสิทธิ ช่องนี้ว่าง สติกเกอร์หน้าตาเหมือนเดิม --}}
        {{-- วางแบบ absolute ไม่ใช่ float — ดวงแคบ float ตกลงบรรทัดใหม่ สติกเกอร์สูงเกินดวง ขึ้นดวงเปล่า (2569-11-05) --}}
        <font class="hright" size="3" style="position:absolute; right:0; top:0;"><b><span></span></b></font>
    </td>
<tr>
    <tr>
    <td nowrap="1" style="max-width: 230px; overflow: hidden;">
        <font class="name-surname" size="4" style="line-height: 0.1; letter-spacing: -0.5px;"> <span></span></font>
    </td>
</tr>
{{-- ชื่ออังกฤษ (2569-10-07) — มีบรรทัดนี้เฉพาะคนที่กรอกชื่ออังกฤษไว้ คนไทยทั่วไปสติกเกอร์หน้าตาเหมือนเดิม --}}
<tr class="name-en-row" style="display:none;">
    <td nowrap="1" style="max-width: 230px; overflow: hidden;">
        <font class="name-en" size="2" style="line-height: 0.1;"><b><span></span></b></font>
    </td>
</tr>
</tr>
<tr>
    <td nowrap="1">
    <font class="age" size="2" style="line-height: 0.1;" > Age: <span></span></font><font class="sex" size="2"> | Sex: <span></span></font> <font class="hn" size="3"> | visit: [{{ date('d-m-') }}{{ date('Y')+543 }}]</font>
    </td>
</tr>
<tr>
    <td nowrap="1" class="barcode-cell">
      <font style="font-family: 'Libre Barcode 39', sans-serif; line-height: 0.5;" class="hn barcode" size="6">*<span></span>*</font>
    </td>
</tr>
</table>
<!-- //////////////////////////////////////////////////////////////    -->    
    </div>
    <div class="modal-footer">
      <a href="#"><button class="btn btn-success" onclick="printSticker('box')">Print</button></a>
      <button type="button" class="btn btn-primary no-print" onclick="closeModal1()">Close</button>

<script>
function closeModal1() {
    document.activeElement.blur(); // เคลื่อน focus ออกก่อน
    $('#myModal').modal('hide');   // ใช้ Bootstrap จัดการซ่อน modal
}
</script>


    </div>
  </div>
  </div>
</div>
<!-- -------------------------------- Modal 1 -------------------------------- -->
<!-- -------------------------------- Modal 2 -------------------------------- -->
<div class="modal fade" id="myModal2" role="dialog">

    <div class="modal-dialog modal-a5">
        <!-- Modal content-->
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">OPD CARD</h4>
            </div>
            <div class="modal-body" id="box2" media="print">
                <style>
                    .a5-paper {
                        width: 210mm;
                        height: 148mm;
                        padding: 10mm;
/*                        border: 1px solid black;*/
                        box-sizing: border-box;
                    }

                    /* ขนาดของ modal สำหรับ A5 แนวนอน */
                    .modal-a5 {
                        width: 793px;  /* กว้าง A5 แนวนอน */
                        height: 561px; /* สูง A5 แนวนอน */
                        max-width: 100%;
                        max-height: 100%;
                    }

                    .table-border {
                        width: 100%;
                        border-collapse: collapse;
                        text-align: left;
                    }

                    .table-border td, .table-border th {
                        border: 1px solid #ccc; /* สีเทาอ่อน */
                        padding: 5px;
                    }

                    .no-border {
                        border: none;
                    }

                    .checkbox-label {
                        margin-right: 20px;
                    }

                    .full-width {
                        width: 100%;
                    }

     @media print {
        #category-box {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    /* แสดง checkbox ที่เลือกแล้ว (checked) */
    input[type="checkbox"]:checked {
        -webkit-appearance: checkbox !important;
        appearance: checkbox !important;
        background-color: #000 !important; /* ให้เครื่องหมายถูกแสดง */
    }

    /* แสดง checkbox ทุกตัวในหน้าพิมพ์ */
    input[type="checkbox"] {
        -webkit-appearance: checkbox !important;
        appearance: checkbox !important;
        visibility: visible !important; /* ทำให้ checkbox แสดง */
    }

    /* แสดง modal ในขณะพิมพ์ */
    .modal, .modal-content {
        display: block !important;
        visibility: visible !important;
        position: absolute !important;
        z-index: 9999 !important;
    }

    /* ซ่อน backdrop ของ modal */
    .modal-backdrop {
        display: none !important;
    }

    /* ซ่อนปุ่มพิมพ์ */
    .btn-print-opd {
        display: none !important;
    }
    .no-print {
            display: none !important;
        }
}

                </style>

                <!-- เริ่มต้นตาราง -->
                <div class="a5-paper">
    <table class="table-border" style="width: 100%; border-collapse: collapse; font-size: 14px;">
        <tr>
            <td colspan="2" style="text-align: center;">
                <img src="https://upload.wikimedia.org/wikipedia/commons/c/c9/Thai_government_Garuda_emblem_%28Version_2%29.svg"
                     alt="Thai Emblem" style="width: 60px; vertical-align: middle;">
            </td>
            <td colspan="4" style="text-align: center; font-size: 18px; font-weight: bold;">
                Confidential<br>
                Service Registration Card
            </td>
            <td style="text-align: right;">
    <svg id="category-box" width="20" height="20" xmlns="http://www.w3.org/2000/svg"
         style="display: inline-block; margin-right: 5px;">
      <rect id="category-box-rect" width="20" height="20" fill="#ffffff" stroke="#ccc"/>
    </svg>
    <div style="font-size: 20px; font-weight: bold; display: inline-block;">
        <span id="opd-code"></span>
    </div>
    <div style="font-family: 'Libre Barcode 39', sans-serif; font-size: 32px; line-height: 1;">
        <span id="opd-barcode"></span>
    </div>
</td>

        </tr>
        <tr>
            <td colspan="7" style="text-align: center; padding: 6px;">
                Preventive Medicine Service Center, Office of Disease Prevention and Control 1, Chiang Mai, Thailand
            </td>
        </tr>
        <tr>
            <td colspan="3"><span id="opd-title-name"></span></td>
            <td colspan="2"><span id="opd-surname"></span></td>
            <td colspan="2"><span id="opd-sex"></span></td>
        </tr>
        <tr>
            <td colspan="3"><span id="opd-idcard"></span></td>
            <td colspan="3"><span id="opd-birth"></span></td>
            <td colspan="1"><span id="opd-age"></span></td>
        </tr>
        <tr>
            <td colspan="7"><span id="opd-address"></span></td>
        </tr>
        <tr>
            <td colspan="3"><span id="opd-tel"></span></td>
            <td colspan="4"><span id="opd-email"></span></td>
        </tr>
        <tr>
            <td colspan="2"><span id="opd-nationality"></span></td>
            <td colspan="2"><span id="opd-education"></span></td>
            <td colspan="3"><span id="opd-marital"></span></td>
        </tr>
        <tr>
            <td colspan="7">
                Category:<br>
                <label><input type="checkbox" id="status-1"> MSM/MSW/TG</label>
                <label><input type="checkbox" id="status-2"> P</label>
                <label><input type="checkbox" id="status-3"> Migrant Worker</label>
                <label><input type="checkbox" id="status-4"> Prisoner</label>
                <label><input type="checkbox" id="status-5"> Youth</label>
                <label><input type="checkbox" id="status-6"> General Public</label>
                <label><input type="checkbox" id="status-7"> Drug User</label>
            </td>
        </tr>
        <tr>
            <td colspan="7">
                Occupation:<br>
                <label><input type="checkbox" id="occupation-1"> Agriculture</label>
                <label><input type="checkbox" id="occupation-2"> Government/State Enterprise</label>
                <label><input type="checkbox" id="occupation-3"> Laborer</label>
                <label><input type="checkbox" id="occupation-4"> Business/Trade</label>
                <label><input type="checkbox" id="occupation-5"> Housework</label>
                <label><input type="checkbox" id="occupation-6"> Student</label>
                <label><input type="checkbox" id="occupation-7"> Military/Police</label>
                <label><input type="checkbox" id="occupation-8"> Business/Trade</label>
                <label><input type="checkbox" id="occupation-9"> Public Health Personnel</label>
                <label><input type="checkbox" id="occupation-10"> Special Profession</label>
                <label><input type="checkbox" id="occupation-11"> Other: <span id="opd-otheroccupation"></span></label>
            </td>
        </tr>
        <tr>
            <td colspan="3"><span id="opd-disease"></span></td>
            <td colspan="4"><span id="opd-allergy"></span></td>
        </tr>
        <tr>
            <td colspan="7"><span id="opd-organization"></span></td>
        </tr>
    </table>
</div>


            <div class="modal-footer no-print">
    <button class="btn btn-success" onclick="printOpdCard('box2')">Print</button>
   <button type="button" class="btn btn-primary no-print" onclick="closeModal2()">Close</button>

<script>
function closeModal2() {
    document.activeElement.blur(); // ลบ focus ก่อน
    $('#myModal2').modal('hide'); // ให้ Bootstrap จัดการ
}

</script>
</div>

        </div>
    </div>
</div>
<!-- -------------------------------- Modal 2 -------------------------------- -->

<!-- Sctipt -->
<!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css"> -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
<!-- <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script> -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<style>
    .btn-purple {
    color: #fff;
    background-color: #fff;
    border-color: #643ab0;
}
.modal-body div{float:left; width: 100%}
.modal-body div p{float:left; width: 20%; font-weight: 600;}
.modal-body div span{float:left; width: 80%}

.vertical{
     transform: rotate(180deg);
     writing-mode: vertical-lr;
     text-align: center;
}
</style>
<script>
// ใช้ event delegation บน document เพื่อให้ปุ่มยังทำงานหลังตารางถูกสลับด้วย realtime search (AJAX)
document.addEventListener('click', function (evt) {
    var button = evt.target.closest('.btn-print-opd');
    if (!button) return;

            // ✅ แปลงข้อมูลจากปุ่ม
            let statusId = parseInt(button.dataset.statusid);
            let occupationId = parseInt(button.dataset.occupationid);

            // ✅ ใส่ข้อมูลใน span
            document.getElementById('opd-code').innerText = '(HN): ' + button.dataset.code;
            document.getElementById('opd-barcode').innerText = '*' + button.dataset.code + '*';
            document.getElementById('opd-title-name').innerText = 'Title/Name: ' + button.dataset.prefix + ' ' + button.dataset.name;
            document.getElementById('opd-surname').innerText = 'Surname: ' + button.dataset.surname;
            document.getElementById('opd-sex').innerText = 'Sex: ' + button.dataset.sex;
            document.getElementById('opd-idcard').innerText = 'ID/Passport Number: ' + button.dataset.idcard;
            document.getElementById('opd-birth').innerText = 'Date of Birth: ' + button.dataset.birth;
            document.getElementById('opd-age').innerText = 'Age: ' + button.dataset.age;
            document.getElementById('opd-tel').innerText = 'Phone Number: ' + button.dataset.tel;
            document.getElementById('opd-email').innerText = 'Email: ' + button.dataset.email;
            document.getElementById('opd-address').innerText = 'Address in Thailand: ' + button.dataset.address;
            document.getElementById('opd-nationality').innerText = 'Nationality: ' + button.dataset.nationality;
            document.getElementById('opd-education').innerText = 'Education Level: ' + button.dataset.education;
            document.getElementById('opd-marital').innerText = 'Marital Status: ' + button.dataset.marital;
            document.getElementById('opd-disease').innerText = 'Underlying Disease: ' + button.dataset.disease;
            document.getElementById('opd-allergy').innerText = 'Drug/Food Allergy: ' + button.dataset.allergy;
            document.getElementById('opd-organization').innerText = 'Organization: ' + button.dataset.organization;
            document.getElementById('opd-otheroccupation').innerText = button.dataset.otheroccupation;

            // ✅ รีเซ็ต checkbox ทั้งหมด
            for (let i = 1; i <= 7; i++) {
                const el = document.getElementById(`status-${i}`);
                if (el) el.checked = false;
            }
            for (let i = 1; i <= 11; i++) {
                const el = document.getElementById(`occupation-${i}`);
                if (el) el.checked = false;
            }

            // ✅ ติ๊ก checkbox ตามค่าที่ได้
            if (statusId) {
                const el = document.getElementById(`status-${statusId}`);
                if (el) {
                    el.checked = true;
                    el.setAttribute('checked', 'checked');
                }
            }
            if (occupationId) {
                const el = document.getElementById(`occupation-${occupationId}`);
                if (el) {
                    el.checked = true;
                    el.setAttribute('checked', 'checked');
                }
            }

             // ✅ กล่องสีตาม category
            const categoryBox = document.getElementById('category-box');
           const categoryColors = {
    1: '#c8f7c5', // MSM/MSW/TG - เขียว
    2: '#f9d5e5', // Sex Worker - ชมพู
    3: '#cce5ff', // Migrant Worker - ฟ้า
    4: '#ffe0b3', // Prisoner - ส้ม
    5: '#fff9b1', // Youth - เหลือง
    6: '#ffffff', // General Public - ขาว
    7: '#e0ccff'  // Drug User - ม่วง
};

const boxRect = document.getElementById('category-box-rect');
if (boxRect && statusId) {
    boxRect.setAttribute('fill', categoryColors[statusId]);
}

            // ✅ เปิด modal
            $('#myModal2').modal('show');
});



    jQuery(document).ready(function($) {
    var table = null;
    function initPatientTable() {
        if ($.fn.DataTable.isDataTable('#example')) {
            try { $('#example').DataTable().clear().destroy(); } catch (e) {}
        }
        table = $('#example').DataTable({
            searching: false,
            responsive: true,
            "autoWidth": false,
            order: [[1, 'desc']],
            "pageLength": 50,
        });
    }
    initPatientTable();
    // realtime search (AJAX) สลับตารางแล้ว -> init DataTables ใหม่
    document.addEventListener('rt:updated', initPatientTable);

const statusAbbreviations = {
  "MSM/MSW/TG": "MS",
  "พนักงานบริการ": "P",
  "แรงงานข้ามชาติ": "MG",
  "ผู้ป่วยเรือนจำ": "PR",
  "เยาวชน": "YT",
  "ประชาชนทั่วไป": "G",
  "ผู้ใช้สารเสพติด": "DU"
};

// อ่านข้อมูลจากปุ่มที่กดโดยตรง (data-*) แทน table.row().data() แบบ index
// กันบั๊ก "พิมพ์ผิดคน" เวลาตาราง sort/แบ่งหน้า/responsive — ปุ่มไหนกด คนนั้นเป๊ะ
$(document).on('click', '#example tbody .btn-print-sticker', function () {
  var d = this.dataset;

  $(".modal-body div span").text("");
  $(".hn span").html(d.hn || '');

  var cleanName = (d.fullname || '').trim();
  // ยิ่งชื่อยาวยิ่งบีบให้พอดีสติกเกอร์
  let scaleRatio = 1;
  if (cleanName.length > 35) { scaleRatio = 0.80; }
  else if (cleanName.length > 25) { scaleRatio = 0.90; }
  $(".name-surname").css({
      "display": "inline-block",
      "transform": "scaleX(" + scaleRatio + ")",
      "transform-origin": "left"
  });
  $(".name-surname span").text(cleanName);
  var nameEn = (d.nameEn || '').trim();
  $(".name-en span").text(nameEn);
  $(".name-en-row").toggle(nameEn !== '');
  $("#sticker-table").toggleClass('has-en', nameEn !== '');

  $(".age span").text(d.age || '');
  $(".sex span").text(d.sex || '');
  // สถานะเป็นตัวย่อ
  $(".status span").text(statusAbbreviations[d.status] || d.status || '');
  $(".hright span").text(d.right || '');

  $("#myModal").modal("show");
});

$(document).on('click', '#example tbody .btn-outline-success', function() {
  var buttonId = $(this).attr('id');
  var patientId = buttonId.split('-')[2];
  var row = $(this).closest('tr');
  var data = table.row(row).data();

  $(".modal-body div span").text("");

  $(".myModal2-hn span").text(data[1]);
  
  var nameSurname = data[2].replace(/<span[^>]*>(.*?)<\/span>/g, '$1');
  $(".name-surname span").text(nameSurname);

  $(".myModal2-age span").text(data[5]);
  $(".myModal2-sex span").text(data[4]);
  $(".myModal2-date span").text(data[0]);
  
  // เปลี่ยนสถานะเป็นตัวย่อ
  $(".myModal2-status span").text(statusAbbreviations[data[6]] || data[6]);

  $("#myModal2").modal("show");
});

} );


// ฟอนต์บาร์โค้ด (ไฟล์ในเครื่อง) — ผูกกับ document.fonts ตั้งแต่เปิดหน้า และโหลดรอไว้เลย (2569-10-30)
// เดิมกฎ @font-face อยู่ในกล่องสติกเกอร์: โหลดตอนเปิด popup ครั้งแรก (บาร์โค้ดว่างชั่วครู่)
// และตอนพิมพ์ body ถูกแทนที่ด้วยเนื้อหากล่อง → กฎฟอนต์ถูกสร้างใหม่ยังไม่โหลด บางครั้งพิมพ์ออกมาไม่มีบาร์โค้ด ต้องเปิดใหม่
// ฟอนต์ที่เพิ่มผ่าน FontFace API อยู่กับเอกสารตลอด ไม่หายตอนแทนที่ body
var barcodeFont = null;
(function () {
    var url = "{{ asset('fonts/libre-barcode-39/LibreBarcode39-Regular.ttf') }}";
    if (window.FontFace && document.fonts) {
        barcodeFont = new FontFace('Libre Barcode 39', "url('" + url + "') format('truetype')", { display: 'block' });
        document.fonts.add(barcodeFont);
        barcodeFont.load().catch(function (e) { console.warn('โหลดฟอนต์บาร์โค้ดไม่ได้', e); });
    } else {
        var st = document.createElement('style');
        st.textContent = "@font-face { font-family: 'Libre Barcode 39'; font-display: block; src: url('" + url + "') format('truetype'); }";
        document.head.appendChild(st);
    }
})();

// รอฟอนต์บาร์โค้ดพร้อมก่อนสั่งพิมพ์ — พิมพ์ตอนฟอนต์ยังไม่มา = ได้ตัวหนังสือ *690001234* หรือช่องว่างแทนบาร์โค้ด
function printWhenBarcodeReady(go) {
    var done = false, run = function () { if (done) return; done = true; requestAnimationFrame(function () { setTimeout(go, 80); }); };
    var wait = barcodeFont ? barcodeFont.loaded
             : (document.fonts && document.fonts.load ? document.fonts.load("40px 'Libre Barcode 39'", '*0123456789*') : null);
    if (!wait) { run(); return; }
    wait.then(function () { return document.fonts ? document.fonts.ready : null; }).then(run, run);
    setTimeout(run, 4000);   // ไฟล์ฟอนต์เสียจริง ๆ ก็ยังพิมพ์ได้ ไม่ค้าง
}

function printSticker(box){
    var printContent = document.getElementById(box).innerHTML;
    var originalContent = document.body.innerHTML;
    document.body.innerHTML = printContent;
    printWhenBarcodeReady(function () {
        window.print();
        document.body.innerHTML = originalContent;
    });
}

window.onafterprint = function() {
    window.location.reload(true);
};

$('#close-modal').on('click', function() {
$('#myModal').modal('hide');
})

// ฟังก์ชันสำหรับพิมพ์
function printOpdCard(boxId) {
  var printContent = document.getElementById(boxId).innerHTML;
  var originalContent = document.body.innerHTML;
  document.body.innerHTML = printContent;
  printWhenBarcodeReady(function () {
    window.print();
    document.body.innerHTML = originalContent;
  });
}
$('#close-modal2').click(function() {
$('#myModal2').modal('hide');
});
// Event listener สำหรับปุ่ม Print ใน Modal 2
$('#printOpdCardBtn').click(function() {
  printOpdCard('box2');
});
window.onafterprint = function() {
    window.location.reload(true);
};
// Event listener สำหรับปุ่ม Close ใน Modal 2

function confirmDelete(btn, patientName) {
    let reason = prompt("กรุณาพิมพ์ \"ยืนยันลบข้อมูล\" เพื่อยืนยันการลบผู้ป่วย " + patientName + ":");
    if (reason === "ยืนยันลบข้อมูล") {
        btn.closest('form').submit(); // บรรทัดนี้ต้องการ btn เพื่อหา form ครับ
    } else if (reason !== null) {
        alert("คำยืนยันไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง");
    }
}

</script>


                    </div>
                </div>
            </div>
        </div>
    </div>
    @include('sweetalert::alert')
    @include('partials.realtime-search')
@endsection
