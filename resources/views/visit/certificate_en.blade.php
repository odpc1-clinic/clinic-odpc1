<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Medical Certificate</title>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: 'Times New Roman', 'THSarabunNew', serif;
            display: flex; justify-content: center; align-items: flex-start;
            background: #f0f0f0;
        }
        .a4-full { width: 210mm; height: 297mm; background: #fff; padding: 5mm; box-sizing: border-box; font-size: 13pt; }
        .a4-frame { width: 100%; height: 100%; border: 1px solid #000; box-sizing: border-box;
                    display: flex; flex-direction: column; padding: 14px 18px; }
        .content-area { flex: 1; }
        .footer-area { margin-top: auto; border-top: 1px solid #666; padding-top: 6px; font-size: 11pt; text-align: center; line-height: 1.4; }

        .head { text-align: center; line-height: 1.35; }
        .head .th { font-size: 14pt; font-weight: bold; }
        .head .en { font-size: 12pt; font-weight: bold; }
        .cert-title { border: 1px solid #666; text-align: center; font-size: 17pt; font-weight: bold;
                      padding: 7px 0; margin: 16px 6px 6px; letter-spacing: .5px; }

        .row  { font-size: 13pt; line-height: 1.7; margin-left: 6px; }
        .lbl  { font-weight: bold; }
        .fill { border-bottom: 1px dotted #000; display: inline-block; min-height: 16px; padding: 0 6px; }
        .section-lbl { font-weight: bold; margin: 14px 0 2px 6px; font-size: 13pt; }
        .para { margin: 0 6px; font-size: 13pt; line-height: 1.9;
                text-decoration: underline dotted 1px; text-underline-offset: 7px; min-height: 44px; }

        .sign-wrap { margin-top: 54px; text-align: right; padding-right: 12px; }
        .sign-box { width: 260px; margin-left: auto; text-align: center; position: relative; }
        .sign-line { border-bottom: 1px dotted #000; height: 24px; margin-bottom: 6px; }
        /* ลายน้ำตราหน่วยงานหลังลายเซ็น — จาง ไม่บังชื่อ */
        .sign-wm { position: absolute; left: 50%; top: -18px; transform: translateX(-50%);
                   width: 130px; height: auto; opacity: .30; pointer-events: none; }

        .editable { display: inline-block; cursor: text; min-width: 20px; min-height: 18px;
                    outline: 1px dashed transparent; transition: background-color .2s, outline .2s; }
        .editable:hover, .editable:focus { background: #e6f7ff; outline: 1px dashed #1890ff; border-radius: 3px; }

        .hint-box { position: fixed; top: 20px; left: 20px; background: #fff3cd; color: #856404;
                    padding: 10px 15px; border-radius: 5px; border: 1px solid #ffeeba; z-index: 999; font-size: 11pt; }
        .print-btn-wrap { position: fixed; top: 20px; right: 20px; z-index: 999; }
        .print-btn { background: #1976d2; color: #fff; border: 0; padding: 10px 20px; font-size: 14pt;
                     border-radius: 5px; cursor: pointer; box-shadow: 0 2px 4px rgba(0,0,0,.2); }

        @media print {
            .print-btn-wrap, .hint-box { display: none !important; }
            .editable, .editable:hover, .editable:focus { background: transparent !important; outline: none !important; }
            body { margin: 0; padding: 0; background: none; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            /* ขอบกระดาษอยู่ในใบเอง — ใบสูง 297mm เต็ม ถ้ามีขอบ @page อีกจะล้นเป็น 2 หน้า (บั๊ก 2569-10-29) */
            @page { size: A4 portrait; margin: 0; }
            .a4-full { padding: 10mm 12mm 14mm 14mm; }
        }
    </style>
</head>

@php
    // วันที่ออก (สากล) และวันที่ตรวจ — visits.date เก็บเป็น พ.ศ. ต้องลบ 543
    $issueDate = now();
    $issueDay   = $issueDate->format('d');
    $issueMonth = $issueDate->format('F');
    $issueYear  = $issueDate->format('Y');

    $examParts = explode('/', $visit->date ?? '');
    $examText = '-';
    if (count($examParts) === 3) {
        $eY = (int) $examParts[2]; if ($eY > 2400) { $eY -= 543; }
        try {
            $examText = \Carbon\Carbon::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $eY, $examParts[1], $examParts[0]))
                        ->format('d F Y');
        } catch (\Throwable $e) { $examText = '-'; }
    }
    if (($t = optional($visit->created_at)) && $t) { $examText .= ' at ' . $t->format('H:i'); }

    // อายุ (จาก birth_date พ.ศ.)
    $ageText = '';
    if (!empty($visit->patient2->birth_date)) {
        $bp = explode('/', $visit->patient2->birth_date);
        if (count($bp) === 3) {
            $bYad = (int) $bp[2] - 543;
            $age = (int) date('Y') - $bYad;
            if (date('m') < $bp[1] || (date('m') == $bp[1] && date('d') < $bp[0])) { $age--; }
            $ageText = " (age {$age})";
        }
    }

    // ชื่ออังกฤษตามพาสปอร์ต ถ้ากรอกไว้ (2569-10-07) ไม่งั้นใช้ชื่อไทยเหมือนเดิม
    $patientFullNameText = ($visit->patient2 ? $visit->patient2->englishFullName() : '') . $ageText;
    $doctorName    = $visit->doctorUser->name ?? '-';
    $licenseNumber = $visit->doctorUser->license_number ?? '-';
    $idCardNumber  = $visit->patient2->passport ?: ($visit->patient2->id_card_number ?? '-');
    $hnCode        = $visit->patient2->code ?? '-';
    $examResult    = trim(($visit->symptom ?? '') . ' ' . ($visit->body_check ?? '')) ?: '-';
    $opinion       = $visit->treatment ?: '-';
    $signatureName = $doctorName;

    // ดูย้อนหลัง = ใช้เนื้อหาที่บันทึกไว้จริง (readonly)
    $readonly = !empty($snapshot);
    if ($readonly) {
        $issueDay      = $snapshot['issueDay']        ?? $issueDay;
        $issueMonth    = $snapshot['issueMonth']      ?? $issueMonth;
        $issueYear     = $snapshot['issueYear']       ?? $issueYear;
        $doctorName    = $snapshot['doctorName']      ?? $doctorName;
        $licenseNumber = $snapshot['licenseNumber']   ?? $licenseNumber;
        $patientFullNameText = $snapshot['patientFullName'] ?? $patientFullNameText;
        $idCardNumber  = $snapshot['idCardNumber']    ?? $idCardNumber;
        $hnCode        = $snapshot['hnCode']          ?? $hnCode;
        $examText      = $snapshot['examDate']        ?? $examText;
        $examResult    = $snapshot['examResult']      ?? $examResult;
        $opinion       = $snapshot['opinion']         ?? $opinion;
        $signatureName = $snapshot['signatureName']   ?? $signatureName;
    }
    $ed = $readonly ? 'false' : 'true';
@endphp

<body>
@if(!$readonly)
    @include('partials.cert_doctor_select')
@endif
    @if($readonly)
        <div class="hint-box">📄 <b>Saved copy</b> — issued {{ $certificate->created_at->format('d/m/Y H:i') }} by {{ $certificate->issuedByUser->name ?? '-' }}</div>
    @else
        <div class="hint-box">💡 <b>Quick-edit:</b> click any text to edit before printing</div>
    @endif

    <div class="a4-full">
        <div class="a4-frame">
            <div class="content-area">

                <div class="head">
                    <div class="th">สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</div>
                    <div class="en">Preventive Medicine Service Center</div>
                    <div class="en">Office of Disease Prevention and Control 1, Chiang Mai, Thailand</div>
                </div>

                <div class="cert-title">MEDICAL CERTIFICATE</div>

                <div style="text-align:right; font-size:13pt; margin:6px 6px 0;">
                    Date of issue
                    <span class="fill" style="width:30px; text-align:center;"><span id="cert_issueDay" class="editable" contenteditable="{{ $ed }}">{{ $issueDay }}</span></span>
                    <span class="fill" style="width:90px; text-align:center;"><span id="cert_issueMonth" class="editable" contenteditable="{{ $ed }}">{{ $issueMonth }}</span></span>
                    <span class="fill" style="width:60px; text-align:center;"><span id="cert_issueYear" class="editable" contenteditable="{{ $ed }}">{{ $issueYear }}</span></span>
                </div>

                <div class="row" style="margin-top:12px;">
                    <span class="lbl">I,</span> Dr.
                    <span class="fill" style="min-width:60%;"><span id="cert_doctorName" class="editable" contenteditable="{{ $ed }}">{{ $doctorName }}</span></span>
                </div>
                <div class="row">
                    <span class="lbl">Medical license number</span>
                    <span class="fill" style="min-width:50%;"><span id="cert_licenseNumber" class="editable" contenteditable="{{ $ed }}">{{ $licenseNumber }}</span></span>
                </div>
                <div class="row">
                    have examined
                    <span class="fill" style="min-width:58%;"><span id="cert_patientFullName" class="editable" contenteditable="{{ $ed }}">{{ $patientFullNameText }}</span></span>
                </div>
                <div class="row">
                    <span class="lbl">Passport / ID number</span>
                    <span class="fill" style="min-width:52%;"><span id="cert_idCardNumber" class="editable" contenteditable="{{ $ed }}">{{ $idCardNumber }}</span></span>
                </div>
                <div class="row">
                    <span class="lbl">Hospital number (HN)</span>
                    <span class="fill" style="min-width:56%;"><span id="cert_hnCode" class="editable" contenteditable="{{ $ed }}">{{ $hnCode }}</span></span>
                </div>
                <div class="row">
                    <span class="lbl">Date of examination</span>
                    <span class="fill" style="min-width:56%;"><span id="cert_examDate" class="editable" contenteditable="{{ $ed }}">{{ $examText }}</span></span>
                </div>

                <div class="section-lbl">Examination result</div>
                <div class="para"><span id="cert_examResult" class="editable" contenteditable="{{ $ed }}">{{ $examResult }}</span></div>

                <div class="section-lbl">Physician’s opinion and recommendation</div>
                <div class="para"><span id="cert_opinion" class="editable" contenteditable="{{ $ed }}">{{ $opinion }}</span></div>

                <div class="sign-wrap">
                    <div class="sign-box">
                        <img src="{{ asset('images/' . rawurlencode('LOGO CM ENG.png')) }}" alt="" class="sign-wm">
                        <div class="sign-line"></div>
                        <div>(&nbsp;<span id="cert_signatureName" class="editable" contenteditable="{{ $ed }}">{{ $signatureName }}</span>&nbsp;)</div>
                        <div style="margin-top:4px;">Physician</div>
                    </div>
                </div>

            </div>

            <div class="footer-area">
                143 Sri-Donchai Rd., Chang Khlan, Mueang, Chiang Mai, Thailand 50100 · Tel. (+66) 53-140774 Ext. 600-604
            </div>
        </div>
    </div>

    <div class="print-btn-wrap">
        <button class="print-btn" id="certPrintBtn"
                onclick="{{ $readonly ? 'window.print()' : 'saveAndPrintCertificate()' }}">
            @if($readonly) 🖨 Print @else 💾🖨 Save &amp; print @endif
        </button>
    </div>

    @if(!$readonly)
    <script>
        function saveAndPrintCertificate() {
            var btn = document.getElementById('certPrintBtn');
            btn.disabled = true;

            var fields = ['issueDay','issueMonth','issueYear','doctorName','licenseNumber','patientFullName','idCardNumber','hnCode','examDate','examResult','opinion','signatureName'];
            var data = { lang: 'en' };   // จำภาษาไว้ ให้ดูย้อนหลังถูกฉบับ (ดู viewMedicalCertificate)
            fields.forEach(function (key) {
                var el = document.getElementById('cert_' + key);
                data[key] = el ? el.innerText.trim() : '';
            });

            var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch("{{ route('visit.medicalCertificate.store', $visit->id) }}", {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ data: data })
            })
            .catch(function () {})
            .finally(function () { btn.disabled = false; window.print(); });
        }
    </script>
    @endif
</body>
</html>
