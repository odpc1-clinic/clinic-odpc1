<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกข้อความส่งผู้ป่วยรับการรักษาต่อ</title>
    <style>
        /* แบบฟอร์มฉบับใหม่ (2569-10-05) — "บันทึกข้อความ" เต็มหน้า A4 แนวตั้ง ตามไฟล์ Word ที่คลินิกส่งมา
           (บันทึกข้อความส่งต่อตรวจ CD4 และรักษา.docx) แทนแบบครึ่งหน้าเดิม
           ขอบกระดาษตามต้นฉบับ: บน 2 ซม. · ขวา 2 ซม. · ล่าง 1 ซม. · ซ้าย 3 ซม. */
        /* ฟอนต์ TH SarabunPSK เลขอารบิก (คลินิกกำหนด 2569-10-05 — ไม่ใช้ TH SarabunIT๙)
           โหลดจากไฟล์ในเซิร์ฟเวอร์ public/fonts/ เครื่องที่ไม่ได้ลงฟอนต์ก็พิมพ์ออกมาเหมือนกัน */
        @font-face { font-family: 'THSarabunPSK-web'; src: url('{{ asset("fonts/THSarabun.ttf") }}') format('truetype'); font-weight: normal; }
        @font-face { font-family: 'THSarabunPSK-web'; src: url('{{ asset("fonts/THSarabun Bold.ttf") }}') format('truetype'); font-weight: bold; }
        @page { size: A4 portrait; margin: 20mm 20mm 10mm 30mm; }

        * { box-sizing: border-box; }
        /* ขนาดตามไฟล์ Word: เนื้อความ 16pt · หัว "บันทึกข้อความ" 29pt */
        body { font-family: 'THSarabunPSK-web', 'TH SarabunPSK', sans-serif; font-size: 16pt; line-height: 1.45;
               margin: 0; color: #000; background: #fff; }
        .page { width: 160mm; min-height: 267mm; margin: 0 auto; position: relative; }

        .head { position: relative; height: 16mm; }
        .head img { position: absolute; left: 0; top: 0; width: 15mm; height: auto; }
        .head h1 { text-align: center; font-size: 29pt; font-weight: bold; margin: 0; line-height: 16mm; }

        .unit { margin-top: 2mm; }
        .indent { text-indent: 25mm; }
        /* แต่ละบรรทัดเป็นแถว flex: ข้อความคงที่กว้างตามตัวอักษร · ช่องเติม (.fill) ยืดจนชนขอบขวา
           เดิมใช้ inline-block กำหนดความกว้างตายตัว ชื่อยาว/ฟอนต์ต่างเครื่อง = ขึ้นบรรทัดใหม่ เส้นประเหลื่อม (คลินิกแจ้ง 2569-10-05) */
        .ln { display: flex; align-items: baseline; white-space: nowrap; }
        .ln > span:not(.fill) { flex: none; }
        .fill { flex: 1 1 auto; border-bottom: 1px dotted #000; text-align: center; padding: 0 4px; margin: 0 4px;
                min-width: 10mm; line-height: 1.2; overflow: hidden; }
        .fill.w-day { flex: 0 0 14mm; } .fill.w-mon { flex: 0 0 34mm; } .fill.w-yr { flex: 0 0 20mm; }
        .fill.w-sex { flex: 0 0 22mm; } .fill.w-age { flex: 0 0 14mm; }
        .ln.date { justify-content: center; margin: 3mm 0; }
        b.label { font-weight: bold; }
        .gap-s { height: 3mm; }

        .sign { margin-left: 60mm; text-align: center; margin-top: 6mm; }
        .sign .space { height: 14mm; }

        .foot { position: absolute; left: 0; bottom: 0; line-height: 1.5; }

        [contenteditable] { outline: none; }
        [contenteditable]:focus { background: #fff3cd; }
        .toolbar { padding: 10px; font-family: 'Sarabun', sans-serif; font-size: 14px; }
        .toolbar button, .toolbar a { font-family: inherit; font-size: 14px; padding: 6px 14px; margin-right: 6px;
            border: 1px solid #999; border-radius: 6px; background: #f4f4f4; cursor: pointer; text-decoration: none; color: #000; }
        .hint { font-size: 13px; color: #666; margin-top: 6px; }

        @media screen { body { background: #e5e7eb; } .page { background: #fff; padding: 20mm 20mm 10mm 30mm; width: 210mm; min-height: 297mm; box-shadow: 0 2px 8px rgba(0,0,0,.15); margin-bottom: 20px; }
                        .foot { left: 30mm; bottom: 10mm; } }
        @media print { .no-print { display: none !important; } [contenteditable]:focus { background: none; } }
    </style>
</head>
<body>

<div class="no-print toolbar">
    <button onclick="window.print()">🖨️ พิมพ์ (A4 แนวตั้ง)</button>
    <a href="{{ route('visit.edit', ['visit' => $visit->id, 'page' => request('page', 2)]) }}">ย้อนกลับ</a>
    <div class="hint">ช่องเส้นประคลิกพิมพ์แก้ได้ก่อนสั่งพิมพ์ (ชื่อโรงพยาบาล สาเหตุ ผู้ลงนาม วันที่) · ตั้ง Scale = 100% ในหน้าต่างพิมพ์</div>
</div>

<div class="page">
    <div class="head">
        <img src="{{ asset('images/garuda_memo.png') }}" alt="ตราครุฑ">
        <h1>บันทึกข้อความ</h1>
    </div>

    <div class="unit">
        <div>ศูนย์บริการเวชศาสตร์ป้องกัน</div>
        <div>สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</div>
    </div>

    <div class="ln date">
        <span>วันที่</span><span class="fill w-day" contenteditable>{{ $day }}</span>
        <span>เดือน</span><span class="fill w-mon" contenteditable>{{ $monthName }}</span>
        <span>พ.ศ.</span><span class="fill w-yr" contenteditable>{{ $year }}</span>
    </div>

    <div class="ln"><span><b class="label">เรื่อง</b>&nbsp;&nbsp; ส่งผู้ป่วยรับการรักษาต่อ</span></div>
    <div class="ln"><span><b class="label">เรียน</b>&nbsp;&nbsp; เจ้าหน้าที่ที่เกี่ยวข้องโรงพยาบาล</span><span class="fill" contenteditable>{{ $hospital }}</span></div>

    <div class="gap-s"></div>
    <div class="indent">ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ ขอส่งผู้ป่วยชื่อ</div>
    <div class="ln">
        <span class="fill">{{ $name }}</span>
        <span>เพศ</span><span class="fill w-sex">{{ $sex }}</span>
        <span>อายุ</span><span class="fill w-age">{{ $age }}</span><span>ปี</span>
    </div>
    <div class="ln"><span>เลขที่บัตรประจำตัวประชาชน</span><span class="fill">{{ $idCard }}</span></div>
    <div class="ln">
        <span>ผลการตรวจเลือด</span><span class="fill" contenteditable>{{ $bloodResult ?: 'Anti-HIV Positive' }}</span>
        <span>เมื่อวันที่</span><span class="fill" contenteditable>{{ $resultDate }}</span>
    </div>
    <div class="ln"><span>สาเหตุที่ส่ง</span><span class="fill" contenteditable>{{ $reason }}</span></div>

    <div class="gap-s"></div>
    <div class="indent">จึงเรียนมาเพื่อโปรดทราบและพิจารณาดำเนินการต่อไปด้วย จักเป็นพระคุณ</div>

    <div class="sign">
        <div>ขอแสดงความนับถือ</div>
        <div class="space"></div>
        <div class="ln" style="justify-content:center"><span>(</span><span class="fill" contenteditable style="flex:0 0 60mm">{{ $signer }}</span><span>)</span></div>
    </div>

    <div class="foot">
        <div>ศูนย์บริการเวชศาสตร์ป้องกัน</div>
        <div>โทร 0 5314 0774 ต่อ 600 หรือ 604</div>
        <div>ID Line: @352bavoo</div>
    </div>
</div>
</body>
</html>
