{{-- <div class="form-group {{ $errors->has('patient_id') ? 'has-error' : ''}}">
    <label for="patient_id" class="control-label">{{ 'Patient Id' }}</label>
    <input class="form-control" name="patient_id" type="number" id="patient_id" value="{{ isset($lab->patient_id) ? $lab->patient_id : ''}}" >
    {!! $errors->first('patient_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($lab->visit_id) ? $lab->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div> --}}

<?php
$users = App\Models\User::pluck('name', 'id');
use Carbon\Carbon;
?>


{{-- Report By / Approve By เลือกเองไม่ได้แล้ว — ประทับจากปุ่ม Report / Approve (คนที่ login + เวลาจริง) ISO 15189 (2569-09-30)
     ผู้ทดสอบ (Test by) เลือกได้ เพราะคนที่ทดสอบอาจไม่ใช่คนที่นั่งลงผล --}}
<!-- <div class="form-group {{ $errors->has('collected_date') ? 'has-error' : ''}}">
    <label for="collected_date" class="control-label">{{ 'Collected Date' }}</label>
    <input class="form-control date" name="collected_date" type="text" id="collected_date" 
       value="{{ old('collected_date', isset($lab->collected_date) ? $lab->collected_date : \Carbon\Carbon::now()->addYears(543)->format('d/m/Y')) }}">

    {!! $errors->first('collected_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('report_date') ? 'has-error' : ''}}">
    <label for="report_date" class="control-label">{{ 'Report Date' }}</label>
    <input class="form-control date" name="report_date" type="text" id="report_date" value="{{ isset($lab->report_date) ? $lab->report_date : ''}}" >
    {!! $errors->first('report_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('LN') ? 'has-error' : ''}}">
    <label for="LN" class="control-label">{{ 'Ln' }}</label>
    <input class="form-control" name="LN" type="text" id="LN" value="{{ isset($lab->LN) ? $lab->LN : ''}}" >
    {!! $errors->first('LN', '<p class="help-block">:message</p>') !!}
</div> -->
<div class="row">
    <div class="col-md-6">
        <div class="form-group {{ $errors->has('collected_date') ? 'has-error' : ''}}">
            <label for="collected_date" class="control-label">Collected Date</label>
            <input class="form-control date" name="collected_date" type="text" id="collected_date" 
                value="{{ old('collected_date', isset($lab->collected_date) ? $lab->collected_date : \Carbon\Carbon::now()->addYears(543)->format('d/m/Y')) }}">
            {!! $errors->first('collected_date', '<p class="help-block">:message</p>') !!}
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group {{ $errors->has('collected_at') ? 'has-error' : ''}}">
    <label for="collected_at" class="control-label">Collected Time</label>
    <input class="form-control" name="collected_at" type="time" id="collected_at"
        value="{{ old('collected_at', isset($lab->collected_at) ? \Carbon\Carbon::parse($lab->collected_at)->format('H:i') : '') }}" readonly>
    {!! $errors->first('collected_at', '<p class="help-block">:message</p>') !!}
</div>

    </div>

    {{-- ผู้ทดสอบ / วันที่ทดสอบ ระดับใบ Lab (ห้อง Lab ขอให้อยู่ที่นี่ 2569-09-30)
         เลือกได้เฉพาะคนที่มีสิทธิ์ Lab (ไม่เอาบัญชีกลาง) · ชื่อว่างไว้ก่อน · วันที่ตั้งต้นวันนี้ --}}
    @php
        $__labStaff = \App\Models\User::role('Lab')->where('name', '!=', 'Admin admin')->orderBy('name')->pluck('name', 'id');
        // ยังไม่ได้เลือก = ตั้งต้นเป็นผู้รับ Lab (ห้อง Lab ขอ 2569-09-30) — เปลี่ยนเป็นคนอื่นได้
        $__curT = old('tested_by', ($lab->tested_by ?? null) ?: ($lab->received_by ?? ''));
        if ($__curT && ! $__labStaff->has($__curT)) { $__labStaff->put($__curT, $users[$__curT] ?? $__curT); }
    @endphp
    <div class="col-md-6">
        <div class="form-group">
            <label for="tested_by" class="control-label">Tested By</label>
            <select class="form-control" name="tested_by" id="tested_by">
                <option value="">— เลือกผู้ทดสอบ —</option>
                @foreach ($__labStaff as $key => $value)
                    <option value="{{ $key }}" @if ((string) $__curT === (string) $key) selected="selected" @endif>{{ $value }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="tested_date" class="control-label">Tested Date</label>
            <input class="form-control date" name="tested_date" type="text" id="tested_date"
                value="{{ old('tested_date', ! empty($lab->tested_date) ? $lab->tested_date : \Carbon\Carbon::now()->addYears(543)->format('d/m/Y')) }}">
        </div>
    </div>
</div>
<div class="form-group {{ $errors->has('remark') ? 'has-error' : ''}}">
    <label for="remark" class="control-label">Remark</label>
    <textarea class="form-control" name="remark" id="remark" rows="3">{{ old('remark', isset($lab->remark) ? $lab->remark : '') }}</textarea>
    {!! $errors->first('remark', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group {{ $errors->has('status') ? 'has-error' : ''}}">
    <label for="status" class="control-label">{{ 'Status' }}</label>
    {{-- <input class="form-control" name="status" type="number" id="status" value="{{ isset($lab->status) ? $lab->status : ''}}" > --}}
    <select class="form-control" name="status" id="status">
        <option value=""></option>
        @foreach (App\Models\Lab::$statusOption  as $key => $value)
            <option value="{{ $key }}" @if (isset($lab->status) && $key == $lab->status) selected="selected" @endif>
                {{ $value }}</option>
        @endforeach
    </select>


    {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>

<script type="text/javascript">
    $(function() {
        $('.date').datepicker({
            language: 'th-th',
        });
    });
</script>
