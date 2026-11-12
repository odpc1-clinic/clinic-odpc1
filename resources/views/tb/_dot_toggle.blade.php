{{-- เปิด/ปิด DOT ของบัตร (ตัวเดิมจาก visit/form/tb_card ที่เลิก include ไปเมื่อ 2569-10-02 แล้วปุ่มหายไปด้วย)
     วางได้ 2 ที่ (คลินิกขอ 2569-10-12): บัตร TB 01 หมวดประวัติ DOT · กล่อง F/U ในหน้าตรวจ
     เป็น <div> ไม่ใช่ <form> — หน้าตรวจทั้งหน้าอยู่ในฟอร์มหลัก · ช่องใช้ data-field แทน name (ไม่ติดไปกับปุ่ม Save)
     ปุ่มบันทึก: JS สร้างฟอร์มต่อกับ body แล้วส่งไป tb.dotStatus (หน้าโหลดใหม่หลังบันทึก)
     ตัวแปร: $reg (TbRegistration) · $offDefault ('Y-m-d' พ.ศ. ไม่บังคับ) = วันปิดที่เติมไว้ให้ · หน้าละหนึ่งกล่อง
     วันที่ปิด DOT แก้ย้อนหลังได้ (คลินิกขอ 2569-10-26) — เปิดจากหน้าตรวจย้อนหลังแล้วเดิมได้วันนี้เสมอ
     วันนี้มีผล: ช่อง DOT ก่อน/ถึงวันปิดยังนับว่า "ไม่ได้บันทึก" ได้ (TbRegistration::dotUnrecordedState) --}}
@php
    // หน้าตรวจแปลง $visit->date เป็น วว/ดด/ปปปป ไปแล้ว — รับเฉพาะ Y-m-d ดิบ ไม่ใช่ก็ใช้วันนี้
    $__offDef = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($offDefault ?? '')) ? $offDefault : null;
    if ($__offDef && (int) substr($__offDef, 0, 4) < 2500) { $__offDef = \App\Utillity::AD2BE($__offDef); }
    if (! $__offDef || $__offDef > \App\Support\ThaiDate::todayBe()) { $__offDef = \App\Support\ThaiDate::todayBe(); }
    $__offVal = \App\Support\ThaiDate::show($reg->dot_active ? $__offDef : ($reg->dot_off_date ?: $__offDef));
@endphp
<div class="d-flex flex-wrap align-items-center gap-1 js-dot-status-form" data-url="{{ route('tb.dotStatus', $reg->id) }}">
    <span class="small fw-bold" style="color:#b45309;">DOT:</span>
    <select data-field="dot_active" class="form-select form-select-sm w-auto js-dot-status">
        <option value="1" {{ $reg->dot_active ? 'selected' : '' }}>กำลังกำกับกินยา (DOT)</option>
        <option value="0" {{ $reg->dot_active ? '' : 'selected' }}>Off DOT</option>
    </select>
    {{-- เหตุผลที่ DOT (คลินิกขอ 2569-10-14) — ช่องเดิม dot_reason ที่รายงานประจำเดือนข้อ 7 ใช้จับกลุ่ม --}}
    <input type="text" data-field="dot_reason" list="tb-dot-toggle-reasons" maxlength="255"
           class="form-control form-control-sm js-dot-on-only {{ $reg->dot_active ? '' : 'd-none' }}" style="width:11rem;"
           value="{{ $reg->dot_reason }}" placeholder="เหตุผลที่ DOT" title="เหตุผล/ระยะที่ต้อง DOT เช่น Active phase, MDR">
    <datalist id="tb-dot-toggle-reasons">
        @foreach(\App\Models\TbRegistration::$dotReasonOption as $__o)<option value="{{ $__o }}">@endforeach
    </datalist>
    <input type="text" data-field="dot_channel" list="tb-dot-toggle-channels" maxlength="255"
           class="form-control form-control-sm js-dot-channel" style="width:10rem;"
           value="{{ $reg->dot_channel }}" placeholder="ช่องทาง DOT" title="ช่องทาง DOT — อสม./รพ.สต. จะไม่แสดงในหน้า DOT">
    <datalist id="tb-dot-toggle-channels">
        @foreach(\App\Models\TbRegistration::$dotChannelOption as $__o)<option value="{{ $__o }}">@endforeach
    </datalist>
    {{-- ผู้รับผิดชอบ: ศูนย์บริการ / ชื่อเจ้าหน้าที่ — เลือกจากรายการหรือพิมพ์เพิ่มเอง --}}
    <input type="text" data-field="dot_owner" list="tb-dot-toggle-owners" maxlength="100"
           class="form-control form-control-sm" style="width:11rem;"
           value="{{ $reg->dot_owner }}" placeholder="ผู้รับผิดชอบ" title="ผู้รับผิดชอบเคส — เลือกหรือพิมพ์เอง">
    <datalist id="tb-dot-toggle-owners">
        @foreach(\App\Models\TbRegistration::dotOwnerOptions() as $__o)<option value="{{ $__o }}">@endforeach
    </datalist>
    <input type="text" data-field="dot_off_reason" maxlength="255"
           class="form-control form-control-sm js-dot-off-reason {{ $reg->dot_active ? 'd-none' : '' }}"
           style="width:14rem;" value="{{ $reg->dot_active ? '' : $reg->dot_off_reason }}"
           placeholder="เหตุผล เช่น AFB เป็นลบแล้ว" {{ $reg->dot_active ? '' : 'disabled' }}>
    <span class="small text-muted js-dot-off-only {{ $reg->dot_active ? 'd-none' : '' }}">ปิดตั้งแต่</span>
    <input type="text" data-field="dot_off_date" maxlength="10" autocomplete="off"
           class="form-control form-control-sm js-dot-off-only js-dot-off-date {{ $reg->dot_active ? 'd-none' : '' }}"
           style="width:7.5rem;" value="{{ $__offVal }}" placeholder="วว/ดด/ปปปป" title="วันที่ปิด DOT — ย้อนหลังได้ ห้ามเป็นวันในอนาคต">
    <button type="button" class="btn btn-sm btn-outline-dark d-none js-dot-status-save">บันทึก DOT</button>
