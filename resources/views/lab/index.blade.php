@extends('layouts.app')

@section('content')
<style>
    .lab-hero { background: linear-gradient(135deg,#0f766e 0%,#0891b2 100%); border-radius:16px; color:#fff; }
    .lab-hero .stat-pill { background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.28); border-radius:12px; padding:8px 18px; }
    .lab-filter { background:#fff; border:1px solid #e5e7eb; border-radius:14px; }
    .lab-filter label { font-size:.72rem; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.03em; margin-bottom:2px; }
    .lab-table { border-collapse:separate; border-spacing:0; }
    .lab-table thead th { background:#f1f5f9; color:#334155; font-size:.78rem; text-transform:uppercase; letter-spacing:.02em;
                          border-bottom:2px solid #e2e8f0; white-space:nowrap; vertical-align:middle; }
    .lab-table tbody tr { transition:background .12s; }
    .lab-table tbody tr:hover { background:#f0fdfa; }
    .lab-table td { vertical-align:middle; }
    .hn-chip { display:inline-block; font-weight:700; color:#0f766e; background:#ccfbf1; border-radius:8px; padding:2px 10px; font-size:.85rem; }
    .result-line { font-size:.85rem; padding:2px 0; border-bottom:1px dashed #f1f5f9; }
    .result-line:last-child { border-bottom:0; }
    .result-line .m-name { font-weight:600; color:#0f172a; }
    .result-pending { color:#b45309; }
    .result-done { color:#15803d; font-weight:600; }
    .status-badge { font-size:.78rem; font-weight:600; padding:5px 12px; border-radius:999px; white-space:nowrap; }
</style>

<div class="container-fluid py-4">

    {{-- ===== Hero ===== --}}
    <div class="lab-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1 fw-bold"><i class="fas fa-vials me-2"></i>ห้องปฏิบัติการ (Laboratory)</h3>
            <div class="opacity-75">รายการตรวจทางห้องปฏิบัติการ · ลงผล · ออกรายงาน</div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="stat-pill text-center">
                <div class="fs-4 fw-bold">{{ number_format($lab->total()) }}</div>
                <div class="small opacity-75">รายการที่พบ</div>
            </div>
            @hasanyrole('Lab|Admin')
            <a href="{{ route('lab.gcId') }}" class="btn btn-light fw-bold shadow-sm">
                <i class="fas fa-microscope text-primary me-1"></i> แบบบันทึกพิสูจน์เชื้อหนองใน
            </a>
            @endhasanyrole
            <a href="{{ route('lab.export', request()->query()) }}" class="btn btn-light fw-bold shadow-sm">
                <i class="fa fa-file-excel text-success me-1"></i> Export Excel
            </a>
        </div>
    </div>

    {{-- ===== Filter ===== --}}
    <form method="GET" action="{{ route('lab.index') }}" class="lab-filter p-3 mb-4 shadow-sm">
        <div class="row g-3 align-items-end">
            <div class="col-lg-4 col-md-6">
                <label for="search">ค้นหา</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" class="form-control js-realtime-search" name="search" id="search"
                           placeholder="HN, LN, ชื่อ หรือ นามสกุล..." value="{{ request('search') }}" autocomplete="off">
                </div>
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="start_date">วันที่เริ่ม (Collected)</label>
                <input type="date" class="form-control js-realtime-submit" name="start_date" id="start_date" value="{{ request('start_date') }}">
            </div>
            <div class="col-lg-2 col-md-3 col-6">
                <label for="end_date">วันที่สิ้นสุด (Collected)</label>
                <input type="date" class="form-control js-realtime-submit" name="end_date" id="end_date" value="{{ request('end_date') }}">
            </div>
            <div class="col-lg-2 col-md-6">
                <label for="status">สถานะ</label>
                <select name="status" class="form-select js-realtime-submit" id="status">
                    <option value="">เลือกสถานะ</option>
                    <option value="all" {{ request('status') == "all" ? 'selected' : '' }}>ทั้งหมด</option>
                    @foreach(\App\Models\Lab::$statusOption as $statusKey => $statusValue)
                    <option value="{{ $statusKey }}" {{ request('status') == $statusKey ? 'selected' : '' }}>{{ $statusValue }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 d-grid">
                <button class="btn btn-primary fw-bold" type="submit" onclick="convertDates()">
                    <i class="fa fa-search me-1"></i> ค้นหา
                </button>
            </div>
        </div>
    </form>

    {{-- ===== Table ===== --}}
    <div id="rt-results">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table lab-table mb-0 align-middle" id="labTable">
                    <thead>
                        <tr>
                            <th class="ps-3">#</th>
                            <th>HN</th>
                            <th>ชื่อ - นามสกุล</th>
                            <th>LN</th>
                            <th style="min-width:260px;">ผลตรวจ (Method / Result)</th>
                            <th>Remark</th>
                            <th>Collected</th>
                            <th>สถานะ</th>
                            <th class="text-center pe-3">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($lab as $index => $item)
                        @php
                            $st = (int) $item->status;
                            $stClass = $st === 2 ? 'bg-success-subtle text-success-emphasis'
                                     : ($st === 1 ? 'bg-info-subtle text-info-emphasis'
                                     : 'bg-warning-subtle text-warning-emphasis');
                        @endphp
                        <tr>
                            <td class="ps-3 text-muted">{{ $index + 1 + (($lab->currentPage() - 1) * $lab->perPage()) }}</td>
                            <td><span class="hn-chip">{{ optional($item->patient)->code ?? '-' }}</span></td>
                            <td class="fw-semibold">
                                {{ optional($item->patient)->name }} {{ optional($item->patient)->surname }}
                                {{-- ห้อง Lab รับงานทุกคลินิก จึงบอกต้นทางไว้ --}}
                                @include('partials.clinic-badge', ['clinic' => optional($item->visit)->clinic])
                            </td>
                            <td class="text-muted">{{ $item->LN ?: '-' }}</td>
                            <td>
                                @if($item->labSub2->isNotEmpty())
                                    @foreach($item->labSub2 as $sub)
                                        <div class="result-line">
                                            <span class="m-name">{{ \App\Models\LabSub::$methodOption[$sub->method] ?? 'ไม่ระบุ' }}</span>{{ !empty($sub->method_other) ? ' ('.$sub->method_other.')' : '' }}
                                            —
                                            @if(!empty($sub->result))
                                                <span class="result-done">{{ $sub->result }}</span>
                                            @else
                                                <span class="result-pending"><i class="fas fa-hourglass-half"></i> รอลงผล</span>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <span class="result-pending"><i class="fas fa-hourglass-half"></i> รอลงผล</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $item->remark ?? '-' }}</td>
                            <td class="text-nowrap small">{{ $item->collected_date }}</td>
                            <td><span class="status-badge {{ $stClass }}">{{ \App\Models\Lab::$statusOption[$item->status] ?? 'ไม่ระบุ' }}</span></td>
                            <td class="text-center pe-3">
                                <a href="{{ route('lab.edit' , ['lab'=>$item->id,'page'=>4]) }}" class="btn btn-outline-primary btn-sm" title="ลงผล / แก้ไข Lab">
                                    <i class="fa fa-pencil"></i> ลงผล
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">
                            <i class="fas fa-vial-circle-check fa-2x mb-2 d-block opacity-50"></i>
                            ไม่พบรายการ Lab ตามเงื่อนไขที่ค้นหา
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-center mt-3">
        {!! $lab->appends(request()->query())->links('pagination::bootstrap-4') !!}
    </div>
    </div>{{-- /#rt-results --}}

    @include('sweetalert::alert')
</div>

<script>
    // เมื่อหน้าโหลด แปลงวันที่ในช่อง filter จาก พ.ศ. -> ค.ศ. เพื่อให้ input type=date แสดงถูก
    window.onload = function() {
        let s = document.getElementById('start_date');
        let e = document.getElementById('end_date');
        if (s && s.value) s.value = convertToAD(s.value);
        if (e && e.value) e.value = convertToAD(e.value);
    };
    function convertToAD(dateStr) {
        let parts = dateStr.split('-');
        if (parts.length === 3) parts[0] = parseInt(parts[0]) - 543;
        return parts.join('-');
    }
    function convertToBE(dateStr) {
        let parts = dateStr.split('-');
        // แปลงเฉพาะปี ค.ศ. (< 2500) เป็น พ.ศ. — กันแปลงซ้ำเป็น พ.ศ. ของ พ.ศ. เวลากรองแบบ realtime
        if (parts.length === 3 && parseInt(parts[0]) < 2500) parts[0] = parseInt(parts[0]) + 543;
        return parts.join('-');
    }
    // ก่อนส่งฟอร์มค้นหา แปลงกลับเป็น พ.ศ. ให้ backend
    function convertDates() {
        let s = document.getElementById('start_date');
        let e = document.getElementById('end_date');
        if (s && s.value) s.value = convertToBE(s.value);
        if (e && e.value) e.value = convertToBE(e.value);
    }
</script>
@include('partials.realtime-search')
@endsection
