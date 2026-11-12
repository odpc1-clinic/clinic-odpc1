@include('partials.th_datepicker_guard')
{{-- ช่องวันที่ของส่วน TB (ส่งตรวจ/สั่งยา) จัดรูปแบบ พ.ศ. ตอนพิมพ์เสร็จ — เฉพาะคลินิกวัณโรค ไม่แตะช่องวันที่ของ STI/TMC --}}
@if(isset($visit) && (int) $visit->clinic === 2)
    @include('tb._date_input_js', ['scope' => '.js-tb-lab-fields, #tb-order'])
@elseif(isset($visit) && (int) $visit->clinic === 3)
    {{-- TMC ใช้กติกาวันที่เดียวกัน (คลินิกขอ 2569-10-05) — แผนนัดวัคซีน · วันที่ฉีด · วันเดินทาง/LMP --}}
    @include('tb._date_input_js', ['scope' => 'body'])
@endif
<style>
    /* หัวข้อแท็บ (ปุ่มย่อ/ขยาย) — จัดสีเป็นชุดให้ดูเป็นระบบ อ่านง่าย */
    [data-tabtoggle] {
        width: 100%;
        color: #fff !important;
        font-weight: 600;
        border: 0 !important;
        border-radius: 10px;
        padding: .7rem 1rem;
        margin-bottom: .3rem;
        box-shadow: 0 2px 6px rgba(0,0,0,.10);
        transition: filter .15s ease, transform .15s ease;
        text-align: center;
    }
    [data-tabtoggle]:hover { filter: brightness(1.08); transform: translateY(-1px); color: #fff !important; }
    [data-tabtoggle] font { color: #fff !important; }

    /* สีเฉพาะแต่ละแท็บ (ไล่โทนตามลำดับการตรวจ) */
    [data-tabtoggle][data-bs-target=".collapse"] { background: #334155 !important; } /* ย่อ/ขยายทั้งหมด - เทาเข้ม */
    [data-tabtoggle][href="#visitReason"]    { background: #2563eb !important; }     /* เหตุผลหลัก - น้ำเงิน */
    [data-tabtoggle][href="#visitSymptom"]   { background: #0891b2 !important; }     /* ประวัติเจ็บป่วย - ฟ้า */
    [data-tabtoggle][href="#visitContact"]   { background: #d97706 !important; }     /* คู่สัมผัส - ส้ม */
    [data-tabtoggle][href="#bodyCheck"]      { background: #16a34a !important; }     /* ตรวจร่างกาย - เขียว */
    [data-tabtoggle][href="#visitLab"]       { background: #7c3aed !important; }     /* Lab - ม่วง */
    [data-tabtoggle][href="#visitDiagnosis"] { background: #db2777 !important; }     /* วินิจฉัย - ชมพูเข้ม */
    [data-tabtoggle][href="#visitTreatment"] { background: #0d9488 !important; }     /* การรักษา - เทีล */
    [data-tabtoggle][href="#visitFollowup"]  { background: #0369a1 !important; }     /* ติดตาม - น้ำเงินเข้ม */
    [data-tabtoggle][href="#visitOther"]     { background: #64748b !important; }     /* อื่นๆ - เทา */
</style>
<?php
if (isset($visit)) {
    $patient = $visit->patient();
} else {
    $patient = App\Models\Patient::find($_GET['patient']);
}

?>
<div class="row">
@if($patient->photo)
    <img src="{{ route('patient.showPhoto', $patient->id) }}" alt="รูปผู้ป่วย" style="max-width:250px;" />
@else
    <p>ไม่มีรูปผู้ป่วย</p>
@endif    
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
        @if((int) ($patient->prefix ?? 0) === 99 && trim((string) ($patient->prefix_other ?? '')) !== '')
            <div class="small text-muted">{{ $patient->prefix_other }}</div>
        @endif
        {!! $errors->first('prefix', '<p class="help-block">:message</p>') !!}
    </div>
<div class="col-2 form-group {{ $errors->has('name') ? 'has-error' : '' }}">
    <label for="name" class="control-label">{{ 'ชื่่อ' }}</label>
    @php
        $nameColor = isset($patient->name) && (str_contains($patient->name, '(L') || str_contains($patient->name, '(T')) ? 'blue' : 'black';
    @endphp
    <input class="form-control" name="name" type="text" id="name" value="{{ isset($patient->name) ? $patient->name : '' }}" style="color: {{ $nameColor }};" readonly>
</div>

<div class="col-2 form-group {{ $errors->has('surname') ? 'has-error' : '' }}">
    <label for="surname" class="control-label">{{ 'นามสกุล' }}</label>
    @php
        $surnameColor = isset($patient->name) && (str_contains($patient->name, '(L') || str_contains($patient->name, '(T')) ? 'blue' : 'black';
    @endphp
    <input class="form-control" name="surname" type="text" id="surname" value="{{ isset($patient->surname) ? $patient->surname : '' }}" style="color: {{ $surnameColor }};" readonly>
</div>
    
    <div class="col-2 form-group {{ $errors->has('birth_date') ? 'has-error' : '' }}">
        <label for="birth_date" class="control-label">{{ 'วันเกิด' }}</label>
        <input class="form-control" name="birth_date" type="text" id="birth_date"
            value="{{ isset($patient->birth_date) ? $patient->birth_date : '' }}" readonly>
    </div>
    {{-- แสดงอย่างเดียว: หน้าตรวจไม่ได้บันทึกสองช่องนี้ แก้ที่หน้าข้อมูลคนไข้
         ห้าม required — หลังแยกพาสปอร์ต (2569-10-07) คนไข้ต่างชาติไม่มีเลขบัตร จะกด Save หน้าตรวจไม่ได้ --}}
    <div class="col-2 form-group">
    <label for="id_card_number" class="control-label">เลขบัตรประชาชน</label>
    <input class="form-control" name="id_card_number" type="text" id="id_card_number"
        value="{{ $patient->id_card_number ?? '' }}" placeholder="-" readonly>
</div>
    <div class="col-2 form-group">
    <label for="passport_view" class="control-label">เลขพาสปอร์ต</label>
    <input class="form-control" type="text" id="passport_view"
        value="{{ $patient->passport ?? '' }}" placeholder="-" readonly>
</div>
    <div class="col-2 form-group">
    <label for="nationality_view" class="control-label">สัญชาติ</label>
    <input class="form-control" type="text" id="nationality_view"
        value="{{ isset($patient) && $patient instanceof \App\Models\Patient ? $patient->nationalityText() : ($patient->nationality ?? '') }}" placeholder="-" readonly>
</div>
    @if(\App\Models\Patient::englishNameReady())
    <div class="col-3 form-group">
    <label for="name_en_view" class="control-label">ชื่อ-สกุล (อังกฤษ)</label>
    <input class="form-control" type="text" id="name_en_view"
        value="{{ trim(($patient->name_en ?? '') . ' ' . ($patient->surname_en ?? '')) }}" placeholder="- (กรอกที่หน้าข้อมูลผู้ป่วย)" readonly>
</div>
    @endif
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
        <label for="tel" class="control-label">{{ 'เบอร์โทร' }} <font color="red">(แก้ไขได้)</font></label>
        <input class="form-control" name="tel" type="text" id="tel" value="{{ isset($patient->tel) ? $patient->tel : '' }}">
   </div>
    {{-- อีเมลคนไข้ (2569-09-30) — แก้ได้ที่นี่เหมือนเบอร์โทร บันทึกกลับไปที่ทะเบียนคนไข้
         ใช้ type="text" ไม่ใช่ "email" — พิมพ์ผิดรูปแบบแล้วเบราว์เซอร์จะบล็อกปุ่ม Save ของทั้งหน้าตรวจ --}}
    <div class="col-3 form-group {{ $errors->has('email') ? 'has-error' : '' }}">
        <label for="patient_email" class="control-label">อีเมล <font color="red">(แก้ไขได้)</font></label>
        <input class="form-control" name="email" type="text" inputmode="email" id="patient_email" maxlength="191"
               value="{{ $patient->email ?? '' }}" placeholder="—">
   </div>
      <div class="col-3 form-group {{ $errors->has('status') ? 'has-error' : '' }}">
                <label for="status" class="control-label">{{ 'Status' }} <font color="red">(แก้ไขได้)</font></label>
                {{-- <input class="form-control" name="status" type="number" id="status" value="{{ isset($patient->status) ? $patient->status : '' }}"> --}}
                
                <select class="form-control" name="patient_status" id="patient_status">
    @foreach (App\Models\Patient::$statusOption as $key => $value)
        <option value="{{ $key }}" @if (isset($patient->status) && $key == $patient->status) selected="selected" @endif>
            {{ $value }}
        </option>
    @endforeach
</select>

                {!! $errors->first('status', '<p class="help-block">:message</p>') !!}
            </div>

<div class="col-4 form-group {{ $errors->has('occupation') ? 'has-error' : '' }}">
        <label for="occupation" class="control-label">{{ 'อาชีพ' }}</label>
        {{-- <input class="form-control" name="occupation" type="number" id="occupation"
            value="{{ isset($patient->occupation) ? $patient->occupation : '' }}"> --}}
        <select class="form-control" name="occupation" id="occupation">
            @foreach (App\Models\Patient::$occupationOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->occupation) && $key == $patient->occupation) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('occupation', '<p class="help-block">:message</p>') !!}
    </div>

    <div id="other_occupation_div" class="col-8 form-group {{ $errors->has('other_occupation') ? 'has-error' : '' }}" style="display:none" >
        <label for="other_occupation" class="control-label">{{ 'อาชีพ' }}</label>
        <input class="form-control" name="other_occupation" type="text" id="other_occupation" value="{{ isset($patient->other_occupation) ? $patient->other_occupation : '' }}" >
        {!! $errors->first('other_occupation', '<p class="help-block">:message</p>') !!}
    </div>

    <div id="organization_div" class="col-8 orm-group {{ $errors->has('organization') ? 'has-error' : '' }}" style="{{ isset($patient->occupation)  && $patient->occupation ==10  ? '' : 'display:none' }}">
        <label for="organization" class="control-label">{{ 'ชื่อสถานประกอบการ' }}</label>
        <input class="form-control" name="organization" type="text" id="organization" value="{{ isset($patient->organization) ? $patient->organization : '' }}">
        {!! $errors->first('organization', '<p class="help-block">:message</p>') !!}
    </div>



   <div class="col-3 form-group {{ $errors->has('congenitaldisease') ? 'has-error' : '' }}">
    <label for="congenitaldisease" class="control-label" style="color: red;">{{ 'โรคประจำตัว' }}</label>
    <input class="form-control" name="congenitaldisease" type="text" id="congenitaldisease" style="color: red;"
        value="{{ isset($patient->congenitaldisease) ? $patient->congenitaldisease : '' }}">
</div>

<div class="col-3 form-group {{ $errors->has('drug_allergy') ? 'has-error' : '' }}">
    <label for="drug_allergy" class="control-label" style="color: red;">{{ 'ประวัติแพ้ยา' }}</label>
    <input class="form-control" name="drug_allergy" type="text" id="drug_allergy" style="color: red;"
        value="{{ isset($patient->drug_allergy) ? $patient->drug_allergy : '' }}">
</div>

{{-- ประวัติวัคซีน STIs (2569-10-21) — เก็บที่ทะเบียนคนไข้ (ค่าเดียวกับหน้าลงทะเบียน) · แก้ที่นี่แล้วกด Save = แก้ของคนไข้ --}}
<div class="col-6 form-group">
    <label for="sti_vaccine_history" class="control-label">ประวัติการได้รับวัคซีน STIs</label>
    <input class="form-control" name="sti_vaccine_history" type="text" id="sti_vaccine_history" maxlength="1000"
        placeholder="เช่น HPV 2 เข็ม · HBV ครบ · Mpox 1 เข็ม · ไม่เคยได้รับ"
        value="{{ $patient->sti_vaccine_history ?? '' }}">
</div>

{{-- สิทธิการรักษา (2569-10-21 พยาบาลขอแก้จากหน้าตรวจได้) — ค่าเดียวกับหน้าลงทะเบียน (ทะเบียนคนไข้) ใบนัดดึงไปพิมพ์ --}}
<div class="col-3 form-group">
    <label for="visit_health_right" class="control-label">สิทธิการรักษา</label>
    <select class="form-control" name="health_right" id="visit_health_right">
        @foreach (App\Models\Patient::$healthRightOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->health_right) && (string) $key === (string) $patient->health_right) selected @endif>{{ $value }}</option>
        @endforeach
    </select>
</div>
<div class="col-3 form-group" id="visit_health_right_hospital_wrap">
    <label for="visit_health_right_hospital" class="control-label">สถานพยาบาลหลัก</label>
    <input class="form-control" name="health_right_hospital" type="text" id="visit_health_right_hospital" maxlength="255"
           placeholder="เช่น รพ.วังเหนือ" value="{{ $patient->health_right_hospital ?? '' }}">
</div>
<script>
// ช่อง รพ. ซ่อนเมื่อเลือกสิทธิที่ไม่ผูกต้นสังกัด (เบิกราชการ/จ่ายเอง) — แบบเดียวกับหน้าลงทะเบียน · ซ่อนอย่างเดียว ไม่ล้างค่า
(function () {
    var sel = document.getElementById('visit_health_right'), wrap = document.getElementById('visit_health_right_hospital_wrap');
    if (!sel || !wrap) return;
    var noHospital = @json(App\Models\Patient::$healthRightNoHospital);
    function sync() { wrap.style.display = noHospital.indexOf(sel.value) !== -1 ? 'none' : ''; }
    sel.addEventListener('change', sync); sync();
})();
</script>


<!-- เรียกใช้ SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* ปรับตำแหน่งให้เป็น "กลางซ้าย" จริงๆ */
    .swal2-left-center {
        position: fixed !important;
        left: 10px !important;
        top: 50% !important;
        transform: translateY(-50%) !important;
        width: fit-content !important;
        z-index: 1050 !important; /* ไม่ให้ทับ Alert หลัก */
    }
</style>


    <div class="col-3 form-group {{ $errors->has('marital_status') ? 'has-error' : '' }}">
        <label for="marital_status" class="control-label">{{ 'สถานภาพ' }}</label>
        {{-- <input class="form-control" name="marital_status" type="number" id="marital_status"
        value="{{ isset($patient->marital_status) ? $patient->marital_status : '' }}"> --}}
        <select class="form-control" name="marital_status" id="marital_status">
            @foreach (App\Models\Patient::$maritalStatusOption as $key => $value)
            <option value="{{ $key }}" @if (isset($patient->marital_status) && $key == $patient->marital_status) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('marital_status', '<p class="help-block">:message</p>') !!}
    </div>
    <div><input class="btn btn-primary" type="submit" value="บันทึก/แก้ไขข้อมูลผู้ป่วย"></div>
</div>
{{-- แถบบัตรวัณโรค TB 01 — ย้ายมาไว้ใต้ข้อมูลคนไข้ (ต่อจากโรคประจำตัว/แพ้ยา) คลินิกขอ 2569-10-02
     ไม่แทรกกลางแถวข้อมูลคนไข้ เพราะจะตัดช่องโรคประจำตัวกับแพ้ยา (สีแดงคู่กัน) ออกจากกัน
     ตอนนี้อยู่ "ใน" ฟอร์มหลักแล้ว แถบนี้จึงห้ามมี <form> — ปุ่มบันทึก DOT ใช้ JS สร้างฟอร์มต่อกับ body --}}
{{-- 2569-10-02 (บ่าย): เอาแถบนี้ออกจากหน้าตรวจ — ซ้ำกับกล่อง F/U ใต้ "การมาครั้งนี้" ซึ่งมีปุ่มเปิดบัตรอยู่แล้ว
     (คลินิกขอ) ไฟล์ tb_card ยังเก็บไว้ ถ้าจะเอากลับแค่ include คืน --}}
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
            document.querySelectorAll('.send_from_item').forEach(item => {
            item.style.cursor = 'pointer';
            });
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
            document.querySelectorAll('.other_from_item').forEach(item => {
            item.style.cursor = 'pointer';
            });            
        </script>

</div>
<br>
<hr>
<style type="text/css">
    /* หัวหมวดของหน้าตรวจ (แถบสีที่กดเปิด/ปิด) กว้างเต็มและเว้นระยะเท่ากันทุกคลินิก (คลินิกขอ 2569-11-02)
       เดิมบางหมวดอยู่ใน col-11 (สั้นกว่าเพื่อน) และระยะห่างมาจาก <br> ที่วางไม่เท่ากันแต่ละหมวด
       ใช้ร่วม STI / TB / TMC — หัวหมวดในไฟล์ย่อย (tb_screening, tmc_*, vitals, followup) ได้กติกาเดียวกัน */
    #visit_edit_form a.btn[data-tabtoggle][href^="#"] {
        display: block; width: 100% !important;
        margin-top: .9rem; margin-bottom: 0;
        padding-top: .55rem; padding-bottom: .55rem;
    }
    /* <br> ที่วางคั่นหัวหมวดไว้ (ไม่เท่ากัน) — ระยะมาจาก margin ด้านบนแทน */
    /* (หัวหมวดในไฟล์ย่อยห่อแบบ .d-grid > a ตรง ๆ หรืออยู่ใน .js-tb-screen-part — ครอบทุกแบบด้วย :has) */
    #visit_edit_form br:has(+ * a.btn[data-tabtoggle]),
    #visit_edit_form br:has(+ br + * a.btn[data-tabtoggle]),
    #visit_edit_form br:has(+ br + br + * a.btn[data-tabtoggle]),
    #visit_edit_form br:has(+ style + br + * a.btn[data-tabtoggle]),
    #visit_edit_form br:has(+ style + * a.btn[data-tabtoggle]),
    #visit_edit_form .js-tb-screen-part > br:last-child { display: none; }
    #visit_edit_form .d-grid:has(a.btn[data-tabtoggle]),
    #visit_edit_form .js-tb-screen-part:has(> .d-grid > a.btn[data-tabtoggle]) { margin-top: 0 !important; margin-bottom: 0 !important; }
</style>
 <div class="btn-group text-center" role="group" aria-label="Toggle Collapses" style="width: 100%">
   <button style="text-center" class="btn btn-warning" type="button" data-tabtoggle="1" data-bs-target=".collapse" aria-expanded="false" aria-controls="visitReason visitSymptom visitContact bodyCheck visitLab visitDiagnosis visitTreatment visitOther"><font size="4">คลิกย่อแท็บทั้งหมด</font></button>
  </div> 

<!-- <div class="btn-group text-center" role="group" aria-label="Toggle Collapses" style="width: 100%">
   <button id="collapseButton" style="text-center" class="btn btn-warning" type="button" data-tabtoggle="1" data-bs-target=".collapse" aria-expanded="false" aria-controls="visitReason visitSymptom visitContact bodyCheck visitLab visitDiagnosis visitTreatment visitOther">
       <font size="4">คลิกย่อแท็บทั้งหมด</font>
   </button>
</div> -->

<!-- <script>
    document.addEventListener("DOMContentLoaded", function() {
        // หน่วงเวลาเล็กน้อยเพื่อให้ Bootstrap โหลดเสร็จสมบูรณ์ก่อนคลิกปุ่ม
        setTimeout(function() {
            document.getElementById("collapseButton").click();
        }, 500); // รอ 500 มิลลิวินาที
    });
</script> -->

<hr>
{{-- เหตุผลหลัก/พฤติกรรมเสี่ยง/วิธีคุมกำเนิด เป็นของ STI เท่านั้น
     TMC ไม่ใช้ (LMP ย้ายไปโซนซักประวัติ TMC แล้ว) · TB ก็ไม่ใช้ มีใบซักประวัติของตัวเอง (2569-09-29) --}}
@if((int) ($visit->clinic ?? 1) === 1)
    @include('visit.form.reason')
@endif

{{-- คลินิก TMC มีงานเฉพาะของตัวเอง (สัญญาณชีพ + ซักประวัติการเดินทาง + ฉีดวัคซีน)
     คลินิก TB ใช้เฉพาะการ์ดสัญญาณชีพ เพราะใบ Form 1 ต้องกรอก BW/Ht/BMI/T/HR/RR/BP/O2sat
     ส่วน STI ไม่ได้ใช้ทั้งคู่ --}}
@if(isset($visit) && (int) $visit->clinic === 3)
    @include('visit.form.tmc')
@elseif(isset($visit) && (int) $visit->clinic === 2)
    {{-- TB เรียงตามงานจริง (คลินิกขอ 2569-10-01): ซักประวัติ/คัดกรอง → สัญญาณชีพ → ตรวจร่างกาย → เอกซเรย์
         ใบคัดกรองแบ่งเป็นส่วน ๆ ใน tb_screening (@section) — include ครั้งเดียวที่นี่ แล้ว @yield ตามตำแหน่ง --}}
    @include('visit.form.tb_screening')
    {{-- นัดครั้งนี้มาเพื่ออะไร — กล่องเดียวกับ TMC (คลินิก TB ขอ 2569-10-14: มาตามนัดแล้วหน้าตรวจไม่บอกว่ามีนัดเดิม) --}}
    @include('visit.form._tmc_this_visit')
    {{-- การมาครั้งนี้ + โรคประจำตัว/สัมผัส/เคยป่วย ขึ้นก่อนซักประวัติ (คลินิกขอ 2569-10-02) --}}
    @yield('tbp_visit_type')
    @yield('tbp_background')
    @yield('tbp_history')
    @include('visit.form.vitals')
    @yield('tbp_pe')
    @yield('tbp_cxr')
@endif

<br>

{{-- ประวัติการเจ็บป่วยปัจจุบัน — คลินิกวัณโรคไม่ใช้ เพราะใบซักประวัติวัณโรคมีช่องอาการของตัวเองครบแล้ว
     (พี่กานต์ยืนยัน 2569-09-29: หน้าคลินิกวัณโรคเอาเฉพาะของ TB) --}}
@if((int) ($visit->clinic ?? 1) !== 2)
<div class="d-grid">
    <div class="row">
        <div>
            <a class="btn btn-light" data-tabtoggle="1" href="#visitSymptom" role="button" aria-expanded="false" style="width: 100%" aria-controls="visitSymptom">
                <font size="4">@if((int) ($visit->clinic ?? 1) === 3)อาการสำคัญ@else ประวัติการเจ็บป่วยปัจจุบัน**@endif</font>
            </a>
            
        </div>
        {{-- ปุ่มบันทึกข้างหัวหมวดย้ายไปไว้ท้ายหมวด (2569-10-07) — ดูสคริปต์ section-save ใน visit/edit --}}
    </div>


</div>
<div class="collapse" id="visitSymptom">
    <div class="card card-body">
        <div class="form-group {{ $errors->has('symptom') ? 'has-error' : '' }}">
            <label for="symptom" class="control-label click" data-bs-toggle="modal" data-bs-target="#symptom_model">{{ (int) ($visit->clinic ?? 1) === 3 ? 'อาการสำคัญ' : 'อาการเจ็บป่วยครั้งปัจจุบัน' }}</label>
            @include('visit.field_history_button', ['field' => 'symptom', 'target' => 'symptom'])
            <textarea class="form-control" rows="5" name="symptom" type="textarea" id="symptom">{{ isset($visit->symptom) ? $visit->symptom : '' }}</textarea>
            {!! $errors->first('symptom', '<p class="help-block">:message</p>') !!}
        </div>

        {{-- วันที่เริ่มป่วย — ใช้ในรายงาน 506 (ช่อง DATESICK)
             ย้ายมาจากหน้าเพิ่มวินิจฉัย: หนึ่ง visit มีวันเริ่มป่วยเดียวพอ ไม่ต้องกรอกซ้ำทีละโรค (2569-08-07)
             ค่าเริ่มต้น = วันนี้ ถ้าคนไข้ป่วยมาก่อนวันที่มาตรวจ ให้แก้เป็นวันที่เริ่มมีอาการจริง --}}
        {{-- TMC ไม่ใช้ (คลินิกขอ 2569-09-30) — คนมาขอวัคซีน/คำแนะนำก่อนเดินทาง ไม่ได้ป่วย และไม่เข้ารายงาน 506
             ไม่มีช่องนี้ในฟอร์ม = ไม่ถูกส่งไป ค่าเดิมในฐานไม่ถูกแตะ --}}
        @if((int) ($visit->clinic ?? 1) !== 3)
        <div class="form-group col-md-4 {{ $errors->has('onset_date') ? 'has-error' : '' }}">
            <label for="onset_date" class="control-label">วันที่เริ่มป่วย</label>
            <input class="form-control date" type="text" autocomplete="off" name="onset_date" id="onset_date"
                   value="{{ $visit->onset_date ?? (now()->format('j/n/') . (now()->year + 543)) }}" placeholder="ว/ด/ป">
            {!! $errors->first('onset_date', '<p class="help-block">:message</p>') !!}
        </div>
        @endif

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
            document.querySelectorAll('.symptom_item').forEach(item => {
            item.style.cursor = 'pointer';
            });             
        </script>

    </div>
</div>
@endif
<br>

{{-- คู่สัมผัส/พฤติกรรมเสี่ยง เป็นของ STI เท่านั้น
     TMC ไม่ใช้ · TB ก็ไม่ใช้ (ผู้สัมผัสวัณโรคเก็บคนละแบบ อยู่ในใบคัดกรองผู้สัมผัส Form 2/3) --}}
@if (isset($visit) && (int) $visit->clinic === 1)
    <div class="d-grid">

        <div class="row">
            <div>
                <a class="btn btn-warning" data-tabtoggle="1" href="#visitContact" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitContact">
                    <font size="4">คู่นอน คู่สัมผัส (contact)</font>
                </a>
            </div>
            <!-- <div class="col-1">
                <div class="col-auto form-group">
                    <button type="submit" class="btn btn-primary btn-save-th"><i class="fa fa-save me-1"></i> บันทึก</button>
                </div>
            </div> -->
        </div>

    </div>
    <div class="collapse" id="visitContact">
        <div class="card card-body">
            @include('visit.contact', ['visit' => $visit, 'page' => request('page', 1)])
        </div>
    </div>
    <br>
@endif

{{-- การตรวจร่างกาย (ช่องข้อความรวม + การ์ดมาร์คร่างกายของ STI)
     คลินิกวัณโรคไม่ใช้ เพราะใบซักประวัติวัณโรคมีช่องแยก GA/HEENT/Heart/Chest/Abdomen/Ext/Neuro
     และมีรูปปอดให้มาร์คของตัวเองแล้ว (2569-09-29) --}}
@if (isset($visit) && (int) ($visit->clinic ?? 1) !== 2)
    @php
        // TMC แทบไม่ได้ลงตรวจร่างกาย — พับไว้ตั้งแต่เปิดหน้า ลดความยาวหน้า (คลินิกขอ 2569-10-07)
        // แต่ถ้า visit นี้มีข้อมูลตรวจร่างกายแล้ว ต้องกางให้เห็นเหมือนเดิม ไม่ให้ข้อมูลที่บันทึกไว้ "หายไป" จากสายตา
        $__peFold = false;
        if ((int) ($visit->clinic ?? 1) === 3) {
            $__peData = is_array($visit->tmc_pe ?? null) ? $visit->tmc_pe : [];
            $__peFold = empty($__peData['normal_all']) && empty($__peData['sys'])
                && trim((string) ($__peData['note'] ?? '')) === '' && trim((string) ($visit->body_check ?? '')) === '';
        }
    @endphp
    <div class="d-grid">
        <div class="row">
            <div>
                <a class="btn btn-success" data-tabtoggle="1" href="#bodyCheck" role="button"
                    style="width: 100%" aria-expanded="{{ $__peFold ? 'false' : 'true' }}" aria-controls="bodyCheck">
                    <font size="4">การตรวจร่างกาย @if($__peFold)<span style="font-size:.8em;opacity:.85;">(ยังไม่ได้บันทึก · กดเพื่อเปิด)</span>@endif</font>
                </a>
            </div>
        </div>
    </div>

    {{-- data-keep-collapsed = ตัว "เปิดทุกแท็บตอนโหลด" ท้ายไฟล์นี้จะข้ามกล่องนี้ --}}
    <div class="collapse" id="bodyCheck" @if($__peFold) data-keep-collapsed="1" @endif>
        <div class="card card-body">

            @if((int) ($visit->clinic ?? 1) === 3)
                {{-- TMC: normal all + สวิตช์ระบบที่ผิดปกติ (คลินิกขอ 2569-09-30) — ไม่มีช่อง body_check ตรง ๆ
                     ระบบประกอบข้อความจากสวิตช์ลง body_check ให้ตอนบันทึก --}}
                @include('visit.form.tmc_pe')
            @else
            {{-- ⬇️ Drop-in replacement for the body-marking block --}}
            <div class="collapse " id="bodyCheck">
        <div class="card card-body">
            <div class="form-group {{ $errors->has('body_check') ? 'has-error' : '' }}">
                <label for="body_check" class="control-label">{{ 'การตรวจร่างกาย' }}</label>
                @include('visit.field_history_button', ['field' => 'body_check', 'target' => 'body_check'])
                <textarea class="form-control" rows="5" name="body_check" type="textarea" id="body_check">{{ isset($visit->body_check) ? $visit->body_check : '' }}</textarea>
                {!! $errors->first('body_check', '<p class="help-block">:message</p>') !!}
            </div>
        </div>
    </div>
            @endif
            {{-- รูปมาร์คร่างกาย (การ์ด STI) — ใช้เฉพาะคลินิก STI
                 TMC ไม่ได้ใช้ · คลินิก TB ก็ไม่ได้ใช้ (พี่กานต์แจ้ง 2569-09-18 ว่าคัดกรองวัณโรคไม่ต้องมีรูป PV)
                 คงช่องข้อความ "การตรวจร่างกาย" ด้านบนไว้ เพราะใช้ร่วมกันทุกคลินิก --}}
            @if((int) ($visit->clinic ?? 1) === 1)
            <div class="mb-3">
                <label class="form-label">การมาร์คร่างกาย (2 แผ่น)</label>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <label class="me-2">แผ่น:</label>
                    <div class="btn-group me-3" role="group" aria-label="Choose Card">
                        <button type="button" class="btn btn-primary btn-sm" data-card="card1" id="btnCard1">Card 1 (pv)</button>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-card="card2" id="btnCard2">Card 2 (body)</button>
                    </div>

                    <label class="me-1">สี:</label>
                    <input type="color" id="markColorPicker" value="#e63946" class="me-3">

                    <label class="me-1">ขนาด:</label>
                    <input type="range" id="markSize" min="3" max="20" value="3" class="me-3">

                    <label class="me-1">โหมด:</label>
                    <select id="drawMode" class="form-select form-select-sm" style="width: auto;">
                        <option value="point">มาร์คจุด</option>
                        <option value="free">วาดเส้น</option>
                    </select>

                    <button type="button" onclick="undoLast()" class="btn btn-warning btn-sm ms-2">Undo</button>
                    <button type="button" onclick="clearAll()" class="btn btn-danger btn-sm">Clear ทั้งหมด (แผ่นนี้)</button>
                    <button type="button" onclick="zoom(1.2)" class="btn btn-secondary btn-sm ms-2">Zoom In</button>
                    <button type="button" onclick="zoom(0.8)" class="btn btn-secondary btn-sm">Zoom Out</button>
                    <button type="button" onclick="togglePan()" class="btn btn-info btn-sm ms-2">เลื่อนภาพ: <span id="panToggle">OFF</span></button>
                </div>

                <div id="canvasWrap" style="position:relative; width:100%; max-width:1200px; margin: auto;">
                    <canvas id="visitCanvas_card1" width="1200" height="200" style="border:1px solid #ccc; cursor:crosshair; width: 100%; height: auto;"></canvas>
                    <canvas id="visitCanvas_card2" width="1200" height="200" style="border:1px solid #ccc; cursor:crosshair; display:none; width: 100%; height: auto;"></canvas>
                </div>

                <input type="hidden" name="body_check_marks" id="body_check_marks">
            </div>
            @endif

            <script>
            (() => {
                // --- Elements ---
                const canvases = {
                    card1: document.getElementById('visitCanvas_card1'),
                    card2: document.getElementById('visitCanvas_card2'),
                };
                // TMC ซ่อนรูปมาร์คร่างกายไว้ (ไม่มี canvas) — ออกก่อน ไม่งั้น getContext ของ null จะพัง
                if (!canvases.card1 || !canvases.card2) return;
                const ctxMap = {
                    card1: canvases.card1.getContext('2d'),
                    card2: canvases.card2.getContext('2d')
                };
                const imgMap = { card1: new Image(), card2: new Image() };
                imgMap.card1.src = '{{ asset("images/visit_cards/card.png") }}';
                imgMap.card2.src = '{{ asset("images/visit_cards/card2.png") }}';

                const colorPicker = document.getElementById('markColorPicker');
                const sizePicker  = document.getElementById('markSize');
                const drawMode    = document.getElementById('drawMode');
                const panToggle   = document.getElementById('panToggle');
                const hiddenOut   = document.getElementById('body_check_marks');

                // --- State ---
                let active = 'card1';
                const view = {
                    card1: { scale: 1, offsetX: 0, offsetY: 0 },
                    card2: { scale: 1, offsetX: 0, offsetY: 0 }
                };
                const drawState = {
                    isDrawing: false,
                    isPanning: false,
                    startPan: { x: 0, y: 0 },
                    panMode: false,
                    currentPath: []
                };
                const marks = { card1: [], card2: [] };

                // --- Utils ---
                const activeCanvas = () => canvases[active];
                const activeCtx    = () => ctxMap[active];
                const activeImg    = () => imgMap[active];
                const activeView   = () => view[active];

                function setActiveCard(key) {
                    if (key === active) return;
                    canvases.card1.style.display = key === 'card1' ? 'block' : 'none';
                    canvases.card2.style.display = key === 'card2' ? 'block' : 'none';
                    active = key;
                    updateButtons();
                    drawAll();
                }

                function updateButtons() {
                    document.getElementById('btnCard1').classList.toggle('btn-primary', active === 'card1');
                    document.getElementById('btnCard1').classList.toggle('btn-outline-primary', active !== 'card1');
                    document.getElementById('btnCard2').classList.toggle('btn-primary', active === 'card2');
                    document.getElementById('btnCard2').classList.toggle('btn-outline-primary', active !== 'card2');
                }

                function zoom(f) {
                    const v = activeView();
                    const c = activeCanvas();
                    const oldScale = v.scale;
                    v.scale *= f;
                    v.offsetX = (c.width / 2) - ( (c.width / 2) - v.offsetX ) * (v.scale / oldScale);
                    v.offsetY = (c.height / 2) - ( (c.height / 2) - v.offsetY ) * (v.scale / oldScale);
                    drawAll();
                }

                function togglePan() {
                    drawState.panMode = !drawState.panMode;
                    panToggle.textContent = drawState.panMode ? 'ON' : 'OFF';
                    activeCanvas().style.cursor = drawState.panMode ? 'move' : 'crosshair';
                }
                window.zoom = zoom;
                window.togglePan = togglePan;

                function getPos(e) {
                    const rect = activeCanvas().getBoundingClientRect();
                    const v = activeView();
                    const mouseX = (e.clientX - rect.left) * (activeCanvas().width / rect.width);
                    const mouseY = (e.clientY - rect.top) * (activeCanvas().height / rect.height);
                    const worldX = (mouseX - v.offsetX) / v.scale;
                    const worldY = (mouseY - v.offsetY) / v.scale;
                    return { x: worldX / activeCanvas().width, y: worldY / activeCanvas().height };
                }

                function drawMark(ctx, mark) {
                    const c = activeCanvas();
                    if (mark.type === 'point') {
                        const cx = mark.x * c.width;
                        const cy = mark.y * c.height;
                        ctx.beginPath();
                        ctx.arc(cx, cy, mark.size || 7, 0, Math.PI * 2);
                        ctx.fillStyle = mark.color || '#e63946';
                        ctx.fill();
                        if (mark.note) {
                            ctx.font = '13px Kanit, sans-serif';
                            ctx.fillStyle = '#ad0505';
                            ctx.fillText(mark.note, cx + 9, cy - 9);
                        }
                    } else if (mark.type === 'path') {
                        ctx.beginPath();
                        ctx.moveTo(mark.points[0][0] * c.width, mark.points[0][1] * c.height);
                        for (let i = 1; i < mark.points.length; i++) {
                            ctx.lineTo(mark.points[i][0] * c.width, mark.points[i][1] * c.height);
                        }
                        ctx.strokeStyle = mark.color;
                        ctx.lineWidth = mark.size || 4;
                        ctx.lineCap = 'round';
                        ctx.stroke();
                    }
                }
                
                function drawAll() {
                    const c = activeCanvas();
                    const ctx = activeCtx();
                    const img = activeImg();
                    const { scale, offsetX, offsetY } = activeView();
                    ctx.save();
                    ctx.setTransform(1, 0, 0, 1, 0, 0);
                    ctx.clearRect(0, 0, c.width, c.height);
                    ctx.translate(offsetX, offsetY);
                    ctx.scale(scale, scale);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    marks[active].forEach(m => drawMark(ctx, m));
                    ctx.restore();
                }

                // [FIXED] Save all marks into a single array with a 'card' property
                function saveMarks() {
                    const combinedMarks = [];
                    // Loop through card1, then card2
                    Object.keys(marks).forEach(cardKey => {
                        marks[cardKey].forEach(mark => {
                            // Add the 'card' property to each mark object
                            combinedMarks.push({ ...mark, card: cardKey });
                        });
                    });
                    hiddenOut.value = JSON.stringify(combinedMarks);
                    // แจ้งว่า body map เปลี่ยน -> มีข้อมูลยังไม่ได้บันทึก
                    document.dispatchEvent(new Event('visit:dirty'));
                }

                // [FIXED] Load marks from a single array and distribute them
                function loadExisting() {
                    marks.card1 = [];
                    marks.card2 = [];
                    try {
                        const existingData = @json($visit->body_check_marks ?? null);

                        // Handles new unified array format
                        if (Array.isArray(existingData)) {
                            existingData.forEach(mark => {
                                const cardKey = mark.card || 'card1'; // Default to card1 for old data
                                if (marks[cardKey]) {
                                    const { card, ...markData } = mark; // separate the 'card' property
                                    marks[cardKey].push(markData);
                                }
                            });
                        } 
                        // Handles old object-based format for backward compatibility
                        else if (existingData && typeof existingData === 'object') {
                            marks.card1 = existingData.card1 || [];
                            marks.card2 = existingData.card2 || [];
                        }
                    } catch (e) {
                        console.error("Error loading marks:", e);
                    }
                    saveMarks(); // Immediately save in the new correct format
                    drawAll();
                }

                function bindCanvas(key) {
                    const c = canvases[key];
                    c.addEventListener('mousedown', e => {
                        if (active !== key) return;
                        if (drawState.panMode) {
                            drawState.isPanning = true;
                            drawState.startPan = { x: e.clientX, y: e.clientY };
                            return;
                        }
                        if (drawMode.value === 'free') {
                            drawState.isDrawing = true;
                            drawState.currentPath = [];
                            const pos = getPos(e);
                            drawState.currentPath.push([pos.x, pos.y]);
                        }
                    });

                    c.addEventListener('mousemove', e => {
                        if (active !== key) return;
                        if (drawState.isPanning) {
                            const v = activeView();
                            const dx = e.clientX - drawState.startPan.x;
                            const dy = e.clientY - drawState.startPan.y;
                            v.offsetX += dx;
                            v.offsetY += dy;
                            drawState.startPan = { x: e.clientX, y: e.clientY };
                            drawAll();
                            return;
                        }
                        if (drawState.isDrawing && drawMode.value === 'free') {
                            const pos = getPos(e);
                            drawState.currentPath.push([pos.x, pos.y]);
                            drawAll();
                            
                            const ctx = activeCtx();
                            const v = activeView();
                            ctx.save();
                            ctx.translate(v.offsetX, v.offsetY);
                            ctx.scale(v.scale, v.scale);
                            ctx.beginPath();
                            ctx.moveTo(drawState.currentPath[0][0] * c.width, drawState.currentPath[0][1] * c.height);
                            for (let i = 1; i < drawState.currentPath.length; i++) {
                                ctx.lineTo(drawState.currentPath[i][0] * c.width, drawState.currentPath[i][1] * c.height);
                            }
                            ctx.strokeStyle = colorPicker.value;
                            ctx.lineWidth = parseInt(sizePicker.value);
                            ctx.lineCap = 'round';
                            ctx.stroke();
                            ctx.restore();
                        }
                    });
                    
                    const endDrawing = () => {
                        if (active !== key) return;
                        if (drawState.isDrawing && drawMode.value === 'free') {
                            drawState.isDrawing = false;
                            if (drawState.currentPath.length > 1) {
                                marks[active].push({ type: 'path', points: drawState.currentPath, color: colorPicker.value, size: parseInt(sizePicker.value) });
                                saveMarks();
                                drawAll();
                            }
                        }
                        drawState.isPanning = false;
                    };
                    
                    c.addEventListener('mouseup', endDrawing);
                    c.addEventListener('mouseleave', endDrawing);

                    c.addEventListener('click', e => {
                        if (active !== key) return;
                        if (drawMode.value !== 'point' || drawState.panMode) return;
                        const pos = getPos(e);
                        const comment = prompt('ใส่ข้อความกำกับจุดนี้ (หรือปล่อยว่าง)');
                        if (comment === null) return;
                        marks[active].push({ type: 'point', x: +pos.x.toFixed(4), y: +pos.y.toFixed(4), color: colorPicker.value, note: comment, size: parseInt(sizePicker.value) });
                        drawAll();
                        saveMarks();
                    });

                    c.addEventListener('contextmenu', e => {
                        if (active !== key) return;
                        e.preventDefault();
                        const pos = getPos(e);
                        const r = 0.02; // click radius
                        const idx = marks[active].findIndex(p => {
                            if (p.type === 'point') {
                                return Math.hypot(p.x - pos.x, p.y - pos.y) < r;
                            } else {
                                return p.points.some(pt => Math.hypot(pt[0] - pos.x, pt[1] - pos.y) < r);
                            }
                        });
                        if (idx !== -1 && confirm('ต้องการลบมาร์คนี้ใช่หรือไม่?')) {
                            marks[active].splice(idx, 1);
                            drawAll();
                            saveMarks();
                        }
                    });
                }

                // Bind both canvases
                bindCanvas('card1');
                bindCanvas('card2');

                // Global actions
                function undoLast() { if (marks[active].length) { marks[active].pop(); drawAll(); saveMarks(); } }
                function clearAll() { if (confirm('ลบทั้งหมดของแผ่นนี้?')) { marks[active] = []; drawAll(); saveMarks(); } }
                window.undoLast = undoLast;
                window.clearAll = clearAll;

                // Buttons
                document.getElementById('btnCard1').addEventListener('click', () => setActiveCard('card1'));
                document.getElementById('btnCard2').addEventListener('click', () => setActiveCard('card2'));

                // Onload: wait for images, then init
                let loaded = 0;
                const imageKeys = Object.keys(imgMap); // Get keys to iterate safely
                imageKeys.forEach(key => {
                    const img = imgMap[key];
                    const canvas = canvases[key];

                    img.onload = () => {
                        // [FIX] Dynamically set canvas height based on the loaded image's aspect ratio
                        // This prevents the image from being stretched or squeezed.
                        const aspectRatio = img.naturalHeight / img.naturalWidth;
                        canvas.height = canvas.width * aspectRatio;

                        if (++loaded === imageKeys.length) {
                            // Once all images are loaded and canvases are resized, load existing marks.
                            loadExisting();
                        }
                    };
                    img.onerror = () => {
                        console.error("Could not load image: " + img.src);
                        if (++loaded === imageKeys.length) { // Still try to init if an image fails
                            loadExisting();
                        }
                    }
                });
            })();
            </script>
            <br>
        </div>
    </div>
@endif



@if (isset($visit))
    <div class="d-grid">

        <div class="row">
            <div>
                <a class="btn btn-primary" data-tabtoggle="1" href="#visitLab" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitLab">
                    <font size="4">Lab</font>
                </a>

            </div>
            {{-- ปุ่มบันทึกข้างหัวหมวดย้ายไปไว้ท้ายหมวด (2569-10-07) — ดูสคริปต์ section-save ใน visit/edit --}}
        </div>

    </div>
    <div class="collapse " id="visitLab">
        <div class="card card-body">
            {{-- TB: "ส่งตรวจทางห้องปฏิบัติการ" ของใบคัดกรองอยู่การ์ดเดียวกับ Lab (คลินิกบอกว่าคืออันเดียวกัน 2569-10-01) --}}
            @if((int) ($visit->clinic ?? 1) === 2)
                {{-- ปุ่มเดียวสั่งได้ทุกอย่าง: Lab ในคลินิก / ส่งนอก / ส่งตรวจวัณโรค (แคตตาล็อกแล็บรวม 2569-10-02)
                     ตารางยังไม่ขึ้น = ไม่มีปุ่ม ช่อง/แผงเดิมด้านล่างทำงานเหมือนเดิม
                     popup อยู่ในฟอร์มหลักตอนเรนเดอร์ แต่ไม่มี <form>/name= และสคริปต์ย้ายไปต่อกับ body ตอนโหลด --}}
                {{-- เหลือแค่เมนูสั่งแล็บ 3 ทาง (คลินิกขอ 2569-10-14) — กดแล้วเปิด popup เฉพาะหมวดนั้นให้เลือกรายการ
                     รายละเอียดทั้งหมด (สิ่งส่งตรวจ/ผล AFB · ตาราง Lab · ไฟล์ผล) พับไว้ใน <details> ด้านล่าง --}}
                @if(\App\Models\LabCatalogItem::availableFor(2))
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2 p-2 rounded" style="background:#eff6ff; border:1px solid #bfdbfe;" data-no-dirty>
                        <span class="fw-bold"><i class="fa fa-flask me-1"></i> สั่งแล็บ:</span>
                        @include('partials.lab_catalog_button', ['visit' => $visit, 'context' => 'opd', 'scope' => 'tb', 'label' => 'วัณโรค', 'btnClass' => 'btn-warning fw-bold'])
                        @include('partials.lab_catalog_button', ['visit' => $visit, 'context' => 'opd', 'scope' => 'in', 'label' => 'แล็บใน', 'btnClass' => 'btn-primary'])
                        @include('partials.lab_catalog_button', ['visit' => $visit, 'context' => 'opd', 'scope' => 'out', 'label' => 'แล็บนอก', 'btnClass' => 'btn-outline-primary'])
                        {{-- ตรวจหัวใจ (EKG) แยกจากแล็บ — สั่งและลงผล QTcF ในหน้าต่างเดียวกัน (คลินิกขอ 2569-10-22) --}}
                        @include('partials.lab_catalog_button', ['visit' => $visit, 'context' => 'opd', 'scope' => 'cardio', 'label' => 'ตรวจหัวใจ (EKG)', 'btnClass' => 'btn-outline-danger'])
                        <span class="small text-muted">กดแล้วเลือกรายการส่งตรวจ (ติ๊กได้หลายรายการ)</span>
                    </div>
                    @include('partials.lab_catalog_picker')
                    @include('partials.lab_catalog_picker_js')
                @endif
                @php
                    $__labSubN = count($visit->labSub());
                    $__labExtN = \App\Models\VisitExternalLab::where('visit_id', $visit->id)->count();
                @endphp
                <details class="mt-1" id="tb-lab-details">
                    <summary class="small fw-bold text-primary" style="cursor:pointer;">
                        รายละเอียด / ลงผลแล็บ · ไฟล์ผล
                        <span class="fw-normal text-muted">(แล็บใน {{ $__labSubN }} · ส่งนอก {{ $__labExtN }} รายการ — กดเพื่อเปิด)</span>
                    </summary>
                    <div class="mt-2">
                        @yield('tbp_lab')
                        @include('visit.form.lab', ['visit' => $visit, 'page' => 2])
                        @include('visit.form._lab_files', ['visit' => $visit])
                    </div>
                </details>
            @else
                @include('visit.form.lab', ['visit' => $visit, 'page' => 2])
                {{-- ไฟล์ผลตรวจจากที่อื่น — ทุกคลินิก (2569-10-07) --}}
                @include('visit.form._lab_files', ['visit' => $visit])
            @endif
        </div>
    </div>
    <br>
@endif

{{-- โซนซักประวัติ/คัดกรองวัณโรค แบ่งเป็นส่วน ๆ แล้ว — include อยู่ด้านบน (ต่อจากเหตุผลที่มา) ส่วนต่าง ๆ วางด้วย @yield --}}

{{-- โซนฉีดวัคซีน TMC — ย้ายไปไว้ล่างก่อนวันนัด คู่กับค่าบริการ (พยาบาลขอ 2569-09-30) --}}

{{-- วินิจฉัยโรค STI — ซ่อนสำหรับ TMC --}}
{{-- คลินิก TMC ก็ลงวินิจฉัยได้แล้ว (เดิมซ่อนไว้เฉพาะ clinic 3)
     เพราะต้องใช้วินิจฉัย Pre-test / Post-test ไปนับในทะเบียน RIHIS-VCT ด้วย --}}
@if (isset($visit))
    <div class="d-grid">


        <div class="row">
            <div>
                <a class="btn btn-light" data-tabtoggle="1" href="#visitDiagnosis" role="button"
                    style="width: 100%" aria-expanded="false" aria-controls="visitDiagnosis">
                    <font size="4">วินิจฉัย**@if((int) ($visit->clinic ?? 1) === 2) · Impression · รหัสโรค ICD-10 @endif</font>
                </a>
            </div>
            <!-- <div class="col-1">
                <div class="col-auto form-group">
                    <button type="submit" class="btn btn-primary btn-save-th"><i class="fa fa-save me-1"></i> บันทึก</button>
                </div>
            </div> -->
        </div>


    </div>
    <div class="collapse  pb-2" id="visitDiagnosis">
        <div class="card card-body">
            {{-- TB: Impression + ICD-10 อยู่การ์ดเดียวกับวินิจฉัย (คลินิกขอ 2569-10-08 — เดิมเป็นหัวข้อแยกต่อจากวินิจฉัย) --}}
            @if((int) ($visit->clinic ?? 1) === 2)
                @yield('tbp_impression')
            @endif
            @include('visit.form.diagnosis', ['visit' => $visit])
            {{-- TB: สั่งยาต่อท้ายวินิจฉัยเลย ไม่ต้องเปิดแถบ "การรักษา" อีกแถบ (คลินิกขอ 2569-10-14) --}}
            @if((int) ($visit->clinic ?? 1) === 2)
                <hr class="my-3">
                @include('visit.form.medicine', ['visit' => $visit])
            @endif
        </div>
    </div>
    <br>
@endif


<div class="d-grid">


    <div class="row">
        <div>
            <a class="btn btn-warning" data-tabtoggle="1" href="#visitTreatment" role="button"
                aria-expanded="false" style="width: 100%" aria-controls="visitTreatment">
                <font size="4">@if((int) ($visit->clinic ?? 1) === 3)ส่วนของแพทย์ · ประเมินความเสี่ยง / วางแผน / คำแนะนำ@else การรักษา + ให้คำแนะนำ@endif</font>
            </a>
        </div>
{{-- ปุ่มบันทึกข้างหัวหมวดย้ายไปไว้ท้ายหมวด (2569-10-07) — ดูสคริปต์ section-save ใน visit/edit --}}
    </div>

</div>
<div class="collapse " id="visitTreatment">
    <div class="card card-body">


        {{-- TB ย้ายสั่งยาไปต่อท้ายการ์ดวินิจฉัยแล้ว (2569-10-14)
             TMC ย้ายไปใต้ Risk/Plan — อยู่บนสุดของส่วนแพทย์ พยาบาลมองข้าม (2569-10-28) --}}
        @if(! in_array((int) ($visit->clinic ?? 1), [2, 3], true))
            @include('visit.form.medicine', ['visit' => $visit])
            <br>
        @endif

        {{-- ราคา Lab: หมอ/พยาบาลตั้งไว้ให้การเงินเห็นพร้อม (เลือด=auto · HBsAg/Anti-HCV=เบิกสปสช · Gram/C&S=คลินิกคิด/แพ็ก) --}}
        @isset($labs)
        @if($labs->count())
        <div class="card mb-3 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2" style="background:#0d6efd; color:#fff;">
                <span><i class="fa fa-vial"></i> ค่าตรวจ Lab <span class="small opacity-75">(เลือด=คิดอัตโนมัติ · Gram/C&S=คลินิกคิดเอง/รวมแพ็ก)</span></span>
                <span class="small">รวม Lab: <b><span id="vlab-subtotal">0.00</span></b> ฿</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr><th>รายการตรวจ</th><th class="text-end" style="width:210px;">ราคา (บาท)</th></tr>
                    </thead>
                    <tbody>
                    @foreach($labs as $lab)
                    <tr>
                        <td>
                            <strong>{{ $lab->name }}</strong>
                            @if(!empty($lab->free))
                                <span class="badge bg-success ms-1" title="RPR ฟรี: วินิจฉัย Sy + สั่ง TPHA คู่กัน"><i class="fas fa-gift"></i> ฟรี</span>
                            @elseif($lab->category === 'manual')
                                <span class="badge bg-warning text-dark ms-1" title="ไม่คิดอัตโนมัติ — คลินิกคิดเอง หรือรวมในแพ็กเกจ"><i class="fas fa-user-nurse"></i> คลินิกคิดเอง/แพ็ก</span>
                            @elseif($lab->category === 'blood')
                                {{-- เดิมใช้ bg-info-subtle (Bootstrap 5.3) ซึ่ง 5.1 ไม่มี -> ป้ายโปร่งใสมองไม่เห็น --}}
                                <span class="badge ms-1" style="background:#cff4fc; color:#055160;">เลือด</span>
                            @endif
                            @if(!empty($lab->waived))
                                <span class="badge bg-secondary ms-1" title="การเงินติ๊กยกเว้นรายการนี้">อนุเคราะห์</span>
                            @endif
                        </td>
                        <td>
                            <input type="number" id="vlab-{{ $lab->id }}"
                                   class="form-control form-control-sm text-end vlab-price"
                                   name="labs[{{ $lab->id }}][price]" value="{{ $lab->price ?? 0 }}" step="0.01" min="0"
                                   {{ (!empty($lab->free) || !empty($lab->sso_claimed)) ? 'readonly' : '' }}>
                            @if($lab->category === 'sso')
                                <div class="form-check mt-1">
                                    <input type="checkbox" class="form-check-input vsso-claim" id="vsso-{{ $lab->id }}"
                                           data-target="vlab-{{ $lab->id }}" data-master="{{ $lab->master_price ?? 0 }}"
                                           {{ !empty($lab->sso_claimed) ? 'checked' : '' }}>
                                    <label class="form-check-label small text-success" for="vsso-{{ $lab->id }}">
                                        <i class="fas fa-hand-holding-medical"></i> เบิก สปสช (ไม่คิดเงิน)
                                    </label>
                                </div>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        (function () {
            function vlabTotal() {
                var t = 0;
                document.querySelectorAll('.vlab-price').forEach(function (i) { t += parseFloat(i.value) || 0; });
                var el = document.getElementById('vlab-subtotal'); if (el) el.textContent = t.toFixed(2);
            }
            document.addEventListener('input', function (e) {
                if (e.target && e.target.classList.contains('vlab-price')) vlabTotal();
            });
            document.addEventListener('change', function (e) {
                if (e.target && e.target.classList.contains('vsso-claim')) {
                    var input = document.getElementById(e.target.dataset.target);
                    if (input) {
                        if (e.target.checked) { input.value = 0; input.readOnly = true; }
                        else { input.value = e.target.dataset.master; input.readOnly = false; }
                    }
                    vlabTotal();
                }
            });
            if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', vlabTotal);
            else vlabTotal();
        })();
        </script>
        @endif
        @endisset

        {{-- ค่าบริการ/หัตถการ — TMC ย้ายไปไว้ต่อจากโซนฉีดวัคซีน ก่อนวันนัด (ดูท้ายส่วนนี้) --}}
        @if((int) ($visit->clinic ?? 1) !== 3)
            @include('visit.form._service_charges')
        @endif

        {{-- TB: Plan ของใบคัดกรอง ต่อจากค่าบริการ ก่อนการให้คำแนะนำ (คลินิกขอ 2569-10-01) --}}
        @if((int) ($visit->clinic ?? 1) === 2)
            @yield('tbp_plan')
        @endif


        {{-- ส่วนของแพทย์ TMC: ประเมินความเสี่ยง (Risk assessment) + การวางแผน (Plan) — ช่องใหญ่ --}}
        @if((int) ($visit->clinic ?? 1) === 3)
        <div class="row g-3 mb-2" id="tmcPhysician" style="font-size:1.08rem;">
            <div class="col-md-6">
                <label for="tmc_risk_assessment" class="control-label fw-bold" style="font-size:1.15rem;">
                    <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> ประเมินความเสี่ยง (Risk assessment)
                </label>
                <textarea class="form-control" rows="8" name="tmc_risk_assessment" id="tmc_risk_assessment"
                          style="font-size:1.08rem;" placeholder="ความเสี่ยงตามพื้นที่/กิจกรรม/โรคประจำตัว ฯลฯ">{{ $visit->tmc_risk_assessment ?? '' }}</textarea>
            </div>
            <div class="col-md-6">
                <label for="tmc_plan" class="control-label fw-bold" style="font-size:1.15rem;">
                    <i class="fa-solid fa-list-check text-primary me-1"></i> การวางแผน (Plan)
                </label>
                <textarea class="form-control" rows="8" name="tmc_plan" id="tmc_plan"
                          style="font-size:1.08rem;" placeholder="แผนวัคซีน / ยา / คำแนะนำ / นัดติดตาม">{{ $visit->tmc_plan ?? '' }}</textarea>
            </div>
        </div>

        {{-- สั่งยาต่อจาก Plan (หมอเขียนแผนแล้วสั่งต่อเลย) — เดิมอยู่บนสุดของส่วนแพทย์ พยาบาลมองข้าม 2569-10-28 --}}
        {{-- ย้ายแล้วพยาบาลยังหาไม่เจอ → ขยายกล่องให้เด่นกว่ากล่องอื่น (เฉพาะ TMC · 2569-10-28) --}}
        <style>
            #tmc-med-order > .row > div > .card { border: 3px solid #0f766e; border-radius: 10px; box-shadow: 0 4px 14px rgba(15,118,110,.25); overflow: hidden; }
            #tmc-med-order .card-header { font-size: 1.6rem !important; padding: .9rem 1.2rem; }
            #tmc-med-order .card-header .badge { font-size: 1.05rem !important; padding: .45em .8em; }
            #tmc-med-order .card-body { background: #f0fdfa; padding: 1.1rem 1.2rem; }
            #tmc-med-order .card-body > .btn,
            #tmc-med-order .card-body > a.btn { font-size: 1.05rem; padding: .4rem .9rem; }
            #tmc-med-order #visit-med-table table { font-size: 1.12rem; background: #fff; }
            #tmc-med-order #visit-med-table th { font-size: 1.05rem; }
        </style>
        <div class="mb-4 mt-3" id="tmc-med-order">
            @include('visit.form.medicine', ['visit' => $visit])
        </div>

        {{-- แผนนัดฉีดวัคซีน: หมอวางล่วงหน้าทั้งคอร์ส (ไม่เกิน 5 นัด) แล้วใบนัดฉีดวัคซีนดึงไปพิมพ์
             เก็บเป็น JSON ใน visits.tmc_vaccine_plan — ชื่อ field เป็น array เพื่อรวมเป็นแถวใน controller --}}
        @php
            $__planVaccines = \App\Models\Medicine::where('is_vaccine', 1)->orderBy('name')->get();
            $__planRows  = \App\Models\VisitVaccine::normalizePlan($visit->tmc_vaccine_plan);
            $__planMax   = \App\Models\VisitVaccine::PLAN_MAX_ROWS;
            $__planSlots = \App\Models\VisitVaccine::PLAN_ITEMS_PER_VISIT;
        @endphp
        <div class="card mb-3" id="tmcVaccinePlan" style="border:2px solid #6d28d9;">
            <div class="card-header d-flex justify-content-between align-items-center" style="background:#6d28d9; color:#fff;">
                <span style="font-size:1.1rem;"><i class="fa-solid fa-calendar-check me-1"></i>
                    แผนนัดฉีดวัคซีน (แพทย์วางล่วงหน้า)</span>
                {{-- ตั้งแต่ 2569-10-01 แพทย์วางแผนเอง ระบบไม่เติมนัดเข็มถัดไปให้แล้ว — ข้อความเดิมที่บอกว่า "ระบบนัดให้" ต้องเอาออก ไม่งั้นหมอรอของที่ไม่มา --}}
                <span class="small opacity-75">สูงสุด {{ $__planMax }} นัด · ใบนัดฉีดวัคซีนจะพิมพ์ตามแผนนี้ ·
                    ดูระยะห่างแต่ละเข็มได้ที่
                    <a href="{{ route('vaccine-schedule.index') }}" target="_blank" class="text-white text-decoration-underline">ตารางนัดวัคซีน</a></span>
            </div>
            @php
                // วันตั้งต้นของปุ่มลัด (+1 สัปดาห์ ฯลฯ) = วันที่มาตรวจครั้งนี้
                // visits.date เป็น พ.ศ. แต่ข้อมูลยุคแรกปน ค.ศ. — ปี < 2500 ถือเป็น ค.ศ. อยู่แล้ว
                // ส่งให้ JS เป็น ค.ศ. Y-m-d เพราะ Date ของ JS และ CLINIC_HOLIDAYS คิดเป็น ค.ศ.
                // ใช้ค่าดิบจากฐาน เพราะ edit() แปลง $visit->date เป็น วว/ดด/ปปปป ไว้ให้ฟอร์มแล้ว (เผื่อทั้งสองแบบ)
                $__planBase = ''; $__y = null;
                $__vd = (string) ($visit->getRawOriginal('date') ?: $visit->date);
                if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $__vd, $__m)) {
                    [$__y, $__mo, $__d] = [(int) $__m[1], $__m[2], $__m[3]];
                } elseif (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $__vd, $__m)) {
                    [$__y, $__mo, $__d] = [(int) $__m[3], str_pad($__m[2], 2, '0', STR_PAD_LEFT), str_pad($__m[1], 2, '0', STR_PAD_LEFT)];
                }
                if ($__y) {
                    $__planBase = sprintf('%04d-%s-%s', $__y >= 2500 ? $__y - 543 : $__y, $__mo, $__d);
                }
                $__planQuick = [['w', 1, '1 สัปดาห์'], ['w', 2, '2 สัปดาห์'], ['m', 1, '1 เดือน'],
                                ['m', 3, '3 เดือน'], ['m', 6, '6 เดือน'], ['y', 1, '1 ปี']];
            @endphp
            <div class="card-body" data-plan-base="{{ $__planBase }}">
                {{-- แผนตอนเปิดหน้า — เซิร์ฟเวอร์ใช้แยกว่ารายการไหนระบบเพิ่ม/เอาออกระหว่างที่หน้านี้เปิดค้าง (สั่ง/ลบวัคซีนจากหน้าสั่งยา) --}}
                <input type="hidden" name="tmc_vaccine_plan_orig" value="{{ json_encode($__planRows, JSON_UNESCAPED_UNICODE) }}">
                @for($__i = 0; $__i < $__planMax; $__i++)
                    @php
                        $__row      = $__planRows[$__i] ?? [];
                        $__rowDate  = $__row['date'] ?? '';
                        $__rowItems = $__row['items'] ?? [];
                        // แถวว่างที่เกินจากที่กรอกไว้ ซ่อนไว้ก่อน กดปุ่มเพิ่มค่อยโผล่ (ไม่ให้หน้ารก)
                        $__hidden = ($__i > 0 && $__rowDate === '' && empty($__rowItems));
                    @endphp
                    <div class="border rounded p-2 mb-2 tmc-plan-row {{ $__hidden ? 'd-none' : '' }}" data-idx="{{ $__i }}">
                        <div class="row g-2 align-items-end">
                            <div class="col-auto fw-bold text-muted pb-2" style="min-width:66px;">นัดที่ {{ $__i + 1 }}</div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-1">วันที่นัด</label>
                                <input type="text" class="form-control tmc-plan-date" autocomplete="off"
                                       name="tmc_vaccine_plan[{{ $__i }}][date]" value="{{ $__rowDate }}"
                                       placeholder="วว/ดด/ปปปป">
                            </div>
                            {{-- ปุ่มลัดวันนัด: นับจากวันที่มาตรวจ ตกวันหยุด/เสาร์-อาทิตย์เลื่อนไปวันทำการถัดไปเอง
                                 type=button สำคัญ — อยู่ในฟอร์มหลัก ถ้าเป็น submit จะกลายเป็นกด Save --}}
                            <div class="col-auto pb-1">
                                <div class="small text-muted mb-1">นัดอีก (นับจากวันที่มาตรวจ)</div>
                                <div class="btn-group btn-group-sm flex-wrap" role="group">
                                    @foreach($__planQuick as $__q)
                                        <button type="button" class="btn btn-outline-secondary tmc-plan-quick"
                                                data-unit="{{ $__q[0] }}" data-n="{{ $__q[1] }}">{{ $__q[2] }}</button>
                                    @endforeach
                                </div>
                            </div>
                            {{-- ฉีดที่นี่ / ฉีดที่อื่น (คลินิกขอ 2569-10-09: หมอนัดให้ไปฉีดที่อื่นได้) — ค่าว่าง = ที่นี่ ปุ่มล้างนัดจึงคืนเป็น "ที่นี่" เอง --}}
                            @php $__siteV = $__row['site'] ?? ''; $__site = $__siteV === 'other'; @endphp
                            <div class="col-auto pb-1">
                                <label class="form-label small text-muted mb-1">ฉีดที่</label>
                                <div class="d-flex gap-1">
                                    <select class="form-select form-select-sm tmc-plan-site" name="tmc_vaccine_plan[{{ $__i }}][site]" style="width:auto;">
                                        <option value="" {{ in_array($__siteV, ['other', 'self'], true) ? '' : 'selected' }}>ที่นี่ (คลินิก)</option>
                                        <option value="other" {{ $__site ? 'selected' : '' }}>ที่อื่น</option>
                                        {{-- วัคซีนชนิดกิน (เช่น ไทฟอยด์/อหิวาต์แบบกิน) รับไปทานเองที่บ้าน — TMC ขอ 2569-10-27 --}}
                                        <option value="self" {{ $__siteV === 'self' ? 'selected' : '' }}>รับไปทานเอง</option>
                                    </select>
                                    <input type="text" class="form-control form-control-sm tmc-plan-place {{ $__site ? '' : 'd-none' }}" maxlength="150"
                                           name="tmc_vaccine_plan[{{ $__i }}][place]" value="{{ $__row['place'] ?? '' }}" placeholder="ที่ไหน เช่น รพ.ใกล้บ้าน" style="width:12rem;">
                                </div>
                            </div>
                            <div class="col-auto ms-auto pb-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary tmc-plan-clear" title="ล้างนัดนี้">
                                    <i class="fa fa-eraser"></i> ล้างนัดนี้
                                </button>
                            </div>
                        </div>

                        {{-- วัคซีนในนัดนี้: เลือกชนิด + เข็มที่/dose ต่อตัว (ช่องที่ 2-3 ซ่อนไว้ กดเพิ่มค่อยโผล่) --}}
                        @for($__s = 0; $__s < $__planSlots; $__s++)
                            @php
                                $__it      = $__rowItems[$__s] ?? [];
                                $__itName  = $__it['name'] ?? '';
                                $__itDose  = $__it['dose'] ?? '';
                                $__slotHid = ($__s > 0 && $__itName === '');
                            @endphp
                            <div class="row g-2 mt-1 tmc-plan-slot {{ $__slotHid ? 'd-none' : '' }}">
                                <div class="col-auto" style="min-width:66px;"></div>
                                <div class="col-md-4">
                                    @if($__s === 0)<label class="form-label small text-muted mb-1">วัคซีน</label>@endif
                                    <select class="form-select" name="tmc_vaccine_plan[{{ $__i }}][items][{{ $__s }}][name]" data-searchable data-search-placeholder="🔍 พิมพ์ค้นหาวัคซีน เช่น yel">
                                        <option value="">— เลือกวัคซีน —</option>
                                        @foreach($__planVaccines as $__pv)
                                            <option value="{{ $__pv->name }}" {{ $__itName === $__pv->name ? 'selected' : '' }}>{{ $__pv->name }}</option>
                                        @endforeach
                                        {{-- วัคซีนที่เคยบันทึกไว้แต่ถูกถอดออกจากแคตตาล็อกแล้ว ต้องไม่หายไปจากแผน --}}
                                        @if($__itName !== '' && !$__planVaccines->contains('name', $__itName))
                                            <option value="{{ $__itName }}" selected>{{ $__itName }}</option>
                                        @endif
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    @if($__s === 0)<label class="form-label small text-muted mb-1">Dose</label>@endif
                                    <select class="form-select" name="tmc_vaccine_plan[{{ $__i }}][items][{{ $__s }}][dose]">
                                        <option value="">—</option>
                                        @foreach(\App\Models\VisitVaccine::$planDoseOption as $__d)
                                            <option value="{{ $__d }}" {{ $__itDose === $__d ? 'selected' : '' }}>{{ $__d }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @if($__s === 0)
                                <div class="col-md-3 align-self-end">
                                    <button type="button" class="btn btn-outline-primary btn-sm tmc-plan-add-slot">
                                        <i class="fa fa-plus"></i> เพิ่มวัคซีนในนัดนี้
                                    </button>
                                </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                @endfor
                <button type="button" class="btn btn-outline-primary btn-sm" id="tmc-plan-add">
                    <i class="fa fa-plus"></i> เพิ่มนัด
                </button>
                <div class="form-text">
                    <span id="tmc-plan-status" class="fw-bold"></span>
                    บันทึกเองทันทีที่เลือก/แก้ (ไม่ต้องรอปุ่ม Save) · เว้นว่างได้ถ้ายังไม่นัด · วันหยุด/เสาร์-อาทิตย์เลือกไม่ได้
                </div>
            </div>
        </div>
        <script>
        // บันทึกแผนนัดทันทีที่แก้ (คลินิกแจ้ง 2569-10-05) — เดิมต้องกด Save ของหน้าตรวจ ถ้ากด "เพิ่มการฉีด" (โหลดหน้าใหม่) ก่อน
        // แผนที่กรอกไว้หายเงียบ ๆ · ส่งทั้งแผน + แผนตอนเปิดหน้า (orig) ให้ฝั่งเซิร์ฟเวอร์รวมกับที่อีกเครื่องแก้ ไม่เขียนทับ
        // ปุ่ม Save เดิมยังส่งแผนไปด้วยเหมือนเดิม (ค่าเดียวกัน) จึงไม่ชนกัน
        (function () {
            var box = document.getElementById('tmcVaccinePlan');
            var origEl = document.querySelector('input[name="tmc_vaccine_plan_orig"]');
            var status = document.getElementById('tmc-plan-status');
            if (!box || !origEl) return;
            var URL = @json(route('visit.vaccinePlan', $visit->id)), timer = null, busy = false, again = false;

            function collect() {
                var fd = new FormData();
                box.querySelectorAll('[name^="tmc_vaccine_plan["]').forEach(function (el) {
                    fd.append(el.name.replace(/^tmc_vaccine_plan/, 'plan'), el.value);
                });
                fd.append('orig', origEl.value);
                return fd;
            }
            function save() {
                if (busy) { again = true; return; }
                busy = true;
                status.className = 'fw-bold text-secondary'; status.textContent = 'กำลังบันทึกแผน… ';
                fetch(URL, { method: 'POST', credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' }, body: collect() })
                    .then(function (r) { if (!r.ok) throw r; return r.json(); })
                    .then(function (d) {
                        origEl.value = JSON.stringify(d.plan || []);
                        status.className = 'fw-bold ' + (d.warn ? 'text-danger' : 'text-success');
                        status.textContent = (d.warn ? '⚠ ' + d.warn + ' · ' : '✓ ') + 'บันทึกแผนแล้ว ' + d.time + ' น.'
                            + (d.appt ? ' · ลงในตารางวันนัดแล้ว (เห็นในตารางเมื่อโหลดหน้าใหม่)' : '') + ' · ';
                    })
                    .catch(function () { status.className = 'fw-bold text-danger'; status.textContent = '✗ บันทึกแผนไม่สำเร็จ — กด Save ของหน้าตรวจแทน · '; })
                    .finally(function () { busy = false; if (again) { again = false; save(); } });
            }
            function later() { clearTimeout(timer); timer = setTimeout(save, 700); }
            // change = เลือก dropdown / ปฏิทินปิด / พิมพ์วันที่เสร็จ · click = ปุ่มลัดวันนัด/ล้างนัด/เพิ่มนัด (ตั้งค่าด้วย JS ไม่มี change)
            box.addEventListener('change', later);
            box.addEventListener('click', function (e) { if (e.target.closest('button')) later(); });
        })();
        </script>
        {{-- ช่องพิมพ์ค้นหาวัคซีนในแผนนัด (2569-10-07) — เลือกจากช่องค้นหาแล้ว select ยิง change ให้ autosave ด้านบนเหมือนเลือกเอง --}}
        @include('partials.searchable_select')
        <script>window.initSearchableSelects && window.initSearchableSelects(document.getElementById('tmcVaccinePlan'));</script>

        {{-- ตัวล็อกวันหยุดต้องมาก่อนสคริปต์ด้านล่าง (@once — ที่เรียกซ้ำท้ายหน้าจะไม่ทำงานซ้ำ) --}}
        @include('partials.holiday_lock')
        <script>
        (function () {
            if (window.__tmcPlanInit) return;
            window.__tmcPlanInit = true;

            // ปฏิทิน พ.ศ. — ใช้คลาสเฉพาะ ไม่ใช้ .date เพราะ .date มี global init อยู่แล้ว (เคยทำให้ปฏิทินซ้อนกัน)
            // ล็อกเสาร์-อาทิตย์/วันหยุดราชการในปฏิทินแผนนัด เหมือนช่องวันนัดหลัก (2569-09-30)
            // ผ่าน bindThDatepicker — สคริปต์นี้รันก่อนไฟล์ปฏิทินท้ายหน้าโหลด ผูกตรง ๆ จะได้ปฏิทินผิดตัว (mm/dd ค.ศ.)
            var __o = {
                format: 'dd/mm/yyyy', language: 'th-th', thaiyear: true, autoclose: true, todayHighlight: true
            };
            window.bindThDatepicker('.tmc-plan-date', window.holidayLockOptions ? holidayLockOptions(__o) : __o);

            var rows = Array.prototype.slice.call(document.querySelectorAll('.tmc-plan-row'));

            var addBtn = document.getElementById('tmc-plan-add');
            if (addBtn) {
                addBtn.addEventListener('click', function () {
                    var next = rows.filter(function (r) { return r.classList.contains('d-none'); })[0];
                    if (!next) { alert('นัดได้สูงสุด {{ $__planMax }} ครั้ง'); return; }
                    next.classList.remove('d-none');
                    if (!rows.filter(function (r) { return r.classList.contains('d-none'); }).length) {
                        addBtn.classList.add('d-none');
                    }
                });
                if (!rows.filter(function (r) { return r.classList.contains('d-none'); }).length) {
                    addBtn.classList.add('d-none');
                }
            }

            // เพิ่มช่องวัคซีนในนัดนั้น (ช่องมีอยู่แล้วแต่ซ่อนไว้ — index ต้องคงที่ ห้ามสร้าง/ลบจริง)
            document.querySelectorAll('.tmc-plan-add-slot').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.tmc-plan-row');
                    if (!row) return;
                    var hidden = Array.prototype.slice.call(row.querySelectorAll('.tmc-plan-slot.d-none'));
                    if (!hidden.length) { alert('นัดหนึ่งครั้ง เพิ่มวัคซีนได้สูงสุด {{ $__planSlots }} ชนิด'); return; }
                    hidden[0].classList.remove('d-none');
                    if (hidden.length === 1) { btn.classList.add('d-none'); }
                });
                // เปิดหน้ามาโดยที่ช่องเต็มอยู่แล้ว -> ซ่อนปุ่มเพิ่ม
                var row0 = btn.closest('.tmc-plan-row');
                if (row0 && !row0.querySelectorAll('.tmc-plan-slot.d-none').length) { btn.classList.add('d-none'); }
            });

            // ล้างนัด: เคลียร์วันที่ + วัคซีน/dose ทั้งแถว แล้วยุบช่องที่ 2-3 กลับ (ไม่ลบแถว เพราะ index ต้องคงที่)
            // ฉีดที่อื่น → โชว์ช่องชื่อสถานที่ · กลับเป็น "ที่นี่" → ล้างชื่อสถานที่
            document.addEventListener('change', function (e) {
                if (!e.target.classList || !e.target.classList.contains('tmc-plan-site')) return;
                var place = e.target.closest('.tmc-plan-row').querySelector('.tmc-plan-place');
                var other = e.target.value === 'other';
                place.classList.toggle('d-none', !other);
                if (other) { place.focus(); } else { place.value = ''; }
            });
            document.querySelectorAll('.tmc-plan-clear').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.tmc-plan-row');
                    if (!row) return;
                    row.querySelectorAll('input[type="text"]').forEach(function (i) { i.value = ''; });
                    row.querySelectorAll('select').forEach(function (s) { s.value = ''; });
                    row.querySelectorAll('.tmc-plan-place').forEach(function (p) { p.classList.add('d-none'); });
                    row.querySelectorAll('.tmc-plan-slot').forEach(function (sl, idx) {
                        if (idx > 0) { sl.classList.add('d-none'); }
                    });
                    var addSlot = row.querySelector('.tmc-plan-add-slot');
                    if (addSlot) { addSlot.classList.remove('d-none'); }
                });
            });

            // ปุ่มลัดวันนัด (+1 สัปดาห์ … +1 ปี) — หมอวางแผนเองตั้งแต่ 2569-10-01 ปุ่มนี้ช่วยไม่ต้องจิ้มปฏิทินทีละเดือน
            // คิดเป็น ค.ศ. ทั้งหมด (ตรงกับ CLINIC_HOLIDAYS) แล้วค่อยแปลงเป็น วว/ดด/พ.ศ. ตอนใส่ช่อง
            function planBaseDate() {
                var card = document.querySelector('#tmcVaccinePlan [data-plan-base]');
                var s = card ? card.getAttribute('data-plan-base') : '';
                var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
                if (m) { return new Date(+m[1], +m[2] - 1, +m[3]); }
                var t = new Date();
                return new Date(t.getFullYear(), t.getMonth(), t.getDate());   // ไม่มีวันที่มาตรวจ -> วันนี้
            }
            function addInterval(d, unit, n) {
                if (unit === 'w') { return new Date(d.getFullYear(), d.getMonth(), d.getDate() + 7 * n); }
                var months = unit === 'y' ? 12 * n : n;
                // 31 ม.ค. + 1 เดือน ต้องได้สิ้น ก.พ. ไม่ใช่ล้นไปต้น มี.ค. (setMonth ของ JS ล้นเดือนเอง)
                var y = d.getFullYear(), mo = d.getMonth() + months;
                var last = new Date(y, mo + 1, 0).getDate();
                return new Date(y, mo, Math.min(d.getDate(), last));
            }
            function pad2(n) { return String(n).padStart(2, '0'); }
            function isClosed(d) {
                var dow = d.getDay();
                if (dow === 0 || dow === 6) return true;
                var key = d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
                return !!(window.CLINIC_HOLIDAYS && window.CLINIC_HOLIDAYS[key]);
            }
            document.querySelectorAll('.tmc-plan-quick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var row = btn.closest('.tmc-plan-row');
                    var input = row ? row.querySelector('.tmc-plan-date') : null;
                    if (!input) return;
                    var d = addInterval(planBaseDate(), btn.dataset.unit, parseInt(btn.dataset.n, 10) || 1);
                    // ตกเสาร์-อาทิตย์/วันหยุดราชการ -> เลื่อนไปวันทำการถัดไป (กันวนไม่จบถ้าข้อมูลวันหยุดเพี้ยน)
                    for (var guard = 0; guard < 60 && isClosed(d); guard++) {
                        d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1);
                    }
                    var val = pad2(d.getDate()) + '/' + pad2(d.getMonth() + 1) + '/' + (d.getFullYear() + 543);
                    input.value = val;
                    if (window.jQuery && jQuery.fn.datepicker) { try { jQuery(input).datepicker('update', val); } catch (e) {} }
                    // datepicker('update') บางรุ่นเขียนค่ากลับเป็นรูปแบบของมันเอง — ยืนยันค่า พ.ศ. ซ้ำอีกที
                    input.value = val;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
        })();
        </script>
        @endif

        {{-- การให้คำแนะนำ — TMC ย้ายไปไว้ก่อนค่าบริการ (ท้ายส่วนนี้) คลินิกอื่นอยู่ที่เดิม --}}
        @if((int) ($visit->clinic ?? 1) !== 3)
            @include('visit.form._treatment_field')
        @endif

        {{-- สุขศึกษา Safe sex / Condom — เรื่องของ STI, TMC และ TB ไม่ใช้ (TMC 2569-09-30 · TB 2569-10-01)
             ไม่มีช่องในฟอร์ม = ไม่ถูกส่งไป ค่าเดิมในฐานไม่ถูกแตะ --}}
        @if((int) ($visit->clinic ?? 1) === 1)
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
        @endif

        {{-- การให้คำปรึกษา/ตรวจ HIV-Syphilis + แจกถุงยาง/สารหล่อลื่น เป็นของ STI — ซ่อนสำหรับ TMC และ TB --}}
        @if((int) ($visit->clinic ?? 1) === 1)
        <div class="row">
            {{-- ช่องเดิม "การให้คำปรึกษาฯ" (dropdown รายเดี่ยว/รายกลุ่ม) ซ่อนไว้ ไม่ได้ลบ:
                 ข้อมูลเก่า 11,000+ แถวยังอยู่ และ Save ต้องส่งค่าเดิมกลับไป ไม่งั้นจะถูกล้างเป็นค่าว่าง --}}
            <input type="hidden" name="hiv_sti_test" value="{{ $visit->hiv_sti_test ?? '' }}">

            <div class="col-6 form-group {{ $errors->has('syphilis_history') ? 'has-error' : '' }}">
                <label for="syphilis_history" class="control-label">{{ 'ประวัติการตรวจ Syphilis' }}</label>
                <input class="form-control" name="syphilis_history" type="text" id="syphilis_history"
                    value="{{ $visit->syphilis_history ?? '' }}"
                    placeholder="เช่น 24/7/69 RPR 1:8 / ไม่เคยตรวจ">
                {!! $errors->first('syphilis_history', '<p class="help-block">:message</p>') !!}
                {{-- คำแนะนำ: ผล TP / RPR ครั้งล่าสุดก่อนวันที่ของ visit นี้ (STI ขอ 2569-10-09) — รูปแบบเดียวกับที่พิมพ์กันอยู่ "24/7/69 RPR 1:8"
                     $__prev คำนวณครั้งเดียวในช่อง HIV ด้านล่าง → ที่นี่คำนวณเองถ้ายังไม่มี (ช่องนี้อยู่ก่อน) --}}
                @php
                    $__prev = $__prev ?? $visit->previousLabResults([6, 7, 8, 11, 12]);
                    $__syParts = [];
                    if (isset($__prev[7])) { $__syParts[] = $__prev[7]['date'] . ' TP ' . $__prev[7]['result']; }
                    if (isset($__prev[8])) { $__syParts[] = $__prev[8]['date'] . ' RPR ' . $__prev[8]['result']; }
                    $__syText = implode(', ', $__syParts);
                @endphp
                @if($__syText !== '')
                    <div class="small mt-1 p-1 rounded d-flex flex-wrap align-items-center gap-2" style="background:#eef6ff; border:1px solid #c7dcf5;">
                        <span><i class="fa fa-lightbulb text-primary"></i> ตรวจที่คลินิกครั้งก่อน: <b>{{ $__syText }}</b></span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0"
                                onclick="var d=document.getElementById('syphilis_history'); d.value={{ json_encode($__syText) }}; d.dispatchEvent(new Event('input',{bubbles:true}));">
                            ใช้ข้อมูลนี้
                        </button>
                    </div>
                @endif
            </div>
            <div class="col-6 form-group {{ $errors->has('last_hiv_syphilis') ? 'has-error' : '' }}">
                <label for="last_hiv_syphilis" class="control-label">{{ 'ประวัติการ On PrEP/on ARV' }}</label>
                <input class="form-control" name="last_hiv_syphilis" type="text" id="last_hiv_syphilis"
                    value="{{ isset($visit->last_hiv_syphilis) ? $visit->last_hiv_syphilis : '' }}"
                    placeholder="เช่น on PrEP แคร์แมท / on ARV รพ.นครพิงค์">
                {!! $errors->first('last_hiv_syphilis', '<p class="help-block">:message</p>') !!}
            </div>
            <div class="col-6 form-group {{ $errors->has('hiv_sti_test_date') ? 'has-error' : '' }}">
                <label for="hiv_sti_test_date" class="control-label">{{ 'ประวัติการตรวจ HIV' }}</label>
                <input class="form-control" name="hiv_sti_test_date" type="text" id="hiv_sti_test_date"
                    value="{{ isset($visit->hiv_sti_test_date) ? $visit->hiv_sti_test_date : '' }}">
                {!! $errors->first('hiv_sti_test_date', '<p class="help-block">:message</p>') !!}
                {{-- คำแนะนำ: ผล Anti-HIV ครั้งล่าสุดที่ตรวจที่คลินิกนี้ "ก่อนวันที่ของ visit นี้" (STI ขอ 2569-10-09)
                     แสดงเป็นข้อเสนอ ไม่เขียนทับเอง — กดปุ่มแล้วค่อยเติมวันที่ + ผลให้ (ยังแก้ได้) · ดู Visit::previousLabResults --}}
                @php
                    $__prev = $__prev ?? $visit->previousLabResults([6, 7, 8, 11, 12]);   // 6 HIV · 7 TP · 8 RPR · 11 HBsAg · 12 Anti-HCV
                    $__prevHiv = $__prev[6] ?? null;
                    $__prevHivKey = $__prevHiv ? array_search($__prevHiv['result'], \App\Models\Visit::$hivtestresultOption, true) : false;
                @endphp
                @if($__prevHiv)
                    <div class="small mt-1 p-1 rounded d-flex flex-wrap align-items-center gap-2" style="background:#eef6ff; border:1px solid #c7dcf5;">
                        <span><i class="fa fa-lightbulb text-primary"></i> ตรวจที่คลินิกครั้งก่อน: <b>{{ $__prevHiv['date'] }}</b> · Anti-HIV <b>{{ $__prevHiv['result'] }}</b></span>
                        <button type="button" class="btn btn-outline-primary btn-sm py-0"
                                onclick="var d=document.getElementById('hiv_sti_test_date'), r=document.getElementById('hiv_sti_test_resule');
                                         d.value={{ json_encode($__prevHiv['date']) }}; d.dispatchEvent(new Event('input',{bubbles:true}));
                                         if (r && {{ json_encode($__prevHivKey) }} !== false) { r.value={{ json_encode((string) $__prevHivKey) }}; r.dispatchEvent(new Event('change',{bubbles:true})); }">
                            ใช้ข้อมูลนี้
                        </button>
                    </div>
                @endif
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
{{-- ไวรัสตับอักเสบ B / C ครั้งก่อน — หน้าตรวจไม่มีช่องเก็บ จึงแสดงให้ดูอย่างเดียว (STI ขอ 2569-10-09) --}}
            @php
                $__hep = [];
                if (isset($__prev[11])) { $__hep[] = 'HBsAg <b>' . e($__prev[11]['result']) . '</b> (' . e($__prev[11]['date']) . ')'; }
                if (isset($__prev[12])) { $__hep[] = 'Anti-HCV <b>' . e($__prev[12]['result']) . '</b> (' . e($__prev[12]['date']) . ')'; }
            @endphp
            @if($__hep)
                <div class="col-12">
                    <div class="small p-1 rounded" style="background:#eef6ff; border:1px solid #c7dcf5;">
                        <i class="fa fa-lightbulb text-primary"></i> ไวรัสตับอักเสบ ตรวจที่คลินิกครั้งก่อน: {!! implode(' · ', $__hep) !!}
                    </div>
                </div>
            @endif
            
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
        @endif {{-- ปิดบล็อก HIV/ถุงยาง (ซ่อนสำหรับ TMC) --}}
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
            <td data-touch-tracing="{{ $item->touchTracing }}">
                {{ App\Models\Visit::$touchTracingOption[$item->touchTracing] }}
            </td>
            <td>
                <button class="btn btn-danger btn-sm" type="button">
                    <i class="fa fa-trash" aria-hidden="true"></i>
                </button>
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
{{-- สคริปต์ของใบคัดกรองวัณโรค — หาช่องด้วย id ตอนรัน จึงต้องอยู่หลังทุกส่วนที่ yield ไว้ด้านบน --}}
@if(isset($visit) && (int) $visit->clinic === 2)
    @yield('tbp_scripts')
