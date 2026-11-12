<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts -->
    {{-- ตัวรับคิวของจอรีเฟรชทันที (partials/realtime ต่อ Reverb ท้ายหน้า) — ตั้งก่อน ให้สคริปต์ในหน้าเรียก ClinicRT.on ได้เลย --}}
    <script>window.ClinicRT = { connected: false, _h: {}, _s: [],
        on: function (t, f) { (this._h[t] = this._h[t] || []).push(f); },
        onState: function (f) { this._s.push(f); } };</script>
    <script src="{{ asset('js/app.js') }}"></script>
<!-- DataTable -->
<script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.22/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

    {{-- ฟอนต์เก็บในเครื่อง (2569-11-03) — เดิมดึง Chakra Petch + ฟอนต์ที่ไม่ได้ใช้อีก 4 ตัวจาก Google ทุกหน้า
         หน้าแรกขึ้นฟอนต์ระบบก่อนแล้วค่อยสลับ ผู้ใช้เห็นตัวหนังสือแปลก ๆ แวบหนึ่ง · preload ตัวที่ใช้บ่อยให้มาก่อนวาดหน้า --}}
    <link rel="preload" href="/fonts/chakra-petch/chakra-petch-400-thai.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/chakra-petch/chakra-petch-400-latin.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/chakra-petch/chakra-petch-600-thai.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/chakra-petch/chakra-petch-700-thai.woff2" as="font" type="font/woff2" crossorigin>
    <link href="/fonts/chakra-petch/chakra-petch.css" rel="stylesheet">
    <!-- Styles -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/main.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- <link href="/css/jquery.datetimepicker.min.css" rel="stylesheet"> -->
    <link href="/css/datepicker.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.dataTables.min.css" rel="stylesheet">
     <link href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.2/css/uikit.min.css" rel="stylesheet">
     <link href="https://cdn.datatables.net/1.13.7/css/dataTables.uikit.min.css" rel="stylesheet">
    {{-- สไตล์แถบเมนู + ฟอนต์ทั้งหน้า ต้องอยู่ใน head (เดิมอยู่หลัง </body> หน้าวาดก่อนแล้วค่อยเปลี่ยน เห็นแวบ ๆ · ย้าย 2569-11-03) --}}
<style>
    /* สีแถบเปลี่ยนตามคลินิกที่กำลังทำงานอยู่ (ดู Visit::$ClinicNavGradient)
       ตั้งใจให้ต่างกันชัดๆ กันเจ้าหน้าที่คีย์ผิดคลินิก */
    .navbar-modern{
  background: {{ \App\Models\Visit::navGradient() }};
  padding: .4rem 0;
  /* เมนูที่กางลงมาต้องอยู่บนแถบแพ้ยา (1040) และป้ายลอยขวา (1042) ของหน้าตรวจ — เคยถูกบังจนกดเมนูไม่ได้ (2569-09-30)
     แต่ต่ำกว่า modal (1055) */
  position: relative; z-index: 1045;
}
.navbar-modern .navbar-brand{
  font-size: 1.15rem;
  font-weight: 700;
}

#time { font-weight: 700; }
.navbar-modern .navbar-toggler{ border-color: rgba(255,255,255,.5); }
.navbar-modern .navbar-toggler-icon{
  filter: invert(1) grayscale(100%);
}

.navbar-modern .nav-link{
  color:#fff !important;
  font-weight:600;
  border-radius:.6rem;
  padding:.45rem .8rem;
  transition: background .2s ease;
}
.navbar-modern .nav-link:hover{
  background: rgba(255,255,255,.18);
}

.navbar-modern .nav-pill{
  display:flex; align-items:center;
}
.navbar-modern .badge-pill{
  display:inline-block;
  border-radius:999px;
  padding:.1rem .45rem;
  background:#fef08a; /* เหลืองพาสเทล */
  color:#111;
  font-weight:800;
}

.navbar-time{
  color:#ffe2e2; font-weight:700; letter-spacing:.3px;
}
.navbar-modern .pastel-1{
  background-color:#b3e5fc; color:#000 !important;
}
.navbar-modern .pastel-1:hover{
  background-color:#81d4fa;
}

.navbar-modern .pastel-2{
  background-color:#e1bee7; color:#000 !important;
}
.navbar-modern .pastel-2:hover{
  background-color:#ce93d8;
}

.navbar-modern .pastel-8{
  background-color:#f8bbd0; color:#000 !important;
}
.navbar-modern .pastel-8:hover{
  background-color:#f48fb1;
}

