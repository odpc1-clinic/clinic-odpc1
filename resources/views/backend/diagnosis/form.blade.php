<div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($diagnosi->visit_id) ? $diagnosi->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('disease') ? 'has-error' : ''}}">
    <label for="disease" class="control-label">{{ 'Disease' }}</label>
    <input class="form-control" name="disease" type="number" id="disease" value="{{ isset($diagnosi->disease) ? $diagnosi->disease : ''}}" >
    {!! $errors->first('disease', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_disease') ? 'has-error' : ''}}">
    <label for="other_disease" class="control-label">{{ 'Other Disease' }}</label>
    <input class="form-control" name="other_disease" type="text" id="other_disease" value="{{ isset($diagnosi->other_disease) ? $diagnosi->other_disease : ''}}" >
    {!! $errors->first('other_disease', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('term_syphilis') ? 'has-error' : ''}}">
    <label for="term_syphilis" class="control-label">{{ 'Term Syphilis' }}</label>
    <input class="form-control" name="term_syphilis" type="number" id="term_syphilis" value="{{ isset($diagnosi->term_syphilis) ? $diagnosi->term_syphilis : ''}}" >
    {!! $errors->first('term_syphilis', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