@endif

{{-- TMC ย้ายการติดตามผู้ป่วยไปล่างสุด (ท้ายไฟล์) · TB ย้ายไปไว้ในบัตร TB 01 (คลินิกขอ 2569-10-01) — STI ยังอยู่ตรงนี้ --}}
@if((int) ($visit->clinic ?? 1) === 1)
    @include('visit.form.followup_section')
@endif

{{-- TMC: ฉีดวัคซีน + ค่าบริการ อยู่ด้วยกัน ก่อนวันนัด (พยาบาลขอ 2569-09-30)
     เดิมวัคซีนอยู่หลัง Lab และค่าบริการซ่อนอยู่ในส่วนการรักษา ต้องเลื่อนขึ้นลงหลายรอบ --}}
@if(isset($visit) && (int) $visit->clinic === 3)
    @include('visit.form.tmc_vaccine')
    {{-- สังเกตอาการหลังฉีด — อยู่เหนือการให้คำแนะนำ (คลินิกขอ 2569-11-06) เนื้อหาอยู่ใน visit/form/tmc_vaccine --}}
    <div class="mt-3">@yield('tmc_obs')</div>
    {{-- การให้คำแนะนำ วางก่อนค่าบริการ (คลินิกขอ 2569-10-01) — ชื่อช่องเดิม เซฟพร้อม Save ของหน้าตรวจเหมือนเดิม --}}
    <div class="mt-3">
        @include('visit.form._treatment_field')
    </div>
    {{-- คำนวณราคาวัคซีน — ใต้คำแนะนำ (TMC ขอ 2569-10-07) เนื้อหาอยู่ใน visit/form/tmc_vaccine --}}
    @yield('tmc_calc')
    <div class="mt-3">
        @include('visit.form._service_charges')
    </div>
