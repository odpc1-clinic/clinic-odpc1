<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บัตรคิว {{ $queueNumber ?? '-' }}</title>
    <style>
        /* กระดาษต่อเนื่อง (Zebra ZD220) : ไม่กำหนด @page size, ไม่ใช้ page-break
           พิมพ์ 2 ใบไหลต่อกัน คั่นด้วยเส้นตัด ✂ ให้ตัดเอง
           ตัวใบกว้าง 2.1" แต่กระดาษกว้างกว่านั้นเล็กน้อย เดิมปล่อยชิดซ้ายเลยเหลือขอบว่างข้างเดียว
           ใช้ margin ซ้าย-ขวาเป็น auto ให้เฉลี่ยขอบสองข้างเท่ากัน — ปลอดภัยกว่าการกำหนดขอบตายตัว
           เพราะถ้ากระดาษแคบกว่า 2.1" auto จะกลายเป็น 0 เอง ไม่ดันเนื้อหาหลุดขอบขวา */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'Sarabun','TH Sarabun New','Segoe UI',sans-serif; color: #000; }

        /* ล็อกแต่ละใบสูง 4" ให้ตรงกับหน้ากระดาษในไดรเวอร์ + จัดกลางแนวตั้ง ; แยกหน้าแต่ละใบ */
        .ticket { width: 2.1in; height: 4in; margin: 0 auto; padding: 4px; text-align: center;
                  overflow: hidden; display: flex; flex-direction: column; justify-content: center;
                  page-break-after: always; break-after: page; }
        .ticket:last-of-type { page-break-after: auto; break-after: auto; }
        .ticket.has-cut { justify-content: flex-start; }   /* ใบผู้ป่วย: เริ่มบนสุดเพื่อวางเส้นตัด */
        /* ใบเจ้าหน้าที่ไม่มี QR จึงสั้นกว่า ถ้าจัดกึ่งกลางแนวตั้งจะลอยต่ำกว่าใบผู้ป่วยเห็นได้ชัด
           ให้เริ่มจากบนเหมือนกัน เว้นหัวเท่าความสูงเส้นตัด หัวกระดาษทั้งสองใบจะตรงกัน */
        .ticket:not(.has-cut) { justify-content: flex-start; padding-top: 18px; }

        .copytag { display: inline-block; font-size: 9pt; font-weight: 700;
                   border: 1px solid #000; border-radius: 3px; padding: 0 8px; }
        .clinic { font-size: 11pt; font-weight: 700; line-height: 1.05; margin-top: 2px; }
        .sub { font-size: 8pt; color: #222; }

        .qbox { border: 2px solid #000; border-radius: 6px; margin: 5px 2px 3px; padding: 2px 4px; }
        .qbox .qt { font-size: 11pt; font-weight: 700; }
        .qbox .qn { font-size: 42pt; font-weight: 800; line-height: 1; }
        /* มีบรรทัดชื่ออังกฤษ = บัตรยาวเกินใบ ย่อเลขคิวแทน (QR ต้องคงขนาดเดิมไว้ให้สแกนได้) — 2569-10-07 */
        .ticket.has-en .qbox .qn { font-size: 30pt; }
        .room { font-size: 16pt; font-weight: 700; }

        .pname { font-size: 12pt; font-weight: 700; line-height: 1.1; margin-top: 3px; }
        .pname-en { font-size: 10pt; font-weight: 700; line-height: 1.1; }
        .phn { font-size: 10pt; }
        .meta { font-size: 8.5pt; color: #222; }
        .wait { font-size: 10pt; font-weight: 700; margin-top: 3px; }

        .sep { border-top: 1px dashed #000; margin: 5px 4px 3px; }
        .survey-cap { font-size: 8.5pt; font-weight: 700; }
        .qr { line-height: 0; margin-top: 3px; }
        .qr svg { width: 0.75in; height: 0.75in; }

        /* เส้นตัด อยู่บนสุดของใบผู้ป่วย (รอยตัดระหว่างดวง) */
        .cutline { width: 100%; text-align: center; font-size: 8pt; color: #000;
                   border-bottom: 1px dashed #000; padding-bottom: 2px; margin-bottom: 4px; }

        @media print { .toolbar { display: none; } }
    </style>
    {{-- ตกแต่งเฉพาะหน้าจอ (preview) แยกไว้ก้อนเดียว — ตอนส่งให้ QZ พิมพ์ จะถูกตัดทิ้งเพื่อไม่ให้ติดพื้นหลัง/เงา --}}
    <style class="screen-only">
        @media screen {
            body { background: #e9e9e9; padding: 16px; }
            .ticket, .cutline { background: #fff; }
            .ticket { box-shadow: 0 2px 10px rgba(0,0,0,.12); }
            .toolbar { text-align: center; padding: 10px 0; margin-bottom: 14px;
                       position: sticky; top: 0; background: #e9e9e9; z-index: 10; }
            .toolbar button { font-size: 14px; padding: 6px 16px; cursor: pointer; }
        }
    </style>
</head>
<body>
    <div class="toolbar qz-noprint">
        <button onclick="window.print()">🖨️ พิมพ์อีกครั้ง</button>
        <button onclick="window.close()">ปิด</button>
    </div>

    @foreach(['patient','staff'] as $copy)
        @php
            $isPatient = $copy === 'patient';
            // ชื่ออังกฤษ ถ้ากรอกไว้ — คนไข้ต่างชาติอ่านชื่อไทยบนบัตรคิวไม่ออก (2569-10-07)
            $__en = $visit->patient2 ? $visit->patient2->englishNameLine() : '';
        @endphp
        <div class="ticket {{ $isPatient ? 'has-cut' : '' }} {{ $__en !== '' ? 'has-en' : '' }}">
            @if($isPatient)
                <div class="cutline">✂ - - - - - - ตัดตรงนี้ - - - - - -</div>
            @endif
            <div class="copytag">{{ $isPatient ? 'สำหรับผู้ป่วย' : 'สำหรับเจ้าหน้าที่' }}</div>
            {{-- ใช้ชื่อศูนย์ ไม่ใช่ชื่อคลินิกใดคลินิกหนึ่ง เพราะกระดาษคิวใบเดียวกันใช้ทั้ง STI · TB · TMC (2569-09-29) --}}
            <div class="clinic">ศูนย์บริการเวชศาสตร์ป้องกัน</div>
            <div class="sub">สคร.1 เชียงใหม่</div>

            <div class="qbox">
                <div class="qt">คิวหมายเลข</div>
                <div class="qn">{{ $queueNumber ?? '-' }}</div>
            </div>
            @if($roomName || ($queue && $queue->room_number))
                <div class="room">{{ $roomName ?? ('ห้อง ' . $queue->room_number) }}</div>
            @endif

            <div class="pname">{{ $visit->patient2->name ?? '-' }} {{ $visit->patient2->surname ?? '' }}</div>
            @if($__en !== '')
                <div class="pname-en">{{ $__en }}</div>
            @endif
            <div class="phn">HN {{ $visit->patient2->code ?? '-' }}</div>
            <div class="meta">{{ \Carbon\Carbon::now()->addYears(543)->format('d/m/Y') }} {{ \Carbon\Carbon::now()->format('H:i') }} น.</div>
            <div class="wait">กรุณารอเรียกคิว</div>

            @if($isPatient && !empty($surveyQr))
                <div class="sep"></div>
                <div class="survey-cap">สแกนประเมินความพึงพอใจ</div>
                <div class="qr">{!! $surveyQr !!}</div>
            @endif
        </div>
    @endforeach

    {{-- พิมพ์ผ่าน Ctrl+P ปกติ (เต็มกระดาษชัวร์) — ตั้งเครื่อง thermal เป็น default ของสถานีคิว จะกด Print ทีเดียวจบ
         (โครง QZ Tray ยังเก็บไว้: QzController, /qz/*, public/js/qz-print.js — เปิดใช้ทีหลังได้ถ้าแก้เรื่อง scaling ได้) --}}
    <script>
        window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
    </script>
</body>
</html>
