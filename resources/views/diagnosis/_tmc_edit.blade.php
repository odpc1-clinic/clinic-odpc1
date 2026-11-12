{{--
    แก้รายการวินิจฉัยของ TMC/TB (ไม่มีสถานะของโรค/ICD-10 — ของงาน STI) — หน้าตาเดียวกับหน้าเพิ่ม (_tmc_picker) แต่เลือกได้รายการเดียว เพราะแก้ทีละแถว (2569-10-01)
    ชื่อที่พิมพ์ใหม่ถูกเก็บเข้ารายการกลางตอนบันทึก (DiagnosisController@update)
    ตัวแปร: $diagnosi · $presets
--}}
@php $__cur = old('tmc_name', (string) $diagnosi->other_disease); @endphp
<style>
    .tmc-dx-opt { border:1px solid #d0d7de; background:#fff; border-radius:8px; padding:.3rem .65rem; font-size:.92rem; }
    .tmc-dx-opt:hover { background:#f1f5f9; }
    .tmc-dx-opt.is-on { background:#0f766e; border-color:#0f766e; color:#fff; }
</style>
<input type="hidden" name="tmc_mode" value="1">
<div id="tmc-dx-edit">
    <label class="form-label fw-bold">diagnosis <span class="text-muted small fw-normal">(เลือกจากรายการ หรือพิมพ์ใหม่)</span></label>
    <input type="text" class="form-control mb-2 js-tmc-name" name="tmc_name" list="tmc-dx-list" maxlength="150" required
           value="{{ $__cur }}" placeholder="พิมพ์ค้นหา หรือพิมพ์ diagnosis ใหม่" style="max-width:34rem;">
    <datalist id="tmc-dx-list">
        @foreach($presets as $p)<option value="{{ $p }}">@endforeach
    </datalist>

    <div class="small text-muted mb-1">รายการที่ใช้บ่อย</div>
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach($presets as $p)
            <button type="button" class="tmc-dx-opt js-tmc-opt" data-name="{{ $p }}">{{ $p }}</button>
        @endforeach
    </div>
    <div class="form-text mt-2">พิมพ์ชื่อที่ไม่มีในรายการแล้วกดบันทึก = เพิ่มเข้ารายการให้เคสต่อไปเลือกได้</div>

    <div class="mt-3">
        <button type="submit" class="btn btn-primary px-4"><i class="fa fa-save me-1"></i> บันทึก</button>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('tmc-dx-edit');
    if (!box) return;
    var input = box.querySelector('.js-tmc-name');
    var norm = function (s) { return String(s || '').toLowerCase().replace(/\s+/g, ''); };
    function paint() {
        box.querySelectorAll('.js-tmc-opt').forEach(function (b) { b.classList.toggle('is-on', norm(b.dataset.name) === norm(input.value)); });
    }
    box.addEventListener('click', function (e) {
        var o = e.target.closest('.js-tmc-opt');
        if (!o) return;
        input.value = o.dataset.name;
        paint();
    });
    input.addEventListener('input', paint);
    paint();
})();
</script>
