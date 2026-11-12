<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($visitcontactperson->visit_id) ? $visitcontactperson->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('CContactPerson_id') ? 'has-error' : ''}}">
    <label for="CContactPerson_id" class="control-label">{{ 'Ccontactperson Id' }}</label>
    <input class="form-control" name="CContactPerson_id" type="number" id="CContactPerson_id" value="{{ isset($visitcontactperson->CContactPerson_id) ? $visitcontactperson->CContactPerson_id : ''}}" >
    {!! $errors->first('CContactPerson_id', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
