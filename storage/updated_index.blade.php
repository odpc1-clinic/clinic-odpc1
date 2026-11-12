<!-- <div class="form-group {{ $errors->has('patient_id') ? 'has-error' : '' }}">
    <label for="patient_id" class="control-label">{{ 'Patient Id' }}</label>
    <input class="form-control" name="patient_id" type="number" id="patient_id"
        value="{{ isset($visit->patient_id) ? $visit->patient_id : '' }}">
    {!! $errors->first('patient_id', '<p class="help-block">:message</p>') !!}
</div> -->
<?php
if (isset($visit)) {
    $patient = $visit->patient();
} else {
    $patient = App\Models\Patient::find($_GET['patient']);
}

?>



<div class="row">
<div class="col-2 form-group {{ $errors->has('code') ? 'has-error' : '' }}">
        <label for="code" class="control-label">{{ 'หมายเลขประจำตัว(HN.)' }}</label>
        <input class="form-control" name="code" type="text" id="code"
            value="{{ isset($patient->code) ? $patient->code : '' }}" readonly>
    </div>
<div class="col-2  form-group {{ $errors->has('prefix') ? 'has-error' : '' }}">
        <label for="prefix" class="control-label">{{ 'คำนำหน้าชื่อ' }}</label>
        {{-- <input class="form-control" name="prefix" type="number" id="prefix" value="{{ isset($patient->prefix) ? $patient->prefix : ''}}" required> --}}
        <select class="form-control" name="prefix" id="prefix" required>
            @foreach (App\Models\Patient::$prefixOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->prefix) && $key == $patient->prefix) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('prefix', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="col-3 form-group {{ $errors->has('name') ? 'has-error' : '' }}">
        <label for="name" class="control-label">{{ 'ชื่่อ' }}</label>
        <input class="form-control" name="name" type="text" id="name"
            value="{{ isset($patient->name) ? $patient->name : '' }}" readonly>
    </div>
    <div class="col-3 form-group {{ $errors->has('surname') ? 'has-error' : '' }}">
        <label for="surname" class="control-label">{{ 'นามสกุล' }}</label>
        <input class="form-control" name="surname" type="text" id="surname"
            value="{{ isset($patient->surname) ? $patient->surname : '' }}" readonly>
    </div>
    <div class="col-2 form-group {{ $errors->has('birth_date') ? 'has-error' : '' }}">
        <label for="birth_date" class="control-label">{{ 'วันเกิด' }}</label>
        <input class="form-control" name="birth_date" type="text" id="birth_date"
            value="{{ isset($patient->birth_date) ? $patient->birth_date : '' }}" readonly>
    </div>
    <div class="col-2  form-group {{ $errors->has('sex') ? 'has-error' : '' }}">
        <label for="sex" class="control-label">{{ 'เพศ' }}</label>
        {{-- <input class="form-control" name="sex" type="number" id="sex" value="{{ isset($patient->sex) ? $patient->sex : ''}}" > --}}
        <select class="form-control" name="sex" id="sex" required>
            @foreach (App\Models\Patient::$sexOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->sex) && $key == $patient->sex) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('sex', '<p class="help-block">:message</p>') !!}
    </div>
    <div class="col-2 form-group">
        <label for="age" class="control-label">{{ 'อายุ' }}</label>
        <input class="form-control" name="age" type="text" id="age"
            value="{{ isset($patient->birth_date) ? $patient->age() : '' }}" readonly>
    </div>
    <div class="col-2 form-group {{ $errors->has('tel') ? 'has-error' : '' }}">
        <label for="tel" class="control-label">{{ 'เบอร์โทร' }}</label>
        <input class="form-control" name="tel" type="text" id="tel" value="{{ isset($patient->tel) ? $patient->tel : '' }}">
   </div>
      <div class="col-3 form-group {{ $errors->has('status') ? 'has-error' : '' }}">
                <label for="status" class="control-label">{{ 'Status' }}</label>
                {{-- <input class="form-control" name="status" type="number" id="status" value="{{ isset($patient->status) ? $patient->status : '' }}"> --}}
                
                <select class="form-control" name="status" id="status1">
                    
                    @foreach (App\Models\Patient::$statusOption as $key => $value)
                        <option value="{{ $key }}" @if (isset($patient->status) && $key == $patient->status) selected="selected" @endif>
                {{ $value }}
                        </option>
                    @endforeach
                </select>
                {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
            </div>

    <div class="col-3 form-group {{ $errors->has('congenitaldisease') ? 'has-error' : '' }}">
        <label for="congenitaldisease" class="control-label">{{ 'โรคประจำตัว' }}</label>
        <input class="form-control" name="congenitaldisease" type="text" id="congenitaldisease"
            value="{{ isset($patient->congenitaldisease) ? $patient->congenitaldisease : '' }}">
    </div>
    
    <div class="col-3 form-group {{ $errors->has('drug_allergy') ? 'has-error' : '' }}">
        <label for="drug_allergy" class="control-label">{{ 'ประวัติแพ้ยา' }}</label>
        <input class="form-control" name="drug_allergy" type="text" id="drug_allergy"
            value="{{ isset($patient->drug_allergy) ? $patient->drug_allergy : '' }}">
    </div>
    <div class="col-3 form-group {{ $errors->has('marital_status') ? 'has-error' : '' }}">
        <label for="marital_status" class="control-label">{{ 'สถานภาพ' }}</label>
        {{-- <input class="form-control" name="marital_status" type="number" id="marital_status"
        value="{{ isset($patient->marital_status) ? $patient->marital_status : '' }}"> --}}
        <select class="form-control" name="marital_status" id="marital_status" disabled>
            @foreach (App\Models\Patient::$maritalStatusOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->marital_status) && $key == $patient->marital_status) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('marital_status', '<p class="help-block">:message</p>') !!}
    </div>
