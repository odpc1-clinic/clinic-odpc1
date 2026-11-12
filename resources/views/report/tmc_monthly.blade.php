@extends('layouts.app')

@section('content')
@php
    $thMonths = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $monthTh = $thMonths[$start->month] . ' ' . ($start->year + 543);
    $prev = $start->copy()->subMonthNoOverflow()->format('Y-m');
    $next = $start->copy()->addMonthNoOverflow()->format('Y-m');
    $pv = $people['visit']; $pc = $people['vaccine'];
    // แถวคนตามลำดับในชีทเดิม: รวม / ไทย / ชาย / หญิง / ต่างชาติ / ชาย / หญิง
    $peopleRows = [
        ['จำนวนผู้รับบริการ', 'total', null, true],
        ['สัญชาติไทย', 'th', 'sum', true],
        ['— เพศชาย', 'th', 0, false],
        ['— เพศหญิง', 'th', 1, false],
        ['ชาวต่างชาติ', 'fx', 'sum', true],
        ['— เพศชาย', 'fx', 0, false],
        ['— เพศหญิง', 'fx', 1, false],
    ];
    $pval = function ($p, $k, $i) {
        if ($k === 'total') { return $p['total']; }
        return $i === 'sum' ? array_sum($p[$k]) : $p[$k][$i];
    };
    $unknownSex = $pv['th'][2] + $pv['fx'][2] + $pc['th'][2] + $pc['fx'][2];