</div>
<script>
(function () {
    if (window.__tbDotToggleInit) return;
    window.__tbDotToggleInit = true;
    var orig = null;
    var FIELDS = ['dot_active', 'dot_reason', 'dot_channel', 'dot_owner', 'dot_off_reason', 'dot_off_date'];
    function box() { return document.querySelector('.js-dot-status-form'); }
    function val(b, f) { var el = b.querySelector('[data-field="' + f + '"]'); return el ? el.value : ''; }
    function snap(b) { var o = {}; FIELDS.forEach(function (f) { o[f] = val(b, f); }); return o; }
    function refresh(b) {
        if (orig === null) return;
        var sel = b.querySelector('.js-dot-status'), why = b.querySelector('.js-dot-off-reason'),
            btn = b.querySelector('.js-dot-status-save'), off = sel.value === '0', now = snap(b);
        why.classList.toggle('d-none', !off); why.disabled = !off;
        b.querySelectorAll('.js-dot-on-only').forEach(function (el) { el.classList.toggle('d-none', off); });
        b.querySelectorAll('.js-dot-off-only').forEach(function (el) { el.classList.toggle('d-none', !off); });
        btn.classList.toggle('d-none', FIELDS.every(function (f) { return now[f] === orig[f]; }));
    }
    (function init() { var b = box(); if (b) orig = snap(b); })();
    // ปฏิทิน พ.ศ. — ผูกตอนคลิกครั้งแรก (ไฟล์ภาษาไทยโหลดท้ายหน้า)
    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (!el.classList || !el.classList.contains('js-dot-off-date') || el.dataset.dp) return;
        if (window.jQuery && jQuery.fn.datepicker && (jQuery.fn.datepicker.dates || {})['th-th']) {
            el.dataset.dp = '1';
            jQuery(el).datepicker({ language: 'th-th', autoclose: true, todayHighlight: true, endDate: new Date() })
                .on('changeDate', function () { var bx = box(); if (bx) refresh(bx); });
            jQuery(el).datepicker('show');
        }
    });
    function save(b) {
        // หน้าโหลดใหม่หลังบันทึก — หน้าตรวจจำที่พิมพ์ค้างไว้ให้แล้ว (visit/_draft_popup_js) จึงไม่ต้องถาม
        if (window.__visitDraftStash) { window.__visitDraftStash(); window.__visitDraftStashed = true; }
        var f = document.createElement('form');
        f.method = 'POST'; f.action = b.dataset.url; f.style.display = 'none';
        var add = function (n, v) { var i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
        add('_token', @json(csrf_token()));
        add('dot_active', val(b, 'dot_active'));
        add('dot_channel', val(b, 'dot_channel'));
        if (b.querySelector('[data-field="dot_reason"]')) add('dot_reason', val(b, 'dot_reason'));
        if (b.querySelector('[data-field="dot_owner"]')) add('dot_owner', val(b, 'dot_owner'));
        if (val(b, 'dot_active') === '0') { add('dot_off_reason', val(b, 'dot_off_reason')); add('dot_off_date', val(b, 'dot_off_date')); }
        document.body.appendChild(f);   // ต่อกับ body เท่านั้น — ห้ามอยู่ในฟอร์มอื่น
        f.submit();
    }
    document.addEventListener('change', function (e) {
        var b = e.target.closest && e.target.closest('.js-dot-status-form');
        if (!b) return;
        refresh(b);
        if (e.target.classList.contains('js-dot-status') && e.target.value === '0' && e.target.value !== orig.dot_active) {
            b.querySelector('.js-dot-off-reason').focus();
        }
    });
    document.addEventListener('input', function (e) {
        var b = e.target.closest && e.target.closest('.js-dot-status-form');
        if (b) refresh(b);
    });
    document.addEventListener('click', function (e) {
        var btn = e.target.closest && e.target.closest('.js-dot-status-save');
        if (btn) { e.preventDefault(); save(btn.closest('.js-dot-status-form')); }
    });
    // Enter ในช่อง DOT = บันทึก DOT ไม่ใช่กดบันทึกฟอร์มที่ครอบอยู่
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        var b = e.target.closest && e.target.closest('.js-dot-status-form');
        if (!b) return;
        e.preventDefault();
        if (!b.querySelector('.js-dot-status-save').classList.contains('d-none')) save(b);
    });
})();
</script>
