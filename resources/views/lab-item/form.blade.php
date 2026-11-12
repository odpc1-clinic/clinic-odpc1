
{{-- <div class="form-group {{ $errors->has('lab_id') ? 'has-error' : ''}}">
    <label for="lab_id" class="control-label">{{ 'Lab Id' }}</label>
    <input class="form-control" name="lab_id" type="number" id="lab_id" value="{{ isset($labitem->lab_id) ? $labitem->lab_id : ''}}" >
    {!! $errors->first('lab_id', '<p class="help-block">:message</p>') !!}
</div> --}}

@php
    $method = (int) old('method', $labsub->method ?? 0);
    $isPCR = ($method === 5);

    // ใช้แม็ปจาก LabSub ให้ตรงกับ method
    $specimenOptions = $isPCR
        ? \App\Models\LabSub::$specimenFromPCROption
        : \App\Models\LabSub::$specimenFromOption;

    $currentSpecimen = (string) old('specimen_from', $labsub->specimen_from ?? '');
@endphp

<div class="row">
    <div class="form-group col-md-3">
        <label for="method" class="control-label">Method</label>
        <select class="form-control" name="method" id="method">
            @foreach (\App\Models\LabSub::orderableMethods(old('method', $labsub->method ?? null)) as $k => $v)
                <option value="{{ $k }}" {{ ((string)old('method', $labsub->method ?? '') === (string)$k) ? 'selected' : '' }}>
                    {{ $v }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="form-group col-md-4 {{ $errors->has('specimen_from') ? 'has-error' : '' }}">
        <label for="specimen_from" class="control-label">Specimen From</label>
        <select class="form-control" name="specimen_from" id="specimen_from">
            @foreach ($specimenOptions as $k => $v)
                <option value="{{ $k }}" {{ ($currentSpecimen === (string)$k) ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
        {!! $errors->first('specimen_from', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="form-group col-md-5 {{ $errors->has('specimen_from_other') ? 'has-error' : '' }}" id="other_specimen_wrap" style="display:none;">
        <label for="specimen_from_other" class="control-label">Other Specimen</label>
        <input class="form-control" name="specimen_from_other" id="specimen_from_other"
               value="{{ old('specimen_from_other', $labsub->specimen_from_other ?? '') }}">
        {!! $errors->first('specimen_from_other', '<p class="help-block">:message</p>') !!}
    </div>
</div>

@push('scripts')
<script>
(function(){
  var methodSel = document.getElementById('method');
  var specSel   = document.getElementById('specimen_from');
  var otherWrap = document.getElementById('other_specimen_wrap');

  var OPT_NORMAL = @json(\App\Models\LabSub::$specimenFromOption);
  var OPT_PCR    = @json(\App\Models\LabSub::$specimenFromPCROption);

  function rebuildSpecimen() {
    var prev = specSel.value;
    var isPCR = String(methodSel.value) === '5';
    var opts = isPCR ? OPT_PCR : OPT_NORMAL;

    specSel.innerHTML = '';
    Object.keys(opts).forEach(function(k){
      var opt = document.createElement('option');
      opt.value = k;
      opt.textContent = opts[k];
      specSel.appendChild(opt);
    });

    if (opts.hasOwnProperty(prev)) specSel.value = prev; // คงค่าเดิมไว้ถ้ามี
    toggleOther();
  }

  function toggleOther() {
    otherWrap.style.display = (specSel.value === '10') ? '' : 'none';
  }

  if (methodSel && specSel) {
    methodSel.addEventListener('change', rebuildSpecimen);
    specSel.addEventListener('change', toggleOther);
    rebuildSpecimen(); // init ตาม method ปัจจุบัน
  }
})();
</script>
@endpush

<hr>
<div class="row" style="margin-left:10px">
    <div class="form-check col-2 {{ $errors->has('gram_stain') ? 'has-error' : ''}}">
        <label for="gram_stain" class="control-label" >{{ 'Gram Stain' }}</label>
        <input class="form-check-input"  name="gram_stain" type="checkbox" id="gram_stain" value="1"   @if( isset($labitem->gram_stain) ) checked @endif >
        {!! $errors->first('gram_stain', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="form-group col-5 {{ $errors->has('gncd') ? 'has-error' : ''}}">
        <label for="gncd" class="control-label">{{ 'Gncd I/E' }}</label>
        <input class="form-control" name="gncd" type="text" id="gncd" value="{{ isset($labitem->gncd) ? $labitem->gncd : ''}}" >
        {!! $errors->first('gncd', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-5 {{ $errors->has('pmn') ? 'has-error' : ''}}">
        <label for="pmn" class="control-label">{{ 'Pmn (Pus Cell)' }}</label>
        <input class="form-control" name="pmn" type="text" id="pmn" value="{{ isset($labitem->pmn) ? $labitem->pmn : ''}}" >
        {!! $errors->first('pmn', '<p class="help-block">:message</p>') !!}
    </div>

</div>

<div class="row" style="margin-left:10px">
    <div class="form-check col-2 {{ $errors->has('wet_preparation') ? 'has-error' : ''}}">
        <label for="wet_preparation" class="control-label">{{ 'Wet Preparation' }}</label>
        <input class="form-check-input" name="wet_preparation" type="checkbox" id="wet_preparation" value="1"   @if( isset($labitem->wet_preparation) ) checked @endif  >
        {!! $errors->first('wet_preparation', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-5 {{ $errors->has('tv') ? 'has-error' : ''}}">
        <label for="tv" class="control-label">{{ 'Tv' }}</label>
        <input class="form-control" name="tv" type="text" id="tv" value="{{ isset($labitem->tv) ? $labitem->tv : ''}}" >
        {!! $errors->first('tv', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-5 {{ $errors->has('clue_cell') ? 'has-error' : ''}}">
        <label for="clue_cell" class="control-label">{{ 'Clue Cell' }}</label>
        <input class="form-control" name="clue_cell" type="text" id="clue_cell" value="{{ isset($labitem->clue_cell) ? $labitem->clue_cell : ''}}" >
        {!! $errors->first('clue_cell', '<p class="help-block">:message</p>') !!}
    </div>
</div>

<div class="row" style="margin-left:10px">
    <div class="form-check col-2 {{ $errors->has('koh') ? 'has-error' : ''}}">
        <label for="koh" class="control-label">{{ 'Koh' }}</label>
        <input class="form-check-input" name="koh" type="checkbox" id="koh" value="1"   @if( isset($labitem->koh) ) checked @endif >
        {!! $errors->first('koh', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-10 {{ $errors->has('yeast_hyphae') ? 'has-error' : ''}}">
        <label for="yeast_hyphae" class="control-label">{{ 'Yeast Hyphae' }}</label>
        <input class="form-control" name="yeast_hyphae" type="text" id="yeast_hyphae" value="{{ isset($labitem->yeast_hyphae) ? $labitem->yeast_hyphae : ''}}" >
        {!! $errors->first('yeast_hyphae', '<p class="help-block">:message</p>') !!}
    </div>
</div>

<div class="row" style="margin-left:10px">
    <div class="form-check col-2 {{ $errors->has('bacterial_ulture') ? 'has-error' : ''}}">
        <label for="bacterial_ulture" class="control-label">{{ 'Bacterial Ulture' }}</label>
        <input class="form-check-input" name="bacterial_ulture" type="checkbox" id="bacterial_ulture" value="1"   @if( isset($labitem->bacterial_ulture) ) checked @endif  >
        {!! $errors->first('bacterial_ulture', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-10 {{ $errors->has('bc_result') ? 'has-error' : ''}}">
        <label for="bc_result" class="control-label">{{ 'Bc Result' }}</label>
        <input class="form-control" name="bc_result" type="text" id="bc_result" value="{{ isset($labitem->bc_result) ? $labitem->bc_result : ''}}" >
        {!! $errors->first('bc_result', '<p class="help-block">:message</p>') !!}
    </div>
</div>
<div class="row" style="margin-left:10px">

    <div class="form-check col-2{{ $errors->has('other_test') ? 'has-error' : ''}}">
        <label for="other_test" class="control-label">{{ 'Other Test' }}</label>
        <input class="form-check-input" name="other_test" type="checkbox" id="other_test" value="1"   @if( isset($labitem->other_test) ) checked @endif  >
        {!! $errors->first('other_test', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="form-group col-10  {{ $errors->has('other_result') ? 'has-error' : ''}}">
        <label for="other_result" class="control-label">{{ 'Other Result' }}</label>
        <input class="form-control" name="other_result" type="text" id="other_result" value="{{ isset($labitem->other_result) ? $labitem->other_result : ''}}" >
        {!! $errors->first('other_result', '<p class="help-block">:message</p>') !!}
    </div>

</div>
<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
