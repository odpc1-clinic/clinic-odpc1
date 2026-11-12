<!-- <div class="form-group {{ $errors->has('lab_id') ? 'has-error' : ''}}">
    <label for="lab_id" class="control-label">{{ 'Lab Id' }}</label>
    <input class="form-control" name="lab_id" type="number" id="lab_id" value="{{ isset($labsub->lab_id) ? $labsub->lab_id : ''}}" >
    {!! $errors->first('lab_id', '<p class="help-block">:message</p>') !!}
</div> -->
<div class="row ">
    <div class="form-group col-2 {{ $errors->has('method') ? 'has-error' : ''}}">
        <label for="method" class="control-label">{{ 'Method' }}</label>
        <!-- <input class="form-control" name="method" type="number" id="method" value="{{ isset($labsub->method) ? $labsub->method : ''}}" > -->
        <select class="form-control" name="method" id="method">
            @foreach (App\Models\LabSub::orderableMethods() as $key => $value)
            <option value="{{ $key }}" @if (isset($labsub->method) && $key == $labsub->method) selected="selected" @endif>
                {{ $value }}
            </option>
            @endforeach
        </select>
        {!! $errors->first('method', '<p class="help-block">:message</p>') !!}
        {{-- ของหายไปจากรายการต้องบอกว่าย้ายไปไหน ไม่งั้นพยาบาลหาไม่เจอแล้วนึกว่าระบบพัง --}}
        <small class="text-muted d-block mt-1">{{ App\Models\LabSub::retiredHint() }}</small>
    </div>

    <div id="method_other_div" class="form-group col-10 {{ $errors->has('method_other') ? 'has-error' : ''}}">
        <label for="method_other" class="control-label">{{ 'Other Method ' }}</label>
        <input class="form-control" name="method_other" type="text" id="method_other" value="{{ isset($labsub->method_other) ? $labsub->method_other : ''}}">
        {!! $errors->first('method_other', '<p class="help-block">:message</p>') !!}
    </div>
</div>

<div id="specimen_from_div" class="form-group {{ $errors->has('specimen_from') ? 'has-error' : ''}}">
    <label for="specimen_from" class="control-label">{{ 'Specimen From' }}</label>
    <!-- <input class="form-control" name="specimen_from" type="number" id="specimen_from" value="{{ isset($labsub->specimen_from) ? $labsub->specimen_from : ''}}" > -->
    <select class="form-control" name="specimen_from[]" id="specimen_from" multiple>
        @foreach (App\Models\LabSub::$specimenFromOption as $key => $value)
        <option value="{{ $key }}" @if (isset($labsub->specimen_from) && $key == $labsub->specimen_from) selected="selected" @endif>
            {{ $value }}
        </option>
        @endforeach
    </select>
    {!! $errors->first('specimen_from', '<p class="help-block">:message</p>') !!}
</div>

<div id="specimen_from_other_div" class="form-group {{ $errors->has('specimen_from_other') ? 'has-error' : ''}}">
    <label for="specimen_from_other" class="control-label">{{ 'Other Specimen From' }}</label>
    <input class="form-control" name="specimen_from_other" type="text" id="specimen_from_other" value="{{ isset($labsub->specimen_from_other) ? $labsub->specimen_from_other : ''}}">
    {!! $errors->first('specimen_from_other', '<p class="help-block">:message</p>') !!}
</div>
<!-- <div class="form-group {{ $errors->has('result') ? 'has-error' : ''}}">
    <label for="result" class="control-label">{{ 'Result' }}</label>
    <input class="form-control" name="result" type="text" id="result" value="{{ isset($labsub->result) ? $labsub->result : ''}}" >
    {!! $errors->first('result', '<p class="help-block">:message</p>') !!}
</div> -->


<div class="form-group">
    <input class="btn btn-primary" type="submit" value="{{ $formMode === 'edit' ? 'Update' : 'Create' }}">
</div>

<script>
    var specimenFromOption = <?php echo json_encode(App\Models\LabSub::$specimenFromOption); ?>;
    var specimenFromPCROption = <?php echo json_encode(App\Models\LabSub::$specimenFromPCROption); ?>;

    $("#method").change(function() {
        // $("#specimen_from option:selected").prop("selected", false)
        $('#specimen_from').empty()
        $("#specimen_from_other_div").hide();
        if ($("#method").val() == 10) {
            $("#method_other_div").show();
            $("#specimen_from_div").hide();  
            $("#specimen_from_other_div").show();         
        } else {
            $("#method_other_div").hide();
            if ($("#method").val() < 5) {
                $.each(specimenFromOption, function(i, item) {
                    $('#specimen_from').append($('<option>', {
                        value: i,
                        text: item
                    }));
                });

                $("#specimen_from_div").show();
            } else if ($("#method").val() == 5) {
                $("#specimen_from_div").show();

                $.each(specimenFromPCROption, function(i, item) {
                    $('#specimen_from').append($('<option>', {
                        value: i,
                        text: item
                    }));
                });


            } else {
                $("#specimen_from_div").hide();
                $("#specimen_from option:selected").prop("selected", false)
            }
        }
    })
    $("#method").change()

    $("#specimen_from").change(function() {
        console.log($("#specimen_from").val())
        if ($("#specimen_from").val().includes('10')) {
            $("#specimen_from_other_div").show();
        } else {
            $("#specimen_from_other_div").hide();
        }
    })
    $("#specimen_from").change()
</script>