<div class="form-group {{ $errors->has('patient_id') ? 'has-error' : ''}}">
    <label for="patient_id" class="control-label">{{ 'Patient Id' }}</label>
    <input class="form-control" name="patient_id" type="number" id="patient_id" value="{{ isset($vistit->patient_id) ? $vistit->patient_id : ''}}" >
    {!! $errors->first('patient_id', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('know_from') ? 'has-error' : ''}}">
    <label for="know_from" class="control-label">{{ 'Know From' }}</label>
    <input class="form-control" name="know_from" type="number" id="know_from" value="{{ isset($vistit->know_from) ? $vistit->know_from : ''}}" >
    {!! $errors->first('know_from', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('send_from') ? 'has-error' : ''}}">
    <label for="send_from" class="control-label">{{ 'Send From' }}</label>
    <input class="form-control" name="send_from" type="text" id="send_from" value="{{ isset($vistit->send_from) ? $vistit->send_from : ''}}" >
    {!! $errors->first('send_from', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('other_from') ? 'has-error' : ''}}">
    <label for="other_from" class="control-label">{{ 'Other From' }}</label>
    <input class="form-control" name="other_from" type="text" id="other_from" value="{{ isset($vistit->other_from) ? $vistit->other_from : ''}}" >
    {!! $errors->first('other_from', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('reason_sti') ? 'has-error' : ''}}">
    <label for="reason_sti" class="control-label">{{ 'Reason Sti' }}</label>
    <input class="form-control" name="reason_sti" type="number" id="reason_sti" value="{{ isset($vistit->reason_sti) ? $vistit->reason_sti : ''}}" >
    {!! $errors->first('reason_sti', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('reason_sti_other') ? 'has-error' : ''}}">
    <label for="reason_sti_other" class="control-label">{{ 'Reason Sti Other' }}</label>
    <input class="form-control" name="reason_sti_other" type="text" id="reason_sti_other" value="{{ isset($vistit->reason_sti_other) ? $vistit->reason_sti_other : ''}}" >
    {!! $errors->first('reason_sti_other', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('reason_vct') ? 'has-error' : ''}}">
    <label for="reason_vct" class="control-label">{{ 'Reason Vct' }}</label>
    <input class="form-control" name="reason_vct" type="number" id="reason_vct" value="{{ isset($vistit->reason_vct) ? $vistit->reason_vct : ''}}" >
    {!! $errors->first('reason_vct', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('\reason_vct_other') ? 'has-error' : ''}}">
    <label for="\reason_vct_other" class="control-label">{{ '\reason Vct Other' }}</label>
    <input class="form-control" name="\reason_vct_other" type="text" id="\reason_vct_other" value="{{ isset($vistit->\reason_vct_other) ? $vistit->\reason_vct_other : ''}}" >
    {!! $errors->first('\reason_vct_other', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('sti_hostory') ? 'has-error' : ''}}">
    <label for="sti_hostory" class="control-label">{{ 'Sti Hostory' }}</label>
    <input class="form-control" name="sti_hostory" type="text" id="sti_hostory" value="{{ isset($vistit->sti_hostory) ? $vistit->sti_hostory : ''}}" >
    {!! $errors->first('sti_hostory', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('contraceptive_method') ? 'has-error' : ''}}">
    <label for="contraceptive_method" class="control-label">{{ 'Contraceptive Method' }}</label>
    <input class="form-control" name="contraceptive_method" type="text" id="contraceptive_method" value="{{ isset($vistit->contraceptive_method) ? $vistit->contraceptive_method : ''}}" >
    {!! $errors->first('contraceptive_method', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('LMP') ? 'has-error' : ''}}">
    <label for="LMP" class="control-label">{{ 'Lmp' }}</label>
    <input class="form-control" name="LMP" type="text" id="LMP" value="{{ isset($vistit->LMP) ? $vistit->LMP : ''}}" >
    {!! $errors->first('LMP', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('symptom') ? 'has-error' : ''}}">
    <label for="symptom" class="control-label">{{ 'Symptom' }}</label>
    <textarea class="form-control" rows="5" name="symptom" type="textarea" id="symptom" >{{ isset($vistit->symptom) ? $vistit->symptom : ''}}</textarea>
    {!! $errors->first('symptom', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('diagnosis') ? 'has-error' : ''}}">
    <label for="diagnosis" class="control-label">{{ 'Diagnosis' }}</label>
    <textarea class="form-control" rows="5" name="diagnosis" type="textarea" id="diagnosis" >{{ isset($vistit->diagnosis) ? $vistit->diagnosis : ''}}</textarea>
    {!! $errors->first('diagnosis', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('\term_syphilis') ? 'has-error' : ''}}">
    <label for="\term_syphilis" class="control-label">{{ '\term Syphilis' }}</label>
    <input class="form-control" name="\term_syphilis" type="number" id="\term_syphilis" value="{{ isset($vistit->\term_syphilis) ? $vistit->\term_syphilis : ''}}" >
    {!! $errors->first('\term_syphilis', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('disease_state') ? 'has-error' : ''}}">
    <label for="disease_state" class="control-label">{{ 'Disease State' }}</label>
    <input class="form-control" name="disease_state" type="number" id="disease_state" value="{{ isset($vistit->disease_state) ? $vistit->disease_state : ''}}" >
    {!! $errors->first('disease_state', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('treatment') ? 'has-error' : ''}}">
    <label for="treatment" class="control-label">{{ 'Treatment' }}</label>
    <textarea class="form-control" rows="5" name="treatment" type="textarea" id="treatment" >{{ isset($vistit->treatment) ? $vistit->treatment : ''}}</textarea>
    {!! $errors->first('treatment', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('disease_state_other') ? 'has-error' : ''}}">
    <label for="disease_state_other" class="control-label">{{ 'Disease State Other' }}</label>
    <input class="form-control" name="disease_state_other" type="text" id="disease_state_other" value="{{ isset($vistit->disease_state_other) ? $vistit->disease_state_other : ''}}" >
    {!! $errors->first('disease_state_other', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('consultation') ? 'has-error' : ''}}">
    <label for="consultation" class="control-label">{{ 'Consultation' }}</label>
    <input class="form-control" name="consultation" type="number" id="consultation" value="{{ isset($vistit->consultation) ? $vistit->consultation : ''}}" >
    {!! $errors->first('consultation', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('hiv_sti_test') ? 'has-error' : ''}}">
    <label for="hiv_sti_test" class="control-label">{{ 'Hiv Sti Test' }}</label>
    <input class="form-control" name="hiv_sti_test" type="number" id="hiv_sti_test" value="{{ isset($vistit->hiv_sti_test) ? $vistit->hiv_sti_test : ''}}" >
    {!! $errors->first('hiv_sti_test', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('hiv_sti_test_date') ? 'has-error' : ''}}">
    <label for="hiv_sti_test_date" class="control-label">{{ 'Hiv Sti Test Date' }}</label>
    <input class="form-control" name="hiv_sti_test_date" type="date" id="hiv_sti_test_date" value="{{ isset($vistit->hiv_sti_test_date) ? $vistit->hiv_sti_test_date : ''}}" >
    {!! $errors->first('hiv_sti_test_date', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('hiv_sti_test_resule') ? 'has-error' : ''}}">
    <label for="hiv_sti_test_resule" class="control-label">{{ 'Hiv Sti Test Resule' }}</label>
    <input class="form-control" name="hiv_sti_test_resule" type="text" id="hiv_sti_test_resule" value="{{ isset($vistit->hiv_sti_test_resule) ? $vistit->hiv_sti_test_resule : ''}}" >
    {!! $errors->first('hiv_sti_test_resule', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('\touch_tracing') ? 'has-error' : ''}}">
    <label for="\touch_tracing" class="control-label">{{ '\touch Tracing' }}</label>
    <input class="form-control" name="\touch_tracing" type="number" id="\touch_tracing" value="{{ isset($vistit->\touch_tracing) ? $vistit->\touch_tracing : ''}}" >
    {!! $errors->first('\touch_tracing', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('touch_tracing_fail') ? 'has-error' : ''}}">
    <label for="touch_tracing_fail" class="control-label">{{ 'Touch Tracing Fail' }}</label>
    <input class="form-control" name="touch_tracing_fail" type="number" id="touch_tracing_fail" value="{{ isset($vistit->touch_tracing_fail) ? $vistit->touch_tracing_fail : ''}}" >
    {!! $errors->first('touch_tracing_fail', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('provide_condom_site') ? 'has-error' : ''}}">
    <label for="provide_condom_site" class="control-label">{{ 'Provide Condom Site' }}</label>
    <input class="form-control" name="provide_condom_site" type="text" id="provide_condom_site" value="{{ isset($vistit->provide_condom_site) ? $vistit->provide_condom_site : ''}}" >
    {!! $errors->first('provide_condom_site', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('provide_condom_quantity') ? 'has-error' : ''}}">
    <label for="provide_condom_quantity" class="control-label">{{ 'Provide Condom Quantity' }}</label>
    <input class="form-control" name="provide_condom_quantity" type="number" id="provide_condom_quantity" value="{{ isset($vistit->provide_condom_quantity) ? $vistit->provide_condom_quantity : ''}}" >
    {!! $errors->first('provide_condom_quantity', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('provide_lubricant_quantity') ? 'has-error' : ''}}">
    <label for="provide_lubricant_quantity" class="control-label">{{ 'Provide Lubricant Quantity' }}</label>
    <input class="form-control" name="provide_lubricant_quantity" type="number" id="provide_lubricant_quantity" value="{{ isset($vistit->provide_lubricant_quantity) ? $vistit->provide_lubricant_quantity : ''}}" >
    {!! $errors->first('provide_lubricant_quantity', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('appointment') ? 'has-error' : ''}}">
    <label for="appointment" class="control-label">{{ 'Appointment' }}</label>
    <input class="form-control" name="appointment" type="date" id="appointment" value="{{ isset($vistit->appointment) ? $vistit->appointment : ''}}" >
    {!! $errors->first('appointment', '<p class="help-block">:message</p>') !!}
</div>
<div class="form-group {{ $errors->has('appointment_reason') ? 'has-error' : ''}}">
    <label for="appointment_reason" class="control-label">{{ 'Appointment Reason' }}</label>
    <input class="form-control" name="appointment_reason" type="text" id="appointment_reason" value="{{ isset($vistit->appointment_reason) ? $vistit->appointment_reason : ''}}" >
    {!! $errors->first('appointment_reason', '<p class="help-block">:message</p>') !!}
</div>


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>
