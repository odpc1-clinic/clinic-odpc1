{{--
    ช่อง diagnosis ของ TMC และ TB (รายการชื่อแยกตามคลินิก) — เลือกจากรายการ หรือพิมพ์เพิ่มเอง (หน้าตาตามโปรแกรม Medivel ที่พยาบาล TMC ใช้มาก่อน · ขอ 2569-10-01)
    ชื่อที่พิมพ์ใหม่ถูกเก็บเข้ารายการกลาง (diagnosis_presets) ตอนบันทึก เคสต่อไปเลือกได้เลย
    บันทึกเป็นหนึ่งแถวต่อรายการ: โรค "Other" + ชื่อ (ดู DiagnosisController@store)
    ตัวแปร: $presets = ชื่อในรายการ · $existing = ที่ลงไว้แล้วใน visit นี้
--}}
@php
    $__done = $existing->where('disease', 11)->pluck('other_disease')->map(fn ($s) => mb_strtolower(trim((string) $s)))->all();
    $__old  = (array) old('tmc_dx', []);
@endphp
<style>
    .tmc-dx-chip { display:inline-flex; align-items:center; gap:.35rem; background:#e0f2f1; color:#0f5f58; border:1px solid #99d5cf;
                   border-radius:999px; padding:.25rem .5rem .25rem .75rem; font-size:.95rem; }
    .tmc-dx-chip button { border:0; background:transparent; color:#0f5f58; font-size:1.1rem; line-height:1; padding:0 .15rem; }
    .tmc-dx-opt { border:1px solid #d0d7de; background:#fff; border-radius:8px; padding:.3rem .65rem; font-size:.92rem; }
    .tmc-dx-opt:hover { background:#f1f5f9; }
    .tmc-dx-opt.is-on { background:#0f766e; border-color:#0f766e; color:#fff; }
    .tmc-dx-opt.is-done { opacity:.55; }
</style>
<div class="card mb-3 border-0 shadow-sm" id="tmc-dx">
    <input type="hidden" name="tmc_mode" value="1">
    <div class="card-body">
        <label class="form-label fw-bold">diagnosis <span class="text-muted small fw-normal">(เลือกได้หลายรายการ · ไม่มีในรายการพิมพ์เพิ่มเองได้)</span></label>

        {{-- ที่เลือกแล้ว --}}
        <div class="d-flex flex-wrap gap-2 mb-2 js-tmc-chips" style="min-height:2.2rem;"></div>

        <div class="input-group mb-3" style="max-width:34rem;">
            <input type="text" class="form-control js-tmc-input" list="tmc-dx-list" maxlength="150"
                   placeholder="พิมพ์ค้นหา หรือพิมพ์ diagnosis ใหม่ แล้วกด Enter">
            <button type="button" class="btn btn-outline-primary js-tmc-add"><i class="fa fa-plus me-1"></i> เพิ่ม</button>
        </div>
        <datalist id="tmc-dx-list">
            @foreach($presets as $p)<option value="{{ $p }}">@endforeach
        </datalist>

        {{-- รายการกลาง กดเลือก/เอาออกได้เลย --}}
        <div class="small text-muted mb-1">รายการที่ใช้บ่อย</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($presets as $p)
                @php $__isDone = in_array(mb_strtolower($p), $__done, true); @endphp
                <button type="button" class="tmc-dx-opt js-tmc-opt {{ $__isDone ? 'is-done' : '' }}" data-name="{{ $p }}"
                        @if($__isDone) title="ลงไว้แล้วใน visit นี้" @endif>{{ $p }}@if($__isDone) ✓@endif</button>
            @endforeach
        </div>
        <div class="form-text mt-2">
            พิมพ์ชื่อที่ไม่มีในรายการ แล้วกดบันทึก = เพิ่มเข้ารายการให้เคสต่อไปเลือกได้ · ชื่อที่ต่างกันแค่ตัวพิมพ์เล็ก/ใหญ่หรือช่องว่าง ระบบใช้ชื่อเดิมในรายการให้
        </div>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('tmc-dx');
    if (!box) return;
    var chips = box.querySelector('.js-tmc-chips');
    var input = box.querySelector('.js-tmc-input');
    var norm = function (s) { return String(s || '').toLowerCase().replace(/\s+/g, ''); };

    function has(name) {
        return Array.prototype.some.call(chips.querySelectorAll('input'), function (i) { return norm(i.value) === norm(name); });
    }
    function paintOpts() {
        box.querySelectorAll('.js-tmc-opt').forEach(function (b) { b.classList.toggle('is-on', has(b.dataset.name)); });
    }
    function add(name) {
        name = String(name || '').trim().replace(/\s+/g, ' ');
        if (!name || has(name)) return;
        // พิมพ์ตรงกับชื่อในรายการ (ไม่สนตัวพิมพ์) → ใช้ชื่อในรายการ
        box.querySelectorAll('.js-tmc-opt').forEach(function (b) { if (norm(b.dataset.name) === norm(name)) name = b.dataset.name; });
        var chip = document.createElement('span');
        chip.className = 'tmc-dx-chip';
        var t = document.createElement('span'); t.textContent = name;
        var h = document.createElement('input'); h.type = 'hidden'; h.name = 'tmc_dx[]'; h.value = name;
        var x = document.createElement('button'); x.type = 'button'; x.title = 'เอาออก'; x.textContent = '×';
        x.addEventListener('click', function () { chip.remove(); paintOpts(); });
        chip.appendChild(t); chip.appendChild(h); chip.appendChild(x);
        chips.appendChild(chip);
        paintOpts();
    }
    function remove(name) {
        chips.querySelectorAll('input').forEach(function (i) { if (norm(i.value) === norm(name)) i.closest('.tmc-dx-chip').remove(); });
        paintOpts();
    }

    box.addEventListener('click', function (e) {
        var o = e.target.closest('.js-tmc-opt');
        if (o) { has(o.dataset.name) ? remove(o.dataset.name) : add(o.dataset.name); return; }
        if (e.target.closest('.js-tmc-add')) { add(input.value); input.value = ''; input.focus(); }
    });
    input.addEventListener('keydown', function (e) {
        // Enter ในช่องนี้ = เพิ่มรายการ ไม่ใช่ส่งฟอร์ม
        if (e.key === 'Enter') { e.preventDefault(); add(input.value); input.value = ''; }
    });
    // พิมพ์ค้างไว้แล้วกดบันทึกเลย ก็ยังนับ
    var form = box.closest('form');
    if (form) form.addEventListener('submit', function () { if (input.value.trim()) { add(input.value); input.value = ''; } });

    @json($__old).forEach(add);
})();
</script>
