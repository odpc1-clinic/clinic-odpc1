{{-- ใบรายงานผล Anti-HIV หนึ่งรายการ (HivRevealController@printResult) — 2569-10-06
     หน้าตาตาม "Print ผลที่เลือก" ของห้อง Lab (lab-sub/table) ที่ใช้กันอยู่ แต่ประกอบฝั่งเซิร์ฟเวอร์
     เพราะหน้าบัตร TB ปิดผลไว้ ไม่มีค่าผลใน HTML ให้ JS หยิบ · เปิดหน้านี้ = ถูกบันทึกว่าพิมพ์ผล HIV แล้ว
     ตัวแปร: $sub (LabSub method 6) · $lab · $patient|null · $visit|null --}}
@php
    // เลือกภาษาใบผล ?lang=en (ห้อง Lab ขอ 2569-10-16) — ดู App\Support\LabReportLang
    $__lg = \App\Support\LabReportLang::lang(request('lang'));
    $__miss = \App\Support\LabReportLang::missing($patient ?? null, [$lab->tested_by ?? null, $lab->report_by ?? null, $lab->approve_by ?? null]);
@endphp
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory Test Report — Anti-HIV</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; font-size: 14px; line-height: 1.6; margin: 0; padding: 16px 24px; color: #000; background: #fff; }
        .head { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .head td { border: none; text-align: center; vertical-align: middle; }
        .head img { max-height: 110px; }
        .title { font-size: 20px; font-weight: 600; margin: 0; }
        .sub { font-size: 11px; }
        .pt { display: flex; flex-wrap: wrap; margin-bottom: 12px; }
        .pt > div { width: 50%; }
        .res { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .res th, .res td { border: 1px solid #000; padding: 8px; text-align: left; }
        .res thead { background: #f1f1f1; }
        .pos { color: red; font-weight: 600; }
        .sign { width: 100%; border-collapse: collapse; margin-top: 24px; font-size: 13px; }
        .sign td { border: none; padding: 4px 8px; }
        .noprint { margin-bottom: 12px; }
        @media print { .noprint { display: none; } @page { size: A4; margin: 10mm; } }
    </style>
</head>
<body>
    <div class="noprint"><button type="button" onclick="window.print()">พิมพ์</button>
        <span style="margin-left:12px;">ภาษา:
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'th']) }}" style="{{ $__lg === 'th' ? 'font-weight:700;' : '' }}">ไทย</a> |
        <a href="{{ request()->fullUrlWithQuery(['lang' => 'en']) }}" style="{{ $__lg === 'en' ? 'font-weight:700;' : '' }}">English</a></span>
        @if($__lg === 'en' && $__miss)<div style="color:#b45309; margin-top:4px;">ยังไม่มีภาษาอังกฤษ: {{ implode(', ', $__miss) }} — ส่วนนั้นใช้ภาษาไทยแทน</div>@endif
    </div>

    <table class="head">
        <tr>
            <td style="width:14%;"><img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt="Logo"></td>
            <td>
                <h3 class="title">Laboratory Test Report</h3>
                <div class="sub">Medical Laboratory for Disease Control, Office of Disease Prevention and Control 1 Chiang Mai</div>
                <div class="sub">143 Sri Don Chai Road, Chang Klan Subdistrict, Mueang District, Chiang Mai 50000<br>Telephone: 053-140 774 to 6</div>
            </td>
            <td style="width:14%;"><img src="{{ asset('images/S__21831716.png') }}" alt="Logo Right"></td>
        </tr>
    </table>

    <div class="pt">
        <div><strong>Patient Number (HN):</strong> {{ $patient->code ?? '' }}</div>
        <div><strong>Sex:</strong> {{ optional($patient)->sex == '1' ? 'M' : (optional($patient)->sex == '2' ? 'F' : '') }}</div>
        <div><strong>Patient Name:</strong> {{ \App\Support\LabReportLang::patientName($patient, $__lg) }}</div>
        <div><strong>Age:</strong> {{ \App\Support\LabReportLang::age($patient, $__lg) }}</div>
        @if($lab->LN)<div><strong>LN:</strong> {{ $lab->LN }}</div>@endif
    </div>

    <h3 style="font-size:18px; margin:0;">Result</h3>
    <table class="res">
        <thead><tr><th style="width:2.5rem;">#</th><th>Method</th><th>Specimen From</th><th>Result</th></tr></thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $sub->method() }}</td>
                <td>{{ $sub->specimenFrom() }}</td>
                <td class="{{ \App\Http\Controllers\HivRevealController::isPositive($sub->result) ? 'pos' : '' }}">
                    {{ $sub->result }}
                    @if(trim((string) $sub->remark) !== '')<br><span style="font-size:.9em; color:#555;">(Note: {{ $sub->remark }})</span>@endif
                </td>
            </tr>
        </tbody>
    </table>
    <div style="margin-top:6px;"># Tests that has been accredited complying with the ISO 15189 standard for medical laboratories.</div>

    <table class="sign">
        <tr>
            <td>Tested By</td><td>{{ \App\Support\LabReportLang::staffName($lab->tested_by ?? null, $__lg) }}</td>
            <td>Tested Date</td><td>{{ \App\Support\LabReportLang::date($lab->tested_date ?? null, $__lg) }}</td>
        </tr>
        <tr>
            <td>Reported By</td><td>{{ \App\Support\LabReportLang::staffName($lab->report_by, $__lg) }}</td>
            <td>Reported Date</td><td>{{ \App\Support\LabReportLang::date($lab->report_date, $__lg, $lab->report_at) }}</td>
        </tr>
        <tr>
            <td>Approved By</td><td>{{ \App\Support\LabReportLang::staffName($lab->approve_by, $__lg) }}</td>
            <td>Approved Date</td><td>{{ \App\Support\LabReportLang::date($lab->approve_date, $__lg, $lab->approve_at) }}</td>
        </tr>
    </table>
</body>
</html>
