<div class="form-group {{ $errors->has('lab_id') ? 'has-error' : ''}}">
    <label for="lab_id" class="control-label">{{ 'Lab Id' }}</label>
    <input class="form-control" name="lab_id" type="number" id="lab_id" value="{{ isset($labsub->lab_id) ? $labsub->lab_id : ''}}" >
    {!! $errors->first('lab_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('method') ? 'has-error' : ''}}">
    <label for="method" class="control-label">{{ 'Method' }}</label>
    <input class="form-control" name="method" type="number" id="method" value="{{ isset($labsub->method) ? $labsub->method : ''}}" >
    {!! $errors->first('method', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('specimen_from') ? 'has-error' : ''}}">
    <label for="specimen_from" class="control-label">{{ 'Specimen From' }}</label>
    <input class="form-control" name="specimen_from" type="number" id="specimen_from" value="{{ isset($labsub->specimen_from) ? $labsub->specimen_from : ''}}" >
    {!! $errors->first('specimen_from', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('result') ? 'has-error' : ''}}">
    <label for="result" class="control-label">{{ 'Result' }}</label>
    <input class="form-control" name="result" type="text" id="result" value="{{ isset($labsub->result) ? $labsub->result : ''}}" >
    {!! $errors->first('result', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
