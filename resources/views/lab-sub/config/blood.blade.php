
<div class="row">
    <div class="form-check col-2  px-5">
        <input class="form-check-input" type="checkbox" id="blood[6]" name="blood[6]"
        @if (array_key_exists(6, $labSubConfig)) checked @endif
        value="{{ (isset($labSubConfig[6]) ) ? $labSubConfig[6]: '-1' }}"
        
        >
        <label class="form-check-label" for="blood[6]">
        Anti-HIV
        </label>
    </div>


    <div class="form-check col-2 ">
        <input class="form-check-input" type="checkbox" id="blood[7]" name="blood[7]" 
        @if (array_key_exists(7, $labSubConfig)) checked @endif
        value="{{ (isset($labSubConfig[7])) ? $labSubConfig[7] : '-1' }}"
        >
        <label class="form-check-label" for="blood[7]">
        TPHA
        </label>
    </div>
    <div class="form-check col-2 ">
        <input class="form-check-input" type="checkbox" id="blood[8]" name="blood[8]" 
        @if (array_key_exists(8, $labSubConfig)) checked @endif
        value="{{ (isset($labSubConfig[8])) ? $labSubConfig[8] : '-1' }}"
        >
        <label class="form-check-label" for="blood[8]">
        RPR
        </label>
    </div>
    <div class="form-check col-2 ">
        <input class="form-check-input" type="checkbox" id="blood[11]" name="blood[11]" 
        @if (array_key_exists(11, $labSubConfig)) checked @endif
        value="{{ (isset($labSubConfig[11])) ? $labSubConfig[11] : '-1' }}"
        >
        <label class="form-check-label" for="blood[11]">
        HBs Antigen
        </label>
    </div>
    {{-- HBs Antibody ย้ายไปสั่งที่ "Lab นอก" แล้ว (พยาบาลขอ 2569-08-26)
         โชว์เฉพาะใบที่เคยสั่งไว้แล้ว ห้ามซ่อนทิ้งดื้อ ๆ เพราะ saveConfig()
         ลบ lab_subs ของใบนี้ทั้งหมดแล้วสร้างใหม่จากที่ติ๊กมา ของเก่าจะหายทันที --}}
    @if (array_key_exists(14, $labSubConfig))
        <div class="form-check col-2 ">
            <input class="form-check-input" type="checkbox" id="blood[14]" name="blood[14]" checked
            value="{{ (isset($labSubConfig[14])) ? $labSubConfig[14] : '-1' }}"
            >
            <label class="form-check-label" for="blood[14]">
            HBs Antibody <span class="badge bg-warning text-dark">ย้ายไป Lab นอก</span>
            </label>
        </div>
    @endif
    <div class="form-check col-2 ">
        <input class="form-check-input" type="checkbox" id="blood[12]" name="blood[12]" 
        @if (array_key_exists(12, $labSubConfig)) checked @endif
        value="{{ (isset($labSubConfig[12])) ? $labSubConfig[12] : '-1' }}"
        >
        <label class="form-check-label" for="blood[12]">
        Anti-HCV
        </label>
    </div>
</div>
