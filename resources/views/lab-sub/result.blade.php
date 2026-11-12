@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <!-- @include('admin.sidebar') -->

        <div class="col-md-12">
            <div class="card">
                <div class="card-header">Edit LabSub #{{ $labsub->id }}</div>
                <div class="card-body">
                    <a href="{{ route('visit.edit', ['visit' => $labsub->visit()->id, 'page' => $_GET['page'] ?? 4]) }}" title="Back">
  <button class="btn btn-warning btn-sm">
    <i class="fa fa-arrow-left" aria-hidden="true"></i> Back
  </button>
</a>

                    <br />
                    <br />

                    @if ($errors->any())
                    <ul class="alert alert-danger">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Method</th>
                                    <th>Specimen From</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>{{ $labsub->method() }}</td>
                                    <td>{{ $labsub->specimenFrom() }}</td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
               
                    <form method="POST" action="{{  route('lab-sub.update',['lab_sub'=>$labsub->id,'page'=>request('page', 1)]) }}" accept-charset="UTF-8" class="form-horizontal" enctype="multipart/form-data">
                        {{ method_field('PATCH') }}
                        {{ csrf_field() }}

                        <input type="hidden" name="lab_id" value="{{$labsub->lab_id}}">

                        <div class="form-group {{ $errors->has('result') ? 'has-error' : ''}}">
                            <label for="result" class="control-label">{{ 'Result' }}</label>

                            @switch($labsub->method)
                            @case(4)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$cultureResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break

                            @case(5)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$pcrResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break

                            @case(6)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$hivResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            
                            @break

                            @case(7)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$tphaResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break

                            @case(8)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$rprResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break

                            @case(11)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$HBsAgResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break
                            @case(14)
                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$HBsAbResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
                                @endforeach
                            </select>
                            @break

                            @case(12)


                            <select class="form-control" name="result" id="result">
                                @foreach (App\Models\LabSub::$AntiHCVResultOption as $key => $value)
                                <option value="{{ $value }}" @if (isset($labsub->result) && $value == $labsub->result) selected="selected" @endif>
                                    {{ $value }}
                                </option>
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
                            <input class="form-control" name="result" type="text" id="result" value="{{ isset($labsub->result) ? $labsub->result : ''}}">
                            @endswitch


                            {!! $errors->first('result', '<p class="help-block">:message</p>') !!}
                        </div>


                        <div class="form-group">
                            <input class="btn btn-primary" type="submit" name="submit" value="result">
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection