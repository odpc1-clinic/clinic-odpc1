<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Immunization Certificate</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
    {{-- ตามแบบฟอร์ม Word ที่ TMC ใช้ (2569-11-10): A4 เต็มหน้า · ตารางวัคซีน × เข็มที่ · แพทย์ลงนาม
         ช่องที่มีเส้นประใต้ = คลิกแก้บนหน้าจอได้ก่อนพิมพ์ (ไม่บันทึกลงระบบ) --}}
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', sans-serif; margin: 0; background: #e5e7eb; color: #000; font-size: 11pt; }
        .page { width: 210mm; min-height: 297mm; margin: 0 auto 10mm; background: #fff; padding: 14mm 22mm 14mm 25mm; position: relative; }

        .logo { text-align: center; }
        .logo img { width: 30mm; height: auto; }
        .title { text-align: center; font-size: 16pt; font-weight: 700; letter-spacing: .5px; margin-top: 2mm; }
        .org { text-align: center; line-height: 1.35; }
        .issue { text-align: right; margin-top: 6mm; }

        p { margin: 0; line-height: 1.6; }
        .greet { margin-top: 4mm; }
        /* ชื่อ/พาสปอร์ต/วันเกิด: ตัวปกติ ขีดเส้นใต้ · ทุกบรรทัดชิดซ้าย (TMC ขอ 2569-11-10) */
        .who { text-decoration: underline; }
        .pp { display: flex; gap: 14mm; }
        .pp u { margin-left: 3mm; }

        table.vac { width: 100%; border-collapse: collapse; margin-top: 4mm; }
        table.vac th, table.vac td { border: 1px solid #000; padding: 1.5mm 2.5mm; vertical-align: middle; }
        table.vac th { font-weight: 700; text-align: center; }
        table.vac td.d { text-align: center; white-space: nowrap; }
        table.vac td.empty { text-align: center; color: #555; }
        /* เข็มที่ไม่มี = ช่องเทาทึบ (แทนขีด -) · ต้องสั่งให้พิมพ์สีพื้นด้วย ไม่งั้นเบราว์เซอร์ตัดทิ้งตอนพิมพ์ */
        table.vac td.na { background: #808080; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .foot { font-size: 9.5pt; margin-top: 2mm; line-height: 1.4; }
        .remark { margin-top: 4mm; min-height: 8mm; white-space: pre-wrap; }

        .sign { margin-top: 14mm; margin-left: auto; width: 85mm; text-align: center; line-height: 1.5; }
        .sign .line { border-bottom: 1px dotted #000; height: 12mm; margin: 0 auto 1mm; width: 55mm; }   /* เส้นเซ็นสั้นลง (TMC ขอ 2569-11-10) */

        [contenteditable="true"] { outline: none; border-bottom: 1px dashed #93c5fd; }
        [contenteditable="true"]:focus { background: #eff6ff; }
        .remark:empty::before { content: attr(data-ph); color: #9ca3af; }

        .bar { position: sticky; top: 0; z-index: 5; background: #fff3cd; border-bottom: 1px solid #ffe08a; padding: 8px 14px;
               display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 12pt; margin-bottom: 10px; }
        .bar select { font: inherit; padding: 2px 6px; }
        .bar button, .bar a { font: inherit; font-size: 11.5pt; padding: 6px 16px; border: 0; border-radius: 6px; cursor: pointer; text-decoration: none; color: #fff; }
        .bar .print { background: #2563eb; }
        .bar .back { background: #6b7280; }
        .bar .warn { color: #b91c1c; }
        .bar .hint { color: #555; font-size: 10.5pt; }

        @media print {
            body { background: #fff; }
            .bar { display: none; }
            .page { margin: 0; min-height: auto; }
            [contenteditable="true"] { border-bottom: 0; }
            .remark:empty { display: none; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
<body>

<div class="bar">
    <button class="print" onclick="window.print()">🖨️ พิมพ์ใบรับรอง</button>
    {{-- วัคซีนเยอะจนล้นหน้า / อยากแก้ถ้อยคำ → โหลดเป็น Word ไปแก้ต่อ (ใช้แพทย์ที่เลือกในช่องข้าง ๆ) --}}
    <a class="print" id="doc-word" style="background:#1d4ed8;" href="{{ route('visit-vaccine.certificateDocx', $visit->id) }}"
       title="ดาวน์โหลดเป็นไฟล์ Word ไปแก้ไขต่อ (สิ่งที่แก้บนหน้าจอนี้ไม่ติดไปด้วย)">⬇ ดาวน์โหลด Word</a>
    <a class="back" href="{{ route('visit.edit', ['visit' => $visit->id, 'page' => request('page', 2)]) }}">ย้อนกลับ</a>
    <b>แพทย์ผู้ลงนาม:</b>
    {{-- เลือกแพทย์ได้ทุกคน · ค่าเริ่มต้น พญ.นิศาชล · ตำแหน่ง Travel Medicine Physician เฉพาะท่าน ท่านอื่นเป็นจุดให้เขียน (TMC 2569-11-10) --}}
    <select id="doc-select">
        @foreach($doctors as $d)
            <option value="{{ $d['id'] }}" {{ $docId === $d['id'] ? 'selected' : '' }}>{{ $d['name'] }}</option>
        @endforeach
        <option value="" {{ $docId ? '' : 'selected' }}>แพทย์ท่านอื่น — เว้นให้กรอกเอง</option>
    </select>
    <span class="warn" id="doc-warn"></span>
    @unless($hasEn)
        <span class="warn">คนไข้ยังไม่มีชื่อภาษาอังกฤษในทะเบียน — แก้ในใบได้ (คลิกที่ชื่อ) หรือเพิ่มในหน้าลงทะเบียน</span>
    @endunless
    <span class="hint">ช่องเส้นประสีฟ้า คลิกแก้ก่อนพิมพ์ได้ (ไม่บันทึกลงระบบ)</span>
</div>

<div class="page">
    <div class="logo"><img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt=""></div>
    <div class="title">IMMUNIZATION CERTIFICATE</div>
    <div class="org">
        Preventive Medicine Service Center<br>
        Office of Disease Prevention and Control region 1, Chiangmai, Thailand
    </div>

    {{-- เว้นวันที่ไว้ — เจ้าหน้าที่ปั๊มวันที่เองตอนคนไข้มารับใบ (TMC ขอ 2569-11-10) · คลิกพิมพ์เองได้ถ้าต้องการ --}}
    <div class="issue">Issue date <span contenteditable="true">..........................</span></div>

    <p class="greet">To whom it may concern,</p>
    <p>This is the verified immunization record certifying that
        <span class="who" contenteditable="true">{{ $nameEn ?: '..............................' }}</span></p>
    <div class="pp">
        <span>Passport no <u contenteditable="true">{{ $patient->passport ?: ($patient->id_card_number ?: '-') }}</u></span>
        <span>date of birth <u contenteditable="true">{{ $dobEn ?: '-' }}</u></span>
    </div>
    <p>has received vaccines and immunization status has been updated as follows:</p>

    @php $ord = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th')); @endphp
    <table class="vac">
        <thead>
            <tr>
                <th rowspan="2" style="width:38%;">Vaccine</th>
                <th colspan="{{ $doseCols }}">Date Given</th>
            </tr>
            <tr>
                @for($i = 1; $i <= $doseCols; $i++)<th style="width:{{ round(62 / $doseCols, 2) }}%;">{{ $ord($i) }}</th>@endfor
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>{{ $r['name'] }}</td>
                    @for($i = 1; $i <= $doseCols; $i++)
                        @if(isset($r['doses'][$i]))
                            <td class="d">{{ $r['doses'][$i]['date'] }}{{ $r['doses'][$i]['mark'] }}</td>
                        @else
                            <td class="d na"></td>
                        @endif
                    @endfor
                </tr>
            @empty
                <tr><td colspan="{{ $doseCols + 1 }}" class="empty">— No vaccination recorded —</td></tr>
            @endforelse
        </tbody>
    </table>

    @if($hasHome)
        <div class="foot">
            @if($hasHome)<div>† Oral vaccine supplied by this center, self-administered.</div>@endif
        </div>
    @endif

    {{-- หมายเหตุอิสระ เช่น ผลภูมิคุ้มกัน "Varicella Zoster IgG On 05 May 2026 = Positive (1.79)" · ว่าง = ไม่พิมพ์ --}}
    <div class="remark" contenteditable="true" data-ph="หมายเหตุ (ถ้ามี) เช่น Varicella Zoster IgG On 05 May 2026 = Positive (1.79) — ว่างไว้ = ไม่พิมพ์"></div>

    <div class="sign">
        <div class="line"></div>
        (<span id="doc-name" contenteditable="true"></span>)<br>
        License No.<span id="doc-license" contenteditable="true"></span><br>
        {{-- ตำแหน่ง: Travel Medicine Physician เฉพาะ พญ.นิศาชล · แพทย์ท่านอื่นเป็นจุดให้เขียนเอง --}}
        <span id="doc-title" contenteditable="true"></span>
    </div>
</div>

<script>
(function () {
    var docs = @json($doctors);
    var sel = document.getElementById('doc-select');
    var warn = document.getElementById('doc-warn');
    function apply() {
        var d = docs.filter(function (x) { return String(x.id) === sel.value; })[0];
        // ไม่ได้เลือก / ข้อมูลไหนไม่มี = จุดไข่ปลาให้เขียนเอง (ชื่อ · เลขใบอนุญาต · ตำแหน่ง)
        var DOTS = '........................................';
        var name = DOTS, lic = '....................', title = DOTS, w = '';
        if (d) {
            if (d.name_en) { name = /M\.?D\.?/i.test(d.name_en) ? d.name_en : d.name_en + ', M.D.'; }
            else { w = 'แพทย์ท่านนี้ยังไม่มีชื่อภาษาอังกฤษในระบบ — เว้นจุดไว้ให้เขียน หรือตั้งที่หน้าจัดการผู้ใช้'; }
            lic = d.license || lic;
            title = d.title || DOTS;   // Travel Medicine Physician เฉพาะ พญ.นิศาชล · ท่านอื่นเขียนตำแหน่งเอง
        }
        document.getElementById('doc-name').textContent = name;
        document.getElementById('doc-license').textContent = lic;
        document.getElementById('doc-title').textContent = title;
        warn.textContent = w;
        var dl = document.getElementById('doc-word');
        dl.href = dl.href.split('?')[0] + (sel.value ? '?doctor=' + encodeURIComponent(sel.value) : '');
    }
    sel.addEventListener('change', apply);
    apply();
})();
</script>
</body>
</html>
