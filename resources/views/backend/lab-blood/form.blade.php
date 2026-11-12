<div class="form-group {{ $errors->has('patient_id') ? 'has-error' : ''}}">
    <label for="patient_id" class="control-label">{{ 'Patient Id' }}</label>
    <input class="form-control" name="patient_id" type="number" id="patient_id" value="{{ isset($labblood->patient_id) ? $labblood->patient_id : ''}}" >
    {!! $errors->first('patient_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($labblood->visit_id) ? $labblood->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('report_by') ? 'has-error' : ''}}">
    <label for="report_by" class="control-label">{{ 'Report By' }}</label>
    <input class="form-control" name="report_by" type="number" id="report_by" value="{{ isset($labblood->report_by) ? $labblood->report_by : ''}}" >
    {!! $errors->first('report_by', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('approve_by') ? 'has-error' : ''}}">
    <label for="approve_by" class="control-label">{{ 'Approve By' }}</label>
    <input class="form-control" name="approve_by" type="number" id="approve_by" value="{{ isset($labblood->approve_by) ? $labblood->approve_by : ''}}" >
    {!! $errors->first('approve_by', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('report_date') ? 'has-error' : ''}}">
    <label for="report_date" class="control-label">{{ 'Report Date' }}</label>
    <input class="form-control date" name="report_date" type="text" id="report_date" value="{{ isset($labblood->report_date) ? $labblood->report_date : ''}}" >
    {!! $errors->first('report_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('hiv') ? 'has-error' : ''}}">
    <label for="hiv" class="control-label">{{ 'Hiv' }}</label>
    <!-- <input class="form-control" name="hiv" type="number" id="hiv" value="{{ isset($labblood->hiv) ? $labblood->hiv : ''}}" > -->
    <select class="form-control" name="know_from" id="know_from">
        @foreach (App\Models\LabBlood::$option as $key => $value)
            <option value="{{ $key }}" @if (isset($labblood->hiv) && $key == $labblood->hiv) selected="selected" @endif>
                {{ $value }}</option>
        @endforeach
    </select>

    {!! $errors->first('hiv', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('syphilis') ? 'has-error' : ''}}">
    <label for="syphilis" class="control-label">{{ 'Syphilis' }}</label>
    <!-- <input class="form-control" name="syphilis" type="number" id="syphilis" value="{{ isset($labblood->syphilis) ? $labblood->syphilis : ''}}" > -->
    <select class="form-control" name="know_from" id="know_from">
        @foreach (App\Models\LabBlood::$option as $key => $value)
            <option value="{{ $key }}" @if (isset($labblood->syphilis) && $key == $labblood->syphilis) selected="selected" @endif>
                {{ $value }}</option>
        @endforeach
    </select>

    {!! $errors->first('syphilis', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('rpr') ? 'has-error' : ''}}">
    <label for="rpr" class="control-label">{{ 'Rpr' }}</label>
    <input class="form-control" name="rpr" type="text" id="rpr" value="{{ isset($labblood->rpr) ? $labblood->rpr : ''}}" >
    {!! $errors->first('rpr', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('pcr_specimen') ? 'has-error' : ''}}">
    <label for="pcr_specimen" class="control-label">{{ 'Pcr Specimen' }}</label>
    <input class="form-control" name="pcr_specimen" type="text" id="pcr_specimen" value="{{ isset($labblood->pcr_specimen) ? $labblood->pcr_specimen : ''}}" >
    {!! $errors->first('pcr_specimen', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('pcr_result') ? 'has-error' : ''}}">
    <label for="pcr_result" class="control-label">{{ 'Pcr Result' }}</label>
    <input class="form-control" name="pcr_result" type="text" id="pcr_result" value="{{ isset($labblood->pcr_result) ? $labblood->pcr_result : ''}}" >
    {!! $errors->first('pcr_result', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>



<script type="text/javascript">
    $(function() {
        $('.date').datepicker({
            dateFormat: 'yy-mm-dd'
        });
    });
</script>