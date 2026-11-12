{{-- ป้ายประกาศ PDPA ติดหน้าคลินิก (2569-10-29) — A4 แนวตั้ง หน้า 1 ไทย · หน้า 2 อังกฤษ (TMC)
     ฉบับย่อของ resources/privacy_notice.php — แก้ประกาศฉบับเต็มแล้วให้ทวนป้ายนี้ด้วย · ไม่มีข้อมูลคนไข้ เปิดได้โดยไม่ต้อง login
     ?lang=th / ?lang=en = พิมพ์เฉพาะภาษานั้น --}}
@php
    $L = [
        'th' => [
            'h1'     => 'การคุ้มครองข้อมูลส่วนบุคคลของผู้รับบริการ',
            'org'    => 'ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่',
            'clinics'=> 'คลินิกโรคติดต่อทางเพศสัมพันธ์ · คลินิกวัณโรค · คลินิกเวชศาสตร์การเดินทางและท่องเที่ยว',
            'lead'   => 'ศูนย์ฯ เก็บและใช้ข้อมูลของท่านเท่าที่จำเป็นต่อการดูแลรักษา และรักษาเป็นความลับตาม พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล พ.ศ. 2562',
            'promise'=> 'ข้อมูลของท่านจะไม่ถูกเปิดเผยให้คู่นอน ครอบครัว นายจ้าง หรือสถานศึกษา เว้นแต่ท่านยินยอมหรือกฎหมายกำหนด',
            'collectH' => 'ข้อมูลที่เราเก็บ',
            'collect'  => ['ชื่อ-สกุล วันเกิด เลขบัตรประชาชน / หนังสือเดินทาง ที่อยู่ เบอร์โทร',
                           'ข้อมูลสุขภาพ ประวัติ พฤติกรรมเสี่ยง ผลตรวจ (รวมถึงผลเอชไอวี) การวินิจฉัย ยา วัคซีน',
                           'ข้อมูลคู่สัมผัสที่ท่านให้ไว้'],
            'useH'   => 'เราใช้ข้อมูลเพื่อ',
            'use'    => ['ตรวจ วินิจฉัย รักษา ให้วัคซีน จ่ายยา ส่งต่อ', 'นัดหมาย ติดตามการรักษา คิดค่าบริการ',
                         'รายงานโรคติดต่อตามที่กฎหมายกำหนด', 'ทำสถิติแบบไม่ระบุตัวบุคคล'],
            'useNote'=> 'เรื่องเหล่านี้ไม่ต้องขอความยินยอม เพราะกฎหมายให้ทำได้เพื่อการรักษา',
            'choiceH'=> 'เรื่องที่ท่านเลือกได้ — เจ้าหน้าที่จะสอบถามท่านทีละเรื่อง',
            'choice' => [['รับข้อความ LINE', 'แจ้งนัด เตือนนัด และแจ้งให้มาฟังผล (ข้อความไม่ระบุชื่อโรคหรือผลตรวจ)'],
                         ['เก็บรูปถ่ายจากบัตรประชาชน', 'ไว้ยืนยันตัวตน ป้องกันการรักษาผิดคน'],
                         ['ให้ศูนย์ฯ ติดต่อคู่สัมผัส', 'เพื่อแนะนำให้มาตรวจ โดยไม่เปิดเผยชื่อของท่าน'],
                         ['เข้าร่วมงานวิจัย', 'มีเอกสารชี้แจงแยกของแต่ละโครงการ']],
            'choiceNote' => 'ไม่ยินยอมก็ยังได้รับการรักษาตามปกติ · เปลี่ยนใจหรือถอนความยินยอมได้ทุกเมื่อ แจ้งที่จุดลงทะเบียน',
            'minorH' => 'ผู้รับบริการอายุต่ำกว่า 20 ปี',
            'minor'  => 'เรื่องที่ต้องขอความยินยอม (4 ข้อด้านบน) ต้องให้ผู้ปกครองเป็นผู้ยินยอม ตามนโยบายกรมควบคุมโรค',
            'minorB' => 'ส่วนการตรวจรักษา รับบริการได้ตามปกติ',
            'keepH'  => 'การเก็บรักษาและสิทธิของท่าน',
            'keep'   => 'เก็บเวชระเบียน 5 ปีนับจากมารับบริการครั้งสุดท้าย แล้วทำลาย · ท่านขอดู ขอสำเนา ขอแก้ไข หรือขอให้ระงับ / ลบข้อมูลได้ตามที่กฎหมายกำหนด',
            'contactH' => 'สอบถาม / ขอใช้สิทธิ',
            'c1'     => ['ศูนย์บริการเวชศาสตร์ป้องกัน สคร.1', 'โทร 053-140774 ต่อ 600-604 · clinicdpc1cm@gmail.com'],
            'c2'     => ['DPO กรมควบคุมโรค', 'โทร 0-2590-3926 · dpo@ddc.mail.go.th'],
            'full'   => 'ขออ่านประกาศความเป็นส่วนตัวฉบับเต็มได้ที่จุดลงทะเบียน',
            'qr'     => 'สแกนอ่านประกาศ PDPA ของกรมฯ',
            'ref'    => 'อ้างอิง: นโยบายการคุ้มครองข้อมูลส่วนบุคคล กรมควบคุมโรค (17 ก.พ. 2568)',
            'ver'    => 'ประกาศฉบับที่',
        ],
        'en' => [
            'h1'     => 'How We Protect Your Personal Data',
            'org'    => 'Preventive Medicine Service Center, Office of Disease Prevention and Control Region 1 Chiang Mai',
            'clinics'=> 'STI Clinic · Tuberculosis Clinic · Travel Medicine Clinic',
            'lead'   => 'We collect and use your data only as needed for your care, and keep it confidential under the Personal Data Protection Act B.E. 2562 (2019).',
            'promise'=> 'We never share your data with your partners, family, employer or school, unless you consent or the law requires it.',
            'collectH' => 'What we collect',
            'collect'  => ['Name, date of birth, ID / passport number, address, phone',
                           'Health data: history, risk behaviour, test results (including HIV), diagnosis, medicines, vaccines',
                           'Information about partners that you give us'],
            'useH'   => 'What we use it for',
            'use'    => ['Examination, diagnosis, treatment, vaccination, medicines, referral', 'Appointments, follow-up and billing',
                         'Communicable-disease reporting required by law', 'Anonymous statistics'],
            'useNote'=> 'These do not need your consent — the law allows them for your care.',
            'choiceH'=> 'Your choices — staff will ask you about each one',
            'choice' => [['LINE messages', 'appointment reminders and result notices (never naming a disease or result)'],
                         ['ID-card photo', 'kept in your record to confirm your identity'],
                         ['Contacting your partners', 'to advise them to get tested, without revealing your name'],
                         ['Research', 'each project has its own information sheet']],
            'choiceNote' => 'Saying no does not affect your treatment · You can change your mind at any time at the registration desk',
            'minorH' => 'Patients under 20',
            'minor'  => 'For the four choices above, a parent or guardian must give consent, as required by the Department of Disease Control.',
            'minorB' => 'Examination and treatment are provided as normal.',
            'keepH'  => 'Retention and your rights',
            'keep'   => 'Medical records are kept for 5 years after your last visit, then destroyed. You may ask to see, copy, correct, restrict or delete your data as the law allows.',
            'contactH' => 'Questions / your rights',
            'c1'     => ['Preventive Medicine Service Center, ODPC 1', 'Tel. +66 53 140 774 ext. 600-604 · clinicdpc1cm@gmail.com'],
            'c2'     => ['DDC Data Protection Officer', 'Tel. +66 2590 3926 · dpo@ddc.mail.go.th'],
            'full'   => 'The full privacy notice is available at the registration desk.',
            'qr'     => 'Scan for DDC privacy notices',
            'ref'    => 'Ref.: Department of Disease Control Personal Data Protection Policy (17 Feb 2025)',
            'ver'    => 'Notice version',
        ],
    ];
    $only = in_array(request('lang'), ['th', 'en'], true) ? [request('lang')] : ['th', 'en'];
