{{-- แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน FM-STI-WI08-02 REV1 — จำลองแบบฟอร์มกระดาษของห้อง Lab (2569-10-02)
     A4 แนวนอน · หน้าละ $perPage แถว เติมแถวว่างให้เต็มหน้า (ใช้เป็นใบงานกรอกมือได้ตั้งแต่ยังไม่มีผล)
     วันที่ในใบเป็น ค.ศ. แบบสั้น d/m/yy ตามตัวอย่างในฟอร์มจริง ("23/9/26") --}}
@php
    $perPage = 14;
    $pages = $rows->chunk($perPage)->map->values()->values();
    if ($pages->isEmpty()) { $pages = collect([collect()]); }
    $hnCount = $rows->countBy('hn');
    $adShort = function ($d) {
        if (! $d) { return ''; }
        $p = explode('-', $d);
        if (count($p) !== 3) { return $d; }
        $y = (int) $p[0] > 2400 ? (int) $p[0] - 543 : (int) $p[0];
        return (int) $p[2] . '/' . (int) $p[1] . '/' . substr((string) $y, -2);
    };
    $cols = ['culture', 'typical', 'gndc', 'oxidase', 'superoxol', 'glucose', 'maltose', 'sucrose', 'lactose', 'final_id'];
@endphp
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>GC Identification {{ $adShort($date) }}</title>
{{-- ฟอนต์ Sarabun ไฟล์ในเครื่อง ตัวเดียวกับใบรายงาน AST --}}
<style>
    @font-face { font-family: 'Sarabun'; src: url('{{ asset("fonts/sarabun/Sarabun-Regular.ttf") }}') format('truetype'); font-weight: normal; font-style: normal; }
    @font-face { font-family: 'Sarabun'; src: url('{{ asset("fonts/sarabun/Sarabun-Bold.ttf") }}') format('truetype'); font-weight: bold; font-style: normal; }
    @page { size: A4 landscape; margin: 8mm 10mm; }
    body { font-family: 'Sarabun', 'Tahoma', sans-serif; font-size: 13px; color: #000; margin: 0 auto; max-width: 1120px; padding: 0 10px; }
    @media print { body { max-width: none; padding: 0; } .noprint { display: none; } }
    .page { page-break-after: always; break-after: page; position: relative; }
    .page:last-child { page-break-after: auto; break-after: auto; }
    .code { position: absolute; top: 0; right: 0; text-align: right; font-size: 12px; line-height: 1.35; }
    .title { text-align: center; font-weight: bold; font-size: 18px; padding-top: 4px; }
    .org { text-align: center; font-size: 14px; margin-bottom: 8px; }
    table.f { width: 100%; border-collapse: collapse; table-layout: fixed; }
    table.f th, table.f td { border: 1px solid #000; text-align: center; padding: 2px 3px; overflow: hidden; }
    table.f th { font-weight: bold; font-size: 12px; line-height: 1.2; vertical-align: middle; }
    table.f th span { display: block; font-weight: normal; }
    table.f td { height: 27px; font-size: 13px; white-space: nowrap; }
    table.f td.rm { text-align: left; white-space: normal; font-size: 12px; }
    table.f td small { font-size: 10px; }
    .sign { display: flex; justify-content: space-between; margin-top: 14px; font-size: 14px; }
    .sign div { width: 48%; }
    .line { display: inline-block; border-bottom: 1px dotted #000; min-width: 240px; text-align: center; }
    .line.d { min-width: 110px; }
    .legend { margin-top: 10px; font-size: 12px; line-height: 1.5; }
</style>
</head>
<body>
<div class="noprint" style="margin:10px 0"><button onclick="window.print()">พิมพ์</button>
    <span style="font-size:12px;color:#555">{{ $rows->count() }} รายการ · ตั้งค่าเครื่องพิมพ์เป็น A4 แนวนอน</span></div>

@foreach($pages as $pi => $chunk)
<div class="page">
    <div class="code">FM-STI-WI08-02 REV1<br>เลขอนุมัติ 69-00340</div>
    <div class="title">แบบบันทึกผลการทดสอบพิสูจน์เชื้อหนองใน</div>
    <div class="org">กลุ่มห้องปฏิบัติการทางการแพทย์ ด้านควบคุมโรค สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</div>

    <table class="f">
        <colgroup>
            <col style="width:9%"><col style="width:8.5%"><col style="width:9.5%"><col style="width:6%">
            <col style="width:6%"><col style="width:6.5%"><col style="width:5%"><col style="width:5.5%"><col style="width:6%">
            <col style="width:5%"><col style="width:5%"><col style="width:5%"><col style="width:5%">
            <col style="width:7.5%"><col style="width:auto">
        </colgroup>
        <thead>
            <tr>
                <th>Lab number</th>
                <th>H.N.</th>
                <th>EGASP No.</th>
                <th>Collected date</th>
                <th>Culture*<span>(G/NG/SO)</span></th>
                <th>Typical Colonies<span>(Y/N)</span></th>
                <th>GNDC<span>(P/N)</span></th>
                <th>Oxidase<span>(P/N)</span></th>
                <th>Superoxol**</th>
                <th>Glucose<span>(P/N)</span></th>
                <th>Maltose<span>(P/N)</span></th>
                <th>Sucrose<span>(P/N)</span></th>
                <th>Lactose<span>(P/N)</span></th>
                <th>Final Identification<span>(NG/Non-NG)</span></th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @foreach($chunk as $r)
                <tr>
                    <td>{{ $r->lab_no_show }}</td>
                    <td>{{ $r->hn }}@if(($hnCount[$r->hn] ?? 0) > 1)<br><small>{{ $r->specimen }}</small>@endif</td>
                    <td>{{ $r->egasp }}</td>
                    <td>{{ $adShort($r->collected_date) }}</td>
                    @foreach($cols as $c)
                        <td>{{ $r->{$c} }}</td>
                    @endforeach
                    <td class="rm">{{ $r->remark }}</td>
                </tr>
            @endforeach
            @for($i = $chunk->count(); $i < $perPage; $i++)
                <tr>@for($j = 0; $j < 15; $j++)<td></td>@endfor</tr>
            @endfor
        </tbody>
    </table>

    <div class="sign">
        <div>Reported by <span class="line">{{ $stamps['reported']['names'] }}</span> /date <span class="line d">{{ $stamps['reported']['date'] }}</span></div>
        <div>Approved by <span class="line">{{ $stamps['approved']['names'] }}</span> /date <span class="line d">{{ $stamps['approved']['date'] }}</span></div>
    </div>

    <div class="legend">
        Culture* (G/NG/SO) = Growth/No Growth/Secondary organisms), (P/N) = Positive/Negative<br>
        Superoxol** = Negative, 1+, 2+, 3+, 4+<br>
        Final Identification (NG/Non-NG) = Neissseria gonorrhoeae (Red Label)/ Non-Neisseria gonorrhoeae<br>
        Remark = Describe other organisms.
    </div>
</div>
@endforeach
</body>
</html>