</div>
<hr>
<div class="row">
    <div class="col-3 form-group {{ $errors->has('date') ? 'has-error' : '' }}">
        <label for="date" class="control-label">{{ 'วันที่' }}</label>
        <input class="form-control date" name="date" type="text" id="date"
            value="{{ isset($visit->date) ? $visit->date : date('d') . '/' . date('m') . '/' . (date('Y') + 543) }}">
        {!! $errors->first('date', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="col-3 form-group {{ $errors->has('know_from') ? 'has-error' : '' }}">
        <label for="know_from" class="control-label">{{ 'ช่องทางการเข้าถึง' }}</label>
        {{-- <input class="form-control" name="know_from" type="number" id="know_from" value="{{ isset($visit->know_from) ? $visit->know_from : ''}}" > --}}
        <select class="form-control" name="know_from" id="know_from">
            @foreach (App\Models\Visit::$knowFromOption as $key => $value)
                <option value="{{ $key }}" @if (isset($visit->know_from) && $key == $visit->know_from) selected="selected" @endif>
                    {{ $value }}
                </option>
            @endforeach
        </select>

        {!! $errors->first('know_from', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="col-3 form-group {{ $errors->has('send_from') ? 'has-error' : '' }}">
        <label for="send_from" class="control-label click" data-bs-toggle="modal" data-bs-target="#send_from_model">{{ 'ส่งต่อมาจาก' }}</label>
        <input class="form-control" name="send_from" type="text" id="send_from" value="{{ isset($visit->send_from) ? $visit->send_from : '' }}">
        {!! $errors->first('send_from', '<p class="help-block">:message</p>') !!}
    </div>

            <div class="modal fade" id="send_from_model" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Refer Option</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$referOption as $item)
                            <p class='send_from_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach             
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.send_from_item').click(function() {
                console.log($(this).text())
                $('#send_from').val($('#send_from').val() + $(this).text() + '');

            })
        </script>

    <div class="col-3 form-group {{ $errors->has('other_from') ? 'has-error' : '' }}">
        <label for="other_from" class="control-label click" data-bs-toggle="modal" data-bs-target="#other_from_model"><font color="red">{{ 'ลงเลขคิว (คลิกเลือกข้อความแล้วกด Enter)' }}</font></label>
        <input class="form-control" name="other_from" type="text" id="other_from" value="{{ isset($visit->other_from) ? $visit->other_from : '' }}">
        {!! $errors->first('other_from', '<p class="help-block">:message</p>') !!}
    </div>

    <div class="modal fade" id="other_from_model" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Queue Option</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$QueueOption as $item)
                            <p class='other_from_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach             
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.other_from_item').click(function() {
                console.log($(this).text())
                $('#other_from').val($('#other_from').val() + $(this).text() + '');

            })
        </script>

