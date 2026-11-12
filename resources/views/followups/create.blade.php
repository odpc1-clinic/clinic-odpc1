@extends('layouts.app')

@section('content')
    <div class="container mt-4">
        <h5 class="mb-4">เพิ่มการติดตามผู้ป่วย</h5>

        <form action="{{ route('visit_followups.store') }}" method="POST">
            @csrf
            <input type="hidden" name="visit_id" value="{{ $visit->id }}">
            {{-- เปิดมาจากบัตร TB 01 → บันทึกแล้วกลับไปที่บัตร --}}
            @if(request('back_tb'))<input type="hidden" name="back_tb" value="{{ (int) request('back_tb') }}">@endif
            <input type="hidden" name="patient_id" value="{{ $visit->patient_id }}">
            {{-- ช่องทางติดต่อของคนไข้ — ดูได้เลยไม่ต้องกลับไปเปิดหน้าลงทะเบียน (2569-09-30) --}}
            @php $__fp = \App\Models\Visit::getPatient($visit); @endphp
            <div class="alert alert-light border py-2 small">
                <i class="fa fa-address-book me-1"></i>
                โทร <b>{{ $__fp->tel ?? '—' }}</b> ·
                อีเมล <b>@if(! empty($__fp->email))<a href="mailto:{{ $__fp->email }}">{{ $__fp->email }}</a>@else — @endif</b> ·
                LINE <b>{{ ! empty($__fp->line_user_id) ? 'ผูกแล้ว' : 'ยังไม่ผูก' }}</b>
            </div>

            <div class="mb-3">
                <label for="method" class="form-label">ช่องทาง</label>
                <select name="method" id="method" class="form-control">
                    <option value="โทรศัพท์">โทรศัพท์</option>
                    <option value="ไลน์">ไลน์</option>
                    <option value="อีเมล">อีเมล</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="followup_count" class="form-label">จำนวนครั้ง</label>
                <input type="number" name="followup_count" id="followup_count" class="form-control" value="1" min="1">
            </div>

            <div class="mb-3">
                <label for="status" class="form-label">สถานะ</label>
                <select name="status" id="status" class="form-control">
                    <option value="สำเร็จ">สำเร็จ</option>
                    <option value="ไม่สำเร็จ">ไม่สำเร็จ</option>
                </select>
            </div>

            <div class="mb-3">
                <label for="remark" class="form-label">หมายเหตุ</label>
                <textarea name="remark" id="remark" class="form-control" rows="3" placeholder="กรอกหมายเหตุเพิ่มเติม (ถ้ามี)"></textarea>
            </div>

            <!-- เพิ่มปุ่มเช็คช่องแจ้งเวชระเบียนให้เปลี่ยนเบอร์โทร -->
           <div class="form-check mb-3">
    <input type="hidden" name="phone_changed" value="0">
    <input type="checkbox" name="phone_changed" id="phone_changed" value="1" class="form-check-input">
    <label for="phone_changed" class="form-check-label text-danger fw-bold">
        เช็คช่องนี้เพื่อแจ้งเวชระเบียนให้เปลี่ยนเบอร์โทรศัพท์
    </label>
</div>


            <button type="submit" class="btn btn-primary">บันทึกการติดตาม</button>
            <a href="{{ route('visit.edit', ['visit' => $visit->id, 'page' => request()->get('page', 1)]) }}" class="btn btn-secondary">ยกเลิก</a>
        </form>
    </div>
@endsection
