@extends('layouts.app')

@section('content')
<div class="container">
    <h5>แก้ไขข้อมูลการติดตามผู้ป่วย</h5>

    <form action="{{ route('followups.update', $followup->id) }}" method="POST">
        @csrf
        @method('PUT')

        <input type="hidden" name="page" value="{{ request()->get('page', 1) }}">
        @if(request('back_tb'))<input type="hidden" name="back_tb" value="{{ (int) request('back_tb') }}">@endif
        <input type="hidden" name="patient_id" value="{{ $visit->patient_id }}">

        <div class="mb-3">
            <label for="method" class="form-label">ช่องทาง</label>
            <select name="method" id="method" class="form-control" required>
                <option value="โทรศัพท์" {{ $followup->method == 'โทรศัพท์' ? 'selected' : '' }}>โทรศัพท์</option>
                <option value="ไลน์" {{ $followup->method == 'ไลน์' ? 'selected' : '' }}>ไลน์</option>
                <option value="อีเมล" {{ $followup->method == 'อีเมล' ? 'selected' : '' }}>อีเมล</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="followup_count" class="form-label">จำนวนครั้ง</label>
            <input type="number" name="followup_count" class="form-control" value="{{ $followup->followup_count }}" min="1" required>
        </div>

        <div class="mb-3">
            <label for="status" class="form-label">สถานะ</label>
            <select name="status" id="status" class="form-control" required>
                <option value="สำเร็จ" {{ $followup->status == 'สำเร็จ' ? 'selected' : '' }}>สำเร็จ</option>
                <option value="ไม่สำเร็จ" {{ $followup->status == 'ไม่สำเร็จ' ? 'selected' : '' }}>ไม่สำเร็จ</option>
            </select>
        </div>

        <div class="mb-3">
            <label for="remark" class="form-label">หมายเหตุ</label>
            <textarea name="remark" class="form-control" rows="3">{{ $followup->remark }}</textarea>
        </div>

        <!-- ช่องติ๊กแจ้งเปลี่ยนเบอร์ -->
<div class="mb-3">
    <input type="hidden" name="phone_changed" value="0">
    <input type="checkbox" name="phone_changed" id="phone_changed" value="1"
        {{ $visit->patient2 && $visit->patient2->phone_changed ? 'checked' : '' }}>
    <label for="phone_changed" class="text-danger fw-bold">แจ้งเวชระเบียนให้เปลี่ยนเบอร์โทรศัพท์</label>
</div>


        <button type="submit" class="btn btn-primary">บันทึกการแก้ไข</button>
        <a href="{{ route('visit.edit', ['visit' => $visit->id, 'page' => request()->get('page', 1)]) }}" class="btn btn-secondary">ยกเลิก</a>
    </form>
</div>
@endsection
