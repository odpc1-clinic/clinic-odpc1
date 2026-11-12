@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <a href="{{ route('medicine-lot.index') }}" class="btn btn-warning btn-sm mb-3"><i class="fa fa-arrow-left"></i> กลับคลังยา</a>

    <div class="card shadow-sm mb-3">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-pills text-primary"></i> {{ $medicine->name }}
                <span class="badge bg-secondary">คงคลังรวม {{ $medicine->totalLotStock() }} {{ $medicine->unit }}</span>
            </h5>
        </div>
        <div class="card-body">
            {{-- ฟอร์มรับยาเข้าคลัง --}}
            <form method="POST" action="{{ route('medicine-lot.store', $medicine->id) }}" class="row g-2 mb-4 p-3 bg-light rounded border">
                @csrf
                <div class="col-12"><b><i class="fas fa-truck-ramp-box"></i> รับยาเข้าคลัง (lot ใหม่)</b></div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">Lot number</label>
                    <input type="text" name="lot_number" class="form-control form-control-sm" placeholder="เช่น A2569">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">วันรับเข้า</label>
                    <input type="text" name="received_date" value="{{ \App\Models\MedicineLot::toThDate(now()) }}"
                           class="form-control form-control-sm date" placeholder="ว/ด/ป (พ.ศ.)" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">วันหมดอายุ</label>
                    <input type="text" name="expiry_date" class="form-control form-control-sm date"
                           placeholder="ว/ด/ป (พ.ศ.)" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">จำนวน <span class="text-danger">*</span></label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="quantity" min="1" required class="form-control form-control-sm">
                        <span class="input-group-text">{{ $medicine->unit ?: 'หน่วย' }}</span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">ราคาทุน/หน่วย</label>
                    <input type="number" step="0.01" name="cost_price" min="0" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0">ราคาขาย/หน่วย</label>
                    <input type="number" step="0.01" name="sale_price" min="0" class="form-control form-control-sm">
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-plus"></i> รับเข้าคลัง</button>
                </div>
            </form>

            {{-- ตาราง lot (เรียงแบบ FEFO: ใกล้หมดอายุอยู่บน = เบิกก่อน) --}}
            <h6 class="text-muted"><i class="fas fa-sort-amount-down"></i> รายการ Lot (เรียงตามวันหมดอายุ — เบิกจากบนลงล่าง FEFO)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Lot</th>
                            <th>วันรับเข้า</th>
                            <th>วันหมดอายุ</th>
                            <th class="text-end">รับเข้า</th>
                            <th class="text-end">คงเหลือ</th>
                            <th class="text-end">ทุน</th>
                            <th class="text-end">ขาย</th>
                            <th>สถานะ</th>
                            <th style="width:130px;">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($lots as $lot)
                        <tr class="{{ $lot->quantity_remaining <= 0 ? 'text-muted' : '' }}">
                            <td>{{ $lot->lot_number ?? '-' }}</td>
                            <td>{{ $lot->received_date_th ?: '-' }}</td>
                            <td>{{ $lot->expiry_date_th ?: '-' }}</td>
                            <td class="text-end">{{ number_format($lot->quantity_received) }} <span class="text-muted small">{{ $medicine->unit }}</span></td>
                            <td class="text-end fw-bold">{{ number_format($lot->quantity_remaining) }} <span class="text-muted small fw-normal">{{ $medicine->unit }}</span></td>
                            <td class="text-end">{{ number_format($lot->cost_price, 2) }}</td>
                            <td class="text-end">{{ number_format($lot->sale_price, 2) }}</td>
                            <td>
                                @if($lot->quantity_remaining <= 0)
                                    <span class="badge bg-secondary">หมด</span>
                                @elseif($lot->isExpired())
                                    <span class="badge bg-dark">หมดอายุแล้ว</span>
                                @elseif($lot->isExpiringSoon())
                                    <span class="badge bg-warning text-dark">ใกล้หมดอายุ</span>
                                @else
                                    <span class="badge bg-success">ปกติ</span>
                                @endif
                            </td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-warning" title="ปรับยอด"
                                    data-bs-toggle="modal" data-bs-target="#adjustModal{{ $lot->id }}"><i class="fas fa-sliders-h"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="แก้ไขข้อมูล"
                                    data-bs-toggle="modal" data-bs-target="#editModal{{ $lot->id }}"><i class="fas fa-pen"></i></button>
                                <form method="POST" action="{{ route('medicine-lot.destroy', $lot->id) }}" class="d-inline"
                                    onsubmit="return confirm('ลบ lot {{ $lot->lot_number ?? $lot->id }} ?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-3">ยังไม่มี lot ในคลัง (ยอดคงคลัง 0)</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modals: ปรับยอด + แก้ไข ต่อ lot --}}
@foreach($lots as $lot)
<div class="modal fade" id="adjustModal{{ $lot->id }}" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('medicine-lot.adjust', $lot->id) }}">
      @csrf
      <div class="modal-header"><h6 class="modal-title">ปรับยอดคงเหลือ — Lot {{ $lot->lot_number ?? $lot->id }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small">จำนวนคงเหลือใหม่ (ปัจจุบัน {{ $lot->quantity_remaining }} {{ $medicine->unit }})</label>
          <input type="number" name="quantity_remaining" min="0" value="{{ $lot->quantity_remaining }}" required class="form-control"></div>
        <div class="mb-2"><label class="form-label small">เหตุผล <span class="text-danger">*</span></label>
          <input type="text" name="reason" required class="form-control" placeholder="เช่น นับสต๊อกผิด, ของเสียหาย, หมดอายุตัดออก"></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-warning">บันทึกปรับยอด</button></div>
    </form>
  </div></div>
</div>
<div class="modal fade" id="editModal{{ $lot->id }}" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('medicine-lot.update', $lot->id) }}">
      @csrf @method('PUT')
      <div class="modal-header"><h6 class="modal-title">แก้ไขข้อมูล Lot {{ $lot->lot_number ?? $lot->id }}</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label small">Lot number</label>
          <input type="text" name="lot_number" value="{{ $lot->lot_number }}" class="form-control"></div>
        <div class="mb-2"><label class="form-label small">วันรับเข้า</label>
          <input type="text" name="received_date" value="{{ $lot->received_date_th }}" class="form-control date" placeholder="ว/ด/ป (พ.ศ.)" autocomplete="off"></div>
        <div class="mb-2"><label class="form-label small">วันหมดอายุ</label>
          <input type="text" name="expiry_date" value="{{ $lot->expiry_date_th }}" class="form-control date" placeholder="ว/ด/ป (พ.ศ.)" autocomplete="off"></div>
        <div class="row"><div class="col"><label class="form-label small">ราคาทุน</label>
          <input type="number" step="0.01" name="cost_price" value="{{ $lot->cost_price }}" class="form-control"></div>
          <div class="col"><label class="form-label small">ราคาขาย</label>
          <input type="number" step="0.01" name="sale_price" value="{{ $lot->sale_price }}" class="form-control"></div></div>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-secondary">บันทึก</button></div>
    </form>
  </div></div>
</div>
@endforeach
@endsection

@push('scripts')
<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>
<script>
    // ปฏิทิน พ.ศ. ให้เหมือนหน้าอื่นในระบบ (ค่าที่ส่งไปเป็น d/m/พ.ศ. ตัว controller แปลงเป็น ค.ศ. ก่อนเก็บ)
    $(function () {
        $('.date').datepicker({ language: 'th-th', autoclose: true, format: 'dd/mm/yyyy' });
    });
</script>
@endpush
