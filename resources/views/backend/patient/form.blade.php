<div class="form-group {{ $errors->has('code') ? 'has-error' : ''}}">
    <label for="code" class="control-label">{{ 'Code' }}</label>
    <input class="form-control" name="code" type="text" id="code" value="{{ isset($patient->code) ? $patient->code : ''}}" >
    {!! $errors->first('code', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('prefix') ? 'has-error' : ''}}">
    <label for="prefix" class="control-label">{{ 'Prefix' }}</label>
    <input class="form-control" name="prefix" type="number" id="prefix" value="{{ isset($patient->prefix) ? $patient->prefix : ''}}" >
    {!! $errors->first('prefix', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('name') ? 'has-error' : ''}}">
    <label for="name" class="control-label">{{ 'Name' }}</label>
    <input class="form-control" name="name" type="text" id="name" value="{{ isset($patient->name) ? $patient->name : ''}}" >
    {!! $errors->first('name', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('surname') ? 'has-error' : ''}}">
    <label for="surname" class="control-label">{{ 'Surname' }}</label>
    <input class="form-control" name="surname" type="text" id="surname" value="{{ isset($patient->surname) ? $patient->surname : ''}}" >
    {!! $errors->first('surname', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('sex') ? 'has-error' : ''}}">
    <label for="sex" class="control-label">{{ 'Sex' }}</label>
    <input class="form-control" name="sex" type="number" id="sex" value="{{ isset($patient->sex) ? $patient->sex : ''}}" >
    {!! $errors->first('sex', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('first_visit') ? 'has-error' : ''}}">
    <label for="first_visit" class="control-label">{{ 'First Visit' }}</label>
    <input class="form-control" name="first_visit" type="date" id="first_visit" value="{{ isset($patient->first_visit) ? $patient->first_visit : ''}}" >
    {!! $errors->first('first_visit', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('id_card_number') ? 'has-error' : ''}}">
    <label for="id_card_number" class="control-label">{{ 'Id Card Number' }}</label>
    <input class="form-control" name="id_card_number" type="text" id="id_card_number" value="{{ isset($patient->id_card_number) ? $patient->id_card_number : ''}}" >
    {!! $errors->first('id_card_number', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('birth_date') ? 'has-error' : ''}}">
    <label for="birth_date" class="control-label">{{ 'Birth Date' }}</label>
    <input class="form-control" name="birth_date" type="date" id="birth_date" value="{{ isset($patient->birth_date) ? $patient->birth_date : ''}}" >
    {!! $errors->first('birth_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('address_id') ? 'has-error' : ''}}">
    <label for="address_id" class="control-label">{{ 'Address Id' }}</label>
    <input class="form-control" name="address_id" type="number" id="address_id" value="{{ isset($patient->address_id) ? $patient->address_id : ''}}" >
    {!! $errors->first('address_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('tel') ? 'has-error' : ''}}">
    <label for="tel" class="control-label">{{ 'Tel' }}</label>
    <input class="form-control" name="tel" type="text" id="tel" value="{{ isset($patient->tel) ? $patient->tel : ''}}" >
    {!! $errors->first('tel', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('nationality') ? 'has-error' : ''}}">
    <label for="nationality" class="control-label">{{ 'Nationality' }}</label>
    <input class="form-control" name="nationality" type="text" id="nationality" value="{{ isset($patient->nationality) ? $patient->nationality : ''}}" >
    {!! $errors->first('nationality', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('education') ? 'has-error' : ''}}">
    <label for="education" class="control-label">{{ 'Education' }}</label>
    <input class="form-control" name="education" type="number" id="education" value="{{ isset($patient->education) ? $patient->education : ''}}" >
    {!! $errors->first('education', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('marital_status') ? 'has-error' : ''}}">
    <label for="marital_status" class="control-label">{{ 'Marital Status' }}</label>
    <input class="form-control" name="marital_status" type="number" id="marital_status" value="{{ isset($patient->marital_status) ? $patient->marital_status : ''}}" >
    {!! $errors->first('marital_status', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('status') ? 'has-error' : ''}}">
    <label for="status" class="control-label">{{ 'Status' }}</label>
    <input class="form-control" name="status" type="number" id="status" value="{{ isset($patient->status) ? $patient->status : ''}}" >
    {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('occupation') ? 'has-error' : ''}}">
    <label for="occupation" class="control-label">{{ 'Occupation' }}</label>
    <input class="form-control" name="occupation" type="number" id="occupation" value="{{ isset($patient->occupation) ? $patient->occupation : ''}}" >
    {!! $errors->first('occupation', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_occupation') ? 'has-error' : ''}}">
    <label for="other_occupation" class="control-label">{{ 'Other Occupation' }}</label>
    <input class="form-control" name="other_occupation" type="text" id="other_occupation" value="{{ isset($patient->other_occupation) ? $patient->other_occupation : ''}}" >
    {!! $errors->first('other_occupation', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('organization') ? 'has-error' : ''}}">
    <label for="organization" class="control-label">{{ 'Organization' }}</label>
    <input class="form-control" name="organization" type="text" id="organization" value="{{ isset($patient->organization) ? $patient->organization : ''}}" >
    {!! $errors->first('organization', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('congenital๘disease') ? 'has-error' : ''}}">
    <label for="congenital๘disease" class="control-label">{{ 'Congenital๘disease' }}</label>
    <input class="form-control" name="congenital๘disease" type="text" id="congenital๘disease" value="{{ isset($patient->congenital๘disease) ? $patient->congenital๘disease : ''}}" >
    {!! $errors->first('congenital๘disease', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('drug_allergy') ? 'has-error' : ''}}">
    <label for="drug_allergy" class="control-label">{{ 'Drug Allergy' }}</label>
    <input class="form-control" name="drug_allergy" type="text" id="drug_allergy" value="{{ isset($patient->drug_allergy) ? $patient->drug_allergy : ''}}" >
    {!! $errors->first('drug_allergy', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('drug_allergy') ? 'has-error' : ''}}">
    <label for="drug_allergy" class="control-label">{{ 'Drug Allergy' }}</label>
    <input class="form-control" name="drug_allergy" type="text" id="drug_allergy" value="{{ isset($patient->drug_allergy) ? $patient->drug_allergy : ''}}" >
    {!! $errors->first('drug_allergy', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
