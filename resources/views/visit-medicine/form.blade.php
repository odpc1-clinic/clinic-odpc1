<!-- <div class="form-group {{ $errors->has('visit_id') ? 'has-error' : ''}}">
    <label for="visit_id" class="control-label">{{ 'Visit Id' }}</label>
    <input class="form-control" name="visit_id" type="number" id="visit_id" value="{{ isset($visitmedicine->visit_id) ? $visitmedicine->visit_id : ''}}" >
    {!! $errors->first('visit_id', '<p class="help-block">:message</p>') !!}
</div> -->
<div class="form-group {{ $errors->has('medicine') ? 'has-error' : ''}}">
    <label for="medicine" class="control-label">{{ 'Medicine' }}</label>
    <!-- <input class="form-control" name="medicine" type="text" id="medicine" value="{{ isset($visitmedicine->medicine) ? $visitmedicine->medicine : ''}}" > -->
    <?php $items = App\Models\Medicine::orderBy('name')->get(); ?>
    <select name="medicine_id" id="medicine_id" class="form-control name" >
        <option value=""></option>
        @foreach($items as $item)
        <option value="{{$item->id}}" dose="{{$item->dose}}" route="{{$item->route}}" amount="{{$item->amount}}"
                data-sig="{{ json_encode(\App\Support\DrugSig::guess($item->dose, $item->route, $item->amount, $item->unit)) }}">{{$item->name}}</option>
        @endforeach
    </select>

    {!! $errors->first('medicine', '<p class="help-block">:message</p>') !!}
</div>
<div data-rx-scope>
@if(\App\Models\VisitMedicine::lotReady())
<div class="form-group">
    <label for="lot_number" class="control-label">Lot</label>
    <input class="form-control" name="lot_number" type="text" id="lot_number" maxlength="50" autocomplete="off"
           value="{{ $visitmedicine->lot_number ?? '' }}" placeholder="เลข Lot (ถ้ามี)">
</div>
@endif
<div class="form-group {{ $errors->has('dose') ? 'has-error' : ''}}">
    <label for="dose" class="control-label">{{ 'Dose' }}</label>
    <input class="form-control" name="dose" type="text" id="dose" value="{{ isset($visitmedicine->dose) ? $visitmedicine->dose : ''}}" >
    {!! $errors->first('dose', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group {{ $errors->has('route') ? 'has-error' : ''}}">
    <label for="route" class="control-label">{{ 'route' }}</label>
    <input class="form-control" name="route" type="text" id="route" value="{{ isset($visitmedicine->route) ? $visitmedicine->route : ''}}" >
    {!! $errors->first('route', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group {{ $errors->has('amount') ? 'has-error' : ''}}">
    <label for="amount" class="control-label">{{ 'amount' }}</label>
    <input class="form-control js-rx-amount" name="amount" type="text" id="amount" value="{{ isset($visitmedicine->amount) ? $visitmedicine->amount : ''}}" >
    {!! $errors->first('amount', '<p class="help-block">:message</p>') !!}
</div>

{{-- วิธีใช้ยาแบบเลือก (2569-09-29) — ใบเก่าที่ยังไม่มีวิธีใช้: ช่องว่างไว้ ค่าที่ระบบเดาโชว์เป็นคำแนะนำ
     ต้องกด "ใช้ค่านี้" เอง ถึงจะถูกบันทึก (รีวิว 2569-10-01: เดิมเติมลงช่องจริง กด Update แล้วค่าที่เดาถูกบันทึกเงียบ ๆ) --}}
@php
    $__vm    = $visitmedicine ?? null;
    $__med   = $__vm ? \App\Models\Medicine::find($__vm->medicine_id) : null;
    $__sig   = $__vm && $__vm->sig ? $__vm->sig : [];
    $__guess = ($__vm && ! $__vm->sig) ? \App\Support\DrugSig::guess($__vm->dose, $__vm->route, $__vm->amount, optional($__med)->unit) : [];
    $__guessText = $__guess ? \App\Support\DrugSig::text($__guess) : '';
@endphp
<div class="form-group mt-2 mb-3 p-2 border rounded" style="background:#f0fdfa;">
    @include('partials.drug_sig_fields', ['prefix' => 'sig', 'sig' => $__sig])
    @if($__guessText !== '')
        <div class="small mt-1">
            <span class="text-muted">ระบบเดาจาก dose/route เดิม:</span> {{ $__guessText }}
            <button type="button" class="btn btn-link btn-sm p-0 ms-1 js-sig-use-guess" data-sig="{{ json_encode($__guess) }}">ใช้ค่านี้</button>
        </div>
        <script>
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('.js-sig-use-guess');
            if (!b || !window.rxSigApply) return;
            window.rxSigApply(document.querySelector('.js-rx-sig'), JSON.parse(b.dataset.sig));
        });
        </script>
    @endif
</div>
</div>
@include('partials.drug_sig_js')


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>

<script type="text/javascript">
    $(document).ready(function() {
        $('.name').change(function(){
            
            // console.log($(this).attr('rowId'))
            // console.log($(this).val())
            $('#dose').val($('option:selected', this).attr('dose')) 
            $('#route').val($('option:selected', this).attr('route')) 
            $('#amount').val($('option:selected', this).attr('amount'))
            // เปลี่ยนยา = วิธีใช้เดิมใช้ไม่ได้แล้ว เติมค่าที่เดาจากคลังยาของตัวใหม่
            var __box = document.querySelector('.js-rx-sig');
            if (__box && window.rxSigApply) { window.rxSigApply(__box, $('option:selected', this).data('sig')); }
 


        });

        $("#medicine_id").val({{ isset($visitmedicine) ? $visitmedicine->medicine_id : ''}});
    });

    
</script>
