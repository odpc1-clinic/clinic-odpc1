@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-hand-holding-medical text-primary me-2"></i>จัดการค่าบริการ (Services)</h5>
                    <a href="{{ route('medicine.index') }}" class="btn btn-outline-secondary btn-sm">กลับ</a>
                </div>
                <div class="card-body">
                    {{-- เพิ่มค่าบริการใหม่ --}}
                    <form method="POST" action="{{ route('service-price.store') }}" class="row g-2 mb-4 p-3 bg-light rounded border">
                        @csrf
                        <div class="col-12"><b><i class="fas fa-plus"></i> เพิ่มค่าบริการใหม่</b></div>
                        <div class="col-md-4">
                            <input type="text" name="name" required class="form-control" placeholder="ชื่อค่าบริการ เช่น ค่าทำแผล">
                        </div>
                        <div class="col-md-2">
                            <input type="number" name="price" step="0.01" min="0" required class="form-control" placeholder="ราคา (บาท)">
                        </div>
                        <div class="col-md-2 d-flex align-items-center gap-2 flex-wrap" title="ใช้กับคลินิกไหน (ติ๊กครบ = ทุกคลินิก)">
                            @foreach(\App\Models\ServicePrice::CLINICS as $__c => $__l)
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input" type="checkbox" name="clinics[]" value="{{ $__c }}" id="new_clinic_{{ $__c }}" checked>
                                    <label class="form-check-label small" for="new_clinic_{{ $__c }}">{{ $__l }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <div class="form-check">
                                <input type="hidden" name="is_package" value="0">
                                <input class="form-check-input" type="checkbox" name="is_package" value="1" id="new_is_package">
                                <label class="form-check-label" for="new_is_package" title="แพ็กเกจเหมาจ่าย: ห้ามขอความอนุเคราะห์">แพ็กเกจ</label>
                            </div>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-success">เพิ่ม</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ชื่อค่าบริการ</th>
                                    <th style="width:140px;" class="text-end">ราคา (บาท)</th>
                                    <th style="width:100px;" class="text-center">แพ็กเกจ</th>
                                    <th style="width:190px;" class="text-center">ใช้กับคลินิก</th>
                                    <th style="width:110px;" class="text-center">สถานะ</th>
                                    <th style="width:190px;" class="text-center">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($services as $svc)
                                {{-- ใช้ HTML5 form attribute เพื่อให้ input ข้ามหลาย <td> อยู่ในฟอร์มเดียวได้ (valid HTML) --}}
                                <tr class="{{ $svc->is_active ? '' : 'text-muted' }}">
                                    <td>
                                        <form id="svcform{{ $svc->id }}" method="POST" action="{{ route('service-price.update', $svc->id) }}">@csrf @method('PUT')</form>
                                        <input form="svcform{{ $svc->id }}" type="text" name="name" value="{{ $svc->name }}" class="form-control form-control-sm">
                                    </td>
                                    <td>
                                        <input form="svcform{{ $svc->id }}" type="number" name="price" value="{{ $svc->price }}" step="0.01" min="0" class="form-control form-control-sm text-end">
                                    </td>
                                    <td class="text-center">
                                        <input form="svcform{{ $svc->id }}" type="hidden" name="is_package" value="0">
                                        <input form="svcform{{ $svc->id }}" class="form-check-input" type="checkbox" name="is_package" value="1" {{ $svc->is_package ? 'checked' : '' }} style="transform:scale(1.3);" title="แพ็กเกจเหมาจ่าย: ห้ามขอความอนุเคราะห์">
                                    </td>
                                    {{-- คลินิกที่ใช้รายการนี้ — ไม่ติ๊ก = หน้าตรวจของคลินิกนั้นไม่แสดงรายการนี้ (แก้แล้วกดปุ่มบันทึกของแถว) --}}
                                    <td class="text-center text-nowrap">
                                        <input form="svcform{{ $svc->id }}" type="hidden" name="clinics_on" value="1">
                                        @foreach(\App\Models\ServicePrice::CLINICS as $__c => $__l)
                                            <div class="form-check form-check-inline m-0 me-1">
                                                <input form="svcform{{ $svc->id }}" class="form-check-input" type="checkbox" name="clinics[]" value="{{ $__c }}"
                                                       id="svc{{ $svc->id }}_c{{ $__c }}" {{ $svc->availableFor($__c) ? 'checked' : '' }}>
                                                <label class="form-check-label small" for="svc{{ $svc->id }}_c{{ $__c }}">{{ $__l }}</label>
                                            </div>
                                        @endforeach
                                    </td>
                                    <td class="text-center">
                                        @if($svc->is_active)
                                            <span class="badge bg-success">ใช้งาน</span>
                                        @else
                                            <span class="badge bg-secondary">ปิด</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <button form="svcform{{ $svc->id }}" type="submit" class="btn btn-sm btn-primary" title="บันทึก"><i class="fas fa-save"></i></button>
                                        <form method="POST" action="{{ route('service-price.toggle', $svc->id) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="เปิด/ปิดใช้งาน"><i class="fas fa-power-off"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('service-price.destroy', $svc->id) }}" class="d-inline" onsubmit="return confirm('ลบค่าบริการ {{ $svc->name }} ?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="ลบ"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีค่าบริการ</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="text-muted small"><i class="fas fa-circle-info"></i> ปิดใช้งานค่าบริการจะไม่ขึ้นในหน้าบิล แต่ประวัติบิลเดิมยังอยู่ครบ ·
                        "ใช้กับคลินิก" = หน้าตรวจของคลินิกที่ไม่ได้ติ๊กจะไม่แสดงรายการนี้ (visit ที่ติ๊กไว้แล้วยังเห็นเหมือนเดิม) · ติ๊กครบหรือไม่ติ๊กเลย = ทุกคลินิก ·
                        แก้แล้วกดปุ่ม <i class="fas fa-save"></i> ของแถวนั้น</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
