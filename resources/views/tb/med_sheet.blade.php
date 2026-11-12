{{--
    ใบกินยาแบบมีรูป (บัตร TB 01) — สำหรับคนไข้ที่อ่านหนังสือไม่ได้ (คลินิกขอ 2569-10-01)
    แต่ละช่วงเวลามีรูปใหญ่ (พระอาทิตย์ขึ้น / เที่ยง / ตก / พระจันทร์+เตียง) และรูปเม็ดยาตามจำนวนที่ต้องกิน
    ข้อมูลมาจาก TbController@medSheet (วิธีใช้ที่บันทึกในบัตร + ขนาดยาที่สั่งล่าสุด)
    หน้าแยก ไม่ใช้ layout หลัก — พิมพ์ A4 แนวตั้ง สีหรือขาวดำก็อ่านรูปได้
--}}
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ใบกินยา {{ optional($reg->patient)->name }}</title>
<style>
    @font-face { font-family: 'SarabunLocal'; src: url('{{ asset("fonts/sarabun/Sarabun-Regular.ttf") }}') format('truetype'); font-weight: 400; }
    @font-face { font-family: 'SarabunLocal'; src: url('{{ asset("fonts/sarabun/Sarabun-Bold.ttf") }}') format('truetype'); font-weight: 700; }
    :root { --ink:#1f2937; --line:#d1d5db; --muted:#6b7280; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #f3f4f6; color: var(--ink); font-family: 'SarabunLocal', 'Sarabun', 'TH Sarabun New', sans-serif; }
    .page { width: 210mm; min-height: 297mm; margin: 12px auto; background: #fff; padding: 12mm 12mm 10mm; }
    .tools { width: 210mm; margin: 12px auto 0; display: flex; gap: 8px; justify-content: flex-end; }
    .tools button { font: inherit; font-size: 16px; padding: 6px 14px; border-radius: 6px; border: 1px solid #b45309; background: #b45309; color: #fff; cursor: pointer; }
    h1 { font-size: 28px; margin: 0; }
    .pt { font-size: 20px; margin-top: 2px; }
    .meta { font-size: 14px; color: var(--muted); }
    .slot { display: grid; grid-template-columns: 120px 1fr; gap: 12px; border: 2px solid var(--line); border-radius: 12px; padding: 10px 12px; margin-top: 10px; break-inside: avoid; }
    .slot .when { text-align: center; }
    .slot .when svg { width: 84px; height: 84px; display: block; margin: 0 auto; }
    .slot .when .lbl { font-size: 24px; font-weight: 700; line-height: 1.1; }
    .slot.empty { opacity: .45; }
    .drug { display: flex; align-items: center; gap: 12px; padding: 6px 0; border-bottom: 1px dashed var(--line); }
    .drug:last-child { border-bottom: 0; }
    .pills { display: flex; flex-wrap: wrap; gap: 6px; min-width: 120px; align-items: center; }
    .pill { width: 34px; height: 34px; border-radius: 50%; border: 3px solid var(--ink); background: #fff; position: relative; }
    .pill::after { content: ''; position: absolute; left: 4px; right: 4px; top: 50%; border-top: 2px solid var(--ink); }
    .pill.half { width: 34px; overflow: hidden; border-radius: 34px 0 0 34px; width: 17px; border-right-width: 1px; }
    .qtytxt { font-size: 30px; font-weight: 700; min-width: 70px; }
    .dname { font-size: 20px; font-weight: 700; }
    .dhow { font-size: 17px; }
    .meal { display: inline-flex; align-items: center; gap: 4px; font-size: 17px; font-weight: 700; padding: 1px 8px; border: 2px solid var(--ink); border-radius: 20px; margin-top: 2px; }
    .meal svg { width: 26px; height: 26px; }
    .foot { margin-top: 12px; font-size: 14px; color: var(--muted); }
    @media print {
        body { background: #fff; }
        .tools { display: none; }
        .page { margin: 0; width: auto; min-height: auto; padding: 8mm; }
        @page { size: A4 portrait; margin: 6mm; }
    }
</style>
</head>
<body>
@php
    $pt = $reg->patient;
    $weekday = \App\Models\TbRegistration::$weekdayOption[(int) $reg->dot_weekday] ?? null;
    // รูปประจำช่วงเวลา (SVG ในไฟล์ ไม่ต้องโหลดจากเน็ต — เครื่องในคลินิกออกเน็ตไม่ได้ทุกเครื่อง)
    $sun = fn ($y, $fill) => '<svg viewBox="0 0 100 100" aria-hidden="true"><line x1="5" y1="80" x2="95" y2="80" stroke="#1f2937" stroke-width="4"/>'
        . '<circle cx="50" cy="' . $y . '" r="20" fill="' . $fill . '" stroke="#1f2937" stroke-width="4"/>'
        . collect(range(0, 7))->map(function ($i) use ($y) {
            $a = $i * M_PI / 4; $x1 = 50 + cos($a) * 28; $y1 = $y + sin($a) * 28; $x2 = 50 + cos($a) * 38; $y2 = $y + sin($a) * 38;
            return $y2 < 78 ? '<line x1="' . round($x1) . '" y1="' . round($y1) . '" x2="' . round($x2) . '" y2="' . round($y2) . '" stroke="#1f2937" stroke-width="4" stroke-linecap="round"/>' : '';
        })->implode('') . '</svg>';
    $icons = [
        'm'    => $sun(62, '#fde68a'),                         // พระอาทิตย์ขึ้น
        'n'    => $sun(40, '#fbbf24'),                         // เที่ยง พระอาทิตย์อยู่สูง
        'e'    => '<svg viewBox="0 0 100 100" aria-hidden="true"><line x1="5" y1="70" x2="95" y2="70" stroke="#1f2937" stroke-width="4"/><path d="M28 70 A22 22 0 0 1 72 70 Z" fill="#fb923c" stroke="#1f2937" stroke-width="4"/><line x1="15" y1="82" x2="85" y2="82" stroke="#1f2937" stroke-width="3" stroke-dasharray="8 6"/></svg>',
        'b'    => '<svg viewBox="0 0 100 100" aria-hidden="true"><path d="M62 10 A26 26 0 1 0 88 40 A20 20 0 1 1 62 10 Z" fill="#c7d2fe" stroke="#1f2937" stroke-width="4"/><rect x="8" y="62" width="84" height="14" rx="3" fill="#fff" stroke="#1f2937" stroke-width="4"/><rect x="12" y="52" width="22" height="12" rx="5" fill="#fff" stroke="#1f2937" stroke-width="4"/><line x1="10" y1="76" x2="10" y2="90" stroke="#1f2937" stroke-width="4"/><line x1="90" y1="76" x2="90" y2="90" stroke="#1f2937" stroke-width="4"/></svg>',
        'week' => '<svg viewBox="0 0 100 100" aria-hidden="true"><rect x="12" y="18" width="76" height="70" rx="8" fill="#fff" stroke="#1f2937" stroke-width="4"/><rect x="12" y="18" width="76" height="18" rx="8" fill="#93c5fd" stroke="#1f2937" stroke-width="4"/><line x1="32" y1="10" x2="32" y2="26" stroke="#1f2937" stroke-width="5"/><line x1="68" y1="10" x2="68" y2="26" stroke="#1f2937" stroke-width="5"/><text x="50" y="76" font-size="34" text-anchor="middle" font-weight="700" fill="#1f2937">1</text></svg>',
        'prn'  => '<svg viewBox="0 0 100 100" aria-hidden="true"><circle cx="50" cy="38" r="22" fill="#fecaca" stroke="#1f2937" stroke-width="4"/><path d="M40 34 q4 -5 8 0 M52 34 q4 -5 8 0" stroke="#1f2937" stroke-width="3" fill="none"/><path d="M40 48 q10 -7 20 0" stroke="#1f2937" stroke-width="3" fill="none"/><path d="M76 14 l6 -8 M84 24 l9 -3 M70 8 l1 -7" stroke="#dc2626" stroke-width="4" stroke-linecap="round"/><text x="50" y="92" font-size="20" text-anchor="middle" fill="#1f2937">ปวด/ไข้</text></svg>',
    ];
    $labels = ['m' => 'เช้า', 'n' => 'กลางวัน', 'e' => 'เย็น', 'b' => 'ก่อนนอน',
               'week' => 'สัปดาห์ละครั้ง' . ($weekday ? ' (วัน' . $weekday . ')' : ''), 'prn' => 'เมื่อมีอาการ'];
    // ก่อน/หลังอาหาร — รูปจานข้าว + ลูกศร ให้ดูออกโดยไม่ต้องอ่าน
    $plate = '<svg viewBox="0 0 40 40" aria-hidden="true"><ellipse cx="20" cy="24" rx="17" ry="9" fill="#fff" stroke="#1f2937" stroke-width="3"/><ellipse cx="20" cy="22" rx="8" ry="4" fill="#fde68a" stroke="#1f2937" stroke-width="2"/></svg>';
    $meal = [
        'ac_bf'  => ['ท้องว่าง ก่อนอาหารเช้า', '⬅'],
        'ac'     => ['ก่อนอาหาร', '⬅'],
        'pc_bf'  => ['หลังอาหารเช้า', '➡'],
        'pc'     => ['หลังอาหาร', '➡'],
        'pc_imm' => ['หลังอาหารทันที', '➡'],
        'cc'     => ['พร้อมอาหาร', '＋'],
        'hs'     => ['ก่อนนอน', ''],
    ];
@endphp

<div class="tools"><button type="button" onclick="window.print()">🖨 พิมพ์</button></div>
<div class="page">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; gap:12px;">
        <div>
            <h1>ใบกินยา</h1>
            <div class="pt">{{ $pt->name ?? '' }} {{ $pt->surname ?? '' }} · HN {{ $pt->code ?? '-' }}</div>
        </div>
        <div class="meta" style="text-align:right;">
            ศูนย์บริการเวชศาสตร์ป้องกัน สคร.1 เชียงใหม่<br>
            พิมพ์วันที่ {{ now()->format('d/m/') . (now()->year + 543) }}
        </div>
    </div>

    @foreach($slots as $slot => $items)
        @continue(in_array($slot, ['week', 'prn'], true) && empty($items))
        <div class="slot {{ empty($items) ? 'empty' : '' }}">
            <div class="when">{!! $icons[$slot] !!}<div class="lbl">{{ $labels[$slot] }}</div></div>
            <div>
                @forelse($items as $it)
                    @php
                        $q = $it['qty'];
                        $qn = preg_match('#^(\d+)/(\d+)$#', $q, $f) ? $f[1] / max(1, $f[2]) : (float) $q;
                        $whole = (int) floor($qn); $half = ($qn - $whole) >= 0.5;
                    @endphp
                    <div class="drug">
                        <div class="pills" aria-hidden="true">
                            @if($it['unit'] === 'เม็ด' || $it['unit'] === 'แคปซูล')
                                @for($i = 0; $i < min($whole, 8); $i++)<span class="pill"></span>@endfor
                                @if($half)<span class="pill half"></span>@endif
                            @endif
                        </div>
                        <div class="qtytxt">{{ $q !== '' ? $q : '–' }}</div>
                        <div>
                            <div class="dname">{{ $it['name'] }}</div>
                            <div class="dhow">
                                {{ $q !== '' ? 'ครั้งละ ' . \App\Support\DrugSig::qtyText($q, $it['unit']) : '' }}
                                @if(($it['route'] ?? 'PO') !== 'PO') · {{ \App\Models\TbRegistration::$sigRoute[$it['route']] ?? $it['route'] }} @endif
                                @if($it['note'] !== '') · {{ $it['note'] }} @endif
                            </div>
                            @if(isset($meal[$it['timing']]) && $it['timing'] !== 'hs')
                                <span class="meal">{{ $meal[$it['timing']][1] }} {!! $plate !!} {{ $meal[$it['timing']][0] }}</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="dhow" style="color:#9ca3af; padding:16px 0;">ไม่มียา</div>
                @endforelse
            </div>
        </div>
    @endforeach

    <div class="foot">
        ยาวัณโรคต้องกินต่อเนื่องทุกวันจนครบ ห้ามหยุดยาเอง · มีอาการผิดปกติ ตัวเหลือง ตาเหลือง ผื่นขึ้น ให้กลับมาพบแพทย์ทันที
    </div>
</div>
</body>
</html>