@endphp
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ป้ายประกาศ PDPA · สคร.1</title>
<style>
    @font-face { font-family: 'Sarabun'; src: url('{{ asset('fonts/sarabun/Sarabun-Regular.ttf') }}') format('truetype'); font-weight: 400; }
    @font-face { font-family: 'Sarabun'; src: url('{{ asset('fonts/sarabun/Sarabun-Bold.ttf') }}') format('truetype'); font-weight: 700; }
    :root { --s:1; --navy:#1e3a8a; --ink:#0f172a; --muted:#475569; --line:#cbd5e1; --ok:#166534; --okbg:#f0fdf4; --soft:#eff6ff; }
    * { box-sizing: border-box; }
    html, body { margin: 0; background: #e5e7eb; color: var(--ink); font-family: 'Sarabun', sans-serif; }
    .page { width: 210mm; height: 297mm; margin: 12px auto; background: #fff; padding: 11mm 15mm 8mm;
            display: flex; flex-direction: column; gap: 4mm; overflow: hidden; page-break-after: always; break-after: page; }
    .page:last-of-type { page-break-after: auto; break-after: auto; }
    .head { display: flex; align-items: center; gap: 6mm; border-bottom: 1.2mm solid var(--navy); padding-bottom: 4mm; }
    .head img { height: 25mm; }
    .head h1 { margin: 0 0 1.5mm; font-size: calc(22pt * var(--s)); line-height: 1.15; color: var(--navy); }
    .head .sub { font-size: calc(11.5pt * var(--s)); color: var(--muted); line-height: 1.45; }
    .lead { font-size: calc(12.5pt * var(--s)); line-height: 1.5; margin: 0; }
    .promise { margin: 0; font-size: calc(13pt * var(--s)); font-weight: 700; line-height: 1.45; padding: 2.5mm 4mm; border-left: 1.5mm solid var(--navy); background: var(--soft); }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; }
    .box { border: 0.35mm solid var(--line); border-radius: 2.5mm; padding: 3mm 4.5mm; }
    .box h2 { margin: 0 0 2mm; font-size: calc(14pt * var(--s)); color: var(--navy); }
    .box p, .box li { font-size: calc(12pt * var(--s)); line-height: 1.42; margin: 0; }
    .box ul { margin: 0; padding-left: 5mm; display: grid; gap: 0.6mm; }
    .muted { color: var(--muted); font-size: calc(10.5pt * var(--s)) !important; margin-top: 2mm !important; }
    .choice { background: var(--okbg); border-color: #86efac; }
    .choice h2 { color: var(--ok); }
    .choice ol { margin: 0; padding-left: 5.5mm; display: grid; gap: 0.8mm; }
    .choice li { font-size: calc(12.5pt * var(--s)); line-height: 1.4; }
    .note { font-weight: 700; font-size: calc(12.5pt * var(--s)) !important; margin-top: 2.5mm !important; }
    .minor { background: #fef2f2; border-color: #fca5a5; }
    .minor h2 { color: #991b1b; }
    .contact { background: var(--soft); border-color: #bfdbfe; display: flex; gap: 5mm; align-items: center; }
    .contact .rows { flex: 1; display: grid; gap: 1mm; }
    /* QR รวมประกาศ PDPA ของกรม (จาก กองโรคจากการประกอบอาชีพและสิ่งแวดล้อม 2569-10-29) */
    .contact .qr img { width: 28mm; height: 28mm; display: block; }
    .contact .qrcap { font-size: calc(8.5pt * var(--s)); color: var(--muted); text-align: center; margin-top: 1mm; max-width: 28mm; line-height: 1.3; }
    .foot { margin-top: auto; font-size: calc(8.5pt * var(--s)); color: var(--muted); display: flex; justify-content: space-between; border-top: 0.3mm solid var(--line); padding-top: 2mm; }
    .toolbar { text-align: center; margin: 12px; }
    .toolbar a, .toolbar button { font: inherit; font-size: 14px; padding: 6px 14px; cursor: pointer; margin: 0 3px; }
    @page { size: A4 portrait; margin: 0; }
    @media print {
        html, body { background: #fff; }
        .page { margin: 0; }
        .toolbar { display: none; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>
</head>
<body>
<div class="toolbar">
    <button onclick="window.print()">🖨️ พิมพ์</button>
    <a href="?">ไทย + อังกฤษ</a> · <a href="?lang=th">เฉพาะไทย</a> · <a href="?lang=en">English only</a>
</div>
@foreach($only as $lg)
    @php $t = $L[$lg]; @endphp
    <div class="page" lang="{{ $lg }}">
        <div class="head">
            <img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt="กรมควบคุมโรค">
            <div>
                <h1>{{ $t['h1'] }}</h1>
                <div class="sub">{{ $t['org'] }}<br>{{ $t['clinics'] }}</div>
            </div>
        </div>

        <p class="lead">{{ $t['lead'] }}</p>
        <p class="promise">{{ $t['promise'] }}</p>

        <div class="grid">
            <div class="box">
                <h2>{{ $t['collectH'] }}</h2>
                <ul>@foreach($t['collect'] as $x)<li>{{ $x }}</li>@endforeach</ul>
            </div>
            <div class="box">
                <h2>{{ $t['useH'] }}</h2>
                <ul>@foreach($t['use'] as $x)<li>{{ $x }}</li>@endforeach</ul>
                <p class="muted">{{ $t['useNote'] }}</p>
            </div>
        </div>

        <div class="box choice">
            <h2>✔ {{ $t['choiceH'] }}</h2>
            <ol>@foreach($t['choice'] as [$b, $x])<li><b>{{ $b }}</b> — {{ $x }}</li>@endforeach</ol>
            <p class="note">{{ $t['choiceNote'] }}</p>
        </div>

        <div class="grid">
            <div class="box minor">
                <h2>{{ $t['minorH'] }}</h2>
                <p>{{ $t['minor'] }} <b>{{ $t['minorB'] }}</b></p>
            </div>
            <div class="box">
                <h2>{{ $t['keepH'] }}</h2>
                <p>{{ $t['keep'] }}</p>
            </div>
        </div>

        <div class="box contact">
            <div class="rows">
                <h2 style="margin:0;">{{ $t['contactH'] }}</h2>
                <p><b>{{ $t['c1'][0] }}</b><br>{{ $t['c1'][1] }}</p>
                <p><b>{{ $t['c2'][0] }}</b><br>{{ $t['c2'][1] }}</p>
                <p>{{ $t['full'] }}</p>
            </div>
            <div>
                <div class="qr"><img src="{{ asset('images/qr-pdpa-ddc.png') }}" alt="QR PDPA กรมควบคุมโรค"></div>
                <div class="qrcap">{{ $t['qr'] }}</div>
            </div>
        </div>

        <div class="foot"><span>{{ $t['ref'] }}</span><span>{{ $t['ver'] }} {{ \App\Models\PatientConsent::NOTICE_VERSION }}</span></div>
    </div>
@endforeach
<script>
// ภาษาไทย/อังกฤษยาวไม่เท่ากัน — ลดขนาดตัวอักษรทั้งหน้าทีละนิดจนพอดี A4 (ไม่ให้ท้ายป้ายถูกตัด) แทนการตั้งขนาดตายตัว
(function () {
    function fit() {
        document.querySelectorAll('.page').forEach(function (pg) {
            var z = 1; pg.style.setProperty('--s', 1);
            while (pg.scrollHeight > pg.clientHeight + 1 && z > 0.7) { z -= 0.02; pg.style.setProperty('--s', z); }
        });
    }
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit); else window.addEventListener('load', fit);
})();
</script>
</body>
</html>
