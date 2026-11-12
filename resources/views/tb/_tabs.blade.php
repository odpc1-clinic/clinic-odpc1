{{--
    แท็บของบัตร TB 01 — หน้ายาวเกินไป แบ่งตามงาน (คลินิกขอ 2569-10-01)
    ไม่ได้ย้าย markup: แต่ละกล่องมี id อยู่แล้ว แท็บแค่ซ่อน/แสดงกล่องตาม id (ฟอร์มยังแยกกันเหมือนเดิม)
    เปิดหน้ามาพร้อม #กล่อง (กดบันทึกแล้วกลับมา) = เปิดแท็บของกล่องนั้น · ไม่มี = แท็บล่าสุดของเครื่องนั้น
--}}
@php
    $__tbTabs = [
        'info' => ['ข้อมูลบัตร', 'fa-id-card',        ['tb-contacts', 'tb-info']],
        'lab'  => ['ผลตรวจ',    'fa-vial',            ['tb-lab-order', 'tb-sputum', 'tb-xray', 'tb-dst', 'tb-lab-history', 'tb-lab-files']],
        'drug' => ['ยา',         'fa-prescription-bottle-medical', ['tb-order', 'tb-dose', 'tb-regimen']],
        'dot'  => ['DOT',        'fa-calendar-check',  ['tb-dot', 'tb-dot-history', 'tb-followup']],
        // ตารางติดตามการรักษา + ตรวจสายตา + ซึมเศร้า (2569-10-05) — กล่องอยู่ใน tb/_monitor
        'monitor' => ['ติดตาม',  'fa-table-list',      ['tb-monitor', 'tb-monitor-extra']],
    ];
@endphp
<style>
    .tb-tabs { position: sticky; top: 0; z-index: 1020; background: #fff; border-radius: .75rem; }
    .tb-tabs .nav-link { font-size: 1.15rem; font-weight: 700; color: #92400e; border: 2px solid #fcd34d; background: #fffbeb;
                         padding: .6rem 1.4rem; border-radius: .6rem; }
    .tb-tabs .nav-link:hover { background: #fef3c7; }
    .tb-tabs .nav-link.active { color: #fff; background: #b45309; border-color: #b45309; }
    /* ปฏิทินเลือกวันต้องอยู่เหนือแถบแท็บที่ค้างด้านบน (z-index 1020) — เดิมหัวปฏิทินโดนบัง เลือกเดือนไม่ได้ (2569-10-02) */
    .datepicker.dropdown-menu, .datepicker-dropdown { z-index: 1090 !important; }
    .tb-tabs .tb-tab-sub { display: block; font-size: .75rem; font-weight: 400; opacity: .85; }

    /* หัวข้อของแต่ละกล่องในบัตร — เดิมตัวเล็กกลืนกับเนื้อหา (คลินิกขอให้ชัด 2569-10-01)
       ทุกกล่องมีหัวข้อ h6.fw-bold ตัวเดียว จึงจับด้วย id ของกล่องได้โดยไม่ต้องแก้ทีละไฟล์ */
    :is(#tb-info, #tb-lab-order, #tb-sputum, #tb-xray, #tb-dst, #tb-lab-history, #tb-lab-files, #tb-order, #tb-dose, #tb-regimen, #tb-dot, #tb-dot-history, #tb-followup) h6.fw-bold {
        font-size: 1.3rem; color: #7c2d12; background: #fff7ed;
        border-left: 6px solid #d97706; border-radius: .4rem;
        padding: .45rem .8rem; margin-bottom: .75rem !important; display: block;
    }
    :is(#tb-info, #tb-lab-order, #tb-sputum, #tb-xray, #tb-dst, #tb-lab-history, #tb-lab-files, #tb-order, #tb-dose, #tb-regimen, #tb-dot, #tb-dot-history, #tb-followup) h6.fw-bold > i:first-child {
        color: #d97706;
    }
</style>
<div class="tb-tabs shadow-sm p-2 mb-3" id="tb-tabs">
    <ul class="nav nav-pills gap-2 flex-wrap" role="tablist">
        @foreach($__tbTabs as $__k => [$__label, $__icon, $__ids])
            <li class="nav-item" role="presentation">
                <button type="button" class="nav-link js-tb-tab" data-tab="{{ $__k }}" role="tab"
                        data-ids="{{ implode(',', $__ids) }}">
                    <i class="fa {{ $__icon }} me-1"></i> {{ $__label }}
                    <span class="tb-tab-sub">
                        {{ ['info' => 'ทะเบียน · การวินิจฉัย · ผลการรักษา', 'lab' => 'สั่งตรวจ · เสมหะ · X-ray · DST · ประวัติ Lab · ไฟล์ผลจากที่อื่น',
                            'drug' => 'สั่งยาครั้งนี้ · ใบสรุปการรักษา · ประวัติสูตรยา', 'dot' => 'ปฏิทินกินยา · ประวัติ DOT · การติดตามผู้ป่วย',
                            'monitor' => 'ตารางตรวจตามเดือน · สายตา · ซึมเศร้า'][$__k] }}
                    </span>
                </button>
            </li>
        @endforeach
    </ul>
</div>
<script>
// กล่องต่าง ๆ อยู่ใต้แท็บนี้ — รอหน้าโหลดครบก่อนค่อยซ่อน/แสดง
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.getElementById('tb-tabs');
    if (!nav) return;
    var KEY = 'tb.card.tab';
    var tabs = Array.prototype.map.call(nav.querySelectorAll('.js-tb-tab'), function (b) {
        return { key: b.dataset.tab, btn: b, ids: b.dataset.ids.split(',') };
    });

    function show(key, scrollTo) {
        tabs.forEach(function (t) {
            var on = t.key === key;
            t.btn.classList.toggle('active', on);
            t.btn.setAttribute('aria-selected', on ? 'true' : 'false');
            t.ids.forEach(function (id) {
                var el = document.getElementById(id);
                if (el) el.classList.toggle('d-none', !on);
            });
        });
        try { localStorage.setItem(KEY, key); } catch (e) {}
        if (scrollTo) {
            var el = document.getElementById(scrollTo);
            if (el) setTimeout(function () { el.scrollIntoView({ block: 'start' }); }, 50);
        }
    }
    function tabOf(id) {
        var t = tabs.filter(function (t) { return t.ids.indexOf(id) !== -1; })[0];
        return t ? t.key : null;
    }

    nav.addEventListener('click', function (e) {
        var b = e.target.closest('.js-tb-tab');
        if (!b) return;
        show(b.dataset.tab);
        // เปลี่ยนแท็บแล้วเลื่อนกลับมาที่หัวแท็บ ไม่ค้างอยู่กลางหน้า
        var top = nav.getBoundingClientRect().top + window.pageYOffset - 8;
        if (window.pageYOffset > top) window.scrollTo(0, top);
    });

    // ลิงก์ #กล่อง ภายในหน้า (เช่นกดบันทึกแล้วกลับมา) — เปิดแท็บของกล่องนั้นก่อน
    function fromHash() {
        var id = (location.hash || '').replace('#', '');
        var key = id && tabOf(id);
        if (key) { show(key, id); return true; }
        return false;
    }
    window.addEventListener('hashchange', fromHash);

    if (!fromHash()) {
        var last = null;
        try { last = localStorage.getItem(KEY); } catch (e) {}
        show(tabs.some(function (t) { return t.key === last; }) ? last : 'lab');
    }
});
</script>
