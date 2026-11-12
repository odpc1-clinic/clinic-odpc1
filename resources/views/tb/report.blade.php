@extends('layouts.app')

{{-- รายงานประจำเดือนคลินิกวัณโรค — 2 ตารางตามแบบฟอร์มของคลินิก (2569-09-30)
     ตัวเลขคิดใน App\Services\TbMonthlyReport · บรรทัด "—" = ระบบไม่มีข้อมูลให้นับ (คลินิกยืนยันว่าไม่มีคนกรอก) --}}

@section('content')
@php
    $thMonth = \App\Utillity::TH_MONTH_FULL;
    [$__y, $__m] = array_map('intval', explode('-', $ym));
    $__fiscal = $__m >= 10 ? $__y + 1 : $__y;   // ปีงบประมาณ ต.ค. - ก.ย.
    $__d = fn ($s) => \App\Utillity::db2thDate($s);
@endphp
<style>
    /* ฟอร์มรายงานใช้ TH Sarabun New 16pt เลขอารบิก (คลินิกขอ 2569-11-10 — ลอง IT๙ แล้วไม่เอาเลขไทย)
       แนบไฟล์ฟอนต์ไปด้วย เครื่องที่ไม่ได้ลงฟอนต์จะได้ไม่เพี้ยนเป็นฟอนต์อื่น */
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew.ttf") }}') format('truetype'); font-weight: normal; }
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew Bold.ttf") }}') format('truetype'); font-weight: bold; }
    .tbr-card, .tbr-card .tbr-table { font-family: 'TH Sarabun New', 'THSarabunNew-web', sans-serif; font-size: 16pt; line-height: 1.15; }
    .tbr-card .small { font-size: 14pt; }
    .tbr-table { font-size: 16pt; }
    .tbr-table td, .tbr-table th { padding: .15rem .5rem; }
    .tbr-table .num { text-align: right; font-variant-numeric: tabular-nums; width: 5.5rem; }
    .tbr-table tr.tbr-head td { background: #fff4e2; font-weight: 700; color: #92400e; }
    .tbr-table tr.tbr-group td { background: #fdf2f8; font-weight: 700; }
    .tbr-table tr.tbr-na td { color: #9ca3af; }
    .tbr-table td.total { font-weight: 700; }
    .tbr-names { font-size: 13pt; color: #6b7280; }
    .tbr-sign { margin: 1.5rem 1rem 1rem auto; width: max-content; text-align: center; line-height: 1.6; page-break-inside: avoid; }
    .tbr-edit { outline: 1px dashed #93c5fd; outline-offset: 1px; padding: 0 2px; }
    @media print { .tbr-edit { outline: none; } }
    /* หน้าตาตอนพิมพ์ตามแบบ Excel ที่คลินิกส่ง: เส้นดำ หัวข้อหลักพื้นเทา ตัวเลขจัดกลาง */
    @media print {
        .tbr-card .card-header { border-bottom: 0 !important; padding: 0 0 .3rem !important; }
        .tbr-table, .tbr-table td, .tbr-table th { border-color: #000 !important; color: #000 !important; }
        .tbr-table thead th { background: #fff !important; text-align: center; }
        .tbr-table .num { text-align: center; width: 2.1cm; }
        .tbr-table td, .tbr-table th { padding: 0 .3rem !important; line-height: 1.1; }   /* ให้ตารางเดือนจบในหน้าเดียว */
        .tbr-sign { margin-top: .8rem; }
        .tbr-card .table-responsive { overflow: visible !important; }   /* ไม่งั้นแถบเลื่อนติดมาในกระดาษ */
        .tbr-table tr.tbr-group td, .tbr-table tr.tbr-head td { background: #d9d9d9 !important; color: #000 !important; }
        .tbr-table tr.tbr-group td, .tbr-table tr.tbr-head td, .tbr-table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
    /* ขอบกระดาษตามแบบหนังสือราชการ: ซ้าย 3 ขวา 2 บน/ล่าง 1.5 ซม. (คลินิกขอ 2569-11-10) */
    @page { size: A4 portrait; margin: 15mm 20mm 15mm 30mm; }
    @media print {
        .no-print, nav, .navbar { display: none !important; }
        .tbr-page { max-width: none !important; padding: 0 !important; margin: 0 !important; }
        /* กรอบของ layout (main py-4 + container-fluid) เว้นเพิ่มอีกราว 3–6 มม. ทำให้ขอบกระดาษเกินที่ตั้งไว้ (คลินิกวัดได้เกิน ~0.5 ซม. ทุกด้าน) */
        main.py-4, main > .container-fluid { padding: 0 !important; margin: 0 !important; }
        body { margin: 0 !important; padding: 0 !important; }
        .tbr-card { box-shadow: none !important; border: 0 !important; page-break-inside: avoid; }
    }
</style>

<div class="container py-3 tbr-page" style="max-width: 980px;">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3 no-print">
        <div>
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-lungs me-1"></i> รายงานประจำเดือน คลินิกวัณโรค</h4>
            <div class="small text-muted">ปีงบประมาณ {{ $__fiscal }} · เดือน{{ $thMonth[$__m] }} {{ $__y }}</div>
        </div>
        <form method="GET" action="{{ route('tb.report') }}" class="d-flex gap-2">
            <select name="month" class="form-select" onchange="this.form.submit()">
                @foreach($months as $v => $label)
                    <option value="{{ $v }}" {{ $v === $ym ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fa fa-print"></i> พิมพ์</button>
        </form>
    </div>

    @if($report['unknownNationality'])
        <div class="alert alert-light border small no-print py-2">
            <i class="fa fa-circle-info me-1"></i> คนไข้คลินิกวัณโรคที่ไม่ได้ระบุสัญชาติ {{ $report['unknownNationality'] }} ราย — นับเป็น "ต่างชาติ" ตามเกณฑ์ (ไม่ใช่ไทย)
        </div>
    @endif

    @foreach([
        ['key' => 'esm',   'title' => 'ตารางกิจกรรม (ESM-Estimate)', 'sub' => 'นับวันที่ ' . $__d($report['esm']['from']) . ' – ' . $__d($report['esm']['to'])],
        // หัวแบบฟอร์มตามที่คลินิกส่งรายงาน (2569-11-10) — 2 บรรทัด จัดกลาง ไม่มีช่วงวันที่
        ['key' => 'month', 'title' => 'รายงานผลการปฏิบัติงานคลินิกวัณโรค เดือน ' . $thMonth[$__m] . ' ' . $__y,
         'org' => 'ศูนย์บริการเวชศาสตร์ป้องกัน สำนักงานป้องกันควบคุมโรคที่ 1 จังหวัดเชียงใหม่'],
    ] as $t)
        <div class="card shadow-sm mb-4 tbr-card">
            @if(isset($t['org']))
                <div class="card-header bg-white text-center">
                    <div class="fw-bold">{{ $t['title'] }}</div>
                    <div class="fw-bold">{{ $t['org'] }}</div>
                </div>
            @else
                <div class="card-header bg-white">
                    <div class="fw-bold">{{ $t['title'] }}</div>
                    <div class="small text-muted">{{ $t['sub'] }}</div>
                </div>
            @endif
            <div class="card-body p-0 table-responsive">
                <table class="table table-bordered table-sm mb-0 tbr-table">
                    <thead class="table-light">
                        <tr>
                            <th>{{ $t['key'] === 'esm' ? 'กิจกรรม' : 'รายงานผลคลินิกวัณโรค' }}</th>
                            <th class="text-center" style="width:4.5rem;">หน่วยนับ</th>
                            <th class="num">ไทย</th>
                            <th class="num">ต่างชาติ</th>
                            <th class="num">รวม</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($report[$t['key']]['rows'] as $r)
                        @if($r['type'] === 'head')
                            <tr class="tbr-head"><td colspan="5">{{ $r['label'] }}</td></tr>
                        @elseif($r['type'] === 'na')
                            <tr class="tbr-na" title="ระบบไม่มีข้อมูลนี้">
                                <td class="ps-3">{{ $r['label'] }}</td>
                                <td class="text-center">{{ $r['unit'] }}</td>
                                <td class="num">—</td><td class="num">—</td><td class="num">—</td>
                            </tr>
                        @else
                            <tr class="{{ $r['type'] === 'group' ? 'tbr-group' : '' }}">
                                <td class="{{ $r['type'] === 'group' || $r['strong'] ? '' : 'ps-3' }}">
                                    {{ $r['label'] }}
                                    {{-- รายชื่อ HN ไว้ตรวจทานตัวเลข (ไม่ออกตอนพิมพ์) --}}
                                    @if($r['total'] && $r['type'] === 'row')
                                        <details class="no-print d-inline ms-1">
                                            <summary class="tbr-names d-inline" style="cursor:pointer;">รายชื่อ</summary>
                                            <div class="tbr-names">
                                                @foreach($r['patients'] as $pid)
                                                    {{ $patients[$pid]->code ?? $pid }} {{ $patients[$pid]->name ?? '' }}@if(! $loop->last), @endif
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </td>
                                <td class="text-center">{{ $r['unit'] }}</td>
                                <td class="num">{{ $r['thai'] }}</td>
                                <td class="num">{{ $r['foreign'] }}</td>
                                <td class="num total">{{ $r['total'] }}</td>
                            </tr>
                        @endif
                    @endforeach
                    </tbody>
                </table>
            </div>
            @if($t['key'] === 'month')
                {{-- ส่วนท้ายแบบฟอร์ม (คลินิกขอ 2569-11-10) — คลิกแก้ได้ จำชื่อไว้ในเครื่องนี้ ครั้งหน้าไม่ต้องพิมพ์ใหม่ --}}
                <div class="tbr-sign">
                    <div>ผู้รายงาน <span class="tbr-edit" contenteditable="true" data-key="sign">..............................................................</span></div>
                    <div>(<span class="tbr-edit" contenteditable="true" data-key="name">............................................</span>)</div>
                    <div><span class="tbr-edit" contenteditable="true" data-key="title">พยาบาลวิชาชีพชำนาญการ</span></div>
                </div>
            @endif
        </div>
    @endforeach

    <div class="small text-muted no-print">
        เกณฑ์: ผู้ป่วยใหม่ = มาคลินิกวัณโรคครั้งแรกในช่วงนั้น · ต่างชาติ = สัญชาติไม่ใช่ไทย ·
        ข้อ 5 นับ ณ วันสิ้นเดือน · ข้อ 7 นับจากวันที่ติ๊ก DOT ในเดือน แยกตามเหตุผลที่ DOT ของวันนั้น (ไม่ได้กรอก = ดูเหตุผลที่บัตร แล้วดูชนิดบัตร/ระยะยา)
        (คนเดียวเปลี่ยนระยะกลางเดือนจะอยู่ในทั้งสองกลุ่ม หัวข้อ 7 จึงนับคนไม่ซ้ำ) ·
        "—" = ระบบไม่มีข้อมูลบรรทัดนี้
    </div>
</div>
<script>
// ชื่อ/ตำแหน่งผู้รายงานที่แก้ไว้ จำในเบราว์เซอร์เครื่องนี้ (ไม่ได้เก็บในระบบ)
document.querySelectorAll('.tbr-edit').forEach(function (el) {
    var k = 'tbReportSign.' + el.dataset.key;
    try { var v = localStorage.getItem(k); if (v) { el.textContent = v; } } catch (e) {}
    el.addEventListener('input', function () {
        try { localStorage.setItem(k, el.textContent.trim()); } catch (e) {}
    });
});
</script>
@endsection
