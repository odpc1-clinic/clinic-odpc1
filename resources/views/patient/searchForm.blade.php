<form method="GET" action="{{ route('/patient') }}" accept-charset="UTF-8" role="search">

    <div class="row">
        <div class="col-sm-2" style="width: 20.5%;border: 1px solid gray;border-radius: 5px;">
            <div class="row">
                <div class="col-sm-6 form-group ">
                    <label for="start" class="control-label">{{ 'วันที่มารับบริการ' }}</label>
                    <input class="form-control date" name="start" type="text" id="start" value="{{ request()->start }}">
                </div>
                <div class="col-sm-6 form-group ">
                    <label for="end" class="control-label">{{ 'ถึง' }}</label>
                    <input class="form-control date" name="end" type="text" id="end" value="{{ request()->end }}">
                </div>
            </div>
        </div>

        <div class="col-sm-1 form-group ">
            <label for="sex" class="control-label">{{ 'เพศ' }}</label>
            <select class="form-control" name="sex" id="sex">
                <option value="0"> </option>
                @foreach (App\Models\Patient::$sexOption as $key => $value)
                    <option value="{{ $key }}" @if ($key == request()->sex) selected="selected" @endif>
                        {{ $value }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-2" style="border: 1px solid gray;border-radius: 5px;">
            <div class="row">

                <div class="col-sm-6 form-group">
                    <label for="start_age" class="control-label">{{ 'อายุ' }}</label>
                    <input class="form-control" name="start_age" type="number" id="start_age" min="0" max="150"
                        value="{{ request()->start_age }}">
                </div>
                <div class="col-sm-6 form-group ">
                    <label for="end_age" class="control-label">{{ 'ถึง' }}</label>
                    <input class="form-control" name="end_age" type="number" id="end_age" min="0" max="150"
                        value="{{ request()->end_age }}">
                </div>

            </div>
        </div>
        <div class="col-sm-1 form-group "><br>
            <button class="btn btn-secondary" type="submit" st>
                <i class="fa fa-search"></i>
            </button>
        </div>
    </div>


</form>

<script src="/js/bootstrap-datepicker.js"></script>
<script src="/js/bootstrap-datepicker-thai.js"></script>
<script src="/js/bootstrap-datepicker.th.js"></script>


<script type="text/javascript">
    $(function() {


        $('.date').datepicker({
            language: 'th-th',
        });

    });
</script>
