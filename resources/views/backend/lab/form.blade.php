<div class="form-group {{ $errors->has('patient_id') ? 'has-error' : ''}}">
    <label for="patient_id" class="control-label">{{ 'Patient Id' }}</label>
    <input class="form-control" name="patient_id" type="number" id="patient_id" value="{{ isset($lab->patient_id) ? $lab->patient_id : ''}}" >
    {!! $errors->first('patient_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($lab->visit_id) ? $lab->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('report_by') ? 'has-error' : ''}}">
    <label for="report_by" class="control-label">{{ 'Report By' }}</label>
    <input class="form-control" name="report_by" type="number" id="report_by" value="{{ isset($lab->report_by) ? $lab->report_by : ''}}" >
    {!! $errors->first('report_by', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('approve_by') ? 'has-error' : ''}}">
    <label for="approve_by" class="control-label">{{ 'Approve By' }}</label>
    <input class="form-control" name="approve_by" type="number" id="approve_by" value="{{ isset($lab->approve_by) ? $lab->approve_by : ''}}" >
    {!! $errors->first('approve_by', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('collected_date') ? 'has-error' : ''}}">
    <label for="collected_date" class="control-label">{{ 'Collected Date' }}</label>
    <input class="form-control" name="collected_date" type="date" id="collected_date" value="{{ isset($lab->collected_date) ? $lab->collected_date : ''}}" >
    {!! $errors->first('collected_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('report_date') ? 'has-error' : ''}}">
    <label for="report_date" class="control-label">{{ 'Report Date' }}</label>
    <input class="form-control" name="report_date" type="date" id="report_date" value="{{ isset($lab->report_date) ? $lab->report_date : ''}}" >
    {!! $errors->first('report_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('LN') ? 'has-error' : ''}}">
    <label for="LN" class="control-label">{{ 'Ln' }}</label>
    <input class="form-control" name="LN" type="text" id="LN" value="{{ isset($lab->LN) ? $lab->LN : ''}}" >
    {!! $errors->first('LN', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
