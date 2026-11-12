<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>ใบนัดหมาย (APPOINTMENT)</title>
    <style>
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset("fonts/sarabun/THSarabunNew.ttf") }}') format('truetype');
            font-weight: normal; font-style: normal;
        }
        @font-face {
            font-family: 'THSarabunNew';
            src: url('{{ asset("fonts/sarabun/THSarabunNew Bold.ttf") }}') format('truetype');
            font-weight: bold; font-style: normal;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; padding: 0;
            font-family: 'THSarabunNew', sans-serif;
            background: #e9e9e9;
        }

        /* กระดาษ A4 แนวตั้ง = 2 ใบนัด A5 แนวนอน ซ้อนกัน (ตัดครึ่งกลาง) */
        .a4 {
            width: 210mm; min-height: 297mm;
            margin: 0 auto; background: #fff;
        }
        /* ครึ่งแผ่น = โซน A5 แนวนอน (สูง ~148.5mm) */
        .half {
            height: 148.5mm; padding: 6mm 7mm 3mm;
            box-sizing: border-box; position: relative;
        }
        .half.top { border-bottom: 1px dashed #333; }   /* เส้นตัดตรงกลางแผ่น */
        .scissor {
            position: absolute; right: 8mm; bottom: -3.5mm;
            background: #fff; padding: 0 6px; font-size: 11pt; color: #333;
        }

        .frame {
            border: 1px solid #000; height: 100%;
            padding: 5px 12px 6px; display: flex; flex-direction: column;
            font-size: 14pt;
        }

        /* หัวราชการ */
        .head { display: flex; align-items: flex-start; gap: 10px; }
        .head img { width: 52px; height: auto; margin-top: 2px; }
        .head .org { line-height: 1.12; flex: 1; }
        .head .org .th1 { font-size: 15pt; font-weight: bold; }
        .head .org .th2 { font-size: 12pt; font-weight: bold; border-bottom: 1px solid #000; padding-bottom: 1px; display: inline-block; }
        .head .org .en  { font-size: 10.5pt; font-weight: bold; }
        .head .title-mini {
            text-align: center; font-size: 14pt; font-weight: bold;
            border: 1px solid #000; border-radius: 4px; padding: 3px 10px;
            white-space: nowrap; align-self: center; line-height: 1.1;
        }

        /* เนื้อหา 2 คอลัมน์ */
        .grid { display: flex; gap: 14px; margin-top: 6px; flex: 1; }
        .col-l { width: 46%; }
        .col-r { width: 54%; padding-left: 14px; border-left: 1px dashed #999; }

        .row { display: flex; gap: 6px; margin: 3px 0; align-items: baseline; }
        .row .lbl { font-weight: bold; white-space: nowrap; }
        .fill { flex: 1; border-bottom: 1px dotted #000; min-height: 19px; padding: 0 4px 1px; }

        .appt-box {
            border: 2px solid #000; border-radius: 6px;
            margin: 8px 0 4px; padding: 5px 8px; text-align: center;
        }
        .appt-box .cap { font-size: 12pt; font-weight: bold; }
        .appt-box .date { font-size: 20pt; font-weight: bold; line-height: 1.05; }
        .appt-box .time { font-size: 13pt; }

        .sec-h { font-weight: bold; font-size: 14pt; margin-bottom: 2px; }
        .prep { border: 1px solid #999; border-radius: 6px; padding: 4px 8px; font-size: 17pt; line-height: 1.25; color: #000; }   /* 13pt ผู้สูงอายุอ่านไม่ออก (คลินิกขอ 2569-11-04) */
        .prep ul { margin: 0; padding-left: 18px; }

        .sign { margin-top: 8px; text-align: right; }
        .sign .line { display: inline-block; width: 200px; border-bottom: 1px dotted #000; height: 20px; }
        .sign .who { display: block; width: 200px; margin-left: auto; text-align: center; margin-top: 3px; }

        .foot {
            border-top: 1px solid #666; margin-top: auto; padding-top: 3px;
            text-align: center; font-size: 10.5pt; line-height: 1.28;
        }
        /* ป้ายบอกฉบับ (ใบนัดวัคซีน: ผู้รับบริการ / สำเนาเก็บที่ TMC) — วางในขอบบนเหนือกรอบ ไม่ทับโลโก้ */
        .copytag { position: absolute; top: 1.5mm; right: 8mm; font-size: 10pt; color: #555; }
        .half.bottom .copytag { right: auto; left: 8mm; }   /* ฝั่งขวาของครึ่งล่างมีป้าย "ตัดตามรอยนี้" อยู่ */

        /* ฉบับเจ้าหน้าที่ (ครึ่งล่าง ใบนัดวัคซีน) — ตัวใหญ่ หยิบดูแล้วรู้ทันทีว่าใคร นัดอะไร */
        .staff-copy { padding: 8px 16px; }
        .staff-copy .sc-title { font-size: 13pt; font-weight: bold; color: #444; border-bottom: 1px solid #999; padding-bottom: 2px; }
        .staff-copy .sc-hn { font-size: 34pt; font-weight: bold; line-height: 1.05; margin-top: 4px; }
        .staff-copy .sc-name { font-size: 26pt; font-weight: bold; line-height: 1.1; }
        .staff-copy .sc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; font-size: 16pt; margin-top: 6px; }
        .staff-copy .sc-grid .lbl { font-weight: bold; }
        .staff-copy .sc-vac { text-align: left; margin-top: 8px; }
        .staff-copy .sc-vac .date { font-size: 17pt !important; }
        .staff-copy .sc-foot { margin-top: auto; text-align: right; font-size: 12pt; color: #444; }

        /* ช่องแก้ไขด่วน */
        .editable {
            display: inline-block; cursor: text; min-width: 20px; min-height: 17px;
            outline: 1px dashed transparent; transition: background-color .2s, outline .2s;
        }
        .editable:hover, .editable:focus { background: #e6f7ff; outline: 1px dashed #1890ff; border-radius: 3px; }

        /* แถบเครื่องมือบนจอ (เลือกแพทย์ + ปุ่มพิมพ์) — ซ่อนตอนพิมพ์ */
        .toolbar {
            position: sticky; top: 0; z-index: 999;
            background: #fff3cd; border-bottom: 1px solid #ffeeba;
            padding: 10px 16px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
            font-size: 13pt;
        }
        .toolbar select {
            font-family: 'THSarabunNew', sans-serif; font-size: 13pt;
            padding: 3px 6px; border: 1px solid #1890ff; border-radius: 4px; background: #f0f9ff;
        }
        .toolbar .hint { color: #856404; }
        .toolbar button {
            margin-left: auto; background: #1976d2; color: #fff; border: 0; border-radius: 5px;
            padding: 8px 18px; font-size: 14pt; font-family: 'THSarabunNew', sans-serif; cursor: pointer;
        }

        @media print {
            body { background: none; }
            .toolbar, .screen-only { display: none !important; }
            .editable, .editable:hover, .editable:focus { background: transparent !important; outline: none !important; }
            .scissor { background: transparent; }
            .a4 { width: 210mm; min-height: auto; margin: 0; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { size: A4 portrait; margin: 0; }
        }
    </style>
</head>
@php
    $thaiMonths = \App\Utillity::TH_MONTH_FULL;

    $prefixName = \App\Models\Patient::prefixLabel($visit->patient2 ?? null);
    $patientFullName = trim($prefixName . ($visit->patient2->name ?? '') . ' ' . ($visit->patient2->surname ?? ''));

    $ageText = '';
    if (isset($visit->patient2->birth_date)) {
        $b = explode('/', $visit->patient2->birth_date);
        if (count($b) == 3) {
            $age = (int)date('Y') - ((int)$b[2] - 543);
            if (date('m') < $b[1] || (date('m') == $b[1] && date('d') < $b[0])) $age--;
            $ageText = ' (อายุ ' . $age . ' ปี)';
        }
    }

    $hnCode = $visit->patient2->code ?? '-';

    // วันนัดมาได้ 2 รูปแบบแล้วแต่ว่าใครโหลด visit มา:
    //   2569-08-28  = ค่าดิบจากคอลัมน์ appointment (เก็บปี พ.ศ. ลงคอลัมน์ชนิด date ตรงๆ)
    //   28/08/2569  = ค่าที่ผ่าน formatDBBack() มาแล้ว
    // ของเดิมรองรับแค่แบบมี / อย่างเดียว พอเจอแบบขีดกลางเลยโชว์ 2569-08-28 ดิบๆ ให้คนไข้อ่าน
    $apptLong = $visit->appointment ?: '-';
    if ($visit->appointment) {
        $raw = trim($visit->appointment);
        $d = $m = $y = null;
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $x)) {
            [$y, $m, $d] = [(int)$x[1], (int)$x[2], (int)$x[3]];
        } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $raw, $x)) {
            [$d, $m, $y] = [(int)$x[1], (int)$x[2], (int)$x[3]];
        }
        // ปีในฐานข้อมูลเป็น พ.ศ. อยู่แล้ว ห้ามบวก 543 ซ้ำ
        if ($m >= 1 && $m <= 12 && $d >= 1 && $d <= 31) {
            $apptLong = $d . ' ' . $thaiMonths[$m] . ' ' . $y;
        }
    }

    // ใช้ appointment_reason (ช่องที่มีตัวเลือกสำเร็จรูป คนไข้อ่านได้)
    // ห้ามใช้ appointment_staff_note — ช่องนั้นพยาบาลเขียนส่งเวรกันเอง
    // ใบนี้คนไข้ถือกลับบ้าน ทำหล่น/ถ่ายรูปส่งต่อได้ จึงห้ามเอาข้อความเจ้าหน้าที่มาพิมพ์
    // นัดหลายวันต่อการมาหนึ่งครั้ง — ใบนี้คนไข้ถือกลับบ้าน
    // ถ้าโชว์แค่นัดแรก คนไข้จะไม่รู้ว่ายังมีนัดถัดไปอีก แล้วขาดนัด
    $apptRows = \App\Models\VisitAppointment::open()->where('visit_id', $visit->id)
        ->where('appointment_date', '>=', \App\Models\VisitAppointment::todayBe())
        ->orderBy('appointment_date')->orderBy('id')->get()
        ->map(function ($a) use ($thaiMonths) {
            $p = explode('-', $a->appointment_date);
            $long = count($p) === 3
                ? ((int) $p[2]) . ' ' . ($thaiMonths[(int) $p[1]] ?? '') . ' ' . ((int) $p[0])
                : $a->appointment_date;
            // ห้ามเอา staff_note ขึ้นใบนี้ ช่องนั้นพยาบาลเขียนส่งเวรกันเอง
            return ['long' => $long, 'reason' => trim((string) ($a->reason ?? ''), ' ,'), 'raw' => $a->appointment_date];
        });

    $patientNote = trim((string) ($visit->appointment_reason ?? ''));
    $doctorName = $visit->doctorUser->name ?? '-';
    $type       = $type ?? 'general';
    $vaccineDue = $vaccineDue ?? collect();

    $issue = now();
    $issueText = (int)$issue->format('d') . ' ' . $thaiMonths[(int)$issue->format('n')] . ' ' . ((int)$issue->format('Y') + 543);
@endphp
<body>

    {{-- แถบเครื่องมือ (เฉพาะบนจอ) --}}
    <div class="toolbar">
        <span class="hint">💡 คลิกข้อความในใบเพื่อพิมพ์แก้ (เวลา/การเตรียมตัว) ได้</span>
        <label><b>เลือกผู้นัดหมาย:</b></label>
        <select id="doc_select" onchange="setDoctor(this.value)">
            <option value="">— พิมพ์เอง / คงเดิม —</option>
            @foreach($doctors as $d)
                <option value="{{ $d->name }}" @if($d->id == $visit->doctor_by) selected @endif>{{ $d->name }}</option>
            @endforeach
        </select>
        <button onclick="window.print()">🖨 พิมพ์ใบนัด (A4 ตัดครึ่ง)</button>
    </div>

    <div class="a4">
        <div class="half top">
            <span class="scissor">✂ - - - - ตัดตามรอยนี้ - - - -</span>
            @if($type === 'vaccine')<span class="copytag">สำหรับผู้รับบริการ</span>@endif
            <div class="frame" id="card-frame">

                <div class="head">
                    <img src="{{ asset('images/' . rawurlencode('logo-กรม-2566 png.png')) }}" alt="logo">
                    <div class="org">
                        <div class="th1">ศูนย์บริการเวชศาสตร์ป้องกัน</div>
                        <div class="th2">สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ กรมควบคุมโรค กระทรวงสาธารณสุข</div>
                        <div class="en">Preventive Medicine Service Center</div>
                        <div class="en">Office of Disease Prevention and Control 1 CHIANGMAI, THAILAND</div>
                    </div>
                    <div class="title-mini">ใบนัดหมาย<br>APPOINTMENT</div>
                </div>

                <div class="grid">
                    <div class="col-l">
                        <div class="row">
                            <span class="lbl">ชื่อ-สกุล:</span>
                            <span class="fill">{{ $patientFullName }}{{ $ageText }}</span>
                        </div>
                        <div class="row">
                            <span class="lbl">HN:</span>
                            <span class="fill">{{ $hnCode }}</span>
                        </div>
                        {{-- สิทธิการรักษา — พิมพ์เฉพาะเมื่อทะเบียนคนไข้มีข้อมูล (คลินิกวัณโรคขอ 2569-09-29)
                             คนไข้เก่าส่วนใหญ่ยังไม่มี ถ้าพิมพ์หัวข้อเปล่าทุกใบจะกินที่บนกระดาษครึ่ง A4 ไปเฉยๆ
                             สถานพยาบาลหลักไม่ขึ้นสำหรับสิทธิที่ไม่ผูกต้นสังกัด (เบิกราชการ / จ่ายเอง) --}}
                        @php
                            // ใช้ patient2 ไม่ใช่ patient — ในโมเดล Visit ตัว patient() ไม่ใช่ relationship
                            // (มันเรียก Patient::find() คืน model มาตรง ๆ) ทั้งหน้านี้จึงใช้ patient2 มาตลอด
                            $__pt        = $visit->patient2;
                            $__rightText = ($__pt && (string) $__pt->health_right !== '') ? $__pt->healthRightText() : '';
                            $__rightHosp = $__pt ? $__pt->healthRightHospital() : '';
                        @endphp
                        @if ($__rightText !== '')
                        <div class="row">
                            <span class="lbl">สิทธิ:</span>
                            <span class="fill">{{ $__rightText }}</span>
                        </div>
                        @endif
                        @if ($__rightHosp !== '')
                        <div class="row">
                            <span class="lbl">สถานพยาบาลหลัก:</span>
                            <span class="fill">{{ $__rightHosp }}</span>
                        </div>
                        @endif
                        {{-- ไม่พิมพ์เลขบัตรประชาชนลงใบนัดโดยตั้งใจ
                             ใบนัดเป็นกระดาษที่คนไข้ถือกลับบ้าน ทำหล่น/วางทิ้ง/ถ่ายรูปส่งต่อได้
                             และมีชื่อ-สกุลอยู่บนใบเดียวกันแล้ว ชื่อคู่กับเลขบัตร 13 หลัก
                             เป็นชุดข้อมูลที่เอาไปสวมรอยได้ ส่วนตอนมาตามนัดเจ้าหน้าที่ค้นจาก HN อยู่แล้ว --}}

                        @if($type === 'vaccine')
                        <div class="appt-box" id="vac-box">
                            <div class="cap">นัดฉีดวัคซีนตามแผน (Vaccination schedule)</div>
                            {{-- เรียงตามวันนัด ก่อน → หลัง (วันว่างไปท้าย) · ชื่อวัคซีนขึ้นบรรทัดใหม่ ไม่ล้นต่อท้ายวันที่ (TMC ขอ 2569-11-11) --}}
                            @php
                                $vaccineDue = $vaccineDue->sortBy(function ($vd) {
                                    return preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim((string) ($vd['date'] ?? '')), $__x)
                                        ? sprintf('%04d-%02d-%02d', $__x[3], $__x[2], $__x[1]) : '9999';
                                })->values();
                            @endphp
                            @foreach($vaccineDue as $__n => $vd)
                                <div class="date" style="font-size:1rem">
                                    • นัดที่ {{ $__n + 1 }}
                                    {{ ($vd['date'] ?? '') !== '' ? $vd['date'] : '.....................' }}
                                    @if(($vd['text'] ?? '') !== '')
                                        <div style="font-weight:400;padding-left:1.1rem;line-height:1.25;">{{ $vd['text'] }}</div>
                                    @endif
                                </div>
                            @endforeach
                            {{-- แถวว่างให้เพิ่มวัคซีน/วันที่นัดเองได้ (กรณีมีหลายตัวหรือยังไม่บันทึกในระบบ) --}}
                            <div class="date" style="font-size:1rem">• <span class="editable" contenteditable="true">{{ $vaccineDue->isEmpty() ? 'ระบุวัคซีน — วันที่นัด' : '.....................................' }}</span></div>
                            {{-- นัดอื่นที่ไม่ใช่นัดฉีดวัคซีน — รวมไว้ในใบเดียว พิมพ์รอบเดียว (TMC ขอ 2569-10-08)
                                 วันที่ซ้ำกับวันในแผนวัคซีนไม่พิมพ์ซ้ำ (นัดจากแผนถูกสร้างเป็นนัดในระบบด้วย) · อยู่ใน #vac-box สำเนาครึ่งล่างจึงได้ด้วย --}}
                            @php
                                $__planDays = $vaccineDue->map(function ($vd) {
                                    return preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', trim((string) ($vd['date'] ?? '')), $__x)
                                        ? sprintf('%04d-%02d-%02d', $__x[3], $__x[2], $__x[1]) : null;
                                })->filter()->all();
                                $__others = $apptRows->reject(fn ($a) => in_array($a['raw'], $__planDays, true))->values();
                            @endphp
                            @if($__others->count())
                                <div class="cap" style="margin-top:1.5mm;">นัดอื่น ๆ (Other appointments)</div>
                                @foreach($__others as $__a)
                                    <div class="date" style="font-size:1rem">• {{ $__a['long'] }}@if($__a['reason'])<span style="font-weight:400"> — {{ $__a['reason'] }}</span>@endif</div>
                                @endforeach
                            @endif
                            <div class="time">เวลา <span class="editable" contenteditable="true">........</span> น.</div>
                        </div>

                        <div class="row">
                            <span class="lbl">หมายเหตุ:</span>
                            <span class="fill"><span class="editable" contenteditable="true">{{ $patientNote !== '' ? $patientNote : 'ฉีดวัคซีนตามกำหนด' }}</span></span>
                        </div>
                        @else
                        <div class="appt-box">
                            <div class="cap">นัดหมายครั้งต่อไป (Next appointment)</div>
                            @if ($apptRows->count() > 1)
                                {{-- นัดหลายวัน: ลิสต์ให้ครบ คนไข้จะได้ไม่ตกนัดถัดไป --}}
                                @foreach ($apptRows as $__i => $__a)
                                    <div class="date" style="{{ $__i ? 'margin-top:2mm;' : '' }}">
                                        {{ $__a['long'] }}@if($__a['reason'])<span style="font-size:.72em;font-weight:400"> — {{ $__a['reason'] }}</span>@endif
                                    </div>
                                @endforeach
                            @else
                                <div class="date">{{ $apptLong }}</div>
                            @endif
                            <div class="time">เวลา <span class="editable" contenteditable="true">........</span> น.</div>
                        </div>

                        <div class="row">
                            <span class="lbl">หมายเหตุ:</span>
                            <span class="fill"><span class="editable" contenteditable="true">{{ $patientNote !== '' ? $patientNote : '' }}</span></span>
                        </div>
                        @endif
                        {{-- ไม่มีช่อง "ผู้นัดหมาย" ซ้ำตรงนี้แล้ว เหลือที่เดียวคือใต้ลายเซ็นมุมขวาล่าง
                             หมอมีคนเดียว ห้องหญิงคนไข้ไม่ได้เจอหมอ และคนทำนัดคือพยาบาลอยู่แล้ว
                             เขียนชื่อคนเดิมซ้ำสองที่ในใบเดียวจึงไม่ได้ให้ข้อมูลอะไรเพิ่ม --}}
                    </div>

                    <div class="col-r">
                        <div class="sec-h">การเตรียมตัวก่อนมาตามนัด (Preparation)</div>
                        {{-- ข้อความมาตรฐานตามที่คลินิกใช้จริง (2569-11-04) · ทุกคลินิกใช้ชุดเดียวกัน
                             พิมพ์แก้/เพิ่มบนหน้าใบนัดได้ก่อนพิมพ์ (ไม่บันทึก) · ข้อ "งดน้ำงดอาหาร" เอาออก — แทบไม่มีเคส --}}
                        <div class="prep" contenteditable="true">
                            <ul>
                                <li>กรุณานำ <b>ใบนัดฉบับนี้พร้อมบัตรประชาชนหรือ<span style="white-space:nowrap">พาสปอร์ต</span></b> มาด้วยทุกครั้ง</li>
                                <li>กรุณามาก่อนเวลาปิดบริการ (ช่วงเช้า ก่อน <b>11.00 น.</b> และ ช่วงบ่าย <b>14.30 น.</b>)</li>
                                <li>หากท่านไม่สามารถมาตามนัดได้ กรุณาแจ้งล่วงหน้าตามช่องทางการติดต่อของศูนย์บริการ</li>
                            </ul>
                        </div>

                        <div class="sign">
                            <div>ออกใบนัดวันที่ {{ $issueText }}</div>
                            <div class="line"></div>
                            <span class="who">(&nbsp;<span class="doc_sign editable" contenteditable="true">{{ $doctorName }}</span>&nbsp;)</span>
                            <span class="who" style="margin-top:2px;">ผู้นัดหมาย</span>
                        </div>
                    </div>
                </div>

                <div class="foot">
                    ที่อยู่ : 143 ถนนศรีดอนไชย ตำบลช้างคลาน อำเภอเมือง จังหวัดเชียงใหม่ 50100 โทร. 053-140774 ต่อ 600-604<br>
                    <b>มือถือคลินิก 081-8830372</b> · <b>ID Line: @352bavoo</b> (กรุณาติดต่อในวันและเวลาราชการ)<br>
                    Address: 143 Sri-Donchai rd., Changklan, Mueang, CHIANGMAI, THAILAND 50100 Tel : (+66)53-140774 Ext.600-604
                </div>
            </div>
        </div>
        {{-- ใบนัดวัคซีน (TMC): ครึ่งล่างเป็นสำเนาเก็บที่ TMC — คลินิกขอ 2569-10-05 ให้ผู้รับบริการกับคลินิกถือคนละครึ่ง
             สำเนาคัดลอกจากครึ่งบนด้วย JS (รวมข้อความที่พิมพ์แก้บนจอ) — แก้ที่ครึ่งบนที่เดียวพอ
             ใบนัดทั่วไปยังเว้นครึ่งล่างว่างเหมือนเดิม --}}
        <div class="half bottom">
            @if($type === 'vaccine')
                <span class="copytag">สำเนา — เก็บที่คลินิก TMC</span>
                {{-- ฉบับเจ้าหน้าที่ไม่ใช่สำเนาเหมือนกันทั้งใบ (คลินิกขอ 2569-10-05): เน้นสิ่งที่ใช้ตามตัวคนไข้
                     HN · ชื่อ ตัวใหญ่ · ช่องทางติดต่อ · วันที่มารับบริการ · นัดฉีดวัคซีนอะไรวันไหน
                     กล่องนัดวัคซีนคัดลอกจากครึ่งบน (รวมที่พิมพ์แก้บนจอ) ด้วย JS --}}
                @php
                    $__pt = $visit->patient2;
                    $__vd = (string) $visit->getRawOriginal('date');
                    $__vdText = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $__vd, $__m)
                        ? ((int) $__m[3]) . ' ' . $thaiMonths[(int) $__m[2]] . ' ' . (((int) $__m[1]) < 2500 ? (int) $__m[1] + 543 : (int) $__m[1])
                        : $__vd;
                @endphp
                <div class="frame staff-copy">
                    <div class="sc-title">ใบนัดฉีดวัคซีน · ฉบับเก็บที่คลินิก TMC</div>
                    <div class="sc-hn">HN {{ $hnCode }}</div>
                    <div class="sc-name">{{ $patientFullName }}{{ $ageText }}</div>
                    <div class="sc-grid">
                        <div><span class="lbl">โทร:</span> <span class="editable" contenteditable="true">{{ $__pt->tel ?? '' ?: '........................' }}</span></div>
                        <div><span class="lbl">LINE:</span> {{ ($__pt && $__pt->line_user_id) ? 'เชื่อม LINE OA แล้ว' : 'ยังไม่ได้เชื่อม LINE OA' }}</div>
                        <div><span class="lbl">มารับบริการวันที่:</span> {{ $__vdText }}</div>
                        <div><span class="lbl">ผู้นัดหมาย:</span> <span class="doc_sign">{{ $doctorName }}</span></div>
                    </div>
                    <div class="appt-box sc-vac" id="vac-copy"></div>
                    <div class="sc-foot">ออกใบนัดวันที่ {{ $issueText }}</div>
                </div>
            @endif
        </div>
    </div>

    <script>
        // เลือกชื่อ -> เติมชื่อในลายเซ็นของทั้ง 2 ฉบับ (ยังพิมพ์แก้เองได้)
        function setDoctor(v) {
            if (!v) return;
            document.querySelectorAll('.doc_sign').forEach(function (e) { e.innerText = v; });
            syncCopy();
        }

        // สำเนาครึ่งล่าง (เฉพาะใบนัดวัคซีน) = ครึ่งบนทุกอย่าง แต่แก้ไม่ได้ จะได้ไม่ต้องแก้สองที่
        function syncCopy() {
            var src = document.getElementById('vac-box'), dst = document.getElementById('vac-copy');
            if (!src || !dst) return;
            dst.innerHTML = src.innerHTML;
            dst.querySelectorAll('[contenteditable]').forEach(function (e) { e.removeAttribute('contenteditable'); });
        }
        document.addEventListener('input', syncCopy);
        window.addEventListener('beforeprint', syncCopy);
        syncCopy();
    </script>
</body>
</html>
