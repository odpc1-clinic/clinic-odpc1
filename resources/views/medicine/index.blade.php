@extends('layouts.app')

@section('content')
<style>
    .med-hero {
        background: linear-gradient(135deg, #2b6cb0 0%, #4299e1 100%);
        border-radius: 16px;
        color: #fff;
    }
    .med-hero .stat-pill {
        background: rgba(255,255,255,.18);
        border-radius: 12px;
        padding: 10px 18px;
    }
    .med-table thead th {
        background: #f1f5f9;
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #64748b;
        border-bottom: 2px solid #e2e8f0;
    }
    .med-table tbody tr { transition: background .15s; }
    .med-table tbody tr:hover { background: #f8fafc; }
    .med-name { font-weight: 600; color: #1e293b; }
    .med-sub { font-size: .8rem; color: #94a3b8; }
    .btn-icon { width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; padding: 0; }
</style>

<div class="container-fluid py-4">

    {{-- Hero header --}}
    <div class="med-hero p-4 mb-4 shadow-sm d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h3 class="mb-1 fw-bold"><i class="fas fa-prescription-bottle-medical me-2"></i>รายการยา (Medicine Master)</h3>
            <div class="opacity-75">จัดการข้อมูลยา · เพิ่ม/แก้ไข/ลบ · เชื่อมกับระบบคลังยา (Lot)</div>
        </div>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="stat-pill text-center">
                <div class="fs-4 fw-bold">{{ number_format($totalMedicine) }}</div>
                <div class="small opacity-75">รายการยาทั้งหมด</div>
            </div>
            <a href="{{ route('medicine.create') }}" class="btn btn-light fw-bold shadow-sm">
                <i class="fas fa-plus text-success me-1"></i> เพิ่มยาใหม่
            </a>
            <a href="{{ route('medicine-lot.index') }}" class="btn btn-outline-light">
                <i class="fas fa-boxes-stacked me-1"></i> ไปคลังยา (Stock/Lot)
            </a>
            <a href="{{ route('lab-price.index') }}" class="btn btn-outline-light">
                <i class="fas fa-flask me-1"></i> ตั้งราคากลาง Lab
            </a>
            <a href="{{ route('standard-price.index') }}" class="btn btn-outline-light">
                <i class="fas fa-scale-balanced me-1"></i> ราคากลางตามประกาศ
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            {{-- Search --}}
            {{-- ค้นหาแบบ realtime (คลินิกขอ 2569-10-05) — พิมพ์แล้วกรองบนจอทันที ไม่ต้องกดค้นหา
                 ยาทั้งคลังโหลดมาหน้าเดียว · Enter ยังส่งค้นหาฝั่งเซิร์ฟเวอร์ได้ (ลิงก์ ?search= เดิมใช้ได้) --}}
            <form method="GET" class="mb-3 d-flex flex-wrap align-items-center gap-2" onsubmit="return false;">
                <div class="input-group" style="max-width: 460px;">
                    <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                    <input type="text" name="search" id="med-search" value="{{ $keyword }}" class="form-control" autocomplete="off" autofocus
                           placeholder="พิมพ์ชื่อยา / dose / วิธีใช้ — กรองทันที">
                    <button class="btn btn-outline-secondary" type="button" id="med-clear" title="ล้าง">&times;</button>
                </div>
                <span class="small text-muted" id="med-count"></span>
            </form>

            <div class="table-responsive">
                <table class="table med-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width:44px;">#</th>
                            <th>ชื่อยา</th>
                            <th>วิธีใช้</th>
                            <th class="text-end">ราคา/หน่วย</th>
                            <th class="text-center">คงคลัง</th>
                            <th class="text-center">จุดสั่งซื้อ</th>
                            <th class="text-end" style="width:170px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($medicine as $item)
                        @php $stock = $item->totalLotStock(); $low = $item->reorder_point > 0 && $stock <= $item->reorder_point; @endphp
                        <tr class="js-med-row" data-q="{{ mb_strtolower($item->name . ' ' . $item->dose . ' ' . $item->route . ' ' . $item->amount . ' ' . $item->unit) }}">
                            <td class="text-muted">{{ $loop->iteration + ($medicine->currentPage()-1)*$medicine->perPage() }}</td>
                            <td>
                                <div class="med-name">{{ $item->name }}</div>
                                @if($item->dose || $item->route)
                                    <div class="med-sub">{{ $item->dose }}@if($item->dose && $item->route) · @endif{{ $item->route }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $item->amount ?: '-' }}</td>
                            <td class="text-end">{{ number_format($item->price, 2) }}</td>
                            <td class="text-center">
                                <span class="fw-bold {{ $low ? 'text-danger' : 'text-dark' }}">{{ $stock }}</span>
                                <span class="text-muted small">{{ $item->unit }}</span>
                                @if($low)<span class="badge bg-danger ms-1">ต่ำ</span>@endif
                            </td>
                            <td class="text-center text-muted">{{ $item->reorder_point }}</td>
                            <td class="text-end">
                                <a href="{{ route('medicine-lot.show', $item->id) }}" class="btn btn-outline-success btn-sm btn-icon" title="คลังยา / รับเข้า lot">
                                    <i class="fas fa-boxes-stacked"></i>
                                </a>
                                <a href="{{ url('/medicine/' . $item->id . '/edit') }}" class="btn btn-outline-primary btn-sm btn-icon" title="แก้ไข">
                                    <i class="fa fa-pencil"></i>
                                </a>
                                <form method="POST" action="{{ url('/medicine/' . $item->id) }}" class="d-inline" onsubmit="return confirm('ยืนยันลบยา {{ $item->name }} ?')">
                                    {{ method_field('DELETE') }} {{ csrf_field() }}
                                    <button type="submit" class="btn btn-outline-danger btn-sm btn-icon" title="ลบ"><i class="fa fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-5">
                            <i class="fas fa-pills fa-2x mb-2 d-block opacity-50"></i>
                            {{ $keyword ? 'ไม่พบยาที่ค้นหา' : 'ยังไม่มีรายการยา' }}
                        </td></tr>
                    @endforelse
                    <tr id="med-none" class="d-none"><td colspan="7" class="text-center text-muted py-5">
                        <i class="fas fa-pills fa-2x mb-2 d-block opacity-50"></i>ไม่พบยาที่ค้นหา
                    </td></tr>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{!! $medicine->links() !!}</div>
        </div>
    </div>
</div>
<script>
(function () {
    var input = document.getElementById('med-search'), rows = document.querySelectorAll('.js-med-row');
    var none = document.getElementById('med-none'), count = document.getElementById('med-count');
    // ตัดช่องว่าง/วงเล็บ/ขีด ออกก่อนเทียบ — "amoxy 500" เจอ "Amoxycillin(500mg)" · หลายคำ = ต้องมีครบทุกคำ
    var norm = function (s) { return String(s || '').toLowerCase().replace(/[\s()\[\]\-_.\/,]+/g, ''); };
    rows.forEach(function (tr) { tr.dataset.n = norm(tr.dataset.q); });
    function filter() {
        var words = (input.value || '').toLowerCase().split(/\s+/).map(norm).filter(Boolean), shown = 0;
        rows.forEach(function (tr) {
            var ok = words.every(function (w) { return tr.dataset.n.indexOf(w) !== -1; });
            tr.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });
        none.classList.toggle('d-none', shown > 0 || !rows.length);
        count.textContent = words.length ? 'พบ ' + shown + ' จาก ' + rows.length + ' รายการ' : 'ทั้งหมด ' + rows.length + ' รายการ';
        // จำคำค้นไว้ใน URL — กลับมาจากหน้าแก้ไขแล้วยังกรองอยู่
        try { var u = new URL(location.href); if (input.value) u.searchParams.set('search', input.value); else u.searchParams.delete('search'); history.replaceState(null, '', u); } catch (e) {}
    }
    input.addEventListener('input', filter);
    document.getElementById('med-clear').addEventListener('click', function () { input.value = ''; filter(); input.focus(); });
    filter();
})();
</script>
@endsection