.navbar-modern .pastel-4{
  background-color:#fff9c4; color:#000 !important;
}
.navbar-modern .pastel-4:hover{
  background-color:#fff59d;
}

.navbar-modern .pastel-lab{
  background-color:#c8e6c9; color:#000 !important;
}
.navbar-modern .pastel-lab:hover{
  background-color:#a5d6a7;
}
/* ห้องตรวจ 7 (TMC) — โทนม่วงให้เข้าชุดกับสีคลินิก TMC บนแถบและป้ายคลินิก */
.navbar-modern .pastel-tmc{
  background-color:#e9d5ff; color:#000 !important;
}
.navbar-modern .pastel-tmc:hover{
  background-color:#d8b4fe;
}
/* ห้องตรวจวัณโรค (ห้อง 2) — โทนส้มอ่อนให้เข้าชุดกับสีคลินิก TB */
.navbar-modern .pastel-tb{
  background-color:#fde8c8; color:#000 !important;
}
.navbar-modern .pastel-tb:hover{
  background-color:#fbd9a5;
}
.navbar-modern .pastel-pharmacy{
  background-color:#f1c232; color:#000 !important;
}
.navbar-modern .pastel-pharmacy:hover{
  background-color:#fff2cc;
}

.navbar-modern .nav-link.nav-pill{
  border-radius: .6rem;
  padding:.45rem .8rem;
  transition: background .2s ease;
}
/* ทำให้แถบหัวสวยและปุ่มห้องใหญ่ขึ้นเหมือนครั้งแรก */
.navbar-modern { padding: 10px 0; }
/* หน้าที่มีตารางยาว (หน้าตรวจ · บัตรวัณโรค) ขอพื้นที่กว้างกว่า .container ปกติ
   คลินิกใช้จอ 1366 ขึ้นไป การบีบไว้ที่ 1140px ทำให้ตารางขนาดยา/ปฏิทินกินยาอ่านยาก (2569-09-24) */
.container.container-wide { max-width: min(1800px, 96vw); }

/* ป้ายคลินิกที่กำลังทำงานอยู่ — ใช้สีเป็นตัวบอก จะได้ไม่กรอกผิดคลินิก */
.clinic-chip {
  color: #fff !important;
  border-radius: 999px;
  padding: .25rem .8rem !important;
  font-weight: 700;
  letter-spacing: .3px;
  box-shadow: 0 2px 10px -4px rgba(0,0,0,.6);
}
.clinic-chip:hover { filter: brightness(1.12); }

/* จัดเรียงปุ่มให้เว้นระยะและรองรับขึ้นบรรทัดใหม่ */
.navbar-modern .navbar-nav {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;           /* ระยะห่างระหว่างปุ่ม */
  align-items: center;
}

/* ปุ่มห้อง – ขยายขนาด */
.navbar-modern .nav-link.nav-pill{
  font-size: 1.05rem;         /* ตัวอักษรใหญ่ขึ้น */
  font-weight: 600;
  padding: .65rem 1rem;       /* ช่องว่างมากขึ้น */
  border-radius: 16px;        /* มนขึ้น */
  line-height: 1.2;
  box-shadow: 0 2px 6px rgba(0,0,0,.06); /* เงาบาง ๆ */
  min-width: 150px;           /* ให้ปุ่มดูกว้างขึ้น (ปรับได้) */
  text-align: center;
}

/* Badge จำนวนนัด/คนไข้ ให้เด่นชัด */
.navbar-modern .nav-link.nav-pill .badge{
  font-weight: 700;
  background: rgba(0,0,0,.12);
  color: #000;
}

/* จอเล็ก ลดขนาดลงนิดเพื่อไม่ให้ล้น */
@media (max-width: 992px){
  .navbar-modern .nav-link.nav-pill{
    font-size: .98rem;
    padding: .55rem .9rem;
    min-width: 130px;
  }
}

/* html นำหน้า = ชนะ "body { font-family: Prompt }" ที่บางหน้าใส่ไว้ (Prompt ไม่ได้โหลด) — เดิมชนะเพราะอยู่ท้ายไฟล์ ย้ายขึ้น head แล้วต้องชนะด้วยน้ำหนักแทน */
html body, html .chartjs-render-monitor {
  font-family: "Chakra Petch", sans-serif;
  font-weight: 400;
  font-style: normal;
}

/* ---- ปุ่ม utility (นัดหมาย/ค้นหา/ยา/สถิติ/แดชบอร์ด) แบบ frosted glass ---- */
.navbar-modern .nav-util{
  background: rgba(255,255,255,.14);
  backdrop-filter: blur(4px);
  border: 1px solid rgba(255,255,255,.22);
  min-width: auto;
}
.navbar-modern .nav-util:hover{
  background: rgba(255,255,255,.28);
  transform: translateY(-1px);
}