</div>
<br>
<hr>
<style type="text/css"></style>
<div class="btn-group text-center" role="group" aria-label="Toggle Collapses" style="width: 100%">
   <button style="text-center" class="btn btn-warning" type="button" data-bs-toggle="collapse" data-bs-target=".collapse" aria-expanded="false" aria-controls="visitReason visitSymptom visitContact bodyCheck visitLab visitDiagnosis visitTreatment visitOther"><font size="4">คลิกย่อแท็บทั้งหมด</font></button>
  </div>
<hr>
@include('visit.form.reason')

<br>

<div class="d-grid">
    <div class="row">
        <div class="col-11">
            <a class="btn btn-light" data-bs-toggle="collapse" href="#visitSymptom" role="button" aria-expanded="false" style="width: 100%" aria-controls="visitSymptom">
                <font size="4">ประวัติการเจ็บป่วยปัจจุบัน**</font>
            </a>
            
        </div>
        <div class="col-1">
                <div class="col-auto form-group">
                    <input class="btn btn-primary" type="submit" value="Save">
                </div>
            </div>
    </div>


</div>
<div class="collapse" id="visitSymptom" data-bs-parent="#accordionExample">
    <div class="card card-body">
        <div class="form-group {{ $errors->has('symptom') ? 'has-error' : '' }}">
            <label for="symptom" class="control-label click" data-bs-toggle="modal" data-bs-target="#symptom_model">{{ 'อาการเจ็บป่วยครั้งปัจจุบัน' }}</label>
            <textarea class="form-control" rows="5" name="symptom" type="textarea" id="symptom">{{ isset($visit->symptom) ? $visit->symptom : '' }}</textarea>
            {!! $errors->first('symptom', '<p class="help-block">:message</p>') !!}
        </div>

        <div class="modal fade" id="symptom_model" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Symptom Option</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$symptomOption as $item)
                            <p class='symptom_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach             
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.symptom_item').click(function() {
                console.log($(this).text())
                $('#symptom').val($('#symptom').val() + $(this).text() + ',\n');

            })
        </script>

    </div>
</div>
<br>

@if (isset($visit))
    <div class="d-grid">

        <div class="row">
            <div>
                <a class="btn btn-warning" data-bs-toggle="collapse" href="#visitContact" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitContact">
                    <font size="4">คู่นอน คู่สัมผัส (contact)</font>
                </a>
            </div>
            <!-- <div class="col-1">
                <div class="col-auto form-group">
                    <input class="btn btn-primary" type="submit" value="Save">
                </div>
            </div> -->
        </div>

    </div>
    <div class="collapse" id="visitContact" data-bs-parent="#accordionExample">
        <div class="card card-body">
            @include('visit.contact', ['visit' => $visit, 'page' => $_GET['page']])
        </div>
    </div>
    <br>
@endif

@if ($_GET['page'] == 1 || $_GET['page'] == 2)
    <div class="d-grid">


        <div class="row">
            <div>
                <a class="btn btn-success" data-bs-toggle="collapse" href="#bodyCheck" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="bodyCheck">
                    <font size="4">การตรวจร่างกาย</font>
                </a>
            </div>

        </div>

    </div>
    <div class="collapse " id="bodyCheck">
        <div class="card card-body">
            <div class="form-group {{ $errors->has('body_check') ? 'has-error' : '' }}">
                <label for="body_check" class="control-label">{{ 'การตรวจร่างกาย' }}</label>
                <textarea class="form-control" rows="5" name="body_check" type="textarea" id="body_check">{{ isset($visit->body_check) ? $visit->body_check : '' }}</textarea>
                {!! $errors->first('body_check', '<p class="help-block">:message</p>') !!}
            </div>
        </div>
    </div>
    <br>
