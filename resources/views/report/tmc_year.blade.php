@extends('layouts.app')

@section('content')
@php
    $thShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    // แถวของตารางรายเดือน: ใช้ชุดแถวของ "รวมทั้งปี" (มีแถววัคซีนพิเศษครบทุกเดือน) แล้วหาค่าของแต่ละเดือนด้วยชื่อแถว
    $keyOf = fn ($r) => str_starts_with($r['label'], '4.') ? 'vac|' . preg_replace('/^4\.\d+\s+/u', '', $r['label']) : $r['no'] . '|' . $r['label'];
    $byMonth = [];
    foreach ($months as $mi => $m) { foreach ($m['rows'] as $r) { $byMonth[$mi][$keyOf($r)] = $r; } }
@endphp
<style>
    #tmc-year { font-size: 1rem; }
    #tmc-year .num { text-align: right; font-variant-numeric: tabular-nums; }
    #tmc-year table td, #tmc-year table th { padding: .3rem .45rem; vertical-align: middle; }
    #tmc-year thead th { background: #f1f4f8; font-size: .88rem; }
    #tmc-year .tmc-dir-head td { background: #f8fafc; }
    @media print {
        nav, .no-print { display: none !important; }
        #tmc-year .table-responsive { overflow: visible; }
        body.print-month #tmc-total-card { display: none !important; }
        body.print-total #tmc-month-card { display: none !important; }
    }
</style>

<div class="container-fluid py-3" id="tmc-year">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
        <h4 class="mb-0 me-3"><i class="fa-solid fa-plane-departure me-2"></i>รายงาน TMC ส่ง ผอ. — ปีงบประมาณ {{ $fy }}</h4>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('report.tmc.year', ['fy' => $fy - 1]) }}"><i class="fa fa-chevron-left"></i> ปีงบ {{ $fy - 1 }}</a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('report.tmc.year', ['fy' => $fy + 1]) }}">ปีงบ {{ $fy + 1 }} <i class="fa fa-chevron-right"></i></a>
        <a class="btn btn-outline-primary btn-sm ms-auto" href="{{ route('report.tmc') }}"><i class="fa fa-arrow-left me-1"></i> รายงานรายเดือน</a>
        <button type="button" class="btn btn-dark btn-sm" onclick="document.body.classList.add('print-total'); window.print(); document.body.classList.remove('print-total');"><i class="fa fa-print me-1"></i> พิมพ์รวมทั้งปี</button>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="document.body.classList.add('print-month'); window.print(); document.body.classList.remove('print-month');"><i class="fa fa-print me-1"></i> พิมพ์แยกรายเดือน</button>
    </div>

    @if(!$months)
        <div class="alert alert-light border">ยังไม่มีข้อมูลของปีงบประมาณนี้</div>
    @else
    <div class="alert alert-light border py-2 small no-print">
        <i class="fa fa-circle-info me-1 text-primary"></i>
        ระบบเริ่มบันทึก TMC ตั้งแต่ ส.ค. 2569 — เดือนก่อนหน้านั้นจะได้ 0 ·
        รวมทั้งปี = ผลรวมรายเดือน (คนที่มาหลายเดือนนับทุกเดือน เหมือนชีท Total) · กดชื่อเดือนเพื่อดูรายละเอียดเดือนนั้น
    </div>

    {{-- รวมทั้งปี — แบบชีท "Total" --}}
    <div class="card mb-3" id="tmc-total-card"><div class="card-body">
        @include('report._tmc_director', ['rows' => $total, 'periodText' => 'ประจำปีงบประมาณ ' . $fy
            . ' (' . $thShort[$months[0]['start']->month] . ' ' . ($months[0]['start']->year + 543) . ' – '
            . $thShort[end($months)['start']->month] . ' ' . (end($months)['start']->year + 543) . ')'])
    </div></div>

    {{-- แยกรายเดือน — แบบชีท "รวมรายเดือน" (ยอดรวมของแต่ละเดือน) --}}
    <div class="card" id="tmc-month-card"><div class="card-body">
        <div class="text-center mb-2">
            <div class="fw-bold" style="font-size:1.1rem">แบบรายงานผลการให้บริการคลินิกเวชศาสตร์การเดินทางและท่องเที่ยว — แยกรายเดือน</div>
            <div>สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ · ปีงบประมาณ {{ $fy }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered mb-0">
                <thead>
                    <tr>
                        <th class="text-center">ลำดับ</th><th>กิจกรรม</th><th class="text-center">หน่วยนับ</th>
                        @foreach($months as $m)
                            <th class="text-end"><a class="no-print-link" href="{{ route('report.tmc', ['m' => $m['start']->format('Y-m')]) }}">{{ $thShort[$m['start']->month] }}</a></th>
                        @endforeach
                        <th class="text-end">รวม</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($total as $r)
                    <tr class="{{ $r['sub'] ? '' : 'tmc-dir-head' }}">
                        <td class="text-center">{{ $r['no'] }}</td>
                        <td class="{{ $r['sub'] ? 'ps-4' : 'fw-bold' }}">{{ $r['label'] }}</td>
                        <td class="text-center">{{ $r['unit'] }}</td>
                        @foreach($months as $mi => $m)
                            @php $c = $byMonth[$mi][$keyOf($r)] ?? null; @endphp
                            <td class="num">{{ !empty($r['na']) ? '—' : number_format($c['total'] ?? 0) }}</td>
                        @endforeach
                        <td class="num fw-bold">{{ !empty($r['na']) ? '—' : number_format($r['total']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div></div>
    @endif
</div>
@endsection
