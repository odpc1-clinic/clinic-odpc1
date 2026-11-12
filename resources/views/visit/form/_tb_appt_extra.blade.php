{{--
    ช่องเสริมของแถวนัดสำหรับคลินิกวัณโรค — เวลา · แพทย์ที่ทำการนัด · ที่ต้องดำเนินการก่อนพบแพทย์
    (คลินิกขอ 2569-09-29) กรอกที่นี่ก่อน แล้วใบนัดวัณโรคดึงไปพิมพ์

    ตัวแปร: $a = แถวนัด (VisitAppointment) หรือ null ถ้าเป็นแถวว่าง · $docs = รายชื่อแพทย์

    ⚠️ ทุกแถวในตารางต้องมีช่องชุดนี้ครบเท่ากัน เพราะฝั่งเซิร์ฟเวอร์จับคู่ appt_time[] / appt_pre[] /
       appt_doctor[] กับ appt_date[] ตามลำดับแถว — ถ้าบางแถวไม่มี ค่าจะไปตกผิดแถว
       แถวที่กด "เพิ่มนัด" โคลนมาจากแถวสุดท้าย จึงได้ช่องครบเอง
--}}
@php
    $__t   = $a->appt_time ?? '';
    $__doc = $a ? ($a->doctor_id ?? '') : ($visit->doctor_by ?? '');
    // ช่วงเวลาที่คลินิกเปิดนัด ทุก 30 นาที
    $__slots = [];
    for ($__h = 8; $__h <= 16; $__h++) {
        foreach (['00', '30'] as $__m) {
            if ($__h === 16 && $__m === '30') { continue; }
            $__slots[] = sprintf('%02d:%s', $__h, $__m);
        }
    }
@endphp
<div class="d-flex gap-1 mt-1">
    <select name="appt_time[]" class="form-select form-select-sm" style="width:7.5rem;" title="เวลานัด">
        <option value="">— เวลา —</option>
        @foreach($__slots as $__s)
            <option value="{{ $__s }}" {{ $__t === $__s ? 'selected' : '' }}>{{ $__s }} น.</option>
        @endforeach
        {{-- เวลาที่บันทึกไว้แต่ไม่อยู่ในช่วงปกติ ต้องยังเลือกค้างไว้ได้ ไม่งั้นกด Save แล้วหาย --}}
        @if($__t !== '' && ! in_array($__t, $__slots, true))
            <option value="{{ $__t }}" selected>{{ $__t }} น.</option>
        @endif
    </select>
    <select name="appt_doctor[]" class="form-select form-select-sm" title="แพทย์ที่ทำการนัด" data-new-default="{{ $visit->doctor_by ?? '' }}">
        <option value="">— แพทย์ที่ทำการนัด —</option>
        @foreach($docs as $__d)
            <option value="{{ $__d->id }}" {{ (string) $__doc === (string) $__d->id ? 'selected' : '' }}>{{ $__d->name }}</option>
        @endforeach
    </select>
</div>
<div class="input-group input-group-sm mt-1">
    <span class="input-group-text" style="font-size:.78rem;">ก่อนพบแพทย์</span>
    <input type="text" name="appt_pre[]" class="form-control js-ar-box" maxlength="500"
           style="background:#fff8e1;" value="{{ $a->pre_visit ?? '' }}"
           placeholder="ที่ต้องดำเนินการก่อนพบแพทย์ เช่น CXR PA upright + AFB x2">
    <button type="button" class="btn btn-outline-secondary js-ar-pick"
            data-bs-toggle="modal" data-bs-target="#appointment_reason_model"
            title="เลือกข้อความสำเร็จรูป">📋</button>
</div>
