<div class="form-group {{ $errors->has('medicine') ? 'has-error' : ''}}">
    <label for="medicine" class="control-label">{{ 'Medicine' }}</label>
    <input class="form-control" name="medicine" type="text" id="medicine" value="{{ isset($medicine->medicine) ? $medicine->medicine : ''}}" >
    {!! $errors->first('medicine', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('dose') ? 'has-error' : ''}}">
    <label for="dose" class="control-label">{{ 'Dose' }}</label>
    <input class="form-control" name="dose" type="text" id="dose" value="{{ isset($medicine->dose) ? $medicine->dose : ''}}" >
    {!! $errors->first('dose', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('route') ? 'has-error' : ''}}">
    <label for="route" class="control-label">{{ 'Route' }}</label>
    <input class="form-control" name="route" type="text" id="route" value="{{ isset($medicine->route) ? $medicine->route : ''}}" >
    {!! $errors->first('route', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('amount') ? 'has-error' : ''}}">
    <label for="amount" class="control-label">{{ 'Amount' }}</label>
    <input class="form-control" name="amount" type="text" id="amount" value="{{ isset($medicine->amount) ? $medicine->amount : ''}}" >
    {!! $errors->first('amount', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