@endif




@if (isset($visit))
    <div class="d-grid">

        <div class="row">
            <div class="col-11">
                <a class="btn btn-primary" data-bs-toggle="collapse" href="#visitLab" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitLab">
                    <font size="4">Lab</font>
                </a>

            </div>
            <div class="col-1">
                <div class="col-auto form-group">
                    <input class="btn btn-primary" type="submit" value="Save">
                </div>
            </div>
        </div>

    </div>
    <div class="collapse " id="visitLab" data-bs-parent="#accordionExample">
        <div class="card card-body">
            @include('visit.form.lab', ['visit' => $visit, 'page' => 2])
        </div>
    </div>
    <br>
@endif

@if (isset($visit))
    <div class="d-grid">


        <div class="row">
            <div>
                <a class="btn btn-light" data-bs-toggle="collapse" href="#visitDiagnosis" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitDiagnosis">
                    <font size="4">วินิจฉัย**</font>
                </a>
            </div>
            <!-- <div class="col-1">
                <div class="col-auto form-group">
                    <input class="btn btn-primary" type="submit" value="Save">
                </div>
            </div> -->
        </div>


    </div>
    <div class="collapse  pb-2" id="visitDiagnosis" data-bs-parent="#accordionExample">
        <div class="card card-body">
            @include('visit.form.diagnosis', ['visit' => $visit])
        </div>
    </div>
    <br>
@endif

<div class="d-grid">


    <div class="row">
        <div class="col-11">
            <a class="btn btn-warning" data-bs-toggle="collapse" href="#visitTreatment" role="button"
                aria-expanded="false" style="width: 100%" aria-controls="visitTreatment">
                <font size="4">การรักษา + ให้คำแนะนำ</font>
            </a>
        </div>
<div class="col-1">
                <div class="col-auto form-group">
                    <input class="btn btn-primary" type="submit" value="Save">
                </div>
            </div>
    </div>

