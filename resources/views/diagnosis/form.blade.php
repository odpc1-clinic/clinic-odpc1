<div class="row">
    <div class="col-6  form-group {{ $errors->has('disease') ? 'has-error' : ''}}">
        <label for="disease" class="control-label">{{ 'Disease' }}</label>
        <!-- <input class="form-control" name="disease" type="number" id="disease" value="{{ isset($diagnosi->disease) ? $diagnosi->disease : ''}}"> -->
        <select class="form-control" name="disease" id="disease">
            @foreach (App\Models\Diagnosi::$diseaseOption as $key => $value)
            <option value="{{ $key }}" @if (isset($diagnosi->disease) && $key == $diagnosi->disease) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('disease', '<p class="help-block">:message</p>') !!}
    </div>

    <div id="other_disease_div" class="col-6 form-group {{ $errors->has('other_disease') ? 'has-error' : ''}}">
        <label for="other_disease" class="control-label">{{ 'Other Disease' }}</label>
        {{-- แก้รายการของ TMC: เลือกจากรายการกลางได้ (diagnosis_presets) จะได้สะกดตรงกับเคสอื่น --}}
        <input class="form-control" name="other_disease" type="text" id="other_disease" list="other-disease-presets" value="{{ isset($diagnosi->other_disease) ? $diagnosi->other_disease : ''}}">
        <datalist id="other-disease-presets">
            @foreach(\App\Models\DiagnosisPreset::namesFor(3) as $__p)<option value="{{ $__p }}">@endforeach
        </datalist>
        {!! $errors->first('other_disease', '<p class="help-block">:message</p>') !!}
    </div>

    {{-- ไม่มีช่อง ICD-10 ในหน้าวินิจฉัยแล้ว (คลินิกขอ 2569-10-01) — ไม่ส่ง icd10 มา = รหัสเดิมไม่ถูกแตะ --}}

    <div id="term_syphilis_div" class="col-6 form-group {{ $errors->has('term_syphilis') ? 'has-error' : '' }}">
        <label for="term_syphilis" class="control-label">{{ 'ซิฟิริสระยะ' }}</label>
        {{-- <input class="form-control" name="term_syphilis" type="number" id="term_syphilis"
            value="{{ isset($diagnosi->term_syphilis) ? $diagnosi->term_syphilis : '' }}"> --}}
        <select class="form-control" name="term_syphilis" id="term_syphilis">
            @foreach (App\Models\Diagnosi::$termSyphilisOption as $key => $value)
            <option value="{{ $key }}" @if (isset($diagnosi->term_syphilis) && $key == $diagnosi->term_syphilis) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('term_syphilis', '<p class="help-block">:message') !!}
    </div>
    <div id="egasp_div" class="col-6 form-group {{ $errors->has('egasp_info') ? 'has-error' : '' }}" style="display: none;">
    <label for="egasp_info" class="control-label">{{ 'Egasp Information' }}</label>
    <input class="form-control" name="egasp_info" type="text" id="egasp_info" value="{{ isset($diagnosi->egasp_info) ? $diagnosi->egasp_info : '' }}">
    {!! $errors->first('egasp_info', '<p class="help-block">:message</p>') !!}
</div>
<!-- ช่องกรอก GF -->
    <div id="gf_div" class="col-6 form-group {{ $errors->has('gf') ? 'has-error' : '' }}" style="display: none;">
        <label for="gf" class="control-label">{{ 'GF Information' }}</label>
        <input class="form-control" name="gf" type="text" id="gf" value="{{ isset($diagnosi->gf) ? $diagnosi->gf : '' }}">
        {!! $errors->first('gf', '<p class="help-block">:message</p>') !!}
    </div>
</div>



<div class="row">
    {{-- สถานะของโรคเป็นของงาน STI — visit TB/TMC ไม่แสดง (ไม่ส่งช่องนี้ = ค่าเดิมไม่ถูกแตะ) --}}
    @unless($hideState ?? false)
    <div class="col-6  form-group {{ $errors->has('disease_state') ? 'has-error' : '' }}">
        <label for="disease_state" class="control-label">{{ 'สถานะของโรค' }}</label>
        {{-- <input class="form-control" name="disease_state" type="number" id="disease_state"
            value="{{ isset($diagnosi->disease_state) ? $diagnosi->disease_state : '' }}"> --}}
        <select class="form-control" name="disease_state" id="disease_state">
            @foreach (App\Models\Diagnosi::$diseaseStateOption as $key => $value)
            <option value="{{ $key }}" @if (isset($diagnosi->disease_state) && $key == $diagnosi->disease_state) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('disease_state', '<p class="help-block">:message
    </div>') !!}
</div>
<div id="disease_state_other_div" class="col-6 form-group {{ $errors->has('disease_state_other') ? 'has-error' : '' }}">
    <label for="disease_state_other" class="control-label">{{ 'สถานะของโรค อื่น ๆ' }}</label>
    <input class="form-control" name="disease_state_other" type="text" id="disease_state_other" value="{{ isset($diagnosi->disease_state_other) ? $diagnosi->disease_state_other : '' }}">
    {!! $errors->first('disease_state_other', '<p class="help-block">:message') !!}
</div>
    @endunless



<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>

<script type="text/javascript">
    // Function เมื่อเปลี่ยนค่าของ #disease
    $("#disease").change(function() {
        const diseaseValue = $("#disease").val(); // อ่านค่าของ disease
        
        // ตรวจสอบเงื่อนไข Disease
        if (diseaseValue == 11) { // Other Disease
            $("#other_disease_div").show();
            $("#term_syphilis_div").hide();
            $("#term_syphilis").val(0); // ล้างค่า Term Syphilis
        } else if (diseaseValue == 1) { // Syphilis
            $("#term_syphilis_div").show();
            $("#other_disease_div").hide();
            $("#other_disease").val(""); // ล้างค่า Other Disease
        } else {
            $("#other_disease_div").hide();
            $("#term_syphilis_div").hide();
            $("#other_disease").val("");
            $("#term_syphilis").val(0);
        }

        // ตรวจสอบและแสดง/ซ่อน Egasp
        if (diseaseValue == 17 || diseaseValue == 18) { // Egasp
            $("#egasp_div").show();
        } else {
            $("#egasp_div").hide();
            $("#egasp_info").val(""); // ล้างข้อมูล Egasp
        }

        // ตรวจสอบและแสดง/ซ่อน GF
        if (diseaseValue == 16) { // GF
            $("#gf_div").show();
        } else {
            $("#gf_div").hide();
            $("#gf").val(""); // ล้างข้อมูล GF
        }
    });

    // Function เมื่อเปลี่ยนค่าของ #disease_state
    $("#disease_state").change(function() {
        const diseaseStateValue = $("#disease_state").val(); // อ่านค่าของ disease_state

        if (diseaseStateValue == 10) { // เงื่อนไข Disease State Other
            $("#disease_state_other_div").show();
        } else {
            $("#disease_state_other_div").hide();
            $("#disease_state_other").val(""); // ล้างข้อมูล Disease State Other
        }
    });

    // เรียกใช้ฟังก์ชันเมื่อหน้าโหลด
    $("#disease").change();
    $("#disease_state").change();
</script>
