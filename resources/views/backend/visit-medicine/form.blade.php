<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($visitmedicine->visit_id) ? $visitmedicine->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('medicine') ? 'has-error' : ''}}">
    <label for="medicine" class="control-label">{{ 'Medicine' }}</label>
    <input class="form-control" name="medicine" type="text" id="medicine" value="{{ isset($visitmedicine->medicine) ? $visitmedicine->medicine : ''}}" >
    {!! $errors->first('medicine', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('method') ? 'has-error' : ''}}">
    <label for="method" class="control-label">{{ 'Method' }}</label>
    <input class="form-control" name="method" type="text" id="method" value="{{ isset($visitmedicine->method) ? $visitmedicine->method : ''}}" >
    {!! $errors->first('method', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('amount') ? 'has-error' : ''}}">
    <label for="amount" class="control-label">{{ 'Amount' }}</label>
    <input class="form-control" name="amount" type="text" id="amount" value="{{ isset($visitmedicine->amount) ? $visitmedicine->amount : ''}}" >
    {!! $errors->first('amount', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
