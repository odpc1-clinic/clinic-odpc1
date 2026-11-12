{{--
    ใบนัดคลินิกวัณโรค — คนละแบบกับใบนัด STI (คลินิกขอ 2569-09-29)

    ยึดหน้าตาและ CSS ตามใบนัดเดิมของคลินิกวัณโรคที่ใช้อยู่ในระบบเก่า (ผู้ใช้ส่งตัวอย่างมาให้)
    เพื่อให้พิมพ์ออกมาเหมือนที่คลินิกและคนไข้คุ้นเคย ครึ่ง A4 ต่อหนึ่งนัด

    ข้อมูลทุกช่องมาจากระบบ ไม่ได้ให้พิมพ์เติมบนใบ — คลินิกต้องการกรอกในหน้าตรวจก่อนแล้วค่อยพิมพ์
    (เวลา · แพทย์ที่ทำการนัด · ที่ต้องดำเนินการก่อนพบแพทย์ อยู่ในแถวนัดของหน้าตรวจ)

    ตัวแปรจาก VisitController@appointmentCard:
      $visit · $slips (หนึ่งรายการต่อหนึ่งนัด) · $hn · $patientName · $barcodeSvg
      $rightText · $rightHospital · $issueText · $tbSlip (ค่าคงที่ของคลินิก)
--}}
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบนัดศูนย์บริการเวชศาสตร์ป้องกัน — คลินิกวัณโรค</title>
<style>
    /* ฟอนต์ Sarabun เก็บไฟล์ไว้ในเครื่อง (public/fonts/sarabun, ลิขสิทธิ์ OFL) ไม่โหลดจากเน็ต
       ใบนัดต้องพิมพ์ออกมาหน้าตาเดิมทุกครั้ง ถ้าเน็ตหลุดตอนพิมพ์จะได้ฟอนต์สำรองที่ขนาดต่างกันมาก
       และล้นกระดาษครึ่ง A4 ได้ (Sarabun ตัวใหญ่กว่า TH Sarabun New ที่ขนาด pt เท่ากันราว 1.4 เท่า) */
    @font-face {
        font-family: 'Sarabun';
        src: url('{{ asset("fonts/sarabun/Sarabun-Regular.ttf") }}') format('truetype');
        font-weight: normal; font-style: normal;
    }
    @font-face {
        font-family: 'Sarabun';
        src: url('{{ asset("fonts/sarabun/Sarabun-Bold.ttf") }}') format('truetype');
        font-weight: bold; font-style: normal;
    }
    body {
        margin: 0; padding: 0;
        font-family: "Sarabun", sans-serif;
        display: flex; justify-content: center; align-items: flex-start;
        background-color: #f0f0f0;
    }
    .a4-half {
        width: 210mm; height: 148.5mm;
        background: white; padding: 5mm; box-sizing: border-box;
        box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        font-size: 10.5pt; margin-bottom: 6mm;
        page-break-inside: avoid; break-inside: avoid;
    }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { word-wrap: break-word; }
    .barcode svg { display: block; margin: 0 auto; max-width: 100%; }
    .empty { background: #fff3cd; border: 1px solid #ffe69c; padding: 10px 14px; border-radius: 6px; font-size: 14pt; }
    @media print {
        body { margin: 0; padding: 0; background: none; }
        .a4-half { margin: 0; width: 210mm; height: 148.5mm; box-shadow: none; }
        @page { size: A4; margin: 10mm 10mm 20mm 20mm !important; }
        table, th, td { border-collapse: collapse !important; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .no-print { display: none !important; }
    }
</style>
</head>
<body>
<div style="display:flex; flex-direction:column; align-items:center;">

    @forelse($slips as $slip)
    <div class="a4-half">
        <table style="width:100%; border:1px solid #000; border-collapse:separate; border-spacing:0;">
            <tr>
                <td>
                    <table style="border:0 !important; font-size:11.5pt; margin-bottom:5px;">
                        <tr style="vertical-align:top;">
                            <td colspan="3" style="font-weight:bold; text-align:center; line-height:1; padding:5px;">
                                ใบนัดศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่<br>
                                กรมควบคุมโรค กระทรวงสาธารณสุข<br>
                                <small>143 ถ.ศรีดอนไชย ต.ช้างคลาน อ.เมือง จ.เชียงใหม่ 50100</small><br>
                                <strong>{{ $tbSlip['contact'] }}</strong>
                            </td>
                            <td colspan="1" style="vertical-align:top; padding:5px; font-size:9.5pt;">
                                เลขที่ รพ. <strong>{{ $hn }}</strong><br>
                                <div style="border:1px solid #000; text-align:center; padding:2px;">
                                    <div class="barcode">{!! $barcodeSvg !!}</div>
                                    <span style="font-size:10pt; line-height:1.1; margin-top:1px; display:block;">{{ $patientName }}</span>
                                </div>
                            </td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px; width:10%">สิทธิหลัก</td>
                            <td colspan="3">{{ $rightText !== '' ? $rightText : '-' }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">สถานพยาบาลหลัก</td>
                            <td colspan="3">{{ $rightHospital !== '' ? $rightHospital : '-' }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">วันที่นัด</td>
                            <td colspan="3" style="vertical-align:top;">
                                วันที่ {{ $slip['date'] }} &nbsp;&nbsp; เวลา {{ $slip['time'] !== '' ? $slip['time'] . ' น.' : '..........' }}
                            </td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td></td>
                            <td colspan="3" style="background-color:rgb(185,235,255); padding-left:3px; padding-right:3px;">
                                <small style="line-height:1;">{{ $tbSlip['days'] }}</small>
                            </td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td></td>
                            <td colspan="3" style="background-color:#f7eca9; padding-left:3px; padding-right:3px;">
                                <small style="font-size:9pt;">
                                    <span style="color:#ff0000;">***{{ $tbSlip['rights_no'] }}***</span>
                                    &nbsp;&nbsp; {{ $tbSlip['rights_except'] }}
                                </small>
                            </td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px; padding-top:5px;">ห้องตรวจ</td>
                            <td colspan="3" style="vertical-align:top; padding-top:5px;">
                                {{ $tbSlip['room'] }}
                                @if($slip['reason'] !== '')<small style="font-size:.85em;">({{ $slip['reason'] }})</small>@endif
                            </td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">สถานที่</td>
                            <td colspan="3" style="vertical-align:top;">{{ $tbSlip['place'] }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">แพทย์ที่ทำการนัด</td>
                            <td colspan="3" style="vertical-align:top;">{{ $slip['doctor'] }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">ชื่อผู้นัด</td>
                            <td colspan="3" style="vertical-align:top;">{{ $slip['maker'] !== '' ? $slip['maker'] : '..............................................' }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">ที่ต้องดำเนินการก่อนพบแพทย์</td>
                            <td colspan="3" style="vertical-align:top;">{{ $slip['pre'] !== '' ? $slip['pre'] : '-' }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">วันที่ออกใบนัด</td>
                            <td colspan="3" style="vertical-align:top;">วันที่ {{ $issueText }}</td>
                        </tr>
                        <tr style="line-height:1.3;">
                            <td style="font-weight:bold; vertical-align:top; padding-left:5px;">หมายเหตุ</td>
                            <td colspan="3" style="vertical-align:top;">
                                <small style="font-size:10pt;">● เพื่อประโยชน์ของท่าน กรุณามายื่นใบนัดก่อนเวลานัด ประมาณ 30 นาที</small><br>
                                <small style="font-size:10pt;">● โปรดแจ้งทุกครั้งถ้ามีประวัติแพ้ยา ตั้งครรภ์ ให้นมบุตร หรือมีโรคประจำตัว</small>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>
    @empty
        <div class="empty" style="margin-top:30px;">
            ยังไม่มีวันนัดที่ยังไม่ถึงของการมาครั้งนี้ — ลงวันนัดในหน้าตรวจ แล้วกด Save ก่อนพิมพ์ใบนัด
        </div>
    @endforelse

    @if($slips->count())
    <div class="no-print" style="margin:10px 0 30px;">
        <button onclick="window.print()" style="background:#1976d2; color:white; border:none; padding:10px 20px;
                font-size:16pt; font-family:'Sarabun',sans-serif; border-radius:5px; cursor:pointer;
                box-shadow:0 2px 4px rgba(0,0,0,0.2);">
            🖨 พิมพ์ใบนัด
        </button>
        @if($slips->count() > 1)
            <div style="font-size:13pt; color:#555; margin-top:6px;">มีนัด {{ $slips->count() }} วัน — พิมพ์ออกมาใบละวัน</div>
        @endif
    </div>
    @endif
</div>
</body>
</html>
