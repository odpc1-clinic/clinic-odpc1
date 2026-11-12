{{-- ใบรายงาน AST ของเชื้อหนองใน — ตามหัวฟอร์ม LABORATORY REPORT ของ สคร.1 ที่ห้อง Lab ส่งมา (2569-09-30)
     หน้านี้เปิดแท็บใหม่เพื่อพิมพ์ ไม่ใช้ layout หลัก · วันที่ในใบเป็น ค.ศ. แบบในฟอร์มต้นฉบับ --}}
@php
    use App\Support\GcAst;
    $ast   = (array) ($sub->ast ?? []);
    $d     = (array) ($ast['drugs'] ?? []);
    // เลือกภาษาใบผล ?lang=en (ห้อง Lab ขอ 2569-10-16) — วันที่ในใบนี้เป็น ค.ศ. อยู่แล้ว ต่างแค่ชื่อ/เพศ
    $__lg = \App\Support\LabReportLang::lang(request('lang'));
    $__miss = \App\Support\LabReportLang::missing($patient ?? null, [$lab->tested_by ?? null, $lab->report_by ?? null, $lab->approve_by ?? null]);
    $user  = function ($id) use ($__lg) {
        $u = $id ? \App\Models\User::find($id) : null;
        if (! $u) { return ''; }
        if ($__lg === 'en' && trim((string) $u->name_en) !== '') { return trim($u->name_en); }
        // บางคนพิมพ์เลขใบอนุญาตไว้ในชื่ออยู่แล้ว — ไม่ต่อซ้ำ
        $lic = trim((string) $u->license_number);
        return trim($u->name . ($lic !== '' && ! str_contains($u->name, $lic) ? ' ' . $lic : ''));
    };
    // วันที่ในฐานเป็น พ.ศ. (visits/labs) — แปลงเป็น ค.ศ. dd/mm/YYYY ตามฟอร์ม
    $ad = function ($date, $sep = '/') {
        if (! $date) { return ''; }
        $c = \Carbon\Carbon::parse($date);
        $y = $c->year > 2400 ? $c->year - 543 : $c->year;
        return $c->format('d' . $sep . 'm' . $sep) . $y;
    };
    $sexTh = ($__lg === 'en' ? [1 => 'Male', 2 => 'Female'] : [1 => 'ชาย', 2 => 'หญิง'])[(int) ($patient->sex ?? 0)] ?? '';
    $pfx   = \App\Models\Patient::prefixLabel($patient) ?? '';
    $interp = fn ($k) => GcAst::INTERP[$d[$k]['i'] ?? ''] ?? '';
    $mic   = collect(GcAst::DRUGS)->where('type', 'mic');
    $zone  = collect(GcAst::DRUGS)->where('type', 'zone');
@endphp
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>AST {{ $patient->code ?? '' }}</title>
{{-- ขนาด/ขอบกระดาษเท่าใบพิมพ์ผล Lab ปกติ (14px, A4 ขอบ 10mm, กว้างแบบ container)
     ฟอนต์ Sarabun ไฟล์ในเครื่อง ตัวเดียวกับใบนัดวัณโรค — Prompt หนาเกินไปสำหรับใบรายงาน (ห้อง Lab ขอ 2569-09-30) --}}
