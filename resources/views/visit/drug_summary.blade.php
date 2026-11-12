<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบสรุปรายการยากลับบ้าน — {{ $patient->code ?? '' }}</title>
    <style>
        /* แบบตามใบของ รพ.นครพิงค์ ที่คลินิกส่งมา (2569-10-06) — A4 แนวตั้ง ฟอนต์ TH SarabunPSK จากเซิร์ฟเวอร์ */
        @font-face { font-family: 'THSarabunPSK-web'; src: url('{{ asset("fonts/THSarabun.ttf") }}') format('truetype'); font-weight: normal; }
        @font-face { font-family: 'THSarabunPSK-web'; src: url('{{ asset("fonts/THSarabun Bold.ttf") }}') format('truetype'); font-weight: bold; }
        @page { size: A4 portrait; margin: 12mm 12mm 12mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'THSarabunPSK-web', 'TH SarabunPSK', sans-serif; font-size: 15pt; line-height: 1.3; margin: 0; color: #000; background: #fff; }
        .page { width: 186mm; min-height: 273mm; margin: 0 auto; position: relative; display: flex; flex-direction: column; }

        .head { display: flex; border: 1.5px solid #000; }
        .head .org { width: 36%; display: flex; align-items: center; gap: 8px; padding: 6px 10px; border-right: 1.5px solid #000; font-weight: bold; line-height: 1.15; }
        .head .org img { height: 40px; }
        .head .title { flex: 1; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 17pt; text-align: center; padding: 6px; }
        .pt { border: 1.5px solid #000; border-top: 0; padding: 4px 10px; font-size: 16pt; }
        .pt b { font-weight: bold; }

        table.drugs { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 14pt; }
        table.drugs th, table.drugs td { border: 1px solid #000; padding: 3px 6px; vertical-align: top; }
        table.drugs th { font-weight: bold; text-align: center; }
        table.drugs td.n { text-align: center; width: 9mm; }
        table.drugs td.amt { white-space: nowrap; width: 22mm; }
        table.drugs td.drug { width: 48mm; }
        table.drugs td.how { width: 52mm; }

        .more { margin-top: 10px; }
        .more .line { border-bottom: 1px dotted #000; min-height: 22px; }
        .sign { text-align: right; margin-top: 14px; }
        .foot { margin-top: auto; border: 1.5px solid #000; padding: 4px 10px; text-align: center; font-size: 13.5pt; }

        [contenteditable] { outline: none; }
        [contenteditable]:focus { background: #fff3cd; }
        .toolbar { padding: 10px; font-family: sans-serif; font-size: 14px; }
        .toolbar button { font-size: 14px; padding: 6px 14px; margin-right: 6px; border: 1px solid #999; border-radius: 6px; background: #f4f4f4; cursor: pointer; }
        .hint { font-size: 13px; color: #666; margin-top: 6px; }
        @media screen { body { background: #e5e7eb; } .page { background: #fff; padding: 12mm; width: 210mm; min-height: 297mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); margin-bottom: 20px; } }
        @media print { .no-print { display: none !important; } [contenteditable]:focus { background: none; } }
    </style>
</head>
<body>
<div class="no-print toolbar">
    <button onclick="window.print()">🖨️ พิมพ์ (A4)</button>
    <div class="hint">คลิกข้อความในตารางเพื่อแก้ได้ก่อนพิมพ์ (โดยเฉพาะช่อง "คำอธิบาย" ซึ่งระบบเติมตามชื่อยา — กรุณาตรวจทาน) · รายการยามาจากการสั่งยาของ visit นี้</div>
</div>

<div class="page">
    <div class="head">
        <div class="org">
            <img src="{{ asset('images/' . rawurlencode('logo-กรม-2566 png.png')) }}" alt="logo">
            <div>ศูนย์บริการเวชศาสตร์ป้องกัน<br><span style="font-weight:normal; font-size:13pt;">สคร.1 เชียงใหม่</span></div>
        </div>
        <div class="title">ใบสรุปรายการยากลับบ้าน หรือส่งต่อสถานพยาบาลใกล้บ้าน</div>
    </div>
    <div class="pt">
        <div><b>ชื่อ</b> {{ $name }} &nbsp; <b>อายุ</b> {{ $age }} &nbsp; <b>HN</b> {{ $patient->code ?? '-' }} &nbsp; <b>คลินิก</b> {{ $clinic }}</div>
        <div><b>ประวัติแพ้ยา :</b> <span contenteditable>{{ $allergy }}</span></div>
        <div><b>วันที่รับยา</b> {{ $visitDate }}</div>
    </div>

    <table class="drugs">
        <thead>
            <tr><th>ลำดับ</th><th>รายการยา</th><th>จำนวน</th><th>วิธีบริหารยา</th><th>คำอธิบาย</th></tr>
        </thead>
        <tbody>
        @forelse($rows as $i => $r)
            <tr>
                <td class="n">{{ $i + 1 }}</td>
                <td class="drug" contenteditable>{{ $r['name'] }}</td>
                <td class="amt" contenteditable>{{ $r['amount'] }}</td>
                <td class="how" contenteditable>{{ $r['how'] }}</td>
                <td contenteditable>{{ $r['advice'] }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center; padding:12px;">ยังไม่มีรายการยาใน visit นี้</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="more">
        <b>ข้อมูลเพิ่มเติม</b>
        <div class="line" contenteditable></div>
    </div>
    <div class="sign">ลงชื่อ ........................................................ (<span contenteditable>เภสัชกร</span>)</div>

    <div class="foot">
        <b>เอกสารสำคัญ</b> สำหรับให้ผู้ป่วยนำกลับบ้าน กรณีกลับมารักษาต่อที่ศูนย์บริการเวชศาสตร์ป้องกันหรือสถานพยาบาลอื่น ให้นำเอกสารฉบับนี้พร้อมยาไปด้วยทุกครั้ง<br>
        ศูนย์บริการเวชศาสตร์ป้องกัน สคร.1 เชียงใหม่ | โทร 053-140774 ต่อ 600-604 · มือถือคลินิก 081-8830372 · ID Line: @352bavoo
    </div>
</div>
</body>
</html>