@endif
<br>
<div class="d-grid">


    <div class="row">
        <div>
            <a class="btn btn-light" data-tabtoggle="1" href="#visitOther" role="button"
                aria-expanded="false" style="width: 100%" aria-controls="visitOther">
                <font size="4">วันนัด</font>
            </a>
        </div>
       <!--  <div class="col-1">
            <div class="col-auto form-group">
                <button type="submit" class="btn btn-primary btn-save-th"><i class="fa fa-save me-1"></i> บันทึก</button>
            </div>
        </div> -->
    </div>
</div>
<div class="collapse " id="visitOther">
    <div class="card card-body">

    {{-- ส่งเวรแบบไม่ต้องมีนัด + ประวัติส่งเวร (2569-10-27) — บล็อกเทา แยกจากบล็อกวันนัดสีฟ้าด้านล่าง --}}
    @if(! empty($visit->id))
        @include('visit._handover', ['hoMode' => 'block'])
    @endif



    <div class="row">
            {{-- นัดหลายวัน (พยาบาลขอ 2569-08-26) — มาตรวจครั้งเดียว ตั้งได้หลายนัด แต่ละนัดมีเหตุผลของตัวเอง
                 ช่อง id="appointment" ตัวเดิมยังอยู่แต่ซ่อนไว้ เพราะสคริปต์เตือน "วันนัดผ่านมาแล้ว" อ้างถึงมัน
                 ค่าที่บันทึกจริงมาจากตาราง appt_* ด้านล่าง แล้ว syncMirror() ตั้ง visits.appointment ให้เอง --}}
            @php
                $__appts = \App\Models\VisitAppointment::open()->where('visit_id', $visit->id ?? 0)
                            ->orderBy('appointment_date')->orderBy('id')->get();
                $__closed = \App\Models\VisitAppointment::whereNotNull('closed_at')
                            ->where('visit_id', $visit->id ?? 0)
                            ->orderBy('appointment_date')->get();
                $__closedBy = \App\Models\User::whereIn('id', $__closed->pluck('closed_by')->filter()->unique())
                            ->pluck('name', 'id');
                // นัดที่ยังค้างจากการมาครั้งก่อน — ของเดิมไม่เคยแสดงตรงนี้
                // พยาบาลจึงไม่รู้ว่าคนไข้ยังมีนัดเก่าค้างอยู่ ต้องย้อนไปเปิด visit เก่าดูเอง
                $__pending = ($visit->id ?? null)
                    ? \App\Models\VisitAppointment::pendingFromOtherVisits($visit->patient_id, $visit->id)
                    : collect();
            @endphp
            <input type="hidden" id="appointment" value="{{ $visit->appointment ?? '' }}">

            <div class="col-12 form-group appt-blk">
                {{-- บล็อกวันนัด สีฟ้า — แยกสัดส่วนจากบล็อกส่งเวร (คลินิกขอ 2569-10-27 "ทำเป็นบล็อกสี สังเกตง่าย") --}}
                <style>
                    .appt-blk { border:2px solid #0284c7; border-radius:.6rem; background:#f0f9ff; padding:0 .8rem .8rem; overflow:hidden; }
                    .appt-blk > .appt-blk-head { background:#0284c7; color:#fff; font-weight:700; margin:0 -.8rem .6rem; padding:.45rem .8rem; }
                    .appt-blk #appt-table { background:#fff; }
                </style>
                <label class="control-label d-flex align-items-center gap-2 appt-blk-head">
                    <i class="fa fa-calendar-check"></i> วันนัด
                    <span class="small fw-normal">ใส่ได้หลายวัน แต่ละวันใส่เหตุผลแยกกันได้ · ช่องเหลือง = คนไข้เห็น · ช่องเทา = ส่งเวรกำกับนัดนั้น</span>
                </label>
                <table class="table table-sm table-bordered mb-1" id="appt-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:150px">วันนัด</th>
                            <th>เหตุผลนัด <span class="badge bg-warning text-dark fw-normal">👁️ คนไข้เห็น</span></th>
                            <th>บันทึกภายใน / ส่งเวร <span class="badge bg-secondary fw-normal">🔒 เจ้าหน้าที่เท่านั้น</span></th>
                            <th style="width:46px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- นัดที่ปิดไปแล้วของครั้งนี้ — แสดงเป็นแถวอ่านอย่างเดียว ห้ามซ่อน
                             เป็นหลักฐานว่าครั้งนั้นนัดวันไหน ด้วยเหตุผลอะไร ใครปิดเมื่อไหร่
                             (เดิมย่อเหลือบรรทัดเล็ก ๆ ใต้ตาราง พยาบาลอ่านแล้วนึกว่าข้อมูลหาย — พี่กานต์แจ้ง 2569-09-16)
                             ไม่มี input name="appt_*" ในแถวนี้ ค่าจึงไม่ถูกส่งไปเขียนทับตอนกด Save --}}
                        @foreach ($__closed as $__ca)
                            @php
                                $__cClosedDate = $__ca->closed_at
                                    ? \App\Utillity::AD2BE(substr((string) $__ca->closed_at, 0, 10)) . ' ' . substr((string) $__ca->closed_at, 11, 5) . ' น.'
                                    : null;
                            @endphp
                            <tr class="appt-row-closed" style="background:#f6f7f8;color:#6c757d;">
                                <td class="align-middle">
                                    <div style="text-decoration:line-through;">{{ \App\Utillity::db2thDate($__ca->appointment_date) }}</div>
                                    <span class="badge bg-secondary fw-normal">ปิดนัดแล้ว</span>
                                </td>
                                <td class="align-middle">{{ $__ca->reason ?: '—' }}</td>
                                <td class="align-middle">
                                    {{ $__ca->staff_note ?: '—' }}
                                    <div class="small mt-1">
                                        {{ \App\Models\VisitAppointment::closedReasonText($__ca->closed_reason) }}
                                        @if($__cClosedDate) · ปิดเมื่อ {{ $__cClosedDate }}@endif
                                        @if($__closedBy[$__ca->closed_by] ?? null) · โดย {{ $__closedBy[$__ca->closed_by] }}@endif
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-link p-0 js-appt-reopen"
                                            data-id="{{ $__ca->id }}" title="เปิดนัดนี้กลับมา">เปิดกลับ</button>
                                </td>
                            </tr>
                        @endforeach
                        @php
                            // คลินิกวัณโรคกรอกเวลา/แพทย์/ก่อนพบแพทย์ในแถวนัดด้วย เพื่อพิมพ์ใบนัดวัณโรค (2569-09-29)
                            $__isTbAppt = (int) ($visit->clinic ?? 0) === 2;
                            $__tbDocs = $__isTbAppt
                                ? \App\Models\User::doctors()
                                : collect();
                        @endphp
                        @foreach ($__appts as $__a)
                            <tr class="appt-row">
                                <td><input type="hidden" name="appt_id[]" value="{{ $__a->id }}">
                                    <input type="text" name="appt_date[]" class="form-control form-control-sm appt-date"
                                           value="{{ \App\Utillity::db2thDate($__a->appointment_date) }}" autocomplete="off"></td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="appt_reason[]" class="form-control js-ar-box"
                                               style="background:#fff8e1;border-left:5px solid #f0ad4e;"
                                               value="{{ $__a->reason }}">
                                        <button type="button" class="btn btn-outline-secondary js-ar-pick"
                                                data-bs-toggle="modal" data-bs-target="#appointment_reason_model"
                                                title="เลือกข้อความสำเร็จรูป">📋</button>
                                    </div>
                                    @if($__isTbAppt) @include('visit.form._tb_appt_extra', ['a' => $__a, 'docs' => $__tbDocs]) @endif
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" name="appt_note[]" class="form-control js-ar-box" maxlength="255"
                                               style="background:#eceff1;border-left:5px solid #78909c;"
                                               value="{{ $__a->staff_note }}">
                                        <button type="button" class="btn btn-outline-secondary js-ar-pick"
                                                data-bs-toggle="modal" data-bs-target="#appointment_reason_model"
                                                title="เลือกข้อความสำเร็จรูป">📋</button>
                                    </div>
                                </td>
                                <td class="text-center align-middle">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-appt-del" title="ลบนัดนี้">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="appt-row">
                            <td><input type="hidden" name="appt_id[]" value="">
                                <input type="text" name="appt_date[]" class="form-control form-control-sm appt-date" autocomplete="off"></td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="appt_reason[]" class="form-control js-ar-box"
                                           style="background:#fff8e1;border-left:5px solid #f0ad4e;">
                                    <button type="button" class="btn btn-outline-secondary js-ar-pick"
                                                data-bs-toggle="modal" data-bs-target="#appointment_reason_model"
                                                title="เลือกข้อความสำเร็จรูป">📋</button>
                                </div>
                                @if($__isTbAppt) @include('visit.form._tb_appt_extra', ['a' => null, 'docs' => $__tbDocs]) @endif
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="text" name="appt_note[]" class="form-control js-ar-box" maxlength="255"
                                           style="background:#eceff1;border-left:5px solid #78909c;"
                                           placeholder="ส่งเวร — ไม่มีนัดก็ได้ ขึ้นตอนคนไข้มาครั้งถัดไป">
                                    <button type="button" class="btn btn-outline-secondary js-ar-pick"
                                                data-bs-toggle="modal" data-bs-target="#appointment_reason_model"
                                                title="เลือกข้อความสำเร็จรูป">📋</button>
                                </div>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn btn-sm btn-outline-danger js-appt-del" title="ลบนัดนี้">&times;</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <button type="button" class="btn btn-sm btn-outline-primary" id="appt-add">
                    <i class="fa fa-plus me-1"></i> เพิ่มวันนัด
                </button>
                <div class="text-muted small mt-1">
                    ลบแถวออก = ยกเลิกนัดนั้น · เว้นว่างไว้ = ไม่ได้นัด ·
                    กด 📋 เพื่อเลือกข้อความสำเร็จรูป ·
                    ช่องเหลืองขึ้นบนใบนัดและส่งเข้า LINE ให้คนไข้ ช่องเทาไม่ส่งออกไปไหน ·
                    <strong class="text-danger">เหตุผลนัด (ช่องเหลือง) ต้องมีวันนัดในแถวเดียวกัน</strong> ·
                    <strong style="color:#455a64;">ช่องเทา (ส่งเวร) ไม่มีวันนัดก็ได้ = ส่งเวรครั้งถัดไป</strong>
                </div>

                {{-- เตือนตั้งแต่ยังไม่กดบันทึก จะได้ไม่ต้องพิมพ์ใหม่
                     (เดิมพิมพ์โน้ตไว้โดยไม่ใส่วันนัด กดเซฟแล้วข้อความหายเงียบ ๆ — พี่กานต์แจ้ง 2569-09-11) --}}
                <div class="alert py-2 px-3 mt-2 mb-0 d-none" id="appt-ho-info" style="background:#eceff1;border:1px solid #b0bec5;color:#37474f;">
                    <i class="fa fa-bullhorn me-1"></i> ช่องเทาที่ไม่มีวันนัด จะบันทึกเป็น <b>ส่งเวรครั้งถัดไป</b> เมื่อกดบันทึก — ขึ้นแถบส้มบนหน้าตรวจตอนคนไข้มาครั้งหน้า
                </div>
                <div class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none" id="appt-note-warn">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    <span id="appt-note-warn-text"></span>
                </div>
                <script>
                (function () {
                    if (window.__apptNoteWarnInit) return;
                    window.__apptNoteWarnInit = true;

                    function check() {
                        var bad = 0, ho = 0;
                        document.querySelectorAll('#appt-table tr.appt-row').forEach(function (tr) {
                            var d = tr.querySelector('input[name="appt_date[]"]');
                            var r = tr.querySelector('input[name="appt_reason[]"]');
                            var n = tr.querySelector('input[name="appt_note[]"]');
                            var noDate = !d || d.value.trim() === '';
                            if (r && r.value.trim() && noDate) { bad++; }
                            if (n && n.value.trim() && noDate) { ho++; }
                        });

                        var box = document.getElementById('appt-note-warn');
                        if (!box) return;
                        if (bad) {
                            document.getElementById('appt-note-warn-text').textContent =
                                'มี ' + bad + ' แถวที่พิมพ์เหตุผลนัด (ช่องเหลือง) ไว้แต่ยังไม่ได้ใส่วันนัด — ถ้ากดบันทึกตอนนี้ เหตุผลนัดนั้นจะไม่ถูกเก็บ';
                            box.classList.remove('d-none');
                        } else {
                            box.classList.add('d-none');
                        }
                        var hb = document.getElementById('appt-ho-info');
                        if (hb) hb.classList.toggle('d-none', !ho);
                    }

                    document.addEventListener('input', function (e) {
                        if (e.target.closest && e.target.closest('#appt-table')) { check(); }
                    });
                    document.addEventListener('change', function (e) {
                        if (e.target.closest && e.target.closest('#appt-table')) { check(); }
                    });
                    check();
                })();
                </script>

                @if ($__pending->count())
                    {{-- คนไข้มาก่อนวันนัดบ่อย (209 ครั้งใน 12 เดือน · 71% ไม่ได้มาในวันนัดอีกเลย)
                         ระบบแยกไม่ออกว่ามาแทนนัดเดิมหรือมาด้วยเรื่องอื่น จึงถามพยาบาลตรงนี้ทีเดียว --}}
                    <div class="alert alert-warning mt-3 mb-0">
                        <div class="fw-bold mb-2">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            คนไข้มีนัดค้างจากการมาครั้งก่อน {{ $__pending->count() }} รายการ
                        </div>
                        @foreach ($__pending as $__pa)
                            <div class="mb-3 js-pending-row" data-id="{{ $__pa->id }}">
                                <div class="d-flex align-items-center flex-wrap gap-2">
                                    <b>{{ \App\Utillity::db2thDate($__pa->appointment_date) }}</b>
                                    <span>{{ trim((string) $__pa->reason, ' ,') ?: 'ไม่ระบุเหตุผล' }}</span>
                                    <button type="button" class="btn btn-sm btn-success js-appt-close"
                                            data-id="{{ $__pa->id }}" data-reason="done_early">
                                        ทำวันนี้แล้ว ปิดนัด
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-appt-keep">
                                        ยังต้องมาตามนัด
                                    </button>
                                </div>

                                {{-- โน้ตส่งเวรของนัดเดิม — ห้ามใช้ <form> ตรงนี้ ทั้งบล็อกอยู่ในฟอร์มหลักของหน้าตรวจ
                                     และต้องบันทึกแยกจากตารางวันนัด ไม่งั้นจะกลายเป็นนัดใหม่ซ้อนขึ้นมาอีกอัน --}}
                                <div class="input-group input-group-sm mt-2" style="max-width:640px;">
                                    <span class="input-group-text" title="เห็นเฉพาะเจ้าหน้าที่ ไม่ขึ้นใบนัด ไม่ส่ง LINE">🔒 ส่งเวร</span>
                                    <input type="text" class="form-control js-pending-note" maxlength="255"
                                           data-id="{{ $__pa->id }}"
                                           value="{{ $__pa->staff_note }}"
                                           placeholder="เช่น นัดเดิม ไม่ต้องนัดซ้ำ / รอผล culture">
                                    <button type="button" class="btn btn-outline-primary js-pending-note-save"
                                            data-id="{{ $__pa->id }}">บันทึกโน้ต</button>
                                </div>
                            </div>
                        @endforeach
                        <div class="small text-muted">
                            ปิดนัดแล้วจะไม่ส่ง LINE เตือน และไม่ขึ้นในรายชื่อนัดอีก · กดผิดเปิดกลับได้ ·
                            <b>ถ้าจะโน้ตกำกับนัดเดิม ให้พิมพ์ในช่อง 🔒 ส่งเวร ด้านบนแล้วกดบันทึกโน้ต</b>
                            (ไม่ต้องไปเพิ่มแถวในตารางวันนัด จะได้ไม่เกิดนัดซ้อน)
                        </div>
                    </div>
                @endif


            </div>
            {{-- เตือนวันนัดที่เป็นอดีต — ต้นเหตุจริงคือพิมพ์ปี พ.ศ. ผิด (2569 แทน 2570)
                 ผลคือ LINE ไม่ยิงยืนยันนัด เพราะระบบข้ามนัดที่เลยวันไปแล้ว --}}
            <div class="col-12">
                <div class="alert alert-warning py-2 px-3 mb-2 d-none" id="appt-past-warn">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    <b>วันนัดนี้ผ่านมาแล้ว <span id="appt-past-ago"></span></b>
                    — ตรวจสอบปี พ.ศ. อีกครั้ง (ระบบจะไม่ส่ง LINE แจ้งนัดที่เลยวันไปแล้ว)
                    <button type="button" class="btn btn-sm btn-outline-dark ms-2 d-none" id="appt-fix-year">
                        แก้เป็น <span id="appt-fix-year-label"></span>
                    </button>
                </div>
            </div>
            {{-- ช่อง Appointment Reason / บันทึกภายใน เดิมอยู่ตรงนี้
                 ย้ายเข้าไปเป็นคอลัมน์ในตารางวันนัดด้านบนแล้ว (2569-08-26)
                 เพราะนัดได้หลายวัน แต่ละวันมีเหตุผลของตัวเอง ถ้าเหลือช่องเดียวไว้
                 พยาบาลจะพิมพ์ลงช่องเก่าแล้วโดนเขียนทับด้วยค่าจากตาราง --}}
        </div>

        <script>
        (function () {
            if (window.__apptPastInit) return;
            window.__apptPastInit = true;

            var inp   = document.getElementById('appointment');
            var box   = document.getElementById('appt-past-warn');
            var ago   = document.getElementById('appt-past-ago');
            var fix   = document.getElementById('appt-fix-year');
            var fixLb = document.getElementById('appt-fix-year-label');
            if (!inp || !box) return;

            // ช่องนี้เป็น d/m/Y พ.ศ. (formatDBBack แปลงมาแล้ว) — ห้ามโยนเข้า Date() ตรงๆ
            function parseBe(raw) {
                var p = String(raw || '').trim().split('/');
                if (p.length !== 3) return null;
                var d = +p[0], m = +p[1], y = +p[2];
                if (!d || !m || !y) return null;
                if (y > 2400) y -= 543;
                var dt = new Date(y, m - 1, d);
                return isNaN(dt) ? null : dt;
            }

            function agoText(dt, today) {
                var y = today.getFullYear() - dt.getFullYear();
                var m = today.getMonth()    - dt.getMonth();
                var d = today.getDate()     - dt.getDate();
                if (d < 0) { m--; d += new Date(today.getFullYear(), today.getMonth(), 0).getDate(); }
                if (m < 0) { m += 12; y--; }
                var out = [];
                if (y) out.push(y + ' ปี');
                if (m) out.push(m + ' เดือน');
                if (d || !out.length) out.push(d + ' วัน');
                return out.join(' ');
            }

            function check() {
                var dt = parseBe(inp.value);
                var today = new Date(); today.setHours(0, 0, 0, 0);

                if (!dt || dt >= today) {
                    box.classList.add('d-none');
                    inp.classList.remove('border-warning');
                    return false;
                }

                ago.textContent = agoText(dt, today);
                box.classList.remove('d-none');
                inp.classList.add('border-warning');

                // เคสที่เจอบ่อยที่สุด: ปีน้อยไป 1 (พิมพ์ 9 แทน 0) — เสนอปุ่มแก้ให้กดทีเดียว
                var p = inp.value.trim().split('/');
                var beYear = +p[2];
                if (beYear > 2400 && new Date(beYear - 543 + 1, +p[1] - 1, +p[0]) >= today) {
                    fixLb.textContent = (beYear + 1);
                    fix.classList.remove('d-none');
                } else {
                    fix.classList.add('d-none');
                }
                return true;
            }

            if (fix) {
                fix.addEventListener('click', function () {
                    var p = inp.value.trim().split('/');
                    if (p.length === 3) { inp.value = p[0] + '/' + p[1] + '/' + (+p[2] + 1); }
                    check();
                });
            }

            ['change', 'input', 'blur'].forEach(function (ev) { inp.addEventListener(ev, check); });
            check();   // เผื่อของเดิมใน visit ก็ผิดอยู่แล้ว

            // ด่านสุดท้าย: datepicker บางตัวยัดค่าแบบไม่ยิง event — เช็คอีกทีตอน Save
            function bindSubmit() {
                var form = document.getElementById('visit_edit_form');
                if (!form) return;
                form.addEventListener('submit', function (e) {
                    if (!check()) return;
                    if (!confirm('⚠ วันนัด ' + inp.value + ' ผ่านมาแล้ว ' + ago.textContent
                               + '\n\nตรวจสอบปี พ.ศ. อีกครั้ง — ระบบจะไม่ส่ง LINE แจ้งนัดที่เลยวันไปแล้ว'
                               + '\n\nยืนยันบันทึกตามนี้หรือไม่?')) {
                        e.preventDefault();
                        e.stopPropagation();
                        inp.focus();
                    }
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bindSubmit);
            } else {
                bindSubmit();
            }
        })();
        </script>

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
                        {{-- ไม่ต้องเลือกช่องปลายทางในนี้แล้ว — ตั้งแต่นัดได้หลายวัน (2569-08-26)
                             ปุ่ม 📋 ที่อยู่ข้างช่องนั้น ๆ เป็นตัวบอกเองว่าจะลงแถวไหน ช่องไหน --}}
                        <div class="mb-2 text-muted small">คลิกข้อความเพื่อเติมลงช่องที่กด 📋 ไว้</div>

                        @foreach (App\Models\Visit::$reasonAppointmentOption as $item)
                            <p class="appointment_reason_item"
                               style="display: list-item; margin-left: 1em;"
                               onmouseover="this.style.color='red'"
                               onmouseout="this.style.color='black'">
                                {{ $item }}
                            </p>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.querySelectorAll('.appointment_reason_item').forEach(item => {
    item.style.cursor = 'pointer';  // ใช้ pointer ในการเปลี่ยน cursor ให้เป็นนิ้ว
});
            // ช่องที่กด 📋 ไว้ล่าสุด — ข้อความสำเร็จรูปจะไปลงช่องนี้
            // ผูกแบบ delegate เพราะแถวนัดถูกสร้างเพิ่มทีหลังได้
            window.__arBox = null;
            // เปิดโมดัลด้วย data-bs-toggle บนตัวปุ่มเอง ไม่เรียก bootstrap.Modal จาก JS
            // เพราะ Bootstrap 5 ถูกรวมอยู่ใน app.js โดยไม่ได้ปล่อยตัวแปร window.bootstrap ออกมา
            // เรียกตรง ๆ แล้วเงื่อนไขไม่ผ่าน ปุ่มจึงเงียบไปเฉย ๆ (เจอตอนพยาบาลลองใช้จริง)
            // JS ตรงนี้เหลือหน้าที่เดียวคือจำว่ากดปุ่มของช่องไหน
            $(document).on('click', '.js-ar-pick', function () {
                window.__arBox = $(this).closest('.input-group').find('.js-ar-box')[0];
            });

            $('.appointment_reason_item').click(function() {
    // ต่อท้ายช่องที่เลือกไว้ด้านบน ไม่ได้ลง appointment_reason เสมอเหมือนเดิม
    if (!window.__arBox) { alert('กด 📋 ข้างช่องที่ต้องการก่อนค่ะ'); return; }
    var $box = $(window.__arBox);
    var currentValue = $box.val();
    var text = $(this).text().trim();
    $box.val(currentValue ? currentValue + text + ', ' : text + ', ');
    // กะพริบให้เห็นว่าลงช่องไหนไป — สองช่องอยู่ห่างกันจนไม่ทันสังเกต
    $box.css('box-shadow', '0 0 0 3px #4caf50');
    setTimeout(function () { $box.css('box-shadow', ''); }, 600);
});


        </script>

    </div>
</div>
<br>

{{-- TMC: การติดตามผู้ป่วยอยู่ล่างสุด ถัดจากวันนัด (ผู้ใช้สั่ง 2026-07-27) --}}
@if((int) ($visit->clinic ?? 1) === 3)
    @include('visit.form.followup_section')
@endif

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

<!-- ติดตั้ง jQuery และ jQuery UI -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>

@include('partials.holiday_lock')

<script type="text/javascript">
    function get_trush_tracks() {
        let trush_tracks = [];

        $("#touchTrackTable tbody tr").each(function () {
            let name = $(this).find("td:nth-child(1)").text();
            let touchTracing = $(this).find("td:nth-child(2)").data("touch-tracing");

            trush_tracks.push({
                name: name.trim(),
                touchTracing: touchTracing
            });
        });

        return trush_tracks;
    }

    $(function () {
        // ตั้งค่า Datepicker
        // ช่องวันนัดล็อกเสาร์-อาทิตย์และวันหยุดราชการ ส่วนช่องวันที่อื่นๆ (วันตรวจ วันรับยา)
        // ต้องเลือกวันไหนก็ได้ เพราะบางทีต้องบันทึกย้อนหลังหรือกรอกวันที่จากใบส่งตัว
        // ผ่าน bindThDatepicker — รอไฟล์ภาษา th-th ก่อน (ผูกก่อนภาษาไทยมา = ได้ mm/dd ปี ค.ศ.)
        window.bindThDatepicker($('.date').not('#appointment'), { language: 'th-th' });

        // ช่องวันนัดใช้ปฏิทินที่ล็อกวันหยุดราชการ (ของเดิมผูกไว้กับ #appointment ช่องเดียว)
        // ตอนนี้นัดได้หลายวัน จึงผูกทีละแถว และต้องผูกซ้ำทุกครั้งที่เพิ่มแถวใหม่
        window.bindApptDate = function (el) {
            window.bindThDatepicker(el, holidayLockOptions({ language: 'th-th' }));
        };
        $('.appt-date').each(function () { window.bindApptDate(this); });

        (function () {
            var table = document.getElementById('appt-table');
            if (!table) { return; }

            document.getElementById('appt-add').addEventListener('click', function () {
                var rows = table.querySelectorAll('.appt-row');
                var last = rows[rows.length - 1];
                var copy = last.cloneNode(true);
                copy.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
                // เวลา/ก่อนพบแพทย์/แพทย์ ต้องกลับเป็นค่าว่างด้วย — เดิมติดค่าของนัดแถวบนมา แล้วถูกบันทึกโดยไม่ได้ตั้งใจ (รีวิว 2569-09-30)
                // แพทย์ตั้งต้นเป็นแพทย์ของ visit นี้ เหมือนแถวว่างที่หน้าเว็บสร้างให้
                copy.querySelectorAll('select').forEach(function (s) {
                    Array.prototype.forEach.call(s.options, function (o) { o.defaultSelected = false; });
                    s.value = s.dataset.newDefault || '';
                    if (s.selectedIndex < 0) { s.selectedIndex = 0; }
                });
                // ปฏิทินตัวเดิมติดมากับสำเนาด้วย ต้องล้างร่องรอยก่อนผูกใหม่ ไม่งั้นกดแล้วไม่ขึ้น
                copy.querySelectorAll('.appt-date').forEach(function (i) {
                    i.className = i.className.replace(/hasDatepicker/g, '');
                    i.removeAttribute('data-provide');
                });
                last.parentNode.appendChild(copy);
                copy.querySelectorAll('.appt-date').forEach(function (i) { window.bindApptDate(i); });
                var d = copy.querySelector('.appt-date'); if (d) { d.focus(); }
            });

            // นัดที่ระบบเพิ่งลงให้ (กล่องสั่งยาวัณโรค 2569-10-29) → โชว์ในตารางนี้พร้อม id
            // แถวใหม่ที่วันเดียวกัน (ยังไม่บันทึก) = แถวเดียวกัน ใส่ id ให้ · ไม่มี = ใช้แถวว่าง หรือเพิ่มแถว
            // มี id แล้วกด Save หน้า = แก้แถวนั้น ไม่สร้างนัดซ้ำ
            window.__apptInject = function (id, dateTh) {
                var rows = Array.prototype.slice.call(table.querySelectorAll('tr.appt-row'));
                var idOf = function (tr) { return tr.querySelector('input[name="appt_id[]"]'); };
                var dOf  = function (tr) { return tr.querySelector('input[name="appt_date[]"]'); };
                if (rows.some(function (tr) { return idOf(tr).value === String(id); })) { return; }
                var tr = rows.filter(function (r) { return !idOf(r).value && dOf(r).value.trim() === dateTh; })[0]
                      || rows.filter(function (r) { return !idOf(r).value && !dOf(r).value.trim(); })[0];
                if (!tr) {
                    document.getElementById('appt-add').click();
                    var all = table.querySelectorAll('tr.appt-row');
                    tr = all[all.length - 1];
                }
                idOf(tr).value = id;
                dOf(tr).value = dateTh;
                tr.style.background = '#ecfdf5';
            };

            // ปุ่มปิด/เปิดนัด — ยิงทันที ไม่รอกด Save
            // เพราะพยาบาลอาจกดปิดแล้วปิดหน้าไปเลย ถ้ารอ Save จะหายไปเฉย ๆ
            function apptAction(id, body, done) {
                fetch("{{ url('/visit-appointment') }}/" + id + "/close", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': "{{ csrf_token() }}",
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(body)
                }).then(function (r) { return r.json(); })
                  .then(function (d) { if (d && d.ok) { done(d.msg); } else { alert('ทำรายการไม่สำเร็จ'); } })
                  .catch(function () { alert('ติดต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่อีกครั้ง'); });
            }

            document.addEventListener('click', function (e) {
                var close = e.target.closest('.js-appt-close');
                if (close) {
                    var row = close.closest('.js-pending-row');
                    apptAction(close.dataset.id,
                        { reason: close.dataset.reason, visit_id: {{ $visit->id ?? 'null' }} },
                        function () { if (row) { row.remove(); } });
                    return;
                }
                var keep = e.target.closest('.js-appt-keep');
                if (keep) {
                    // ไม่ต้องบันทึกอะไร แค่ซ่อนคำถามไม่ให้ถามซ้ำในหน้านี้
                    // (นัดยังเปิดอยู่ตามเดิม เปิดหน้าใหม่จะถามอีก ซึ่งถูกแล้ว)
                    var r2 = keep.closest('.js-pending-row');
                    if (r2) { r2.remove(); }
                    return;
                }
                var reopen = e.target.closest('.js-appt-reopen');
                if (reopen) {
                    apptAction(reopen.dataset.id, { action: 'reopen' }, function () { location.reload(); });
                    return;
                }

                // บันทึกโน้ตส่งเวรใส่นัดเดิม — ยิงแยกจากฟอร์มหลัก จึงไม่สร้างนัดใหม่
                var noteBtn = e.target.closest('.js-pending-note-save');
                if (noteBtn) {
                    var box = document.querySelector('.js-pending-note[data-id="' + noteBtn.dataset.id + '"]');
                    if (!box) { return; }

                    var old = noteBtn.innerHTML;
                    noteBtn.disabled = true;
                    noteBtn.innerHTML = 'กำลังบันทึก...';

                    fetch("{{ url('/visit-appointment') }}/" + noteBtn.dataset.id + "/note", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': "{{ csrf_token() }}",
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ staff_note: box.value })
                    }).then(function (r) { return r.json(); })
                      .then(function (d) {
                          noteBtn.disabled = false;
                          if (d && d.ok) {
                              noteBtn.innerHTML = '✓ บันทึกแล้ว';
                              box.classList.add('border-success');
                              setTimeout(function () {
                                  noteBtn.innerHTML = old;
                                  box.classList.remove('border-success');
                              }, 2500);
                          } else {
                              noteBtn.innerHTML = old;
                              alert('บันทึกโน้ตไม่สำเร็จ');
                          }
                      })
                      .catch(function () {
                          noteBtn.disabled = false;
                          noteBtn.innerHTML = old;
                          alert('ติดต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่อีกครั้ง');
                      });
                }
            });

            table.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-appt-del');
                if (!btn) { return; }
                var row = btn.closest('.appt-row');
                var rows = table.querySelectorAll('.appt-row');
                // เหลือแถวเดียว = ล้างค่าแทนการลบทิ้ง จะได้ยังมีที่ให้กรอก
                if (rows.length === 1) {
                    row.querySelectorAll('input, textarea').forEach(function (i) { i.value = ''; });
                    row.querySelectorAll('select').forEach(function (s) { s.value = s.dataset.newDefault || ''; if (s.selectedIndex < 0) { s.selectedIndex = 0; } });
                    return;
                }
                row.remove();
            });
        })();

        // กำหนดค่า status ของ visit
        $('#status').val({{ $visit->status }});

        // เปิดทุกแท็บตอนโหลด
        // ยกเว้นกล่องที่ตั้งใจพับไว้ [data-keep-collapsed] (เช่น ตรวจร่างกาย TMC ที่ยังไม่มีข้อมูล) — กดหัวข้อแล้วกางได้ตามปกติ
        document.querySelectorAll('.collapse').forEach(function (el) { if (!el.closest('[data-keep-collapsed]')) el.classList.add('show'); });

        // จัดการย่อ/ขยายแท็บเอง 100% (ตัด collapse JS ของ Bootstrap ออกทั้งหมดด้วยการเปลี่ยน attribute เป็น data-tabtoggle)
        // Bootstrap จึงไม่ยุ่งกับปุ่มเหล่านี้เลย ใช้แค่ CSS (.collapse:not(.show){display:none}) เป็นตัวซ่อน/แสดง
        if (!window.__collapseToggleBound) {
            window.__collapseToggleBound = true;
            document.addEventListener('click', function (e) {
                var t = e.target.closest('[data-tabtoggle]');
                if (!t) return;
                e.preventDefault();
                var sel = t.getAttribute('data-bs-target') || t.getAttribute('href');
                if (!sel || sel === '#') return;
                try {
                    document.querySelectorAll(sel).forEach(function (el) { el.classList.toggle('show'); });
                } catch (err) {}
            });
        }

        // จัดการการส่งฟอร์ม
        $("#visit_edit_form").submit(function () {
            console.log("visit_edit_form");

            let trush_tracks = get_trush_tracks();
            let $form = $(this);

            let $existingInput = $form.find("input[name='trush_tracks']");
            if ($existingInput.length === 0) {
                $("<input />")
                    .attr("type", "hidden")
                    .attr("name", "trush_tracks")
                    .val(JSON.stringify(trush_tracks))
                    .appendTo($form);
            } else {
                $existingInput.val(JSON.stringify(trush_tracks));
            }

            return true;
        });

        // ซ่อน/แสดง input ตามอาชีพ
        $('#occupation').change(function () {
            $('#organization_div').hide();
            $('#other_occupation_div').hide();
            $('#organization').val("");
            $('#other_occupation').val("");

            switch ($(this).val()) {
                case '10':
                    $('#organization_div').show();
                    break;
                case '11':
                    $('#other_occupation_div').show();
                    break;
            }
        });
    });
