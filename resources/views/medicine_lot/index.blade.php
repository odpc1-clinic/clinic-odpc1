@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-boxes-stacked text-primary"></i> คลังยา (Stock / Lot)</h5>
            <div class="d-flex gap-2">
                <a href="{{ route('medicine.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left"></i> กลับรายการยา</a>
                <a href="{{ route('medicine-lot.report') }}" class="btn btn-warning btn-sm"><i class="fas fa-triangle-exclamation"></i> รายงานหมดอายุ/สต็อกต่ำ</a>
            </div>
        </div>
        <div class="card-body">
            {{-- โหมดบล็อกจ่ายยาเมื่อสต๊อกไม่พอ --}}
            <div class="alert {{ $blockMode ? 'alert-danger' : 'alert-secondary' }} d-flex justify-content-between align-items-center py-2">
                <div>
                    <i class="fas fa-shield-halved"></i>
                    โหมดบล็อกเมื่อสต๊อกไม่พอ:
                    <b>{{ $blockMode ? 'เปิด (สต๊อกไม่พอ = จ่ายยาไม่ได้)' : 'ปิด (จ่ายได้แม้ไม่พอ ตัดเท่าที่มี)' }}</b>
                </div>
                <form method="POST" action="{{ route('medicine-lot.toggleBlock') }}"
                    onsubmit="return confirm('{{ $blockMode ? 'ปิดโหมดบล็อก? (จ่ายยาได้แม้สต๊อกไม่พอ)' : 'เปิดโหมดบล็อก? (สต๊อกไม่พอจะจ่ายยาไม่ได้ — ควรทำเมื่อลง stock ครบแล้ว)' }}');">
                    @csrf
                    <input type="hidden" name="enable" value="{{ $blockMode ? 0 : 1 }}">
                    <button type="submit" class="btn btn-sm {{ $blockMode ? 'btn-outline-danger' : 'btn-outline-success' }}">
                        {{ $blockMode ? 'ปิดโหมดบล็อก' : 'เปิดโหมดบล็อก' }}
                    </button>
                </form>
            </div>

            <form method="GET" class="row g-2 mb-3">
                <div class="col-md-5">
                    <input type="text" name="search" value="{{ $keyword }}" class="form-control" placeholder="ค้นหาชื่อยา">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> ค้นหา</button>
                    <a href="{{ route('medicine-lot.index') }}" class="btn btn-outline-secondary">ล้าง</a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ชื่อยา</th>
                            <th style="width:90px;">หน่วย</th>
                            <th style="width:120px;" class="text-end">คงคลังรวม</th>
                            <th style="width:120px;">จุดเตือน</th>
                            <th style="width:120px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($medicines as $m)
                        @php $total = $m->totalLotStock(); @endphp
                        <tr>
                            <td>{{ $m->name }}</td>
                            <td>{{ $m->unit }}</td>
                            <td class="text-end fw-bold {{ $total <= ($m->reorder_point ?? 0) ? 'text-danger' : '' }}">{{ $total }}</td>
                            <td>{{ $m->reorder_point }}</td>
                            <td>
                                <a href="{{ route('medicine-lot.show', $m->id) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-layer-group"></i> ดู/รับเข้า
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-3">ไม่พบยา</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $medicines->links() }}
        </div>
    </div>
</div>
@endsection
