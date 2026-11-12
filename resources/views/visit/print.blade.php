<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Medical Record - {{ $patient->name }} {{ $patient->surname }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            line-height: 1.4;
            color: #000;
            background: #fff;
            margin: 0; /* ลบ margin ของ body */
        }
        
        /* ตั้งค่าหน้ากระดาษ A4 และขอบกระดาษ (Margin) */
        @page { 
            size: A4; 
            margin: 1.5cm 1.5cm; /* บน-ล่าง 1.5cm, ซ้าย-ขวา 1.5cm */
        }

        /* Container ไม่ต้องฟิก 210mm แล้ว ให้เต็มพื้นที่ที่เหลือจาก Margin */
        .container { 
            width: 100%; 
            margin: 0 auto; 
            padding: 0; 
        }

        /* ซ่อนปุ่มเวลาปริ้น */
        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; }
        }

        .header { text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h2 { margin: 0; font-size: 20px; }
        
        .section-box { border: 1px solid #000; padding: 10px; margin-bottom: 10px; border-radius: 4px; page-break-inside: avoid; }
        .section-title { font-weight: bold; font-size: 16px; text-decoration: underline; margin-bottom: 5px; }
        
        .row { display: flex; flex-wrap: wrap; }
        .col-3 { width: 25%; }
        .col-4 { width: 33.33%; }
        .col-6 { width: 50%; }
        .col-12 { width: 100%; }
        
        .label { font-weight: bold; color: #333; }
        .value { border-bottom: 1px dotted #999; display: inline-block; min-width: 50px; padding-left: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        table, th, td { border: 1px solid #000; }
        th, td { padding: 4px; text-align: left; vertical-align: top; font-size: 13px; }
        th { background-color: #f0f0f0; }

        .footer-sign { margin-top: 30px; display: flex; justify-content: flex-end; page-break-inside: avoid; }
        .sign-box { text-align: center; width: 200px; }
    </style>
</head>
<body>

    <div class="text-center no-print" style="padding: 10px; background: #eee; text-align:center;">
        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">🖨️ Print Medical Record</button>
    </div>

    <div class="container">
        
        {{-- === ส่วนหัวกระดาษ (แบบรวมตรงกลาง) === --}}
        <div style="text-align: center; margin-bottom: 15px; border-bottom: 2px solid #000; padding-bottom: 10px;">
            
            {{-- ใช้ Flexbox จัดให้โลโก้และข้อความอยู่ติดกันตรงกลาง --}}
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                
                {{-- โลโก้ 1: กระทรวงสาธารณสุข --}}
                <img src="{{ asset('images/logo-กรม-2566 png.png') }}" style="height: 70px; width: auto;">

                {{-- ข้อความหน่วยงาน --}}
                <div style="text-align: center;">
                    <h3 style="margin: 0; font-size: 18px; font-weight: bold;">ศูนย์บริการเวชศาสตร์ป้องกัน</h3>
                    <div style="font-size: 14px; font-weight: normal;">Preventive Medicine Service Center</div>
                    
                    <h4 style="margin: 5px 0 0 0; font-size: 16px; font-weight: bold;">สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</h4>
                    <div style="font-size: 12px; font-weight: normal;">The Office of Disease Prevention and Control 1 Chiang Mai</div>
                </div>

                {{-- โลโก้ 2: สคร.1 --}}
                <img src="{{ asset('images/สำนักงานป้องกันควบคุมโรคที่ 1.png') }}" style="height: 70px; width: auto;">
            
            </div>

        </div>

        {{-- === หัวข้อเอกสาร (Medical Record Title) === --}}
        <div class="header" style="border-bottom: 1px solid #ccc; margin-bottom: 15px; padding-bottom: 5px;">
            <h2 style="font-size: 20px; margin: 0;">ใบบันทึกเวชระเบียน (Medical Record)</h2>
            <p style="margin: 5px 0 0 0; font-size: 14px;">
                วันที่ตรวจ: <b>{{ date('d/m/Y', strtotime(str_replace('/', '-', $visit->date))) }}</b> 
                | HN: <b>{{ $patient->code }}</b>
            </p>
        </div>


        <div class="section-box">
            <div class="section-title">ข้อมูลทั่วไป (General Information)</div>
            <div class="row">
                <div class="col-6">
                    <span class="label">ชื่อ-สกุล:</span> 
                    <span class="value">
                        {{ isset($patient->prefix) ? \App\Models\Patient::prefixLabel($patient) ?? '' : '' }} 
                        {{ $patient->name }} {{ $patient->surname }}
                    </span>
                </div>
                <div class="col-3"><span class="label">อายุ:</span> <span class="value">{{ $patient->age() }} ปี</span></div>
                <div class="col-3"><span class="label">เพศ:</span> <span class="value">{{ App\Models\Patient::$sexOption[$patient->sex] ?? '-' }}</span></div>
                
                <div class="col-6"><span class="label">อาชีพ:</span> <span class="value">{{ App\Models\Patient::$occupationOption[$patient->occupation] ?? '-' }}</span></div>
                <div class="col-6"><span class="label">เบอร์โทร:</span> <span class="value">{{ $patient->tel }}</span></div>
                
                <div class="col-12" style="margin-top:5px;">
                    <span class="label" style="color:red;">โรคประจำตัว:</span> 
                    <span class="value">{{ $patient->congenitaldisease ?? '-' }}</span>
                    &nbsp;&nbsp;
                    <span class="label" style="color:red;">ประวัติแพ้ยา:</span> 
                    <span class="value">{{ $patient->drug_allergy ?? '-' }}</span>
                </div>
            </div>
        </div>

        <div class="section-box">
            <div class="section-title">ประวัติการเจ็บป่วย (History & Physical Exam)</div>
            <p><span class="label">อาการสำคัญ (CC):</span> {{ $visit->symptom ?? '-' }}</p>
            
            <div class="row">
                <div class="col-3"><span class="label">BW:</span> <span class="value">{{ $visit->weight ?? '' }}</span> kg.</div>
                <div class="col-3"><span class="label">HT:</span> <span class="value">{{ $visit->height ?? '' }}</span> cm.</div>
                <div class="col-3"><span class="label">BP:</span> <span class="value">{{ $visit->bp ?? '' }}</span> mmHg</div>
                <div class="col-3"><span class="label">P:</span> <span class="value">{{ $visit->pulse ?? '' }}</span> /min</div>
                <div class="col-3"><span class="label">T:</span> <span class="value">{{ $visit->temp ?? '' }}</span> °C</div>
                <div class="col-3"><span class="label">O2Sat:</span> <span class="value">{{ $visit->oxygen ?? '' }}</span> %</div>
            </div>
        </div>

        @if(isset($visit->body_check_marks) && $visit->body_check_marks != '[]')
        <div class="section-box">
            <div class="section-title">การตรวจร่างกาย (Body Marking)</div>
            
            <div style="text-align: center; padding: 10px;">
                <canvas id="printCanvas_card1" width="600" height="300" 
                    style="border:1px solid #ccc; width: 80%; height: auto; margin-bottom: 15px;"></canvas>
                
                <canvas id="printCanvas_card2" width="600" height="300" 
                    style="border:1px solid #ccc; width: 80%; height: auto;"></canvas>
            </div>
        </div>
        @endif

   
        <div class="section-box">
            <div class="section-title">ผลทางห้องปฏิบัติการ (Laboratory)</div>
            
            @if($visit->labRef)
                {{-- ส่วนที่ 1: ข้อมูลเจ้าหน้าที่และวันที่ (เพิ่มใหม่) --}}
                <div class="row" style="margin-bottom: 5px;">
                    <div class="col-6">
                        <span class="label">ผู้รายงาน (Report By):</span> 
                        <span class="value">
                            {{-- เรียกใช้ฟังก์ชัน getName จาก User Model ตามที่มีในระบบเดิม --}}
                            {{ \App\Models\User::getName($visit->labRef->report_by) }}
                        </span>
                    </div>
                    <div class="col-6">
                        <span class="label">ผู้รับรอง (Approve By):</span> 
                        <span class="value">
                            {{ \App\Models\User::getName($visit->labRef->approve_by) }}
                        </span>
                    </div>
                </div>
                <div class="row" style="margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px dashed #ccc;">
                    <div class="col-6">
                        <span class="label">Collected Date:</span> 
                        <span class="value">{{ $visit->labRef->collected_date }}</span>
                    </div>
                    <div class="col-6">
                        <span class="label">Report Date:</span> 
                        <span class="value">{{ $visit->labRef->report_date }}</span>
                    </div>
                </div>
            @endif

         
            {{-- ส่วนที่ 2: ตารางรายการ Lab รายละเอียด --}}
            @if($visit->labRef && $visit->labRef->labSub2->count() > 0)
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th style="border: 1px solid #000; padding: 5px; width: 5%; text-align: center;">#</th>
                        <th style="border: 1px solid #000; padding: 5px; width: 25%;">Method</th>
                        <th style="border: 1px solid #000; padding: 5px; width: 25%;">Specimen</th>
                        <th style="border: 1px solid #000; padding: 5px;">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visit->labRef->labSub2 as $index => $item)
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;">{{ $index + 1 }}</td>
                        <td style="border: 1px solid #000; padding: 5px;">
                            {{ $item->method() }} 
                        </td>
                        <td style="border: 1px solid #000; padding: 5px;">
                            {{ $item->specimenFrom() }}
                        </td>
                        <td style="border: 1px solid #000; padding: 5px;">
                            {!! nl2br(e($item->result ?? '-')) !!}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
                <p style="font-style: italic; color: #666; margin-top: 10px;">- ไม่พบรายการ Lab เพิ่มเติม -</p>
            @endif
        </div>

        {{-- ใช้ diagnosiAllString() ที่แสดงผลในหน้าประวัติ --}}
        <div class="section-box">
            <div class="section-title">การวินิจฉัย (Diagnosis)</div>
            <p>
                <span class="label">ผลวินิจฉัย:</span> 
                {{ method_exists($visit, 'diagnosiAllString') ? $visit->diagnosiAllString() : '-' }}
            </p>
            <p>
                <span class="label">สถานะโรค:</span> 
                {{ method_exists($visit, 'diseaseStateString') ? $visit->diseaseStateString() : '-' }}
            </p>
        </div>

        {{-- ตรวจสอบว่ามีข้อมูลใน trush_tracks หรือไม่ --}}
        @if(method_exists($visit, 'trush_tracks') && count($visit->trush_tracks()) > 0)
        <div class="section-box">
            <div class="section-title">การติดตามคู่สัมผัส (Contact Tracing)</div>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th style="border: 1px solid #000; padding: 5px;">ชื่อ-นามสกุล</th>
                        <th style="border: 1px solid #000; padding: 5px;">สถานะการติดตาม</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visit->trush_tracks() as $track)
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px;">{{ $track->name }}</td>
                        <td style="border: 1px solid #000; padding: 5px;">
                            {{ isset(App\Models\Visit::$touchTracingOption[$track->touchTracing]) 
                                ? App\Models\Visit::$touchTracingOption[$track->touchTracing] 
                                : $track->touchTracing }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section-box">
            <div class="section-title">การรักษาและการสั่งยา (Treatment & Rx)</div>
            <p><span class="label">คำแนะนำ:</span> {{ $visit->treatment ?? '-' }}</p>
            
            {{-- ตรวจสอบว่ามีรายการยาใน visitMedicines หรือไม่ --}}
            @if(isset($visit->visitMedicines) && $visit->visitMedicines->count() > 0)
            
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="background-color: #f0f0f0;">
                        <th style="border: 1px solid #000; padding: 5px;">ชื่อยา</th>
                        <th style="border: 1px solid #000; padding: 5px; width: 15%; text-align: center;">จำนวน</th>
                        <th style="border: 1px solid #000; padding: 5px;">วิธีใช้</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visit->visitMedicines as $item)
                    <tr>
                        <td style="border: 1px solid #000; padding: 5px;">
                            {{-- ดึงชื่อยาจาก relation medicine --}}
                            {{ $item->medicine->name ?? 'ไม่พบชื่อยา (ID: '.$item->medicine_id.')' }}
                        </td>
                        
                        <td style="border: 1px solid #000; padding: 5px; text-align: center;">
                            {{ $item->amount ?? '-' }}
                        </td>
                        
                        <td style="border: 1px solid #000; padding: 5px;">
                            {{-- นำ Dose และ Route มาแสดงคู่กัน --}}
                            {{-- dose/route เดิมพิมพ์เสมอ (มีขนาดยา เช่น 500 mg) — วิธีใช้แบบเลือกต่อท้ายบรรทัดใหม่ (รีวิว 2569-10-01) --}}
                            {{ $item->dose ?? '' }} {{ $item->route ?? '' }}
                            @if($item->sigText() !== '')
                                <div style="font-size: .9em;">{{ $item->sigText() }}</div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
                <p style="margin-top: 10px; font-style: italic;">- ไม่มีรายการยา -</p>
            @endif
        </div>

        @if($visit->appointment)
        <div class="section-box">
            <div class="row">
                <div class="col-6"><span class="label">วันนัดครั้งต่อไป:</span> {{ $visit->appointment }}</div>
                <div class="col-6"><span class="label">เหตุผลการนัด:</span> {{ $visit->appointment_reason }}</div>
            </div>
        </div>
        @endif

        <div class="footer-sign">
            <div class="sign-box">
                <br><br>
                __________________________<br><br>
                ( ........................................ )<br>
                แพทย์ผู้ตรวจ / ผู้บันทึก
            </div>
        </div>

    </div>

    <script>
    (function() {
        // ข้อมูล Marks จาก Database
        const rawData = @json($visit->body_check_marks ?? null);
        if (!rawData) return;

        let marksData = [];
        try {
            if (Array.isArray(rawData)) {
                marksData = rawData;
            } else if (typeof rawData === 'object') {
                // Support Legacy Format
                if(rawData.card1) rawData.card1.forEach(m => marksData.push({...m, card: 'card1'}));
                if(rawData.card2) rawData.card2.forEach(m => marksData.push({...m, card: 'card2'}));
            }
        } catch(e) { console.error(e); }

        function drawOnCanvas(canvasId, cardKey, imgSrc) {
            const canvas = document.getElementById(canvasId);
            if(!canvas) return;
            const ctx = canvas.getContext('2d');
            const img = new Image();
            img.src = imgSrc;

            img.onload = () => {
                // Resize canvas to match image ratio
                const ratio = img.naturalHeight / img.naturalWidth;
                canvas.height = canvas.width * ratio;
                
                // Draw BG
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

                // Filter Marks for this card
                const marks = marksData.filter(m => (m.card || 'card1') === cardKey);
                
                marks.forEach(mark => {
                    if (mark.type === 'point') {
                        const cx = mark.x * canvas.width;
                        const cy = mark.y * canvas.height;
                        ctx.beginPath();
                        ctx.arc(cx, cy, mark.size || 7, 0, Math.PI * 2);
                        ctx.fillStyle = mark.color || '#e63946';
                        ctx.fill();
                        // Note text
                        if (mark.note) {
                            ctx.font = '12px Sarabun';
                            ctx.fillStyle = 'red';
                            ctx.fillText(mark.note, cx + 10, cy);
                        }
                    } else if (mark.type === 'path') {
                        ctx.beginPath();
                        ctx.moveTo(mark.points[0][0] * canvas.width, mark.points[0][1] * canvas.height);
                        for (let i = 1; i < mark.points.length; i++) {
                            ctx.lineTo(mark.points[i][0] * canvas.width, mark.points[i][1] * canvas.height);
                        }
                        ctx.strokeStyle = mark.color;
                        ctx.lineWidth = mark.size || 4;
                        ctx.stroke();
                    }
                });
            };
        }

        // เรียกฟังก์ชันวาด (ตรวจสอบ Path รูปภาพให้ถูกต้องตามโปรเจคจริงของคุณ)
        drawOnCanvas('printCanvas_card1', 'card1', '{{ asset("images/visit_cards/card.png") }}');
        drawOnCanvas('printCanvas_card2', 'card2', '{{ asset("images/visit_cards/card2.png") }}');
    })();
    </script>

</body>
</html>