</script>

@php
// ตรวจสถานะการมาตามนัด (รองรับนัดซ้อน) — ย้าย logic ไปไว้ใน Visit::appointmentAlert() แล้ว
$apptAlert = $visit->appointmentAlert();
$visitStatus = $apptAlert['html'];
@endphp




<script>
document.addEventListener("DOMContentLoaded", function () {
    let visitStatus = @json($visitStatus);

    let drugAllergy = document.getElementById("drug_allergy").value.trim();
    let congenitalDisease = document.getElementById("congenitaldisease").value.trim();

    let hasDrugAllergy = drugAllergy && drugAllergy !== "ปฏิเสธ" && drugAllergy !== "-";
    let hasCongenitalDisease = congenitalDisease && congenitalDisease !== "ปฏิเสธ" && congenitalDisease !== "-";

    let patientInfoMessage = "";
    if (hasDrugAllergy) {
        patientInfoMessage += `<b>แพ้ยา:</b> <span style="color: red; font-size: 18px;">${drugAllergy}</span><br>`;
    }
    if (hasCongenitalDisease) {
        patientInfoMessage += `<b>โรคประจำตัว:</b> <span style="color: red; font-size: 18px;">${congenitalDisease}</span>`;
    }

    if ((visitStatus && visitStatus.trim() !== "") || patientInfoMessage) {
        let fullMessage = "";

        if (visitStatus && visitStatus.trim() !== "") {
            fullMessage += `<div style="margin-bottom: 10px;">${visitStatus}</div>`;
        }
        if (patientInfoMessage) {
            fullMessage += `<div>${patientInfoMessage}</div>`;
        }

        // 🔴 แสดงไอคอน "warning" ถ้ามีแพ้ยา หรือไม่ได้มาตามนัด (เลยวันนัด)
        let showIcon = "info";
        let overdueOrAllergy = visitStatus.includes("ไม่ได้มาตามนัด") || hasDrugAllergy;
        if (overdueOrAllergy) {
            showIcon = "warning";
        }

        Swal.fire({
    toast: true,
    icon: showIcon,
    title: "แจ้งเตือน",
    html: `<div style="max-width: 280px; white-space: normal;">${fullMessage}</div>`,
    showConfirmButton: false,
    // เคสสำคัญ (เลยนัด/แพ้ยา) ให้อยู่นานขึ้น หรือให้ปิดเอง เพื่อไม่พลาด
    timer: overdueOrAllergy ? 15000 : 9000,
    timerProgressBar: true,
    position: "bottom-start", // ซ้ายล่าง
    customClass: {
        popup: 'swal2-small swal-blue',
        title: 'swal-title-small swal-blue-title',
        htmlContainer: 'swal-html-small swal-blue-text'
    }
});

    }

    // ตรวจจับเมื่อเปลี่ยนค่า
    ["congenitaldisease", "drug_allergy"].forEach(id => {
        document.getElementById(id).addEventListener("change", function () {
            let drugAllergy = document.getElementById("drug_allergy").value.trim();
            let congenitalDisease = document.getElementById("congenitaldisease").value.trim();

            let hasDrugAllergy = drugAllergy && drugAllergy !== "ปฏิเสธ" && drugAllergy !== "-";
            let hasCongenitalDisease = congenitalDisease && congenitalDisease !== "ปฏิเสธ" && congenitalDisease !== "-";

            let patientInfoMessage = "";
            if (hasDrugAllergy) {
                patientInfoMessage += `<b>แพ้ยา:</b> <span style="color: red; font-size: 18px;">${drugAllergy}</span><br>`;
            }
            if (hasCongenitalDisease) {
                patientInfoMessage += `<b>โรคประจำตัว:</b> <span style="color: red; font-size: 18px;">${congenitalDisease}</span>`;
            }

            if (patientInfoMessage) {
                Swal.fire({
    toast: true,
    icon: showIcon,
    title: "แจ้งเตือน",
    html: `<div style="max-width: 250px; white-space: normal;">${fullMessage}</div>`,
    showConfirmButton: false,
    timer: 8000,
    timerProgressBar: true,
    position: "bottom-start", // ซ้ายล่าง
    customClass: {
        popup: 'swal2-small swal-blue',
        title: 'swal-title-small swal-blue-title',
        htmlContainer: 'swal-html-small swal-blue-text'
    }
});

            }
        });
    });
});

