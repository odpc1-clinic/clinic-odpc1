@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i class="fas fa-triangle-exclamation text-warning"></i> รายงานคลังยา (หมดอายุ / ใกล้หมดอายุ / สต็อกต่ำ)</h5>
        <a href="{{ route('medicine-lot.index') }}" class="btn btn-outline-secondary btn-sm">กลับคลังยา</a>
    </div>

    {{-- หมดอายุแล้ว --}}
    <div class="card shadow-sm mb-3 border-danger">
        <div class="card-header bg-danger text-white">
            <i class="fas fa-ban"></i> ยาหมดอายุแล้ว (ยังมีของค้างในคลัง) — {{ $expired->count() }} รายการ
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>ยา</th><th>Lot</th><th>วันหมดอายุ</th><th class="text-end">คงเหลือ</th></tr></thead>
                <tbody>
                @forelse($expired as $lot)
                    <tr class="table-danger">
                        <td>{{ $lot->medicine->name ?? '-' }}</td>
                        <td>{{ $lot->lot_number ?? '-' }}</td>
                        <td>{{ $lot->expiry_date_th }} <span class="small text-danger">(เลยมา {{ (int) $lot->expiry_date->diffInDays(now(), true) }} วัน)</span></td>
                        <td class="text-end fw-bold">{{ $lot->quantity_remaining }} {{ $lot->medicine->unit ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-2">ไม่มียาหมดอายุค้างในคลัง</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ใกล้หมดอายุ --}}
    <div class="card shadow-sm mb-3 border-warning">
        <div class="card-header bg-warning">
            <i class="fas fa-hourglass-half"></i> ยาใกล้หมดอายุภายใน
            <form method="GET" class="d-inline">
                <input type="number" name="days" value="{{ $days }}" min="1" style="width:70px;" class="form-control form-control-sm d-inline-block">
                <button class="btn btn-sm btn-dark">วัน</button>
            </form>
            — {{ $expiringSoon->count() }} รายการ
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>ยา</th><th>Lot</th><th>วันหมดอายุ</th><th class="text-end">คงเหลือ</th></tr></thead>
                <tbody>
                @forelse($expiringSoon as $lot)
                    <tr>
                        <td>{{ $lot->medicine->name ?? '-' }}</td>
                        <td>{{ $lot->lot_number ?? '-' }}</td>
                        <td>{{ $lot->expiry_date_th }} <span class="small text-muted">(อีก {{ (int) now()->diffInDays($lot->expiry_date, true) }} วัน)</span></td>
                        <td class="text-end fw-bold">{{ $lot->quantity_remaining }} {{ $lot->medicine->unit ?? '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-2">ไม่มียาใกล้หมดอายุในช่วงนี้</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- สต็อกต่ำ --}}
    <div class="card shadow-sm mb-3 border-info">
        <div class="card-header bg-info text-white">
            <i class="fas fa-arrow-trend-down"></i> ยาสต็อกต่ำกว่าจุดสั่งซื้อ — {{ $lowStock->count() }} รายการ
        </div>
        <div class="card-body p-0">
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>ยา</th><th class="text-end">คงคลังรวม</th><th class="text-end">จุดสั่งซื้อ</th><th></th></tr></thead>
                <tbody>
                @forelse($lowStock as $m)
                    <tr>
                        <td>{{ $m->name }}</td>
                        <td class="text-end fw-bold text-danger">{{ $m->totalLotStock() }} {{ $m->unit }}</td>
                        <td class="text-end">{{ $m->reorder_point }}</td>
                        <td><a href="{{ route('medicine-lot.show', $m->id) }}" class="btn btn-sm btn-outline-primary">รับเข้า</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-2">ไม่มียาสต็อกต่ำ</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
