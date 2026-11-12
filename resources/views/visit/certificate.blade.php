<!DOCTYPE html>
<html lang="th">
<head>
  	<meta charset="UTF-8">
  	<meta name="csrf-token" content="{{ csrf_token() }}">
  	<title>ใบรับรองแพทย์ (MEDICAL CERTIFICATE)</title>
	<style>
    /* ฝังฟอนต์โดยใช้ asset() ของ Laravel เพื่อให้ Path ถูกต้อง 100% ทุกลิงก์ */
    /* ฟอนต์ตัวธรรมดา (ใช้ woff/woff2 ที่มีอยู่เดิมได้เลย) */
    @font-face {
        font-family: 'THSarabunNew';
        src: url('{{ asset("fonts/sarabun/THSarabunNew.ttf") }}') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    
    /* ฟอนต์ตัวหนา (ดึงไฟล์ .ttf ที่คุณกานเพิ่งนำมาใส่) */
    @font-face {
        font-family: 'THSarabunNew';
        src: url('{{ asset("fonts/sarabun/THSarabunNew Bold.ttf") }}') format('truetype');
        font-weight: bold;
        font-style: normal;
    }
    body {
        margin: 0;
        padding: 0;
        /* เปลี่ยนชื่อฟอนต์ให้ตรงกับที่ตั้งไว้ด้านบน */
        font-family: 'THSarabunNew', sans-serif; 
        display: flex;
        justify-content: center;
        align-items: flex-start;
        background-color: #f0f0f0;
    }
		.a4-full {
			width: 210mm;
			height: 297mm;
			background: white;
			padding: 5mm;
			box-sizing: border-box;
			font-size: 14pt;
		}
		.a4-frame {
			width: 100%;
			height: 100%;
			border: 1px solid #000;
			box-sizing: border-box;
			display: flex;
			flex-direction: column;
			padding: 10px 12px 10px 12px;
		}
		.content-area {
			flex: 1;
		}
		.footer-area {
			margin-top: auto;
			border-top: 1px solid #666;
			padding-top: 6px;
			font-size: 14pt;
			line-height: 1.5;
			text-align: center;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			table-layout: fixed;
		}
		th, td {
			border: none;
			word-wrap: break-word;
			vertical-align: top;
		}
		
		/* --- ส่วนที่เพิ่มใหม่: ตกแต่งช่องที่สามารถแก้ไขได้ --- */
		.editable {
			display: inline-block;
			cursor: text;
			min-width: 20px;
			min-height: 20px;
			transition: background-color 0.2s, outline 0.2s;
			outline: 1px dashed transparent;
		}
		.editable:hover, .editable:focus {
			background-color: #e6f7ff;
			outline: 1px dashed #1890ff;
			border-radius: 3px;
		}
		.hint-box {
			position: fixed;
			top: 20px;
			left: 20px;
			background: #fff3cd;
			color: #856404;
			padding: 10px 15px;
			border-radius: 5px;
			border: 1px solid #ffeeba;
			box-shadow: 0 2px 4px rgba(0,0,0,0.1);
			z-index: 999;
			font-size: 12pt;
		}

		.print-btn-wrap {
			position: fixed;
			top: 20px;
			right: 20px;
			z-index: 999;
		}

		/* สำหรับการพิมพ์ */
		@media print {
			.print-btn-wrap, .hint-box {
				display: none !important;
			}
			.editable:hover, .editable:focus, .editable {
				background-color: transparent !important;
				outline: none !important;
			}
			body {
				margin: 0;
				padding: 0;
				background: none;
			}
			.a4-full {
				width: 210mm;
				height: 297mm;
				background: white;
				box-sizing: border-box;
				font-size: 14pt;
				margin: 0;
				/* ขอบกระดาษย้ายมาไว้ในใบเอง (@page margin 0) — ใบสูง 297mm เต็ม ถ้ามีขอบ @page อีกจะล้นเป็น 2 หน้า (บั๊ก 2569-10-29) */
				padding: 10mm 10mm 15mm 15mm;
			}
			.a4-frame {
				width: 100%;
				height: 100%;
				border: 1px solid #000;
				box-sizing: border-box;
				display: flex;
				flex-direction: column;
				padding: 10px 12px 10px 12px;
			}
			.content-area {
				flex: 1;
			}
			.footer-area {
				margin-top: auto;
				border-top: 1px solid #666;
				padding-top: 6px;
				font-size: 14pt;
				line-height: 1.5;
				text-align: center;
			}
			.no-print-border,
			.no-print-border td,
			.no-print-border th {
				border: none !important;
			}
			table, th, td {
				border: none !important;
				border-collapse: collapse !important;
			}
			* {
				-webkit-print-color-adjust: exact;
				print-color-adjust: exact;
			}
			button {
				display: none !important;
			}
			@page {
				size: A4 portrait;
				margin: 0 !important;
			}
		}
	</style>
</head>

@php
    // คำนวณตัวแปรวันที่และอายุ
    $thaiMonths = \App\Utillity::TH_MONTH_SHORT;
    
    // วันที่ออกใบรับรอง (ปัจจุบัน)
    $issueDate = now();
    $issueDay = $issueDate->format('d');
    $issueMonth = $thaiMonths[$issueDate->format('n')];
    $issueYear = $issueDate->format('Y') + 543;

    // วันที่ตรวจ
    $examDateStr = $visit->date ?? date('d/m/Y');
    $examParts = explode('/', $examDateStr);
    $examDay = $examParts[0] ?? '-';
    $examMonthNum = isset($examParts[1]) ? (int)$examParts[1] : 0;
    $examMonth = ($examMonthNum > 0 && $examMonthNum <= 12) ? $thaiMonths[$examMonthNum] : '-';
    $examYear = $examParts[2] ?? '-'; 
    $examTime = $visit->created_at ? $visit->created_at->format('H:i') : '-';

    // ดึงคำนำหน้าชื่อ
    $prefixName = \App\Models\Patient::prefixLabel($visit->patient2 ?? null);
    $patientFullName = $prefixName . ($visit->patient2->name ?? '') . ' ' . ($visit->patient2->surname ?? '');

    // คำนวณอายุ
    $ageText = '';
    if (isset($visit->patient2->birth_date)) {
        $b_parts = explode('/', $visit->patient2->birth_date);
        if (count($b_parts) == 3) {
            $b_year_ad = $b_parts[2] - 543;
            $age_year = date("Y") - $b_year_ad;
            if (date("m") < $b_parts[1] || (date("m") == $b_parts[1] && date("d") < $b_parts[0])) {
                $age_year--;
            }
            $ageText = " (อายุ " . $age_year . " ปี)";
        }
    }

    $doctorName = $visit->doctorUser->name ?? '-';
    $licenseNumber = $visit->doctorUser->license_number ?? '-';
    $patientFullNameText = $patientFullName . $ageText;
    $idCardNumber = $visit->patient2->id_card_number ?? '-';
    $hnCode = $visit->patient2->code ?? '-';
    $examDateText = $examDay . ' ' . $examMonth . ' ' . $examYear . ' เวลา ' . $examTime . ' น.';
    $examResult = trim(($visit->symptom ?? '-') . ' ' . ($visit->body_check ?? ''));
    $opinion = $visit->treatment ?? '-';
    $signatureName = $doctorName;

    // ถ้าเป็นการดูย้อนหลัง (ผูกกับ VisitCertificate ที่บันทึกไว้) ใช้เนื้อหาที่แก้ไขจริงตอนออกแทนของสด
    $readonly = !empty($snapshot);
    if ($readonly) {
        $issueDay = $snapshot['issueDay'] ?? $issueDay;
        $issueMonth = $snapshot['issueMonth'] ?? $issueMonth;
        $issueYear = $snapshot['issueYear'] ?? $issueYear;
        $doctorName = $snapshot['doctorName'] ?? $doctorName;
        $licenseNumber = $snapshot['licenseNumber'] ?? $licenseNumber;
        $patientFullNameText = $snapshot['patientFullName'] ?? $patientFullNameText;
        $idCardNumber = $snapshot['idCardNumber'] ?? $idCardNumber;
        $hnCode = $snapshot['hnCode'] ?? $hnCode;
        $examDateText = $snapshot['examDate'] ?? $examDateText;
        $examResult = $snapshot['examResult'] ?? $examResult;
        $opinion = $snapshot['opinion'] ?? $opinion;
        $signatureName = $snapshot['signatureName'] ?? $signatureName;
    }
@endphp

<body>
@if(!$readonly)
    @include('partials.cert_doctor_select')
@endif
	{{-- กล่องคำแนะนำบนหน้าจอ --}}
	@if($readonly)
		<div class="hint-box">
			📄 <b>ฉบับที่บันทึกไว้</b> ออกเมื่อ {{ $certificate->created_at->format('d/m/Y H:i') }} โดย {{ $certificate->issuedByUser->name ?? '-' }}
		</div>
	@else
		<div class="hint-box">
			💡 <b>โหมดแก้ไขด่วน:</b> สามารถคลิกที่ข้อความในเอกสารเพื่อพิมพ์แก้ไขก่อนปริ้นท์ได้ทันที
		</div>
	@endif

	<div class="a4-full">
		<div class="a4-frame">
			<div class="content-area">
				<table style="width: 100%; table-layout: fixed; border: none; border-collapse: collapse; vertical-align: top;">
					<colgroup>
						<col style="width: 50%;">
						<col style="width: 8.33%;">
						<col style="width: 8.33%;">
						<col style="width: 8.33%;">
						<col style="width: 8.33%;">
						<col style="width: 8.33%;">
						<col style="width: 8.33%;">
					</colgroup>
					<tr style="line-height: 1;">
						<td colspan="7" style="padding: 0; margin: 0; vertical-align: top;">
							<table class="no-print-border" style="font-size: 15pt; width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;">
								<tr>
									<td style="vertical-align: top; text-align: center; border: none; padding: 0; padding-top: 10px; margin: 0; width: 250px; min-width: 200px;">
										<img src="{{ url('/images') }}/logo-%E0%B8%81%E0%B8%A3%E0%B8%A1-2566%20png.png" style="width: 90px; height: auto; display: inline-block;">
									</td>
									<td style="vertical-align: top; border: none; padding-top: 20px;">
										<strong style="display: inline-block; font-size: 18pt; margin-bottom: 3px;">ศูนย์บริการเวชศาสตร์ป้องกัน</strong><br>
										<strong style="display: inline-block; font-size: 15pt; border-bottom: 1px solid #000; padding-bottom: 2px;">
											สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ กรมควบคุมโรค กระทรวงสาธารณสุข
										</strong> <br> <br>
										<strong>Preventive Medicine Service Center</strong> <br>
										<strong>Office of Disease Prevention and Control 1 CHIANGMAI , THAILAND</strong>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-top: 18px;">
										<div style="border: 1px solid #666; text-align: center; font-size: 18pt; padding: 8px 0; margin: 0 10px;">
											<strong>ใบรับรองแพทย์ (MEDICAL CERTIFICATE)</strong>
										</div>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-top: 15px; text-align: right; font-size: 14pt;">
										วันที่ออกใบรับรอง (date of issue)
										<span style="display: inline-block; border-bottom: 1px dotted #000; width: 35px; height: 14px; text-align: center;">
											<span id="cert_issueDay" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}">{{ $issueDay }}</span>
										</span>
										/
										<span style="display: inline-block; border-bottom: 1px dotted #000; width: 55px; height: 14px; text-align: center;">
											<span id="cert_issueMonth" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}">{{ $issueMonth }}</span>
										</span>
										/
										<span style="display: inline-block; border-bottom: 1px dotted #000; width: 70px; height: 14px; margin-right: 10px; text-align: center;">
											<span id="cert_issueYear" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}">{{ $issueYear }}</span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-top: 14px; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>ข้าพเจ้า</strong> (physician’s name)
										<span style="display: inline-block; position: relative; width: 75%; height: 15px; vertical-align: bottom;">
											<span id="cert_doctorName" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $doctorName }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>ใบประกอบโรคศิลป์เลขที่</strong> (medical license number)
										<span style="display: inline-block; position: relative; width: 55%; height: 15px; vertical-align: bottom;">
											<span id="cert_licenseNumber" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $licenseNumber }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>ได้ทำการรักษาประวัติ/ตรวจร่างกายของ</strong> (patient’s name)
										<span style="display: inline-block; position: relative; width: 52%; height: 15px; vertical-align: bottom;">
											<span id="cert_patientFullName" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $patientFullNameText }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>เลขที่บัตรประชาชน / พาสปอร์ต</strong> (ID / passport number)
										<span style="display: inline-block; position: relative; width: 52%; height: 15px; vertical-align: bottom;">
											<span id="cert_idCardNumber" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $idCardNumber }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>หมายเลขประจำตัวผู้ป่วย</strong> (HN.)
										<span style="display: inline-block; position: relative; width: 74%; height: 15px; vertical-align: bottom;">
											<span id="cert_hnCode" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $hnCode }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>

								<tr>
									<td colspan="2" style="border: none; padding-left: 14px; font-size: 16pt; line-height: 1.5;">
										<strong>ทำการตรวจเมื่อวันที่</strong> (examination date)
										<span style="display: inline-block; position: relative; width: 65%; height: 15px; vertical-align: bottom;">
											<span id="cert_examDate" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="position: absolute; left: 0; bottom: -1px; padding-left: 14px; width: 100%; box-sizing: border-box; z-index: 2;">{{ $examDateText }}</span>
											<span style="position: absolute; left: 0; right: 0; bottom: 5px; border-bottom: 1px dotted #000; z-index: 1;"></span>
										</span>
									</td>
								</tr>
                                
                                <tr>
									<td colspan="2" style="border: none; padding-top: 12px; padding-left: 14px; font-size: 16pt;">
										<span><strong>ผลการตรวจ</strong> (examination result)</span>
									</td>
								</tr>
								<tr>
									<td colspan="2" style="border: none; padding-left: 4px; padding-right: 4px; font-size: 16pt; line-height: 1.8; vertical-align: top;">
										<div style="margin: 0 10px;">
											<span id="cert_examResult" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="text-decoration-line: underline; text-decoration-style: dotted; text-decoration-thickness: 1px; text-underline-offset: 8px; width: 100%;">
												{{ $examResult }}
											</span>
										</div>
									</td>
								</tr>
                                
                                <tr>
									<td colspan="2" style="border: none; padding-top: 18px; padding-left: 14px; font-size: 16pt;">
										<span>
											<strong>ความเห็นของแพทย์</strong> (physician’s opinion and recommendation)
										</span>
									</td>
								</tr>
								<tr>
									<td colspan="2" style="border: none; padding-left: 4px; padding-right: 4px; font-size: 16pt; line-height: 1.8; vertical-align: top;">
										<div style="margin: 0 10px;">
											<span id="cert_opinion" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}" style="text-decoration-line: underline; text-decoration-style: dotted; text-decoration-thickness: 1px; text-underline-offset: 8px; width: 100%;">
												{{ $opinion }}
											</span>
										</div>
									</td>
								</tr>
                                
                                <tr>
									<td colspan="2" style="border: none; padding-top: 60px; padding-right: 14px; text-align: right; font-size: 16pt;">
										<div style="width: 260px; margin-left: auto; text-align: center;">
											<div style="border-bottom: 1px dotted #000; height: 24px; width: 100%; margin: 0 auto 6px auto;"></div>
											<div style="margin-bottom: 10px;">(<span id="cert_signatureName" class="editable" contenteditable="{{ $readonly ? 'false' : 'true' }}">{{ $signatureName }}</span>)</div>
											<div style="margin-top: 6px;"> แพทย์ผู้ตรวจ (physician)</div>
										</div>
									</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</div>
			<div class="footer-area">
				<span class="editable" contenteditable="true">
				ที่อยู่ : 143 ถนนศรีดอนไชย ตำบลช้างคลาน อำเภอเมือง จังหวัดเชียงใหม่ 50100 หมายเลขโทรศัพท์ 053-140774 ต่อ 600-604<br>
				Address: 143 Sri-Donchai rd., Changklan, Mueang, CHIANGMAI, THAILAND 50100 Tel : (+66)53-140774 Ext.600-604
				</span>
			</div>
		</div>
	</div>
	<div class="print-btn-wrap">
		<button id="certPrintBtn" onclick="{{ $readonly ? 'window.print()' : 'saveAndPrintCertificate()' }}" style="
			background: #1976d2;
			color: white;
			border: none;
			padding: 10px 20px;
			font-size: 16pt;
			font-family: 'Sarabun', sans-serif;
			border-radius: 5px;
			cursor: pointer;
			box-shadow: 0 2px 4px rgba(0,0,0,0.2);
		">
			@if($readonly)
				🖨 พิมพ์ใบรับรองแพทย์
			@else
				💾🖨 บันทึกและพิมพ์ใบรับรองแพทย์
			@endif
		</button>
	</div>

	@if(!$readonly)
	<script>
		function saveAndPrintCertificate() {
			var btn = document.getElementById('certPrintBtn');
			btn.disabled = true;

			var fields = ['issueDay', 'issueMonth', 'issueYear', 'doctorName', 'licenseNumber', 'patientFullName', 'idCardNumber', 'hnCode', 'examDate', 'examResult', 'opinion', 'signatureName'];
			var data = {};
			fields.forEach(function (key) {
				var el = document.getElementById('cert_' + key);
				data[key] = el ? el.innerText.trim() : '';
			});

			var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

			fetch("{{ route('visit.medicalCertificate.store', $visit->id) }}", {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-CSRF-TOKEN': token,
					'X-Requested-With': 'XMLHttpRequest'
				},
				body: JSON.stringify({ data: data })
			})
				.catch(function () {})
				.finally(function () {
					btn.disabled = false;
					window.print();
				});
		}
	</script>
	@endif
</body>
</html>