<style>
    @font-face { font-family: 'Sarabun'; src: url('{{ asset("fonts/sarabun/Sarabun-Regular.ttf") }}') format('truetype'); font-weight: normal; font-style: normal; }
    @font-face { font-family: 'Sarabun'; src: url('{{ asset("fonts/sarabun/Sarabun-Bold.ttf") }}') format('truetype'); font-weight: bold; font-style: normal; }
    @page { size: A4; margin: 10mm; }
    body { font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 14px; line-height: 1.5; color: #000;
           max-width: 1140px; margin: 0 auto; padding: 0 12px; }
    @media print { body { max-width: none; padding: 0; } }
    .hdr { display: flex; border: 1px solid #000; }
    .hdr > div { padding: 6px 10px; }
    .hdr-l { flex: 1.1; display: flex; gap: 10px; align-items: center; border-right: 1px solid #000; }
    .hdr-l img { width: 90px; }
    .hdr-l .t1 { font-weight: 600; font-size: 18px; text-align: center; line-height: 1.3; }
    .hdr-l .t2 { font-size: 11px; text-align: center; }
    .hdr-r { flex: 1; }
    .hdr-r .t1 { font-weight: 600; font-size: 20px; text-align: right; letter-spacing: .02em; }
    .hdr-r .kv { text-align: right; margin-top: 4px; font-weight: 700; }
    .pt { display: grid; grid-template-columns: 1.3fr 1fr 1fr 1.3fr; gap: 3px 10px; margin: 10px 4px 2px; }
    .pt b { font-weight: 700; }
    .final { text-align: center; font-weight: 700; margin: 6px 0 10px; }
    h3 { font-size: 18px; font-weight: 400; margin: 8px 0 4px; }
    table { width: 100%; border-collapse: collapse; }
    .ast td, .ast th { border: 1px solid #000; padding: 5px 8px; }
    .ast th { font-weight: 400; }
    .ast .c { text-align: center; }
    .ast .drug { text-align: right; padding-right: 30px; width: 22%; }
    .ast .sec td { border-top: 2px solid #000; }
    .R { font-weight: 700; }
    .sign td { padding: 5px 4px; border: 0; font-size: 14px; }
    .foot { margin-top: 16px; font-size: 12px; border-top: 1px solid #000; padding-top: 4px; }
    .noprint { margin: 10px 0; }
    @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button onclick="window.print()">พิมพ์</button>
    <span style="margin-left:12px;">ภาษา:
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'th']) }}" style="{{ $__lg === 'th' ? 'font-weight:700;' : '' }}">ไทย</a> |
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" style="{{ $__lg === 'en' ? 'font-weight:700;' : '' }}">English</a></span>
        @if($__lg === 'en' && $__miss)<div style="color:#b45309; margin-top:4px;">ยังไม่มีภาษาอังกฤษ: {{ implode(', ', $__miss) }} — ส่วนนั้นใช้ภาษาไทยแทน</div>@endif
</div>

<div class="hdr">
    <div class="hdr-l">
        <img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt="">
        <div>
            <div class="t1">Office of Disease Prevention<br>and Control 1 Chiang Mai</div>
            <div class="t2">143 Si Donchai Rd. Chang Khlan sub-district, Muang, Chiangmai,<br>THAILAND 50100, Tel: 053-276 364-168 : Fax: 053-273590</div>
        </div>
    </div>
    <div class="hdr-r" style="display:flex; align-items:center; gap:10px;">
        {{-- ตรารับรอง ilac-MRA / DMSc ISO 15189 เลขทะเบียน 4287/65 — ตามหัวฟอร์มจริง --}}
        <img src="{{ asset('images/S__21831716.png') }}" alt="" style="width:140px;">
        <div style="flex:1;">
            <div class="t1">LABORATORY REPORT</div>
            <div class="kv">ODPC1 No: {{ $lab->LN }}</div>
            <div class="kv">LSN: {{ $lab->id }}</div>
        </div>
    </div>
</div>

<div class="pt">
    <div><b>Name:</b> {{ \App\Support\LabReportLang::patientName($patient ?? null, $__lg) }}</div>
    <div><b>Gender:</b> {{ $sexTh }}</div>
    <div><b>ID:</b> {{ $patient->id_card_number ?? '' }}</div>
    <div><b>Passport No:</b> {{ $patient->passport ?? '' }}</div>

    <div><b>Hospital:</b> ศูนย์บริการเวชศาสตร์ป้องกัน สคร.1</div>
    <div><b>HN:</b> {{ $patient->code ?? '' }}</div>
    <div><b>Age:</b> {{ $patient && preg_match('/\d+/', (string) $patient->age(), $__am) ? $__am[0] . ' years' : '' }}</div>
    <div><b>Date of birth:</b> {{ $patient && $patient->birth_date ? $ad($patient->birth_date) : '- -' }}</div>

    <div><b>Specimen:</b> {{ $sub->specimenFrom() }}</div>
    <div><b>Code:</b></div>
    <div><b>Objective:</b> Diagnosis</div>
    <div><b>Event No:</b></div>

    <div><b>HIP**:</b></div>
    <div></div>
    <div><b>Request By:</b> {{ $visit ? $user($visit->doctor_by) : '' }}</div>
    <div><b>Collection DT*:</b> {{ $ad($lab->collected_date, '-') }} {{ $lab->collected_at ? substr($lab->collected_at, 0, 5) : '' }}</div>

    <div><b>Remark:</b> {{ $lab->remark }}</div>
    <div></div><div></div>
    <div><b>Received DT*:</b> {{ $lab->received_at ? $ad($lab->received_at, '-') . ' ' . \Carbon\Carbon::parse($lab->received_at)->format('H:i:s') : '' }}</div>
</div>
<div style="font-size:10px; margin-left:4px;">*Date/Time, ** History of present illness</div>
{{-- AST แก้หลัง Approve = ยังไม่มีใครรับรองผลชุดนี้ ห้ามขึ้น FINAL พร้อมลายเซ็นของการ approve ครั้งก่อน (รีวิว 2569-09-30) --}}
@php $__astPending = \App\Support\GcAst::pendingApproval($lab, $sub->ast); @endphp
<div class="final">{{ $lab->approve_by && ! $__astPending ? '** FINAL REPORT **' : '** PRELIMINARY REPORT **' }}</div>

<h3># Antimicrobial susceptibility testing of <i>Neisseria gonorrhoeae</i></h3>
<table class="ast">
    <tr><td colspan="4">Culture : &nbsp;&nbsp;&nbsp; {{ $sub->result }}</td></tr>
    <tr><td colspan="4">Identification : &nbsp; <i>Neisseria gonorrhoeae</i></td></tr>
    <tr class="sec"><th class="c">E-test</th><th class="c">MIC Breakpoint (ug/ml)</th><th class="c">Interpretation</th><th class="c">Normal range</th></tr>
    @foreach($mic as $k => $def)
        <tr>
            <td class="drug">{{ $def['label'] }}</td>
            <td class="c">{{ $d[$k]['v'] ?? '' }}</td>
            <td class="c {{ ($d[$k]['i'] ?? '') === 'R' ? 'R' : '' }}">{{ $interp($k) }}</td>
            <td class="c">{{ $def['range'] }}</td>
        </tr>
    @endforeach
    <tr class="sec"><th class="c">Disc Diffusion</th><th class="c">Inhibition zone (mm.)</th><th class="c">Interpretation</th><th class="c">Normal range</th></tr>
    <tr>
        <td class="drug" style="text-align:left;">beta lactamase</td>
        <td class="c">{{ $d['betalactamase']['v'] ?? '' }}</td>
        <td class="c">{{ $interp('betalactamase') }}</td>
        <td></td>
    </tr>
    @foreach($zone as $k => $def)
        <tr>
            <td class="drug">{{ $def['label'] }}</td>
            <td class="c">{{ $d[$k]['v'] ?? '' }}</td>
            <td class="c {{ ($d[$k]['i'] ?? '') === 'R' ? 'R' : '' }}">{{ $interp($k) }}</td>
            <td class="c">{{ $def['range'] }}</td>
        </tr>
    @endforeach
</table>

<table class="sign" style="margin-top:10px;">
    <tr><td style="width:18%;">Comment</td><td colspan="3">{{ $ast['comment'] ?? GcAst::DEFAULT_COMMENT }}</td></tr>
</table>
<div style="margin-top:14px; font-size:13px;">Detection Method:</div>
<div style="margin:4px 0 30px;">{{ $ast['method'] ?? GcAst::DEFAULT_METHOD }}</div>

<table class="sign">
    <tr><td style="width:18%;">Tested by</td><td style="width:40%;">{{ $user($lab->tested_by) }}</td>
        <td style="width:18%;">Tested Date</td><td>{{ $ad($lab->tested_date) }}</td></tr>
    <tr><td>Reported by</td><td>{{ $user($lab->report_by) }}</td>
        <td>Reported Date</td><td>{{ $ad($lab->report_date) }}</td></tr>
    <tr><td>Approved by</td><td>{{ $__astPending ? '' : $user($lab->approve_by) }}</td>
        <td>Approved Date</td><td>{{ $__astPending ? '' : $ad($lab->approve_date) }}</td></tr>
</table>

<div class="foot"># Tests that has been accredited complying with the ISO 15189 standard for medical laboratories</div>
</body>
</html>
