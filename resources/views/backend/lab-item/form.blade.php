<div class="form-group {{ $errors->has('lab_id') ? 'has-error' : ''}}">
    <label for="lab_id" class="control-label">{{ 'Lab Id' }}</label>
    <input class="form-control" name="lab_id" type="number" id="lab_id" value="{{ isset($labitem->lab_id) ? $labitem->lab_id : ''}}" >
    {!! $errors->first('lab_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('urethra') ? 'has-error' : ''}}">
    <label for="urethra" class="control-label">{{ 'Urethra' }}</label>
    <input class="form-control" name="urethra" type="number" id="urethra" value="{{ isset($labitem->urethra) ? $labitem->urethra : ''}}" >
    {!! $errors->first('urethra', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('begina') ? 'has-error' : ''}}">
    <label for="begina" class="control-label">{{ 'Begina' }}</label>
    <input class="form-control" name="begina" type="number" id="begina" value="{{ isset($labitem->begina) ? $labitem->begina : ''}}" >
    {!! $errors->first('begina', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('cervix') ? 'has-error' : ''}}">
    <label for="cervix" class="control-label">{{ 'Cervix' }}</label>
    <input class="form-control" name="cervix" type="number" id="cervix" value="{{ isset($labitem->cervix) ? $labitem->cervix : ''}}" >
    {!! $errors->first('cervix', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('anus') ? 'has-error' : ''}}">
    <label for="anus" class="control-label">{{ 'Anus' }}</label>
    <input class="form-control" name="anus" type="number" id="anus" value="{{ isset($labitem->anus) ? $labitem->anus : ''}}" >
    {!! $errors->first('anus', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('pharaynx') ? 'has-error' : ''}}">
    <label for="pharaynx" class="control-label">{{ 'Pharaynx' }}</label>
    <input class="form-control" name="pharaynx" type="number" id="pharaynx" value="{{ isset($labitem->pharaynx) ? $labitem->pharaynx : ''}}" >
    {!! $errors->first('pharaynx', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_specimen') ? 'has-error' : ''}}">
    <label for="other_specimen" class="control-label">{{ 'Other Specimen' }}</label>
    <input class="form-control" name="other_specimen" type="text" id="other_specimen" value="{{ isset($labitem->other_specimen) ? $labitem->other_specimen : ''}}" >
    {!! $errors->first('other_specimen', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('gram_stain') ? 'has-error' : ''}}">
    <label for="gram_stain" class="control-label">{{ 'Gram Stain' }}</label>
    <input class="form-control" name="gram_stain" type="number" id="gram_stain" value="{{ isset($labitem->gram_stain) ? $labitem->gram_stain : ''}}" >
    {!! $errors->first('gram_stain', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('gncd') ? 'has-error' : ''}}">
    <label for="gncd" class="control-label">{{ 'Gncd' }}</label>
    <input class="form-control" name="gncd" type="text" id="gncd" value="{{ isset($labitem->gncd) ? $labitem->gncd : ''}}" >
    {!! $errors->first('gncd', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('pmn') ? 'has-error' : ''}}">
    <label for="pmn" class="control-label">{{ 'Pmn' }}</label>
    <input class="form-control" name="pmn" type="text" id="pmn" value="{{ isset($labitem->pmn) ? $labitem->pmn : ''}}" >
    {!! $errors->first('pmn', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('wet_preparation') ? 'has-error' : ''}}">
    <label for="wet_preparation" class="control-label">{{ 'Wet Preparation' }}</label>
    <input class="form-control" name="wet_preparation" type="number" id="wet_preparation" value="{{ isset($labitem->wet_preparation) ? $labitem->wet_preparation : ''}}" >
    {!! $errors->first('wet_preparation', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('tv') ? 'has-error' : ''}}">
    <label for="tv" class="control-label">{{ 'Tv' }}</label>
    <input class="form-control" name="tv" type="text" id="tv" value="{{ isset($labitem->tv) ? $labitem->tv : ''}}" >
    {!! $errors->first('tv', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('pmcure_celln') ? 'has-error' : ''}}">
    <label for="pmcure_celln" class="control-label">{{ 'Pmcure Celln' }}</label>
    <input class="form-control" name="pmcure_celln" type="text" id="pmcure_celln" value="{{ isset($labitem->pmcure_celln) ? $labitem->pmcure_celln : ''}}" >
    {!! $errors->first('pmcure_celln', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('koh') ? 'has-error' : ''}}">
    <label for="koh" class="control-label">{{ 'Koh' }}</label>
    <input class="form-control" name="koh" type="number" id="koh" value="{{ isset($labitem->koh) ? $labitem->koh : ''}}" >
    {!! $errors->first('koh', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('yeast_hyphae') ? 'has-error' : ''}}">
    <label for="yeast_hyphae" class="control-label">{{ 'Yeast Hyphae' }}</label>
    <input class="form-control" name="yeast_hyphae" type="text" id="yeast_hyphae" value="{{ isset($labitem->yeast_hyphae) ? $labitem->yeast_hyphae : ''}}" >
    {!! $errors->first('yeast_hyphae', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('bacterial_ulture') ? 'has-error' : ''}}">
    <label for="bacterial_ulture" class="control-label">{{ 'Bacterial Ulture' }}</label>
    <input class="form-control" name="bacterial_ulture" type="number" id="bacterial_ulture" value="{{ isset($labitem->bacterial_ulture) ? $labitem->bacterial_ulture : ''}}" >
    {!! $errors->first('bacterial_ulture', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('bc_result') ? 'has-error' : ''}}">
    <label for="bc_result" class="control-label">{{ 'Bc Result' }}</label>
    <input class="form-control" name="bc_result" type="text" id="bc_result" value="{{ isset($labitem->bc_result) ? $labitem->bc_result : ''}}" >
    {!! $errors->first('bc_result', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_test') ? 'has-error' : ''}}">
    <label for="other_test" class="control-label">{{ 'Other Test' }}</label>
    <input class="form-control" name="other_test" type="number" id="other_test" value="{{ isset($labitem->other_test) ? $labitem->other_test : ''}}" >
    {!! $errors->first('other_test', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_result') ? 'has-error' : ''}}">
    <label for="other_result" class="control-label">{{ 'Other Result' }}</label>
    <input class="form-control" name="other_result" type="text" id="other_result" value="{{ isset($labitem->other_result) ? $labitem->other_result : ''}}" >
    {!! $errors->first('other_result', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
