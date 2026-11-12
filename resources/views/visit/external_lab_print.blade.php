<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบรายงานผล Lab ส่งตรวจภายนอก</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', 'Nunito', sans-serif; font-size: 14px; line-height: 1.6; }

        .header-table { width: 100%; margin-bottom: 18px; }
        .header-table td { border: none !important; }
        .header-table img { max-height: 110px; }
        .header-title { font-size: 20px; font-weight: 600; }
        .header-subtitle { font-size: 11px; }

        table.result { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.result, table.result th, table.result td { border: 1px solid #000; }
        table.result th, table.result td { padding: 7px 8px; text-align: left; font-size: 14px; }
        table.result thead { background: #f1f1f1; }

        .text-red { color: #c00; font-weight: 600; }
        .sign-table { width: 100%; margin-top: 26px; }
        .sign-table td { border: none !important; font-size: 13px; padding: 4px 8px; }

        .ext-note {
            border: 1px solid #999; border-left: 4px solid #0aa2c0;
            padding: 8px 12px; margin-top: 14px; font-size: 12.5px; background: #f8feff;
        }

        @media print {
            @page { size: A4; margin: 10mm; }
            .no-print { display: none !important; }
            .ext-note { background: #fff; }
        }
    </style>
</head>
<body class="container">

    <div class="no-print my-3 d-flex gap-2">
        <button onclick="window.print()" class="btn btn-primary">🖨️ พิมพ์ใบรายงานผล</button>
        <a href="{{ route('visit.edit', ['visit' => $visit->id, 'page' => request('page', 4)]) }}" class="btn btn-secondary">ย้อนกลับ</a>
    </div>

    <table class="header-table">
        <tr>
            <td class="text-center" style="width: 12%;">
                <img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt="Logo">
            </td>
            <td class="text-center" style="width: 46%;">
                <div class="header-title">ใบรายงานผลการตรวจทางห้องปฏิบัติการภายนอก</div>
                <div class="header-subtitle">External Laboratory Test Report</div>
                <div class="header-subtitle">
                    ศูนย์ควบคุมโรคติดต่อทางเพศสัมพันธ์ สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่<br>
                    143 ถนนศรีดอนไชย ตำบลช้างคลาน อำเภอเมือง จังหวัดเชียงใหม่ 50000 · โทร. 053-140 774 ถึง 6
                </div>
            </td>
            {{-- ไม่ใส่โลโก้ ISO 15189 (S__21831716) — ใบนี้เป็นผลจากแล็บนอก
                 เราไม่ได้เป็นผู้ตรวจ จึงอ้างการรับรอง ISO ของห้องแล็บเราไม่ได้ --}}
            <td class="text-center" style="width: 12%;"></td>
        </tr>
    </table>

    <div class="row mb-3">
        <div class="col-6">
            <div><strong>HN:</strong> {{ $patient->code ?? '-' }}</div>
            <div><strong>ชื่อ-นามสกุล:</strong>
                {{ \App\Models\Patient::prefixLabel($patient) ?? '' }}{{ $patient->name ?? '' }} {{ $patient->surname ?? '' }}
            </div>
        </div>
        <div class="col-6">
            <div><strong>เพศ:</strong>
                {{ optional($patient)->sex == '1' ? 'ชาย' : (optional($patient)->sex == '2' ? 'หญิง' : '-') }}
                &nbsp;&nbsp;<strong>อายุ:</strong> {{ $patient ? $patient->age() : '-' }}
            </div>
            <div><strong>วันที่รับบริการ:</strong> {{ $visit->date }}</div>
        </div>
    </div>

    <table class="result">
        <thead>
            <tr>
                <th style="width:38px;">#</th>
                <th>รายการตรวจ (Test)</th>
                <th style="width:150px;">สิ่งส่งตรวจ</th>
                <th style="width:170px;">ผลการตรวจ</th>
                <th style="width:95px;">วันที่ส่ง</th>
                <th style="width:95px;">วันที่ได้ผล</th>
            </tr>
        </thead>
        <tbody>
        @forelse($rows as $i => $r)
            @php
                // ผลที่มีคำว่า positive/detected/reactive ให้เน้นสีแดง เหมือนใบผลของ Lab ในบ้าน
                $__flag = preg_match('/positive|detected|reactive/i', (string) $r->result)
                          && !preg_match('/non-?reactive|not detected/i', (string) $r->result);
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    {{ $r->name }}
                    @if($r->code) <span style="color:#666;">({{ $r->code }})</span> @endif
                </td>
                <td>{{ $r->specimen ?: '-' }}</td>
                {{-- รายละเอียดผลอยู่ใต้ผล เหมือนใบผลของ Lab ในบ้าน (Note ใต้ค่าที่ตรวจได้) --}}
                <td>
                    <span class="{{ $__flag ? 'text-red' : '' }}">{{ $r->result ?: 'รอผล' }}</span>
                    @if($r->remark)
                        <br><span style="font-size:.9em; color:#555; white-space:pre-line;">{{ $r->remark }}</span>
                    @endif
                </td>
                <td>{{ $r->sent_date_th ?: '-' }}</td>
                <td>{{ $r->result_date_th ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center">ไม่มีรายการส่งตรวจภายนอก</td></tr>
        @endforelse
        </tbody>
    </table>

    {{-- ต้องระบุให้ชัดว่าไม่ได้ตรวจเอง ไม่งั้นจะเข้าใจผิดว่าเป็นผลจากห้องแล็บของศูนย์ฯ
         (ใบผลของ Lab ในบ้านมีข้อความรับรอง ISO 15189 อยู่ ใบนี้ต้องไม่มี) --}}
    <div class="ext-note">
        รายการข้างต้นเป็นการ<strong>ส่งตรวจต่อยังห้องปฏิบัติการภายนอก</strong>
        @if($vendors) ({{ $vendors }}) @endif
        ผลการตรวจออกโดยห้องปฏิบัติการดังกล่าว ศูนย์ฯ เป็นผู้บันทึกผลเข้าระบบเท่านั้น
    </div>

    <table class="sign-table">
        <tr>
            <td style="width:50%;" class="text-center">
                ผู้บันทึกผล<br><br>
                ..............................................<br>
                ( {{ $recordedBy ?: '.....................................' }} )
            </td>
            <td style="width:50%;" class="text-center">
                วันที่พิมพ์<br><br>
                ..............................................<br>
                ( {{ \App\Utillity::db2thDate(\App\Utillity::AD2BE(now()->toDateString())) }} )
            </td>
        </tr>
    </table>

</body>
</html>
