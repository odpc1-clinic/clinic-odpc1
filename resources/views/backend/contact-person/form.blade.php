<div class="form-group {{ $errors->has('date') ? 'has-error' : ''}}">
    <label for="date" class="control-label">{{ 'Date' }}</label>
    <input class="form-control" name="date" type="date" id="date" value="{{ isset($contactperson->date) ? $contactperson->date : ''}}" >
    {!! $errors->first('date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('name') ? 'has-error' : ''}}">
    <label for="name" class="control-label">{{ 'Name' }}</label>
    <input class="form-control" name="name" type="text" id="name" value="{{ isset($contactperson->name) ? $contactperson->name : ''}}" >
    {!! $errors->first('name', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('surname') ? 'has-error' : ''}}">
    <label for="surname" class="control-label">{{ 'Surname' }}</label>
    <input class="form-control" name="surname" type="text" id="surname" value="{{ isset($contactperson->surname) ? $contactperson->surname : ''}}" >
    {!! $errors->first('surname', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('sex') ? 'has-error' : ''}}">
    <label for="sex" class="control-label">{{ 'Sex' }}</label>
    <input class="form-control" name="sex" type="number" id="sex" value="{{ isset($contactperson->sex) ? $contactperson->sex : ''}}" >
    {!! $errors->first('sex', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('sex_worker_ty') ? 'has-error' : ''}}">
    <label for="sex_worker_ty" class="control-label">{{ 'Sex Worker Ty' }}</label>
    <input class="form-control" name="sex_worker_ty" type="number" id="sex_worker_ty" value="{{ isset($contactperson->sex_worker_ty) ? $contactperson->sex_worker_ty : ''}}" >
    {!! $errors->first('sex_worker_ty', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('husband_wife_ty') ? 'has-error' : ''}}">
    <label for="husband_wife_ty" class="control-label">{{ 'Husband Wife Ty' }}</label>
    <input class="form-control" name="husband_wife_ty" type="number" id="husband_wife_ty" value="{{ isset($contactperson->husband_wife_ty) ? $contactperson->husband_wife_ty : ''}}" >
    {!! $errors->first('husband_wife_ty', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('regular_partner_ty') ? 'has-error' : ''}}">
    <label for="regular_partner_ty" class="control-label">{{ 'Regular Partner Ty' }}</label>
    <input class="form-control" name="regular_partner_ty" type="number" id="regular_partner_ty" value="{{ isset($contactperson->regular_partner_ty) ? $contactperson->regular_partner_ty : ''}}" >
    {!! $errors->first('regular_partner_ty', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('temporary_partner_ty') ? 'has-error' : ''}}">
    <label for="temporary_partner_ty" class="control-label">{{ 'Temporary Partner Ty' }}</label>
    <input class="form-control" name="temporary_partner_ty" type="number" id="temporary_partner_ty" value="{{ isset($contactperson->temporary_partner_ty) ? $contactperson->temporary_partner_ty : ''}}" >
    {!! $errors->first('temporary_partner_ty', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('vagina_mt') ? 'has-error' : ''}}">
    <label for="vagina_mt" class="control-label">{{ 'Vagina Mt' }}</label>
    <input class="form-control" name="vagina_mt" type="number" id="vagina_mt" value="{{ isset($contactperson->vagina_mt) ? $contactperson->vagina_mt : ''}}" >
    {!! $errors->first('vagina_mt', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('mouth_mt') ? 'has-error' : ''}}">
    <label for="mouth_mt" class="control-label">{{ 'Mouth Mt' }}</label>
    <input class="form-control" name="mouth_mt" type="number" id="mouth_mt" value="{{ isset($contactperson->mouth_mt) ? $contactperson->mouth_mt : ''}}" >
    {!! $errors->first('mouth_mt', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('penis_mt') ? 'has-error' : ''}}">
    <label for="penis_mt" class="control-label">{{ 'Penis Mt' }}</label>
    <input class="form-control" name="penis_mt" type="number" id="penis_mt" value="{{ isset($contactperson->penis_mt) ? $contactperson->penis_mt : ''}}" >
    {!! $errors->first('penis_mt', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('anus_mt') ? 'has-error' : ''}}">
    <label for="anus_mt" class="control-label">{{ 'Anus Mt' }}</label>
    <input class="form-control" name="anus_mt" type="number" id="anus_mt" value="{{ isset($contactperson->anus_mt) ? $contactperson->anus_mt : ''}}" >
    {!! $errors->first('anus_mt', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('use_cd') ? 'has-error' : ''}}">
    <label for="use_cd" class="control-label">{{ 'Use Cd' }}</label>
    <input class="form-control" name="use_cd" type="number" id="use_cd" value="{{ isset($contactperson->use_cd) ? $contactperson->use_cd : ''}}" >
    {!! $errors->first('use_cd', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('unuse_cd') ? 'has-error' : ''}}">
    <label for="unuse_cd" class="control-label">{{ 'Unuse Cd' }}</label>
    <input class="form-control" name="unuse_cd" type="number" id="unuse_cd" value="{{ isset($contactperson->unuse_cd) ? $contactperson->unuse_cd : ''}}" >
    {!! $errors->first('unuse_cd', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('brea_slip_cd') ? 'has-error' : ''}}">
    <label for="brea_slip_cd" class="control-label">{{ 'Brea Slip Cd' }}</label>
    <input class="form-control" name="brea_slip_cd" type="number" id="brea_slip_cd" value="{{ isset($contactperson->brea_slip_cd) ? $contactperson->brea_slip_cd : ''}}" >
    {!! $errors->first('brea_slip_cd', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('unbrea_slip_cd') ? 'has-error' : ''}}">
    <label for="unbrea_slip_cd" class="control-label">{{ 'Unbrea Slip Cd' }}</label>
    <input class="form-control" name="unbrea_slip_cd" type="number" id="unbrea_slip_cd" value="{{ isset($contactperson->unbrea_slip_cd) ? $contactperson->unbrea_slip_cd : ''}}" >
    {!! $errors->first('unbrea_slip_cd', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
