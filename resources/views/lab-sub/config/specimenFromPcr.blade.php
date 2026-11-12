<div class="row">
    <div class="form-check col-2 px-5">
        <input class="form-check-input vv" type="checkbox" id="pcr[1]" name="pcr[1]"
            @if (isset($labSubConfig[$method]) && array_key_exists(1, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][1]) ? $labSubConfig[$method][1] : '-1' }}">
        <label class="form-check-label" for="pcr[1]">
            Urethra
        </label>
    </div>

    @if ($sex == 2) <!-- แสดง Vagina ถ้า sex เป็น 2 -->
        <div class="form-check col-2 px-5">
            <input class="form-check-input" type="checkbox" id="pcr[4]" name="pcr[4]"
                @if (isset($labSubConfig[$method]) && array_key_exists(4, $labSubConfig[$method])) checked @endif
                value="{{ isset($labSubConfig[$method][4]) ? $labSubConfig[$method][4] : '-1' }}">
            <label class="form-check-label" for="pcr[4]">
                Vagina
            </label>
        </div>
    @endif

    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="pcr[2]" name="pcr[2]"
            @if (isset($labSubConfig[$method]) && array_key_exists(2, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][2]) ? $labSubConfig[$method][2] : '-1' }}">
        <label class="form-check-label" for="pcr[2]">
            Anus/Rectal swab
        </label>
    </div>

    <!-- <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="pcr[3]" name="pcr[3]"
            @if (isset($labSubConfig[$method]) && array_key_exists(3, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][3]) ? $labSubConfig[$method][3] : '-1' }}">
        <label class="form-check-label" for="pcr[3]">
            Pharynx
        </label>
    </div> -->
</div>

<div class="row">
    <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="pcr[5]" name="pcr[5]"
            @if (isset($labSubConfig[$method]) && array_key_exists(5, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][5]) ? $labSubConfig[$method][5] : '-1' }}">
        <label class="form-check-label" for="pcr[5]">
            Urine
        </label>
    </div>

    <div class="form-check col-3 px-5">
        <input class="form-check-input" type="checkbox" id="pcr[6]" name="pcr[6]"
            @if (isset($labSubConfig[$method]) && array_key_exists(6, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][6]) ? $labSubConfig[$method][6] : '-1' }}">
        <label class="form-check-label" for="pcr[6]">
            Oral swab/Pharynx
        </label>
    </div>
    <!-- <div class="form-check col-2 px-5">
        <input class="form-check-input" type="checkbox" id="pcr[7]" name="pcr[7]"
            @if (isset($labSubConfig[$method]) && array_key_exists(7, $labSubConfig[$method])) checked @endif
            value="{{ isset($labSubConfig[$method][7]) ? $labSubConfig[$method][7] : '-1' }}">
        <label class="form-check-label" for="pcr[7]">
            Rectal swab
        </label>
    </div> -->
</div>