/* ---- เมนูที่กำลังเปิดอยู่ (active) เด่นชัด ---- */
.navbar-modern .nav-pill.active-pill{
  background: #ffffff !important;
  color: #1d4ed8 !important;
  box-shadow: 0 4px 12px rgba(0,0,0,.18);
  position: relative;
}
.navbar-modern .nav-pill.active-pill::after{
  content: "";
  position: absolute;
  left: 18%; right: 18%; bottom: -6px;
  height: 3px; border-radius: 3px;
  background: #ffffff;
}
.navbar-modern .nav-util.active-pill{ border-color: #fff; }

/* ---- นาฬิกา ---- */
.navbar-clock{
  display:inline-flex; align-items:center;
  background: rgba(0,0,0,.15);
  color:#fff; font-weight:700; letter-spacing:.3px;
  padding:.35rem .75rem; border-radius:999px;
  font-variant-numeric: tabular-nums;
}

/* ---- ชิปผู้ใช้ + avatar ---- */
.navbar-modern .user-chip{
  display:flex; align-items:center; gap:.5rem;
  background: rgba(255,255,255,.14);
  border:1px solid rgba(255,255,255,.22);
  border-radius:999px; padding:.3rem .8rem .3rem .3rem;
}
.navbar-modern .user-chip:hover{ background: rgba(255,255,255,.26); }
.user-avatar{
  width:30px; height:30px; border-radius:50%;
  background:#fff; color:#4f46e5; font-weight:800;
  display:inline-flex; align-items:center; justify-content:center;
  text-transform:uppercase; font-size:.95rem;
}
.user-menu{ border:0; border-radius:14px; padding:.4rem; min-width:230px; }
.user-menu .dropdown-item{ border-radius:8px; padding:.5rem .75rem; }
.user-menu .dropdown-item:hover{ background:#f1f5f9; }
.user-menu .dropdown-header{ padding:.4rem .75rem; }
</style>
</head>

<body>
{{-- ระบบทดสอบ (clinic-test, APP_ENV=staging) — แถบแดงทุกหน้า กันพยาบาลเข้าผิดแล้วลงข้อมูลคนไข้จริงในระบบทดสอบ (2569-10-19) --}}
@if(app()->environment('staging'))
    <div style="position:sticky;top:0;z-index:2000;background:#b91c1c;color:#fff;text-align:center;font-weight:700;padding:4px 8px;">
        ระบบทดสอบ — ข้อมูลไม่ใช่ของจริง ห้ามลงข้อมูลคนไข้จริงที่นี่ (ระบบจริงอยู่ที่ https://<CLINIC_DOMAIN>)
    </div>
@endif
    <div id="app">
        <nav class="navbar navbar-expand-md navbar-modern shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center text-white" href="{{ url('/') }}">
      <i class="fa-solid fa-hospital me-2"></i>
      <span>{{ config('app.name', 'Laravel') }}</span>
    </a>
    @include('partials.season_fx')

    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
            aria-expanded="false" aria-label="{{ __('Toggle navigation') }}">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarSupportedContent">
      {{-- Left --}}
      {{-- ปุ่มห้องขึ้นตามสิทธิ์ของแต่ละห้อง + Admin เห็นทุกปุ่ม (ผู้ดูแลขอ 2569-09-30)
           แค่ "เห็นปุ่ม/เปิดดูได้" — การประทับเวลาพยาบาล/หมอในหน้าตรวจยังดูสิทธิ์ Nurse/Doctor ตรง ๆ
           Admin อย่างเดียวจึงไม่ถูกนับเป็นพยาบาล/หมอ และไม่อยู่ในรายชื่อผู้ทดสอบ Lab --}}
      <ul class="navbar-nav me-auto align-items-md-center gap-1">
        @auth
          @hasanyrole('Registrar|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-1" href="{{ route('patient.index') }}">
    <i class="fa-solid fa-user-plus me-1"></i> ลงทะเบียน #1
  </a>
</li>
@endhasanyrole

@hasanyrole('Doctor|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-2 {{ request()->routeIs('visit.room') && request()->route('status') == 2 ? 'active-pill' : '' }}" href="{{ route('visit.room', ['status' => 2]) }}">
    <i class="fa-solid fa-stethoscope me-1"></i>
    ห้องตรวจชาย #4
    <span class="badge badge-pill ms-1" data-room-count="2">{{ App\Models\Visit::count(2) }}</span> ราย
  </a>
</li>

<li class="nav-item">
  <a class="nav-link nav-pill pastel-8 {{ request()->routeIs('visit.room') && request()->route('status') == 8 ? 'active-pill' : '' }}" href="{{ route('visit.room', ['status' => 8]) }}">
    <i class="fa-solid fa-user-doctor me-1"></i>
    ห้องตรวจหญิง #5
    <span class="badge badge-pill ms-1 text-danger" data-room-count="8">{{ App\Models\Visit::count(8) }}</span> ราย
  </a>
</li>
@endhasanyrole

@hasanyrole('Nurse|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-4 {{ request()->routeIs('visit.room') && request()->route('status') == 3 ? 'active-pill' : '' }}" href="{{ route('visit.room', ['status' => 3]) }}">
    <i class="fa-solid fa-syringe me-1"></i>
    ห้องเจาะเลือด/ฉีดยา #6
    <span class="badge badge-pill ms-1" data-room-count="3">{{ App\Models\Visit::count(3) }}</span> ราย
  </a>
</li>
@endhasanyrole

{{-- ห้องตรวจ 7 = TMC (เวชศาสตร์การเดินทาง) — สีม่วงตามชุดสีคลินิก TMC --}}
@hasanyrole('Doctor|Nurse|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-tmc {{ request()->routeIs('visit.room') && request()->route('status') == 7 ? 'active-pill' : '' }}" href="{{ route('visit.room', ['status' => 7]) }}">
    <i class="fa-solid fa-plane-departure me-1"></i>
    ห้องตรวจ 7 (TMC)
    <span class="badge badge-pill ms-1" data-room-count="7">{{ App\Models\Visit::count(7) }}</span> ราย
  </a>
</li>
@endhasanyrole

{{-- ห้องตรวจวัณโรค = ห้อง 2 (คลินิก TB เดินห้อง 2 -> 6 -> 10) — สีส้มตามชุดสีคลินิก TB --}}
@hasanyrole('Doctor|Nurse|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-tb {{ request()->routeIs('visit.room') && request()->route('status') == 10 ? 'active-pill' : '' }}" href="{{ route('visit.room', ['status' => 10]) }}">
    <i class="fa-solid fa-lungs me-1"></i>
    ห้องตรวจวัณโรค #2
    <span class="badge badge-pill ms-1" data-room-count="10">{{ App\Models\Visit::count(10) }}</span> ราย
  </a>
</li>
@endhasanyrole
@hasanyrole('Pharmacy|Admin')
                                <li class="nav-item">
                                    <a class="nav-link nav-pill pastel-pharmacy {{ request()->routeIs('pharmacy.*') ? 'active-pill' : '' }}"
                                        href="{{ route('pharmacy.index') }}"><i class="fa-solid fa-pills me-2"></i> ห้องยา/เก็บเงิน <span class="badge badge-pill ms-1" data-room-count="5">{{ App\Models\Visit::count(5) }}</span> ราย</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link nav-pill pastel-pharmacy {{ request()->routeIs('finance.*') ? 'active-pill' : '' }}"
                                        href="{{ route('finance.index') }}"><i class="fa-solid fa-cash-register me-2"></i> บิล/การเงิน <span class="badge badge-pill ms-1" data-room-count="9">{{ App\Models\Visit::count(9) }}</span> ราย</a>
                                </li>
                            @endhasanyrole
@hasanyrole('Lab|Admin')
<li class="nav-item">
  <a class="nav-link nav-pill pastel-lab {{ request()->routeIs('lab.*') ? 'active-pill' : '' }}" href="{{ route('lab.index') }}">
    <i class="fa-solid fa-flask me-1"></i> ห้อง Lab
  </a>
</li>
@endhasanyrole


          {{-- เมนูงานอื่น (ยุบรวมให้แถบห้องเด่น) — นัดหมาย/ค้นหา/คนไข้วันนี้/ยา --}}
          @php
            $menuActive = request()->routeIs('visit.appointment') || request()->routeIs('visit.index')
                        || request()->routeIs('visit.today') || request()->routeIs('medicine.*') || request()->routeIs('medicine-lot.*')
                        || request()->routeIs('holiday.*') || request()->routeIs('tb.*');
          @endphp
          <li class="nav-item dropdown">
            <a class="nav-link nav-pill nav-util dropdown-toggle {{ $menuActive ? 'active-pill' : '' }}"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-bars me-1"></i> เมนู
              {{-- จุดแดงเมื่อมีบันทึกการเปลี่ยนแปลงที่เครื่องนี้ยังไม่ได้เปิดอ่าน --}}
              <span class="d-none" id="cl-menu-dot"
                    style="display:inline-block; width:.5rem; height:.5rem; border-radius:50%; background:#dc3545; vertical-align:top;"></span>
            </a>
            <ul class="dropdown-menu shadow" style="font-size:1.05rem;">
              <li><a class="dropdown-item py-2 {{ request()->routeIs('visit.appointment') ? 'active' : '' }}" href="{{ route('visit.appointment') }}"><i class="fa-solid fa-calendar-check me-2"></i> นัดหมาย</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('holiday.*') ? 'active' : '' }}" href="{{ route('holiday.index') }}" title="วันที่คลินิกปิด — ล็อกไม่ให้นัด และไม่ส่ง LINE เตือน"><i class="fa-solid fa-calendar-xmark me-2"></i> วันหยุดราชการ</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('visit.index') ? 'active' : '' }}" href="{{ route('visit.index') }}"><i class="fa-solid fa-magnifying-glass me-2"></i> ค้นหา</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('visit.today') ? 'active' : '' }}" href="{{ route('visit.today') }}" title="คนไข้ที่มาวันนี้ทั้งหมด ทุกคลินิก"><i class="fa-solid fa-list-check me-2"></i> คนไข้วันนี้</a></li>
              {{-- ทะเบียนวัณโรค — บัตร TB 01 อยู่นอกหน้าตรวจ เพราะเป็นข้อมูลของคอร์สการรักษาที่ยาวข้ามปี --}}
              @hasanyrole('Doctor|Nurse|Admin')
              <li><a class="dropdown-item py-2 {{ request()->routeIs('tb.index') || request()->routeIs('tb.show') || request()->routeIs('tb.create') ? 'active' : '' }}" href="{{ route('tb.index') }}" title="ทะเบียนผู้ป่วยวัณโรค TB 01 / LTBI / MDR-TB"><i class="fa-solid fa-lungs me-2"></i> ทะเบียนวัณโรค</a></li>
              {{-- งานประจำวัน: ติ๊กว่าใครกินยาแล้ว ไม่ต้องเปิด visit --}}
              <li><a class="dropdown-item py-2 {{ request()->routeIs('tb.dotBoard*') ? 'active' : '' }}" href="{{ route('tb.dotBoard') }}" title="รายชื่อคนไข้ที่ต้องกำกับกินยารายวัน"><i class="fa-solid fa-list-check me-2"></i> DOT รายวัน</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('tb.report') ? 'active' : '' }}" href="{{ route('tb.report') }}" title="รายงานประจำเดือนคลินิกวัณโรค (ESM + ผลการปฏิบัติงาน)"><i class="fa-solid fa-file-lines me-2"></i> รายงานวัณโรครายเดือน</a></li>
              @endhasanyrole
              @hasanyrole('Pharmacy|Admin')
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('medicine.*') || request()->routeIs('medicine-lot.*') ? 'active' : '' }}" href="{{ route('medicine.index') }}"><i class="fa-solid fa-pills me-2"></i> คลังยา</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('vaccine-schedule.*') ? 'active' : '' }}" href="{{ route('vaccine-schedule.index') }}" title="ระยะห่างแต่ละเข็ม ใช้นัดเข็มถัดไปอัตโนมัติ"><i class="fa-solid fa-syringe me-2"></i> ตารางนัดวัคซีน</a></li>
              @endhasanyrole
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('changelog.*') ? 'active' : '' }}" href="{{ route('changelog.index') }}" title="ดูว่าระบบเปลี่ยนอะไรไปบ้าง"><i class="fa-solid fa-bullhorn me-2"></i> มีอะไรใหม่ <span class="badge bg-danger ms-1 d-none" id="cl-new-dot">ใหม่</span></a></li>
            </ul>
          </li>

          {{-- รายงาน/สถิติ ยุบรวม — สถิติ/แดชบอร์ด/506/LINE --}}
          @php
            $repActive = request()->routeIs('stats.index') || request()->routeIs('report.yearly')
                       || request()->routeIs('r506.*') || request()->routeIs('line.health')
                       || request()->routeIs('report.explore*') || request()->routeIs('report.tmc') || request()->routeIs('report.sti-k1');
          @endphp
          <li class="nav-item dropdown">
            <a class="nav-link nav-pill nav-util dropdown-toggle {{ $repActive ? 'active-pill' : '' }}"
               href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fa-solid fa-chart-line me-1"></i> รายงาน
            </a>
            <ul class="dropdown-menu shadow" style="font-size:1.05rem;">
              <li><a class="dropdown-item py-2 {{ request()->routeIs('stats.index') ? 'active' : '' }}" href="{{ route('stats.index') }}"><i class="fa-solid fa-chart-pie me-2"></i> สถิติบริการ</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('report.yearly') ? 'active' : '' }}" href="{{ route('report.yearly') }}"><i class="fa-solid fa-chart-line me-2"></i> แดชบอร์ด</a></li>
              @hasanyrole('Admin|Nurse|Doctor')
              {{-- ดึงรายชื่อคนไข้เองจากตัวกรอง — ไว้ที่นี่เพราะคนมาหาที่เมนูรายงานก่อนเสมอ --}}
              <li><a class="dropdown-item py-2 {{ request()->routeIs('report.explore*') ? 'active' : '' }}" href="{{ route('report.explore') }}" title="เลือกเงื่อนไขเองแล้วดึงรายชื่อคนไข้ + ออก Excel"><i class="fa-solid fa-magnifying-glass-chart me-2"></i> ดึงรายชื่อคนไข้เอง</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('report.tmc') ? 'active' : '' }}" href="{{ route('report.tmc') }}" title="สรุปผู้รับบริการ + จำนวนโดสวัคซีนรายเดือน (นับจากบันทึกการฉีด)"><i class="fa-solid fa-plane-departure me-2"></i> รายงาน TMC รายเดือน</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('report.sti-k1') ? 'active' : '' }}" href="{{ route('report.sti-k1') }}" title="แบบ ก.1 รายเดือน — นับให้ แก้ตัวเลขได้ทุกช่อง"><i class="fa-solid fa-file-lines me-2"></i> รายงาน ก.1 (STI)</a></li>
              @endhasanyrole
              <li><a class="dropdown-item py-2 {{ request()->routeIs('contact-sti.*') ? 'active' : '' }}" href="{{ route('contact-sti.index') }}" title="ทะเบียนติดตามผู้ป่วย 5 โรคหลัก + คู่สัมผัส"><i class="fa-solid fa-people-arrows me-2"></i> ทะเบียนคู่สัมผัส</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('r506.*') ? 'active' : '' }}" href="{{ route('r506.index') }}" title="ส่งออกรายงานเฝ้าระวังโรค 506 (STI)"><i class="fa-solid fa-file-medical me-2"></i> รายงาน 506</a></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('rihis-vct.*') ? 'active' : '' }}" href="{{ route('rihis-vct.index') }}" title="ทะเบียนบริการปรึกษาและตรวจเอชไอวี (RIHIS-VCT)"><i class="fa-solid fa-hand-holding-medical me-2"></i> ทะเบียน RIHIS-VCT</a></li>
              @if(auth()->user()->hasRole('Admin'))
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item py-2 {{ request()->routeIs('line.health') ? 'active' : '' }}" href="{{ route('line.health') }}" title="สถานะการเชื่อมต่อระบบแจ้งเตือน LINE"><i class="fa-brands fa-line me-2"></i> สถานะ LINE</a></li>
              @endif
            </ul>
          </li>

          {{-- แจ้งว่ามีบันทึกการเปลี่ยนแปลงใหม่ที่ "เครื่องนี้" ยังไม่ได้เปิดอ่าน
               เก็บใน localStorage ไม่ใช่ DB เพราะเป็นเรื่องของเครื่อง/เบราว์เซอร์ ไม่ใช่ข้อมูลคนไข้
               (เครื่องกลางหลายคนใช้ร่วมกัน ผูกกับ user จะเตือนผิดคน) --}}
          <script>
          (function () {
              var latest = @json(\App\Http\Controllers\ChangelogController::latestDate());
              if (!latest) return;
              var seen = null;
              try { seen = localStorage.getItem('changelog_seen'); } catch (e) { return; }
              if (seen === latest) return;
              ['cl-menu-dot', 'cl-new-dot'].forEach(function (id) {
                  var el = document.getElementById(id);
                  if (el) el.classList.remove('d-none');
              });
          })();
          </script>
        @endauth
      </ul>

      {{-- Right --}}
      <ul class="navbar-nav ms-auto align-items-md-center">
        @auth
          {{-- สลับคลินิกที่กำลังทำงานอยู่: รายการงาน/รายงานจะกรองตามตัวนี้ --}}
          @php
            $__ac     = \App\Models\Visit::activeClinic();
            $__acName = \App\Models\Visit::$ClinicShortName[$__ac] ?? 'STI';
            $__acCol  = \App\Models\Visit::$ClinicColor[$__ac] ?? '#0d6efd';
          @endphp
          <li class="nav-item dropdown me-2">
            <a class="nav-link dropdown-toggle clinic-chip" href="#" id="navbarClinic" role="button"
               data-bs-toggle="dropdown" aria-expanded="false"
               style="background: {{ $__acCol }};" title="คลินิกที่กำลังทำงานอยู่ — กดเพื่อสลับ">
              <i class="fa-solid fa-hospital me-1"></i>{{ $__acName }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="navbarClinic">
              <li><h6 class="dropdown-header">เลือกคลินิกที่ทำงาน</h6></li>
              @foreach(\App\Models\Visit::$ClinicOption as $__cid => $__cname)
                <li>
                  <a class="dropdown-item d-flex align-items-center gap-2 {{ $__cid === $__ac ? 'active' : '' }}"
                     href="{{ route('clinic.switch', $__cid) }}">
                    <span style="width:.65rem;height:.65rem;border-radius:50%;background:{{ \App\Models\Visit::$ClinicColor[$__cid] }};"></span>
                    {{ $__cname }}
                    @if($__cid === $__ac)<i class="fas fa-check ms-auto"></i>@endif
                  </a>
                </li>
              @endforeach
              <li><hr class="dropdown-divider"></li>
              <li><span class="dropdown-item-text small text-muted">รายการงานและรายงานจะแสดงเฉพาะคลินิกนี้</span></li>
            </ul>
          </li>
        @endauth

        <!-- <li class="nav-item me-3 d-none d-md-block">
          <span class="navbar-clock"><i class="fa-regular fa-clock me-1"></i><span id="navbar-time"></span></span>
        </li> -->

        @guest
          @if (Route::has('login'))
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('login') }}">{{ __('Login') }}</a></li>
          @endif
          @if (Route::has('register'))
            <li class="nav-item"><a class="nav-link text-white" href="{{ route('register') }}">{{ __('Register') }}</a></li>
          @endif
        @else
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle text-white user-chip" href="#" id="navbarUser" role="button"
               data-bs-toggle="dropdown" aria-expanded="false">
               <span class="user-avatar">{{ mb_substr(Auth::user()->name, 0, 1) }}</span>
               <span class="d-none d-lg-inline">{{ Auth::user()->name }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow user-menu" aria-labelledby="navbarUser">
              <li class="dropdown-header">
                <div class="fw-bold text-truncate">{{ Auth::user()->name }}</div>
                <div class="small text-muted">{{ Auth::user()->roles->pluck('name')->join(', ') ?: 'ผู้ใช้งาน' }}</div>
              </li>
              <li><hr class="dropdown-divider"></li>

              {{-- เปลี่ยนรหัสผ่าน — หนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569
                   วางไว้บนสุดของเมนูให้หาเจอง่าย คนที่ยังจำรหัสเดิมได้จะได้เปลี่ยนเองไม่ต้องรอแอดมิน --}}
              <li>
                <a class="dropdown-item fw-bold" href="{{ route('password.mine') }}">
                  <i class="fa-solid fa-key text-warning me-2"></i> เปลี่ยนรหัสผ่านของฉัน
                </a>
              </li>
              @role('Admin')
                <li>
                  <a class="dropdown-item" href="{{ route('password.status') }}">
                    <i class="fa-solid fa-shield-halved text-primary me-2"></i> สถานะเปลี่ยนรหัสผ่าน
                  </a>
                </li>
              @endrole
              <li><hr class="dropdown-divider"></li>

              @role('Admin')
                <li><a class="dropdown-item" href="/backend"><i class="fa-solid fa-gear text-secondary me-2"></i> Backend</a></li>
                <li><a class="dropdown-item" href="{{ route('activity-log.index') }}"><i class="fa-solid fa-clock-rotate-left text-secondary me-2"></i> ประวัติการแก้ไข (Log)</a></li>
                <li><a class="dropdown-item" href="{{ route('season-fx.index') }}"><i class="fa-solid fa-wand-magic-sparkles text-secondary me-2"></i> ตั้งค่าเอฟเฟกต์เทศกาล</a></li>
              @endrole
              <li><a class="dropdown-item" href="/home"><i class="fa-solid fa-house text-secondary me-2"></i> Home</a></li>
              @hasanyrole('Pharmacy|Admin')
              <li><a class="dropdown-item" href="{{ route('medicine-lot.index') }}"><i class="fas fa-boxes-stacked text-primary me-2"></i> คลังยา (Stock/Lot)</a></li>
              <li><a class="dropdown-item" href="{{ route('service-price.index') }}"><i class="fas fa-hand-holding-medical text-primary me-2"></i> ตั้งราคาค่าบริการ</a></li>
              <li><a class="dropdown-item" href="{{ route('external-lab-test.index') }}"><i class="fas fa-vials text-info me-2"></i> รายการ Lab นอก</a></li>
              <li><a class="dropdown-item" href="{{ route('lab-price.index') }}"><i class="fas fa-flask text-primary me-2"></i> ตั้งราคากลาง Lab</a></li>
              @endhasanyrole
              {{-- แคตตาล็อกแล็บรวม (2569-10-02) — ซ่อนจนกว่าตารางจะขึ้น --}}
              @if(\App\Models\LabCatalogItem::ready())
                @hasanyrole('Admin|Lab')
                <li><a class="dropdown-item" href="{{ route('lab-catalog.index') }}"><i class="fas fa-list-check text-primary me-2"></i> แคตตาล็อกแล็บรวม</a></li>
                @endhasanyrole
              @endif
              <li><a class="dropdown-item" href="{{ route('line.logs') }}"><i class="fab fa-line text-success me-2"></i> ประวัติข้อความ LINE</a></li>
              <li><hr class="dropdown-divider"></li>
              <li>
                <a class="dropdown-item text-danger" href="{{ route('logout') }}"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                  <i class="fa-solid fa-right-from-bracket me-2"></i> {{ __('Logout') }}
                </a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">@csrf</form>
              </li>
            </ul>
          </li>
        @endguest
      </ul>
    </div>
  </div>
</nav>


        <main class="py-4">
            <div class="container-fluid">
                <div id="flash-msg">
                    @include('partials.flash_message')
                    @include('flash::message')
                </div>

                @if (session('appointment_holiday_warning'))
                    <div class="alert alert-warning d-flex align-items-start gap-2">
                        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                        <div>
                            <b>วันนัดที่บันทึกไปตรงกับวันหยุด — {{ session('appointment_holiday_warning') }}</b><br>
                            <span class="small">คลินิกปิดวันนั้น ระบบจะไม่ส่ง LINE เตือนนัดนี้ให้คนไข้
                            ถ้าไม่ได้ตั้งใจ กรุณาแก้วันนัดใหม่</span>
                        </div>
                    </div>
                @endif

                @include('partials.holiday_reminder')

                @role('Admin')
                    @php
                        $lineQuota = \Illuminate\Support\Facades\Cache::get('line_quota_status');
                    @endphp
                    @if($lineQuota && $lineQuota['limit'] > 0 && ($lineQuota['used'] / $lineQuota['limit']) >= 0.8)
                        <div class="alert alert-warning">
                            <i class="fab fa-line"></i> LINE push quota ใกล้เต็มแล้ว: ใช้ไป {{ $lineQuota['used'] }}/{{ $lineQuota['limit'] }} ข้อความเดือนนี้
                            ({{ round($lineQuota['used'] / $lineQuota['limit'] * 100) }}%) — ถ้าเกินจะส่งแจ้งเตือนนัดหมายไม่ได้จนกว่าจะขึ้นเดือนใหม่ หรือต้องอัปเกรดแผน
                            <span class="text-muted small">(เช็คล่าสุด {{ \Carbon\Carbon::parse($lineQuota['checked_at'])->format('d/m/Y H:i') }})</span>
                        </div>
                    @endif
                @endrole

                @yield('content')
                @stack('scripts')
        </main>
    </div>

    @include('partials.obs_timer')
    @auth @include('partials.activity_toast') @include('partials.realtime') @include('partials.room_counts_live') @endauth
    {{-- ช่องข้อความยาวยืดตามเนื้อหาเอง ต้องอยู่ท้ายสุดเพื่อให้เห็นช่องที่ @stack('scripts') เพิ่งใส่เข้ามา --}}
    @include('partials.textarea_autogrow')
    {{-- ตัวทำงานของตัวเลือก ICD-10 — โหลดเฉพาะหน้าที่มีตัวเลือกจริง (ตัวเลือกตั้งธงไว้ตอนวาด) --}}
    @if(\App\Models\Icd10Code::$pickerUsed)
        @include('partials.icd10_picker_js')
    @endif
</body>


</html>
<script>
  function tick(){
    const el = document.getElementById('navbar-time');
    if(!el) return;
    el.textContent = new Date().toLocaleTimeString();
  }
  tick();
  setInterval(tick, 1000);
</script>