</div>
<div class="collapse " id="visitTreatment" data-bs-parent="#accordionExample">
    <div class="card card-body">


        @include('visit.form.medicine', ['visit' => $visit])
        <br>


        <div class="form-group {{ $errors->has('treatment') ? 'has-error' : '' }}">
            <label for="treatment" class="control-label click" data-bs-toggle="modal"
                data-bs-target="#staticBackdrop">{{ 'การให้คำแนะนำ' }}</label>

            <textarea class="form-control" rows="5" name="treatment" type="textarea" id="treatment">{{ isset($visit->treatment) ? $visit->treatment : '' }}</textarea>
            {!! $errors->first('treatment', '<p class="help-block">:message</p>') !!}
        </div>


        <div class="modal fade" id="staticBackdrop" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">การให้คำแนะนำ</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$treatmentOption as $item)
                            <!-- <p class='treatment_item' style="display: list-item; margin-left : 1em; ">&nbsp;{{ $item }}</p> -->
                            <p class='treatment_item' style="display: list-item; margin-left : 1em;" onmouseover="this.style.color='red'" onmouseout ="this.style.color='black'"> {{ $item }}</p>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.treatment_item').click(function() {
                console.log($(this).text())
                $('#treatment').append($(this).text() + ', ');

            })
        </script>

        <div class="form-group {{ $errors->has('consultation') ? 'has-error' : '' }}">
            <label for="consultation"
                class="control-label">{{ 'การให้สุขศึกษา/การปรึกษาเรื่อง Safe sex, Condom, ความรู้เรื่องโรค ยาและผลข้างเคียง' }}</label>
            {{-- <input class="form-control" name="consultation" type="number" id="consultation"
        value="{{ isset($visit->consultation) ? $visit->consultation : '' }}"> --}}
            <select class="form-control" name="consultation" id="consultation">
                @foreach (App\Models\Visit::$consultationOption as $key => $value)
                    <option value="{{ $key }}" @if (isset($visit->consultation) && $key == $visit->consultation) selected="selected" @endif>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
            {!! $errors->first('consultation', '<p class="help-block">:message</p>') !!}
        </div>

        <div class="row">
            <div class="col-6 form-group {{ $errors->has('hiv_sti_test') ? 'has-error' : '' }}">
                <label for="hiv_sti_test"
                    class="control-label">{{ 'การให้คำปรึกษาเพื่อตรวจหาเชื้อ HIV/Syphilis' }}</label>
                {{-- <input class="form-control" name="hiv_sti_test" type="number" id="hiv_sti_test"
            value="{{ isset($visit->hiv_sti_test) ? $visit->hiv_sti_test : '' }}"> --}}

                <select class="form-control" name="hiv_sti_test" id="hiv_sti_test" data-bs-parent="#accordionExample">
                    @foreach (App\Models\Visit::$hivStiTestResuleOption as $key => $value)
                        <option value="{{ $key }}"
                            @if (isset($visit->hiv_sti_test) && $key == $visit->hiv_sti_test) selected="selected" @endif>
                            {{ $value }}
                        </option>
                    @endforeach
                </select>
                {!! $errors->first('hiv_sti_test', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-6 form-group {{ $errors->has('last_hiv_syphilis') ? 'has-error' : '' }}">
                <label for="last_hiv_syphilis" class="control-label">{{ 'ประวัติการตรวจ Sti ล่าสุด / On PrEP ล่าสุด' }}</label>
                <input class="form-control" name="last_hiv_syphilis" type="text" id="last_hiv_syphilis"
                    value="{{ isset($visit->last_hiv_syphilis) ? $visit->last_hiv_syphilis : '' }}">
                {!! $errors->first('last_hiv_syphilis', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-6 form-group {{ $errors->has('hiv_sti_test_date') ? 'has-error' : '' }}">
                <label for="hiv_sti_test_date" class="control-label">{{ 'Hiv Test Date' }}</label>
                <input class="form-control" name="hiv_sti_test_date" type="text" id="hiv_sti_test_date"
                    value="{{ isset($visit->hiv_sti_test_date) ? $visit->hiv_sti_test_date : '' }}">
                {!! $errors->first('hiv_sti_test_date', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-6 form-group {{ $errors->has('hiv_sti_test_resule') ? 'has-error' : '' }}">
                <label for="hiv_sti_test_resule" class="control-label">{{ 'Hiv Test Result' }}</label>
                {{-- <input class="form-control" name="hiv_sti_test_resule" type="text" id="hiv_sti_test_resule"
        value="{{ isset($visit->hiv_sti_test_resule) ? $visit->hiv_sti_test_resule : '' }}"> --}}
            <select class="form-control" name="hiv_sti_test_resule" id="hiv_sti_test_resule">
                @foreach (App\Models\Visit::$hivtestresultOption as $key => $value)
                    <option value="{{ $key }}" @if (isset($visit->hiv_sti_test_resule) && $key == $visit->hiv_sti_test_resule) selected="selected" @endif>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
            {!! $errors->first('hiv_sti_test_resule', '<p class="help-block">:message</p>') !!}
            </div>
            
            <!-- {{-- <input class="form-control" name="hiv_sti_test_resule" type="text" id="hiv_sti_test_resule"
        value="{{ isset($visit->hiv_sti_test_resule) ? $visit->hiv_sti_test_resule : '' }}"> --}}
            <select class="form-control" name="hiv_sti_test_resule" id="hiv_sti_test_resule">
                @foreach (App\Models\Visit::$hivtestresultOption as $key => $value)
                    <option value="{{ $value }}" @if (isset($visit->hiv_sti_test_resule) && $key == $visit->hiv_sti_test_resule) selected="selected" @endif>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
            {!! $errors->first('hiv_sti_test_resule', '<p class="help-block">:message</p>') !!} -->



        </div>


        <div class="row">
            <div class="col-4 form-group {{ $errors->has('provide_condom_site') ? 'has-error' : '' }}">
                <label for="provide_condom_site" class="control-label">{{ 'แจกถุงยางอนามัย ขนาด' }}</label>
                <input class="form-control" name="provide_condom_site" type="text" id="provide_condom_site"
                    value="{{ isset($visit->provide_condom_site) ? $visit->provide_condom_site : '' }}">
                {!! $errors->first('provide_condom_site', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-4 form-group {{ $errors->has('provide_condom_quantity') ? 'has-error' : '' }}">
                <label for="provide_condom_quantity"
                    class="control-label">{{ 'แจกถุงยางอนามัย จำนวน (ชิ้น)' }}</label>
                <input class="form-control" name="provide_condom_quantity" type="number"
                    id="provide_condom_quantity"
                    value="{{ isset($visit->provide_condom_quantity) ? $visit->provide_condom_quantity : '' }}">
                {!! $errors->first('provide_condom_quantity', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-4 form-group {{ $errors->has('provide_lubricant_quantity') ? 'has-error' : '' }}">
                <label for="provide_lubricant_quantity"
                    class="control-label">{{ 'แจกสารหล่อลื่น จำนวน (ชิ้น)' }}</label>
                <input class="form-control" name="provide_lubricant_quantity" type="number"
                    id="provide_lubricant_quantity"
                    value="{{ isset($visit->provide_lubricant_quantity) ? $visit->provide_lubricant_quantity : '' }}">
                {!! $errors->first('provide_lubricant_quantity', '<p class="help-block">:message</p>') !!}
            </div>
        </div>
        @if (0)
            <hr>
            <div id="touchTrackDiv">
                <h4 class="text-center">การติดตามคู่สัมผัส</h4>

                <div class="table-responsive">
                    <table id="touchTrackTable" class="table">
                        <thead>
                            <tr>
                                <th>ชื่อ-นามสกุล </th>
                                <th>การติดตามคู่สัมผัส </th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($visit->trush_tracks() as $key => $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td value="{{ $item->touchTracing }}">
                                        {{ App\Models\Visit::$touchTracingOption[$item->touchTracing] }}</td>
                                    <td>
                                        <button class="btn btn-danger btn-sm" type="button"><i class="fa fa-trash"
                                                aria-hidden="true"></i></button>

                                    </td>



                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="row">
                    <div class="col-6 form-group {{ $errors->has('touch_tracing_name') ? 'has-error' : '' }}">
                        <label for="touch_tracing_name" class="control-label">{{ 'ชื่อ-นามสกุล' }}</label>
                        <input class="form-control" name="touch_tracing_name" type="text" id="touch_tracing_name"
                            value="">
                    </div>

                    <div class="col-5 form-group {{ $errors->has('touch_tracing') ? 'has-error' : '' }}">
                        <label for="touch_tracing" class="control-label">{{ 'การติดตามคู่สัมผัส' }}</label>

                        <select class="form-control" name="touch_tracing_id" id="touch_tracing_id">
                            @foreach (App\Models\Visit::$touchTracingOption as $key => $value)
                                <option value="{{ $key }}" name=" {{ $value }}">
                                    {{ $value }}
                                </option>
                            @endforeach
                        </select>
                        {!! $errors->first('touch_tracing', '<p class="help-block">:message</div>') !!}
                    </div>

                    <div class="col-1">
                        <div style="padding-top: 24px">
                            <button type="button" class="btn btn-info" style="width: 100%"><i class="fa fa-plus"
                                    aria-hidden="true"></i> </button>
                        </div>
                    </div>

                </div>

                <script>
                    $(document).ready(function() {
                        function removeRow() {
                            $(this).closest('tr').remove()
                        }
                        $("#touchTrackTable .btn-danger").click(removeRow)

                        $("#touchTrackDiv .btn-info").click(function() {
                            console.log("click")
                            $("#touchTrackTable tbody").append(`
                        <tr>
                                <td>${$("#touch_tracing_name").val()}</td>
                                <td value="${$("#touch_tracing_id").val()}">${$("#touch_tracing_id").find('option:selected').attr("name")}</td>
                                <td>
                                    <button class="btn btn-danger btn-sm" type="button"><i class="fa fa-trash"
                                            aria-hidden="true"></i></button>
                                </td>
                            </tr>
                        `)

                            $("#touchTrackTable .btn-danger").unbind("click");
                            $("#touchTrackTable .btn-danger").click(removeRow)
                            $("#touch_tracing_name").val("");
                            $("#touch_tracing_id").val(0)
                        })
                        $("#touchTrackTable body").click(function() {
                            $(this).closest('tr').remove()
                        })
                    });
                </script>
            </div>
        @endif

    </div>
</div>
<br>

<div class="d-grid">


    <div class="row">
        <div>
            <a class="btn btn-light" data-bs-toggle="collapse" href="#visitOther" role="button"
                aria-expanded="false" style="width: 100%" aria-controls="visitOther">
                <font size="4">วันนัด</font>
            </a>
        </div>
       <!--  <div class="col-1">
            <div class="col-auto form-group">
                <input class="btn btn-primary" type="submit" value="Save">
            </div>
        </div> -->
    </div>
</div>
<div class="collapse " id="visitOther" data-bs-parent="#accordionExample">
    <div class="card card-body">



    <div class="row">
            <div class="col-2 form-group {{ $errors->has('appointment') ? 'has-error' : '' }}">
                <label for="appointment" class="control-label">{{ 'Appointment' }}</label>
                <input class="form-control date" name="appointment" type="text" id="appointment"
                    value="{{ isset($visit->appointment) ? $visit->appointment : '' }}">
                {!! $errors->first('appointment', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-10 form-group {{ $errors->has('appointment_reason') ? 'has-error' : '' }}">
                <label for="appointment_reason" class="control-label click" data-bs-toggle="modal"
                    data-bs-target="#appointment_reason_model">{{ 'Appointment Reason' }}</label>
                <input class="form-control " name="appointment_reason" type="text" id="appointment_reason"
                    value="{{ isset($visit->appointment_reason) ? $visit->appointment_reason : '' }}">
                {!! $errors->first('appointment_reason', '<p class="help-block">:message</p>') !!}
            </div>
        </div>

        <div class="modal fade" id="appointment_reason_model" data-bs-keyboard="false" tabindex="-1"
            aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="staticBackdropLabel">Appointment Reason Option</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        @foreach (App\Models\Visit::$reasonAppointmentOption as $item)
                            <p class='appointment_reason_item' style="display: list-item; margin-left : 1em; ">{{ $item }}</p>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $('.appointment_reason_item').click(function() {
                console.log($(this).text())
                $('#appointment_reason').val($('#appointment_reason').val() + $(this).text() + ', ');

            })
        </script>

    </div>
</div>
<br>



<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
    aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                ...
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary">Save changes</button>
            </div>
        </div>
    </div>
</div>


<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>

<script type="text/javascript">
    function get_trush_tracks() {
        // trush_tracks = $("#touchTrackTable tbody tr").map(function() {
        //     name = $(this).find("td:nth-child(1)").text(),
        //         touchTracing = $(this).find("td:nth-child(2)").attr('value')

        //     return {
        //         "name": name,
        //         "touchTracing": touchTracing
        //     }
        // })
        // return trush_tracks;
        trush_tracks = []
        for (i = 0; i < $("#touchTrackTable tbody tr").length; i++) {
            tr = $("#touchTrackTable tbody tr")[i]
            name = $(tr).find("td:nth-child(1)").text(),
                touchTracing = $(tr).find("td:nth-child(2)").attr('value')
            trush_tracks.push({
                "name": name,
                "touchTracing": touchTracing
            })
        }

        return trush_tracks;
    }



    $(function() {

        $('.date').datepicker({
            language: 'th-th',
        });
        $('#status').val({{ $visit->status }})

        $('.collapse').collapse('show');


        $("#visit_edit_form").submit(function() {
            console.log("visit_edit_form")
            trush_tracks = get_trush_tracks()

            $("<input />").attr("type", "hidden")
                .attr("name", "trush_tracks")
                .attr("value", JSON.stringify(trush_tracks))
                .appendTo("#visit_edit_form")

            return true
        })

    });
</script>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // ยกเลิกการเลือกแท็บเริ่มต้น
    let tabs = document.querySelectorAll('.nav-link');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    let tabContents = document.querySelectorAll('.tab-pane');
    tabContents.forEach(content => content.classList.remove('show', 'active'));
});
</script>
