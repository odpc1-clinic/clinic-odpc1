{{-- การตรวจร่างกาย TMC — "PE = normal all" + สวิตช์ระบบที่ผิดปกติ (คลินิกขอ 2569-09-30)
     เปิดสวิตช์ระบบไหน ช่องกรอกสิ่งที่พบของระบบนั้นถึงจะโผล่ · ระบบประกอบข้อความลง body_check ให้เอง
     อยู่ในฟอร์มหลักของหน้าตรวจ ทุกช่องชื่อขึ้นต้น tmc_pe จึงเข้ากติกา _touched ได้เลย
     ทุกสวิตช์มี hidden ค่า 0 คู่ไว้ ปิดสวิตช์แล้วค่าต้องถูกส่งเป็น 0 ไม่ใช่หายไป --}}
@php
    $__pe  = is_array($visit->tmc_pe ?? null) ? $visit->tmc_pe : null;
    $__sys = (array) ($__pe['sys'] ?? []);
    // visit เก่าที่ยังไม่เคยใช้สวิตช์: ข้อความ PE เดิมย้ายไปอยู่ช่อง "เพิ่มเติม" ไม่ให้หาย
    $__note = $__pe ? ($__pe['note'] ?? '') : ($visit->body_check ?? '');
@endphp
<style>
    .tmc-pe .form-switch .form-check-input { width: 2.6em; height: 1.35em; cursor: pointer; }
    .tmc-pe .pe-sys-label { font-weight: 600; color: #4f46e5; min-width: 7.5rem; cursor: pointer; }
    .tmc-pe .pe-row { padding: .35rem 0; border-bottom: 1px dashed #e5e7eb; }
    .tmc-pe .pe-row.is-abn .pe-sys-label { color: #dc2626; }
</style>
<div class="tmc-pe mb-2" id="tmc-pe">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fw-bold">PE =</span>
        <input type="hidden" name="tmc_pe[normal_all]" value="0">
        <div class="form-check mb-0">
            <input class="form-check-input js-pe-normal" type="checkbox" name="tmc_pe[normal_all]" value="1" id="pe-normal-all"
                   style="transform:scale(1.3);" {{ ! empty($__pe['normal_all']) ? 'checked' : '' }}>
            <label class="form-check-label fw-bold" for="pe-normal-all" style="color:#4f46e5;">normal all</label>
        </div>
        @include('visit.field_history_button', ['field' => 'body_check', 'target' => 'tmc-pe-note'])
    </div>

    @foreach(\App\Models\Visit::$tmcPeSystems as $__k => $__label)
        @php $__abn = array_key_exists($__k, $__sys); @endphp
        <div class="pe-row js-pe-row {{ $__abn ? 'is-abn' : '' }}">
            <div class="d-flex align-items-center gap-2">
                <input type="hidden" name="tmc_pe[abn][{{ $__k }}]" value="0">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input js-pe-switch" type="checkbox" role="switch" id="pe-{{ $__k }}"
                           name="tmc_pe[abn][{{ $__k }}]" value="1" {{ $__abn ? 'checked' : '' }}
                           title="เปิด = ผิดปกติ">
                </div>
                <label class="pe-sys-label mb-0" for="pe-{{ $__k }}">{{ $__label }}</label>
                <input type="text" class="form-control form-control-sm js-pe-text {{ $__abn ? '' : 'd-none' }}"
                       name="tmc_pe[text][{{ $__k }}]" maxlength="500" value="{{ $__sys[$__k] ?? '' }}"
                       placeholder="สิ่งที่พบผิดปกติ ({{ $__label }})">
            </div>
        </div>
    @endforeach

    <label for="tmc-pe-note" class="form-label small text-muted mt-2 mb-1">เพิ่มเติม</label>
    <textarea class="form-control" rows="2" name="tmc_pe[note]" id="tmc-pe-note">{{ $__note }}</textarea>
</div>
<script>
(function () {
    var box = document.getElementById('tmc-pe');
    if (!box) return;
    box.addEventListener('change', function (e) {
        var sw = e.target.closest('.js-pe-switch');
        if (sw) {
            var row = sw.closest('.js-pe-row');
            var txt = row.querySelector('.js-pe-text');
            row.classList.toggle('is-abn', sw.checked);
            txt.classList.toggle('d-none', !sw.checked);
            if (sw.checked) { txt.focus(); }
        }
    });
})();
</script>
