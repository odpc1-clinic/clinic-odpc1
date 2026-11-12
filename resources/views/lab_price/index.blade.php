@extends('layouts.app')

@section('content')
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-flask text-primary me-2"></i>ตั้งราคากลาง Lab</h5>
                    <a href="{{ route('medicine.index') }}" class="btn btn-outline-secondary btn-sm">กลับ</a>
                </div>
                <div class="card-body">
                    <div class="alert alert-info d-flex align-items-center gap-2">
                        <i class="fas fa-circle-info"></i>
                        <div class="small">
                            ราคาที่ตั้งไว้จะ <b>เด้งขึ้นอัตโนมัติ</b> ในหน้าคิดเงินห้องยา ตามแต่ละรายการตรวจ
                            (ยังพิมพ์แก้ราคารายครั้งได้) · เว้นว่าง = ไม่ตั้งราคากลาง ต้องพิมพ์เอง
                        </div>
                    </div>

                    <form method="POST" action="{{ route('lab-price.save') }}">
                        @csrf
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>รายการตรวจ (Lab)</th>
                                        <th style="width:200px;" class="text-end">ราคากลาง (บาท)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($rows as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row->name }}</td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="0.01" min="0"
                                                       name="prices[{{ $row->name }}]"
                                                       value="{{ $row->price !== null ? $row->price : '' }}"
                                                       class="form-control text-end"
                                                       placeholder="ยังไม่ตั้ง">
                                                <span class="input-group-text">฿</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-save me-1"></i> บันทึกราคาทั้งหมด
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
