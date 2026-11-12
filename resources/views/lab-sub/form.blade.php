<!-- <div class="form-group {{ $errors->has('lab_id') ? 'has-error' : '' }}">
    <label for="lab_id" class="control-label">{{ 'Lab Id' }}</label>
    <input class="form-control" name="lab_id" type="number" id="lab_id" value="{{ isset($labsub->lab_id) ? $labsub->lab_id : '' }}" >
    {!! $errors->first('lab_id', '<p class="help-block">:message</p>') !!}
</div> -->

<div class="row ">
    <div class="form-group col-2 {{ $errors->has('method') ? 'has-error' : '' }}" id="method_div">
        <label for="method" class="control-label">{{ 'Method' }}</label>
        <!-- <input class="form-control" name="method" type="number" id="method" value="{{ isset($labsub->method) ? $labsub->method : '' }}" > -->
        <select class="form-control" name="method" id="method">
            @foreach (App\Models\LabSub::orderableMethods($labsub->method ?? null) as $key => $value)
                <option value="{{ $key }}" @if (isset($labsub->method) && $key == $labsub->method) selected="selected" @endif>
                    {{ $value }}</option>
            @endforeach
        </select>
        {!! $errors->first('method', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="form-group col-10 {{ $errors->has('method_other') ? 'has-error' : '' }}" id="method_other_div">
        <label for="method_other" class="control-label">{{ 'Other Method ' }}</label>
        <input class="form-control" name="method_other" type="text" id="method_other"
            value="{{ isset($labsub->method_other) ? $labsub->method_other : '' }}">
        {!! $errors->first('method_other', '<p class="help-block">:message</p>') !!}
    </div>
</div>

<div class="form-group {{ $errors->has('specimen_from') ? 'has-error' : '' }}" id="specimen_from_div">
    <label for="specimen_from" class="control-label">{{ 'Specimen From' }}</label>
    <!-- <input class="form-control" name="specimen_from" type="number" id="specimen_from" value="{{ isset($labsub->specimen_from) ? $labsub->specimen_from : '' }}" > -->
    <select class="form-control" name="specimen_from" id="specimen_from">
        @foreach (App\Models\LabSub::$specimenFromOption as $key => $value)
            <option value="{{ $key }}" @if (isset($labsub->specimen_from) && $key == $labsub->specimen_from) selected="selected" @endif>
                {{ $value }}</option>
        @endforeach
    </select>
    {!! $errors->first('specimen_from', '<p class="help-block">:message</p>') !!}
</div>

<div class="form-group {{ $errors->has('specimen_from_other') ? 'has-error' : '' }}" id="specimen_from_other_div">
    <label for="specimen_from_other" class="control-label">{{ 'Other Specimen From' }}</label>
    <input class="form-control" name="specimen_from_other" type="text" id="specimen_from_other"
        value="{{ isset($labsub->specimen_from_other) ? $labsub->specimen_from_other : '' }}">
    {!! $errors->first('specimen_from_other', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('result') ? 'has-error' : '' }}" id="result_div">
    <label for="result" class="control-label">{{ 'Result' }}</label>
    @switch($labsub->method)
                    
        @case(6)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$hivResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break

        @case(7)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$tphrResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break

        @case(8)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$rprResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break

        @case(11)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$HBsAgResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break
        @case(14)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$HBsAbResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break
        @case(12)
            <select class="form-control" name="result" id="result">
                @foreach (App\Models\LabSub::$AntiHCVResultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                        {{ $value }}</option>
                @endforeach
            </select>
        @break
        @case(13)
@php
    $resultOptions = \App\Models\LabSub::$papResultOption ?? [];
    $labsubResult = $labsub->result ?? '';
    $isOther = !in_array($labsubResult, $resultOptions);
@endphp

<select class="form-control" name="result_select" id="result_select" onchange="toggleOtherInput(this)">
    @foreach ($resultOptions as $value)
        <option value="{{ $value }}" {{ (!$isOther && $labsubResult === $value) ? 'selected' : '' }}>
            {{ $value }}
        </option>
    @endforeach

    {{-- เพิ่ม option "Others" ถ้าค่าปัจจุบันไม่อยู่ใน options --}}
    @if ($isOther)
        <option value="Others (see interpretation)" selected>
            Others (see interpretation)
        </option>
    @endif
</select>

<!-- ช่องกรอกเพิ่มเติม -->
<div class="input-group mt-2" id="other_input_group" style="display: {{ $isOther ? 'flex' : 'none' }};">
    <input type="text" class="form-control" name="result" id="result_input"
        value="{{ $isOther ? $labsubResult : '' }}"
        placeholder="กรอกผลอื่น ๆ">
    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#textResultModal">
        เลือกข้อความ
    </button>
</div>


<!-- Modal สำหรับเลือกข้อความ -->
<div class="modal fade" id="textResultModal" tabindex="-1" aria-labelledby="textResultModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="textResultModalLabel">เลือกข้อความผลตรวจ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
      </div>
      <div class="modal-body">
        <ul class="list-group">
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Fungus consistent with Candida spp')">
            Fungus consistent with Candida spp
          </li>
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Bacteria consistent with Actinomyces spp')">
            Bacteria consistent with Actinomyces spp
          </li>  
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Atypical squamous cells of undetermined significance (ASC-US)')">
            Atypical squamous cells of undetermined significance (ASC-US)
          </li>
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Atypical squamous cells cannot exclude HSIL (ASC-H)')">
            Atypical squamous cells cannot exclude HSIL (ASC-H)
          </li>
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Low grade squamous intraepithelial lesion (LSIL)')">
            Low grade squamous intraepithelial lesion (LSIL)
          </li>
          <li class="list-group-item list-group-item-action" onclick="setTextResult('High grade squamous intraepithelial lesion (HSIL)')">
            High grade squamous intraepithelial lesion (HSIL)
          </li>
          <li class="list-group-item list-group-item-action" onclick="setTextResult('Squamous cell carcinoma')">
            Squamous cell carcinoma
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
function setTextResult(text) {
    const input = document.getElementById('result_input');
    if (input.value) {
        // ถ้ามีข้อความอยู่แล้ว ให้เพิ่มใหม่โดยคั่นด้วย comma
        input.value += ', ' + text;
    } else {
        // ถ้าไม่มีข้อความใน input ให้ใส่ข้อความเลย
        input.value = text;
    }
    // ไม่ปิด modal เพื่อให้เลือกข้อความอื่น ๆ ต่อได้
}

function toggleOtherInput(select) {
    const otherValue = 'Others (see interpretation)';
    const inputGroup = document.getElementById('other_input_group');
    const input = document.getElementById('result_input');

    if (select.value === otherValue) {
        inputGroup.style.display = 'flex';
        input.required = true;

        if (!input.value || input.value === select.value) {
            input.value = '';
        }
    } else {
        inputGroup.style.display = 'none';
        input.required = false;
        input.value = select.value;
    }
}

// โหลดหน้า -> ตรวจสอบค่าเริ่มต้น
document.addEventListener("DOMContentLoaded", function () {
    toggleOtherInput(document.getElementById('result_select'));
});
</script>


        @break
        @default
            <input class="form-control" name="result" type="text" id="result"
                value="{{ isset($labsub->result) ? $labsub->result : '' }}">
        @break

    @endswitch

    {!! $errors->first('result', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
<script>
    function changeMethod() {
        if ($("#method").val() > 5) {
            $("#specimen_from").prop('disabled', true);

            $("#specimen_from_div").hide()
            $("#specimen_from_other_div").hide()
            $("#method_other_div").hide()

        } else {
            $("#specimen_from").prop('disabled', false);
            $("#specimen_from_div").show()
            $("#specimen_from_other_div").show()
            $("#method_other_div").show()
        }
        // $("#result_div").show()
    }
    $(document).ready(function() {
        console.log($("#method").val())
        $("#method").change(changeMethod)
        changeMethod()
    });
</script>