@endphp
<style>
    #tmc-rep { font-size: 1.02rem; }
    #tmc-rep .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 600; }
    #tmc-rep table td, #tmc-rep table th { padding: .35rem .55rem; vertical-align: middle; }
    #tmc-rep thead th { background: #f1f4f8; font-size: .9rem; }
    #tmc-rep .sub td:first-child { padding-left: 1.6rem; color: #555; }
    #tmc-rep .dose-chip { display: inline-block; border: 1px solid #ced4da; border-radius: 4px; padding: 0 .4rem; margin: 0 .2rem .15rem 0; font-size: .85rem; background: #fff; }
    #tmc-rep .dose-chip.q { border-color: #ffc107; background: #fff3cd; }
    #tmc-rep .shots { max-height: 60vh; overflow: auto; }
    #tmc-rep .shots thead th { position: sticky; top: 0; z-index: 1; }
    #tmc-rep .tmc-dir-table td, #tmc-rep .tmc-dir-table th { padding: .3rem .5rem; }
    #tmc-rep .tmc-dir-head td { background: #f8fafc; }
    #tmc-destroy-card .destroy-title { display: none; flex-direction: column; align-items: center; text-align: center; margin-bottom: .6rem; }
    #tmc-destroy-card .destroy-sign { display: none; justify-content: space-around; margin-top: 2.5rem; text-align: center; }
    #tmc-destroy-card .destroy-sign .ln { border-bottom: 1px dotted #000; width: 14rem; height: 1.6rem; margin: 0 auto .2rem; }
    @media print {
        nav, .no-print { display: none !important; }
        #tmc-rep .shots { max-height: none; overflow: visible; }
        /* ปุ่ม "พิมพ์แบบรายงานส่ง ผอ." = พิมพ์เฉพาะแบบฟอร์ม */
        body.print-dir #tmc-rep > *:not(#tmc-dir-card) { display: none !important; }
        body.print-dir #tmc-dir-card { border: 0 !important; }
        body.print-dir .tmc-dir-sign { display: block !important; }
        /* ปุ่ม "พิมพ์รายการส่งทำลาย" = พิมพ์เฉพาะกล่องสมุดส่งทำลาย */
        body.print-destroy #tmc-rep > *:not(#tmc-destroy-card) { display: none !important; }
        body.print-destroy #tmc-destroy-card { border: 0 !important; margin: 0 !important; }
        body.print-destroy #tmc-destroy-card .card-header { display: none !important; }
        body.print-destroy #tmc-destroy-card .destroy-title,
        body.print-destroy #tmc-destroy-card .destroy-sign { display: flex !important; }
    }

    /* แบบรายงานส่ง ผอ.: TH Sarabun New 16pt ขอบกระดาษแบบหนังสือราชการ ซ้าย 3 ขวา 2 บน/ล่าง 1.5 ซม. (คลินิกขอ 2569-11-10 เหมือนรายงาน TB)
       แนบไฟล์ฟอนต์ไปด้วย เครื่องที่ไม่ได้ลงฟอนต์จะได้ไม่เพี้ยน */
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew.ttf") }}') format('truetype'); font-weight: normal; }
    @font-face { font-family: 'THSarabunNew-web'; src: url('{{ asset("fonts/sarabun/THSarabunNew Bold.ttf") }}') format('truetype'); font-weight: bold; }
    #tmc-dir-card .tmc-dir, #tmc-dir-card .tmc-dir-sign, #tmc-dir-card .tmc-dir-table { font-family: 'TH Sarabun New', 'THSarabunNew-web', sans-serif; font-size: 16pt; line-height: 1.15; }
    #tmc-dir-card .tmc-dir .fw-bold[style] { font-size: 16pt !important; }
    #tmc-dir-card .tmc-dir-table .small { font-size: 14pt; }
    #tmc-dir-card .tmc-dir-table thead th { font-size: 16pt; }
    /* คอลัมน์ตัวเลขแคบลง ให้ช่องกิจกรรมกว้างพอไม่ต้องตัดบรรทัด */
    #tmc-dir-card .tmc-dir-table th:nth-child(1) { width: 1.5cm !important; }
    #tmc-dir-card .tmc-dir-table th:nth-child(3) { width: 1.7cm !important; }
    #tmc-dir-card .tmc-dir-table th:nth-child(n+4) { width: 2.2cm !important; }
    .tmc-edit { outline: 1px dashed #93c5fd; outline-offset: 1px; padding: 0 2px; }
    .tmc-sign-box { margin: 1.2rem 1rem .5rem auto; width: max-content; text-align: center; line-height: 1.6; }
    @page { size: A4 portrait; margin: 15mm 20mm 15mm 30mm; }
    @media print {
        .tmc-edit { outline: none; }
        /* กรอบของ layout (main py-4 + container-fluid + container) เว้นเพิ่ม ทำให้ขอบเกินที่ตั้ง */
        main.py-4, main > .container-fluid, #tmc-rep { padding: 0 !important; margin: 0 !important; max-width: none !important; }
        body { margin: 0 !important; padding: 0 !important; }
        #tmc-dir-card, #tmc-dir-card .card-body { padding: 0 !important; }
        #tmc-dir-card .table-responsive { overflow: visible !important; }
        #tmc-dir-card .tmc-dir-table, #tmc-dir-card .tmc-dir-table td, #tmc-dir-card .tmc-dir-table th { border-color: #000 !important; color: #000 !important; }
        #tmc-dir-card .tmc-dir-table td, #tmc-dir-card .tmc-dir-table th { padding: 0 .3rem !important; }
        #tmc-dir-card .tmc-dir-table thead th { background: #fff !important; text-align: center !important; }
        #tmc-dir-card .tmc-dir-head td { background: #d9d9d9 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    }
</style>

<div class="container py-3" id="tmc-rep">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h4 class="mb-0 me-3"><i class="fa-solid fa-plane-departure me-2"></i>รายงาน TMC — {{ $monthTh }}</h4>
        <form method="GET" action="{{ route('report.tmc') }}" class="d-flex align-items-center gap-1 no-print">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('report.tmc', ['m' => $prev]) }}" title="เดือนก่อน"><i class="fa fa-chevron-left"></i></a>
            <input type="month" name="m" value="{{ $start->format('Y-m') }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('report.tmc', ['m' => $next]) }}" title="เดือนถัดไป"><i class="fa fa-chevron-right"></i></a>
        </form>
        <a class="btn btn-outline-primary btn-sm no-print ms-auto" href="{{ route('report.tmc.year', ['fy' => $start->month >= 10 ? $start->year + 544 : $start->year + 543]) }}"
           title="แบบรายงานส่ง ผอ. แยกรายเดือน ต.ค.–ก.ย. + รวมทั้งปี"><i class="fa fa-calendar me-1"></i> รวมทั้งปีงบประมาณ</a>
        <button type="button" class="btn btn-dark btn-sm no-print"
                onclick="document.body.classList.add('print-dir'); window.print(); document.body.classList.remove('print-dir');">
            <i class="fa fa-print me-1"></i> พิมพ์แบบรายงานส่ง ผอ.</button>
        <button type="button" class="btn btn-outline-dark btn-sm no-print" onclick="window.print()"><i class="fa fa-print me-1"></i> พิมพ์ทั้งหน้า</button>
    </div>

    <div class="alert alert-light border py-2 small">
        <i class="fa fa-circle-info me-1 text-primary"></i>
        วัคซีนนับจาก <b>บันทึกการฉีดวัคซีน</b> (ที่พยาบาลลงตอนฉีด) ไม่ได้นับจากใบสั่งยา ·
        นับเฉพาะเข็มที่ <b>ฉีดที่คลินิกนี้</b> ไม่นับประวัติที่ฉีดมาจากที่อื่น ·
        ระบบเริ่มบันทึกการฉีดตั้งแต่ 4 ส.ค. 2569 เดือนก่อนหน้านั้นจะได้ 0
    </div>

    {{-- แบบรายงานส่ง ผอ. — หน้าตาตามไฟล์ "รายงานTMCส่งผอ.69" (คลินิกขอ 2569-11-04) --}}
    <div class="card mb-3" id="tmc-dir-card">
        <div class="card-body">
            @include('report._tmc_director', ['rows' => $director['rows'], 'periodText' => 'ประจำเดือน' . $monthTh])
            <div class="small text-muted no-print">
                ผู้รับบริการ = คนไม่ซ้ำในเดือน · ให้คำปรึกษา = มาแต่ไม่ได้รับวัคซีนในเดือนนั้น (ช่วงการเดินทางว่าง/ไม่มีการเดินทางไปต่างประเทศ นับเป็นก่อนเดินทาง · ระหว่างเดินทาง นับเป็นหลังเดินทาง) ·
                ส่งต่อกามโรค/วัณโรค = วันเดียวกันไปเปิดงานคลินิก STI/TB · โรงพยาบาลอื่น = ช่อง Refer ในหน้าตรวจ
                @if($director['unknownNat']) · <span class="text-danger">สัญชาติว่าง {{ $director['unknownNat'] }} คน — นับในช่องรวมอย่างเดียว</span>@endif
            </div>
            {{-- ท้ายฟอร์ม — คลิกแก้ได้ จำไว้ในเครื่องนี้ (แบบเดียวกับรายงาน TB · 2569-11-10) --}}
            <div class="tmc-dir-sign mt-3">
                <div>หมายเหตุ <span class="tmc-edit" contenteditable="true" data-key="note">...................................................................</span></div>
                <div class="tmc-sign-box">
                    <div>ผู้รายงาน <span class="tmc-edit" contenteditable="true" data-key="sign">..............................................................</span></div>
                    <div>(<span class="tmc-edit" contenteditable="true" data-key="name">............................................</span>)</div>
                    <div><span class="tmc-edit" contenteditable="true" data-key="title">พยาบาลวิชาชีพชำนาญการ</span></div>
                </div>
            </div>
        </div>
    </div>

    <h5 class="mt-4 mb-2 no-print">รายละเอียด / สำหรับลงชีทนับมือ</h5>
    <div class="row g-3">
        {{-- ตารางเรียงตามชีทเดิม — กดคัดลอกแล้ววางลงคอลัมน์ตัวเลขในชีทได้เลย --}}
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center bg-white">
                    <b>สรุปจำนวนการให้บริการ</b>
                    <button type="button" class="btn btn-success btn-sm ms-auto no-print" id="tmc-copy"
                            title="คัดลอกเฉพาะตัวเลข เรียงบรรทัดตามตาราง ไปวางในชีทได้เลย">
                        <i class="fa fa-copy me-1"></i> คัดลอกตัวเลข
                    </button>
                </div>
                <table class="table table-sm table-bordered mb-0" id="tmc-sheet">
                    <tbody>
                    @foreach($peopleRows as [$label, $k, $i, $bold])
                        <tr class="{{ $bold ? '' : 'sub' }}">
                            <td>{!! $bold ? '<b>' . e($label) . '</b>' : e($label) !!}</td>
                            <td class="num" data-v>{{ $pval($pv, $k, $i) }}</td>
                            <td style="width:3.5rem">คน</td>
                        </tr>
                    @endforeach
                    @foreach($sheet as [$label, $val, $unit])
                        <tr class="{{ $loop->first ? '' : 'sub' }}">
                            <td>{!! $loop->first ? '<b>' . e($label) . '</b>' : e($label) !!}</td>
                            <td class="num" data-v>{{ $val }}</td>
                            <td>{{ $unit }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <div class="card-footer bg-white small text-muted">
                    "ผู้รับบริการ" = คนไม่ซ้ำที่เปิดงานคลินิก TMC ในเดือนนี้ ({{ number_format($visitCount) }} ครั้ง) — มาหลายครั้งในเดือนนับ 1 คน
                    @if($pv['na']) · สัญชาติว่าง {{ $pv['na'] }} คน (ไม่ได้นับในไทย/ต่างชาติ) @endif
                    @if($unknownSex) · <span class="text-danger">ไม่ระบุเพศ {{ $unknownSex }} คน</span> @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            {{-- เทียบคนที่มา กับคนที่ได้ฉีดจริง — มาปรึกษาอย่างเดียวไม่ได้ฉีดก็มี --}}
            <div class="card mb-3">
                <div class="card-header bg-white"><b>ผู้รับบริการ (คน)</b></div>
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th></th><th class="text-end">มารับบริการ TMC</th><th class="text-end">ได้รับวัคซีน</th></tr></thead>
                    <tbody>
                    @foreach($peopleRows as [$label, $k, $i, $bold])
                        <tr class="{{ $bold ? '' : 'sub' }}">
                            <td>{!! $bold ? '<b>' . e($label) . '</b>' : e($label) !!}</td>
                            <td class="num">{{ $pval($pv, $k, $i) }}</td>
                            <td class="num">{{ $pval($pc, $k, $i) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header bg-white"><b>แยกตามวัคซีน</b> <span class="text-muted small">— รวม {{ $shots->count() }} โดส</span></div>
                <table class="table table-sm table-bordered mb-0">
                    <thead><tr><th>วัคซีน</th><th class="text-end">โดส</th><th class="text-end">คน</th><th>แยกตามเข็มที่</th></tr></thead>
                    <tbody>
                    @forelse($rows as $r)
                        <tr>
                            <td>{{ $r['label'] }} @unless($r['known'])<span class="badge bg-secondary ms-1" title="ไม่เข้ากลุ่มที่ตั้งไว้ แสดงตามชื่อที่บันทึก">ชื่อตามบันทึก</span>@endunless</td>
                            <td class="num">{{ $r['doses'] }}</td>
                            <td class="num">{{ $r['people'] }}</td>
                            <td>
                                @foreach($r['byDose'] as $d => $n)
                                    <span class="dose-chip {{ $d === '?' ? 'q' : '' }}">{{ $d === '?' ? 'ไม่ระบุเข็ม' : 'เข็ม ' . $d }}: {{ $n }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">ไม่มีบันทึกการฉีดวัคซีนในเดือนนี้</td></tr>
                    @endforelse
                    </tbody>
                </table>
                @if($takeHome)
                    <div class="card-footer bg-white small">
                        <i class="fa fa-house me-1 text-primary"></i> อหิวาตกโรครับไปทานเองที่บ้าน {{ array_sum($takeHome) }} โดส ({{ count($takeHome) }} คน)
                        — รายการเก่าที่ลงไว้แค่ในแผนนัด (ไม่ได้ลงเป็น "จ่ายให้ไปทานเอง") นับรวมใน dose 2 ของตารางซ้ายแล้ว (ตารางนี้นับเฉพาะที่บันทึกไว้)
                    </div>
                @endif
                @if($noDose)
                    <div class="card-footer bg-white small" style="color:#856404">
                        <i class="fa fa-triangle-exclamation me-1"></i> มี {{ $noDose }} โดสที่ไม่ได้ระบุ "เข็มที่" — ถ้าเป็นอหิวาตกโรค จะไม่เข้าแถว dose 1/dose 2 ให้แก้ในบันทึกการฉีดของ visit นั้น
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ที่มาของตัวเลขสมุดเหลือง — ทุกประเภท รวมใช้เล่มเดิม · ปุ่มกรองตามประเภท
         ชำรุด + เสีย อยู่กลุ่มเดียวกัน เพราะเป็นเล่มที่อยู่ในมือ ต้องส่งทำลาย · หายแยกไว้ (ไม่มีเล่มให้ทำลาย) — คลินิกขอ 2569-11-04 --}}
    @php
        $kindTh = ['first' => 'ออกเล่มแรก', 'damaged' => 'สมุดเสีย/ชำรุด — ออกใหม่ทดแทน', 'spoiled' => 'สมุดเสีย → ออกเล่มใหม่',
                   'lost' => 'ทดแทน — เล่มเดิมหาย', 'replace' => 'ทดแทน (ไม่ทราบว่าชำรุดหรือหาย)', 'old' => 'ใช้เล่มเดิม'];
        $kindN = array_count_values(array_column($book['events'], 'kind'));
        $groups = [
            ['first', 'ออกเล่มแรก'],
            ['damaged,spoiled', 'ชำรุด + เสีย (ส่งทำลาย)'],
            ['lost', 'หาย'],
            ['replace', 'ทดแทน ไม่ทราบสาเหตุ'],
            ['old', 'ใช้เล่มเดิม'],
        ];
        $destroy = array_values(array_filter($book['events'], fn ($e) => in_array($e['kind'], ['damaged', 'spoiled'], true)));
    @endphp
    <div class="card mt-3">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <b>สมุดเหลืองในเดือน</b>
            <span class="text-muted small">{{ count($book['events']) }} รายการ</span>
            <span class="ms-auto no-print">
                <button type="button" class="btn btn-sm btn-secondary mb-1 js-book-kind" data-kind="">ทั้งหมด <span class="badge bg-light text-dark">{{ count($book['events']) }}</span></button>
                @foreach($groups as [$ks, $t])
                    @php $n = array_sum(array_map(fn ($k) => $kindN[$k] ?? 0, explode(',', $ks))); @endphp
                    @if($n)
                        <button type="button" class="btn btn-sm btn-outline-secondary mb-1 js-book-kind" data-kind="{{ $ks }}">{{ $t }} <span class="badge bg-secondary">{{ $n }}</span></button>
                    @endif
                @endforeach
            </span>
        </div>
        <div class="shots">
        <table class="table table-sm table-bordered mb-0" id="tmc-book-table">
            <thead><tr><th>วันที่</th><th>คนไข้</th><th>ประเภท</th><th>เลขเดิม / เล่มที่ใช้</th><th>เลขใหม่</th></tr></thead>
            <tbody>
            @forelse($book['events'] as $e)
                <tr data-kind="{{ $e['kind'] }}">
                    <td style="white-space:nowrap">{{ \App\Models\VisitVaccine::toThDate($e['date']) }} {{ empty($e['inferred']) ? substr($e['date'], 11, 5) : '' }}</td>
                    <td>{{ $e['label'] }}</td>
                    <td>{{ $kindTh[$e['kind']] }}@if(!empty($e['inferred']))<span class="text-muted small" title="ไม่ได้เลือกในกล่องสมุดเหลืองของ visit · ระบบคิดจาก: ได้ฉีดเดือนนี้ และมีเลขเล่มก่อนต้นเดือน · วันที่ = วันที่ฉีดเข็มแรกของเดือน"> (ระบบคิดให้)</span>@endif</td>
                    <td>{{ $e['old'] }}</td>
                    <td>{{ $e['new'] }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-2">ไม่มีรายการสมุดเหลืองในเดือนนี้</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        @if(!empty($kindN['replace']))
            <div class="card-footer bg-white small" style="color:#856404">
                <i class="fa fa-triangle-exclamation me-1"></i> {{ $kindN['replace'] }} รายการออกทดแทนแต่ไม่พบว่าเล่มเดิมชำรุดหรือหาย (ประวัติเล่มเก่าอาจถูกลบ) — ไม่ได้นับเข้ารายการส่งทำลาย
            </div>
        @endif
    </div>

    {{-- สมุดส่งทำลาย — พิมพ์ได้เฉพาะกล่องนี้ / ดาวน์โหลดเป็นไฟล์ Excel
         ไม่ใส่ชื่อคนไข้ในใบพิมพ์/ไฟล์ — เอกสารส่งออกนอกห้องตรวจ ใช้เลขเล่ม + HN ก็ตามหาได้ --}}
    <div class="card mt-3" id="tmc-destroy-card">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <b>สมุดส่งทำลาย (ชำรุด + เสีย)</b>
            <span class="text-muted small">{{ count($destroy) }} เล่ม</span>
            <span class="ms-auto no-print">
                <button type="button" class="btn btn-sm btn-outline-success" id="tmc-destroy-csv" @disabled(!$destroy)><i class="fa fa-file-excel me-1"></i> ดาวน์โหลด Excel</button>
                <button type="button" class="btn btn-sm btn-dark" @disabled(!$destroy)
                        onclick="document.body.classList.add('print-destroy'); window.print(); document.body.classList.remove('print-destroy');"><i class="fa fa-print me-1"></i> พิมพ์รายการส่งทำลาย</button>
            </span>
        </div>
        <div class="destroy-title">
            <div style="font-size:1.15rem;font-weight:bold;">รายการสมุดรับรองการฉีดวัคซีน (สมุดเหลือง) ส่งทำลาย</div>
            <div>คลินิกเวชศาสตร์การเดินทาง ศูนย์บริการเวชศาสตร์ป้องกัน สคร.1 เชียงใหม่ · ประจำเดือน {{ $monthTh }}</div>
        </div>
        <table class="table table-sm table-bordered mb-0" id="tmc-destroy-table">
            <thead><tr><th class="text-center">ลำดับ</th><th>วันที่</th><th>เลขเล่มที่ส่งทำลาย</th><th>สาเหตุ</th><th>เล่มของ</th><th>HN</th><th>เลขเล่มใหม่ที่ออกแทน</th></tr></thead>
            <tbody>
            @forelse($destroy as $e)
                <tr>
                    <td class="text-center">{{ $loop->iteration }}</td>
                    <td style="white-space:nowrap">{{ \App\Models\VisitVaccine::toThDate($e['date']) }}</td>
                    <td>{!! ($e['book'] ?? '') !== '' ? e($e['book']) : '<span class="text-danger">ไม่ทราบเลข</span>' !!}</td>
                    <td>{{ $e['kind'] === 'spoiled' ? 'สมุดเสีย (เขียนผิด)' : 'เสีย/ชำรุด' }}</td>
                    <td>{{ $e['kind'] === 'spoiled' || ($e['src'] ?? null) === 'ours' ? 'คลินิกนี้' : (($e['src'] ?? null) === 'other' ? 'ที่อื่น' : '—') }}</td>
                    <td>{{ $e['code'] ?? '' }}</td>
                    <td>{{ $e['new'] }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-2">เดือนนี้ไม่มีสมุดชำรุด/เสีย</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="destroy-sign">
            <div><div class="ln"></div>ผู้จัดทำ<br>(......................................................)</div>
            <div><div class="ln"></div>ผู้ตรวจสอบ<br>(......................................................)</div>
        </div>
    </div>

    {{-- ประเทศปลายทาง — กดชื่อประเทศ (หรือเลือกในช่อง) เพื่อกรองรายชื่อด้านล่าง --}}
    @if($dest['ready'])
    <div class="card mt-3" id="tmc-dest-card">
        <div class="card-header bg-white d-flex flex-wrap align-items-center gap-2">
            <b>ประเทศปลายทาง</b>
            <span class="text-muted small">{{ count($dest['list']) }} visit · ยังไม่ได้เลือกประเทศ {{ $dest['none'] }} visit · คนเดียวไปหลายประเทศนับทุกประเทศ</span>
            <select id="tmc-dest-filter" class="form-select form-select-sm ms-auto no-print" style="width:auto;min-width:14rem;">
                <option value="">— ทุกประเทศ —</option>
                @foreach($dest['rows'] as $code => $r)
                    <option value="{{ $code }}">{{ $r['name'] }} ({{ $r['people'] }} คน)</option>
                @endforeach
                <option value="-">ยังไม่ได้เลือกประเทศ ({{ $dest['none'] }})</option>
            </select>
        </div>
        <div class="card-body py-2 no-print">
            @forelse($dest['rows'] as $code => $r)
                <button type="button" class="btn btn-sm btn-outline-success mb-1 js-dest-chip" data-code="{{ $code }}">{{ $r['name'] }} <span class="badge bg-success">{{ $r['people'] }} คน</span></button>
            @empty
                <span class="text-muted small">เดือนนี้ยังไม่มี visit ที่เลือกประเทศปลายทาง</span>
            @endforelse
        </div>
        <div class="shots">
            <table class="table table-sm table-hover table-bordered mb-0" id="tmc-dest-table">
                <thead><tr><th>วันที่มา</th><th>HN</th><th>ชื่อ</th><th>ประเทศ</th><th>เมือง / รายละเอียด</th></tr></thead>
                <tbody>
                @foreach($dest['list'] as $v)
                    @php [$__y, $__m, $__d] = array_pad(explode('-', (string) $v['date']), 3, ''); if ((int) $__y && (int) $__y < 2500) { $__y = (int) $__y + 543; } @endphp
                    <tr data-codes="{{ $v['countries'] ? ',' . implode(',', array_keys($v['countries'])) . ',' : '-' }}">
                        <td style="white-space:nowrap">{{ $__d }}/{{ $__m }}/{{ $__y }}</td>
                        <td><a href="{{ route('visit.edit', $v['id']) }}" target="_blank">{{ $v['code'] }}</a></td>
                        <td>{{ $v['name'] }}</td>
                        <td>{!! $v['countries'] ? e(implode(', ', $v['countries'])) : '<span class="text-muted">—</span>' !!}</td>
                        <td class="small">{{ $v['text'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- รายการทุกเข็ม ไว้ตรวจทานกับชีทเดิม --}}
    <div class="card mt-3">
        <div class="card-header bg-white d-flex align-items-center">
            <b>รายการฉีดทั้งหมดในเดือน</b>
            <span class="text-muted small ms-2">{{ $shots->count() }} รายการ · ไว้ตรวจทาน</span>
        </div>
        <div class="shots">
            <table class="table table-sm table-hover table-bordered mb-0">
                <thead><tr><th>#</th><th>วันที่ฉีด</th><th>HN</th><th>ชื่อ</th><th>สัญชาติ</th><th>วัคซีน</th><th>นับในแถว</th><th class="text-center">เข็มที่</th><th>Lot</th></tr></thead>
                <tbody>
                @foreach($shots as $s)
                    <tr>
                        <td class="text-muted">{{ $loop->iteration }}</td>
                        <td style="white-space:nowrap">{{ \App\Models\VisitVaccine::toThDate($s->vaccinated_date) }}</td>
                        <td>
                            @if($s->visit_id)
                                <a href="{{ route('visit.edit', $s->visit_id) }}" target="_blank">{{ $s->code }}</a>
                            @else
                                {{ $s->code }}
                            @endif
                        </td>
                        <td>{{ $s->pname }} {{ $s->surname }}</td>
                        <td>{{ $s->nationality }}</td>
                        <td>{{ $s->name }} @if((int) $s->source === \App\Models\VisitVaccine::TAKE_HOME)<span class="badge ms-1" style="background:#0e7490;color:#fff;" title="คลินิกจ่ายให้ไปทานเองที่บ้าน · นับตามวันที่ทาน">ไปทานเอง</span>@endif</td>
                        <td class="small text-muted">{{ $s->group_label }}</td>
                        <td class="text-center">{!! $s->dose_number ?: '<span class="text-warning">?</span>' !!}</td>
                        <td>{{ $s->lot_number }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('tmc-copy').addEventListener('click', function () {
    var btn = this;
    var vals = Array.prototype.map.call(document.querySelectorAll('#tmc-sheet [data-v]'), function (td) { return td.textContent.trim(); });
    var text = vals.join('\n');
    var done = function () {
        var old = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-check me-1"></i> คัดลอกแล้ว ' + vals.length + ' บรรทัด';
        setTimeout(function () { btn.innerHTML = old; }, 2000);
    };
    // หน้าเปิดผ่าน https อยู่แล้ว แต่กันไว้ — เครื่องเก่าบางเครื่องไม่มี clipboard API
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done);
    } else {
        var ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta);
        ta.select(); document.execCommand('copy'); ta.remove(); done();
    }
});
// สมุดเหลือง: กรองตามประเภท
(function () {
    var btns = document.querySelectorAll('.js-book-kind');
    btns.forEach(function (b) {
        b.addEventListener('click', function () {
            var k = b.dataset.kind;
            document.querySelectorAll('#tmc-book-table tbody tr[data-kind]').forEach(function (tr) {
                tr.style.display = (k === '' || k.split(',').indexOf(tr.dataset.kind) > -1) ? '' : 'none';
            });
            btns.forEach(function (x) {
                x.classList.toggle('btn-secondary', x === b);
                x.classList.toggle('btn-outline-secondary', x !== b);
            });
        });
    });
})();

// สมุดส่งทำลาย → ไฟล์ CSV (UTF-8 BOM ให้ Excel อ่านภาษาไทยถูก) สร้างจากตารางในหน้า ไม่ต้องโหลดใหม่
(function () {
    var b = document.getElementById('tmc-destroy-csv');
    if (!b) return;
    b.addEventListener('click', function () {
        var q = function (t) { t = String(t).replace(/\s+/g, ' ').trim(); return '"' + t.replace(/"/g, '""') + '"'; };
        var lines = Array.prototype.map.call(document.querySelectorAll('#tmc-destroy-table tr'), function (tr) {
            return Array.prototype.map.call(tr.cells, function (c) { return q(c.innerText); }).join(',');
        });
        var blob = new Blob(['\ufeff' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'สมุดเหลืองส่งทำลาย_{{ $start->format('Y-m') }}.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
    });
})();

// ชื่อ/ตำแหน่งผู้รายงานที่แก้ไว้ จำในเบราว์เซอร์เครื่องนี้ (หมายเหตุไม่จำ เปลี่ยนทุกเดือน)
document.querySelectorAll('.tmc-edit').forEach(function (el) {
    if (el.dataset.key === 'note') return;
    var k = 'tmcReportSign.' + el.dataset.key;
    try { var v = localStorage.getItem(k); if (v) { el.textContent = v; } } catch (e) {}
    el.addEventListener('input', function () {
        try { localStorage.setItem(k, el.textContent.trim()); } catch (e) {}
    });
});

// กรองรายชื่อตามประเทศ — ช่องเลือกกับปุ่มชื่อประเทศใช้ตัวเดียวกัน กดปุ่มเดิมซ้ำ = กลับมาทุกประเทศ
(function () {
    var f = document.getElementById('tmc-dest-filter');
    if (!f) return;
    function apply() {
        var c = f.value;
        document.querySelectorAll('#tmc-dest-table tbody tr').forEach(function (tr) {
            var codes = tr.dataset.codes;
            tr.style.display = (c === '' || (c === '-' ? codes === '-' : codes.indexOf(',' + c + ',') > -1)) ? '' : 'none';
        });
        document.querySelectorAll('.js-dest-chip').forEach(function (b) {
            b.classList.toggle('btn-success', b.dataset.code === c);
            b.classList.toggle('btn-outline-success', b.dataset.code !== c);
        });
    }
    f.addEventListener('change', apply);
    document.querySelectorAll('.js-dest-chip').forEach(function (b) {
        b.addEventListener('click', function () { f.value = (f.value === b.dataset.code) ? '' : b.dataset.code; apply(); });
    });
})();
</script>
@endsection
