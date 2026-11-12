<div class="row">
    @if ($method == 4) 
        <!-- ถ้า method เป็น Culture ให้แสดง Cervix + Urethra -->
        <!-- <div class="form-check col-2 px-5">
            <input class="form-check-input" type="checkbox" id="item[{{ $method }}][8]"
                name="item[{{ $method }}][8]" 
                @if (isset($labSubConfig[$method]) && array_key_exists(8, $labSubConfig[$method])) checked @endif 
                value="{{ isset($labSubConfig[$method][8]) ? $labSubConfig[$method][8] : '-1' }}">
            <label class="form-check-label" for="item[{{ $method }}][8]">
                Cervix + Urethra
            </label>
        </div> -->
        <div class="form-check col-2 px-5">
                <input class="form-check-input" type="checkbox" id="item[{{ $method }}][3]"
                    name="item[{{ $method }}][3]" 
                    @if (isset($labSubConfig[$method]) && array_key_exists(3, $labSubConfig[$method])) checked @endif
                    value="{{ isset($labSubConfig[$method][3]) ? $labSubConfig[$method][3] : '-1' }}">
                <label class="form-check-label" for="item[{{ $method }}][3]">
                    Cervix
                </label>
            </div>
        <div class="form-check col-2 px-5">
            <input class="form-check-input vv" type="checkbox" id="item[{{ $method }}][1]"
                name="item[{{ $method }}][1]" 
                @if (isset($labSubConfig[$method]) && array_key_exists(1, $labSubConfig[$method])) checked @endif
                value="{{ isset($labSubConfig[$method][1]) ? $labSubConfig[$method][1] : '-1' }}">
            <label class="form-check-label" for="item[{{ $method }}][1]">
                Urethra
            </label>
        </div>
    @else
        <!-- ถ้า method ไม่ใช่ Culture ให้แสดง Cervix และ Urethra แยกกัน -->
        @if ($sex == 2)
            <div class="form-check col-2 px-5">
                <input class="form-check-input" type="checkbox" id="item[{{ $method }}][3]"
                    name="item[{{ $method }}][3]" 
                    @if (isset($labSubConfig[$method]) && array_key_exists(3, $labSubConfig[$method])) checked @endif
                    value="{{ isset($labSubConfig[$method][3]) ? $labSubConfig[$method][3] : '-1' }}">
                <label class="form-check-label" for="item[{{ $method }}][3]">
                    Cervix
                </label>
            </div>
        @endif
        <div class="form-check col-2 px-5">
            <input class="form-check-input vv" type="checkbox" id="item[{{ $method }}][1]"
                name="item[{{ $method }}][1]" 
                @if (isset($labSubConfig[$method]) && array_key_exists(1, $labSubConfig[$method])) checked @endif
                value="{{ isset($labSubConfig[$method][1]) ? $labSubConfig[$method][1] : '-1' }}">
            <label class="form-check-label" for="item[{{ $method }}][1]">
                Urethra
            </label>
        </div>
    @endif

    @if ($sex == 2)
        <div class="form-check col-2 px-5">
            <input class="form-check-input" type="checkbox" id="item[{{ $method }}][2]"
                name="item[{{ $method }}][2]" 
                @if (isset($labSubConfig[$method]) && array_key_exists(2, $labSubConfig[$method])) checked @endif
                value="{{ isset($labSubConfig[$method][2]) ? $labSubConfig[$method][2] : '-1' }}">
            <label class="form-check-label" for="item[{{ $method }}][2]">
                Vagina
            </label>
        </div>
    @endif

    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="item[{{ $method }}][4]"
            name="item[{{ $method }}][4]" 
            @if (isset($labSubConfig[$method]) && array_key_exists(4, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][4]) ? $labSubConfig[$method][4] : '-1' }}">
        <label class="form-check-label" for="item[{{ $method }}][4]">
            Anus
        </label>
    </div>
</div>

<div class="row">
    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="item[{{ $method }}][5]"
            name="item[{{ $method }}][5]" 
            @if (isset($labSubConfig[$method]) && array_key_exists(5, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][5]) ? $labSubConfig[$method][5] : '-1' }}">
        <label class="form-check-label" for="item[{{ $method }}][5]">
            Urine
        </label>
    </div>
    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="item[{{ $method }}][6]"
            name="item[{{ $method }}][6]" 
            @if (isset($labSubConfig[$method]) && array_key_exists(6, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][6]) ? $labSubConfig[$method][6] : '-1' }}">
        <label class="form-check-label" for="item[{{ $method }}][6]">
            Oral swab
        </label>
    </div>
    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="item[{{ $method }}][7]"
            name="item[{{ $method }}][7]" 
            @if (isset($labSubConfig[$method]) && array_key_exists(7, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][7]) ? $labSubConfig[$method][7] : '-1' }}">
        <label class="form-check-label" for="item[{{ $method }}][7]">
            Rectal swab
        </label>
    </div>

    <div class="form-check col-4 px-5" style="display: flex;">
        <label class="control-label px-0" for="item[{{ $method }}][10]">
            Other 
        </label>
        <input class="form-control" type="text" id="item[{{ $method }}][10][key]"
            name="item[{{ $method }}][10][key]"
            value="{{ isset($labSubConfig[$method][10]) ? $labSubConfig[$method][10]['key'] : '' }}">
        <input type="hidden" name="item[{{ $method }}][10][value]"
            value="{{ isset($labSubConfig[$method][10]) ? $labSubConfig[$method][10]['value'] : '' }}">
    </div>
</div>