</script>
<style>
.swal2-small {
    width: 260px !important;
    font-size: 14px !important;
    border-radius: 10px !important;
    padding: 8px !important;
}

.swal-title-small {
    font-size: 16px !important;
    margin-bottom: 5px;
}

.swal-html-small {
    font-size: 14px !important;
}

.swal-blue {
    background-color: #e8f4fd !important;
    color: #084298 !important;
}

.swal-blue-title {
    color: #084298 !important;
}

.swal-blue-text {
    color: #084298 !important;
}
</style>


<style>
    /* กำหนดขนาดข้อความใน title และ html */
    /* เปลี่ยนสีข้อความ title ให้เป็นสีแดง */
.swal-red-title {
    color: red !important;  /* ทำให้ title เป็นสีแดง */
}

/* เปลี่ยนสีข้อความ html ของ SweetAlert ให้เป็นสีแดง */
.swal-red .swal2-popup {
    background-color: #fff3f3 !important;  /* เปลี่ยนสีพื้นหลังเป็นสีอ่อน */
    border: 1px solid red !important; /* เพิ่มเส้นขอบสีแดง */
}

.swal-red-text .swal2-html-container {
    color: red !important; /* เปลี่ยนสีข้อความใน html ให้เป็นสีแดง */
}

.swal-title-large {
    font-size: 24px !important;
}

.swal-html-large {
    font-size: 18px !important;
}

</style>


@include("visit.field_history_modal")
