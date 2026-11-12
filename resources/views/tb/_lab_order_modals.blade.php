{{-- popup ของกล่อง "สั่งตรวจครั้งนี้" ในบัตร TB 01 (2569-10-02)
     ⚠️ ต้องวางท้ายหน้า นอกฟอร์มอื่นทั้งหมด (CLAUDE.md ข้อ 1) — popup แรกตัวมันเองเป็น <form> ส่วน popup แล็บนอกมีฟอร์มย่อยในเนื้อหา
     เนื้อในโหลดด้วย fetch ตอนเปิด (ไม่มี <script>) ตัวจัดการอยู่ใน tb/_lab_order_js --}}
@php
    $__pt = $reg->patient;
    $__tv = \App\Http\Controllers\TbController::todayTbVisit($reg);
    $__who = trim(($__pt->name ?? '') . ' ' . ($__pt->surname ?? ''));
@endphp
<style>
    /* ปุ่ม/การ์ดสรุป — โทนเดียวกับบัตร TB 01 (อำพัน) · Bootstrap 5.1 ไม่มีคลาส *-subtle จึงกำหนดสีเอง */
    .tb-lo-btn-main { background:#b45309; border-color:#b45309; color:#fff; font-weight:600; }
    .tb-lo-btn-main:hover, .tb-lo-btn-main:focus { background:#92400e; border-color:#92400e; color:#fff; }
    .tb-lo-btn-alt  { background:#fff; border:1px solid #f59e0b; color:#92400e; font-weight:600; }
    .tb-lo-btn-alt:hover, .tb-lo-btn-alt:focus { background:#fffbeb; color:#7c2d12; }
    /* ปุ่มเปิด popup 3 ปุ่ม — เดิมใช้สีส้มเดียวกับแท็บที่เลือกอยู่ พยาบาลบอกว่าดูคล้ายแท็บ จะงง (2569-10-02)
       จึงแยกสีให้ไม่ซ้ำกับแท็บ และแต่ละปุ่มคนละสี จำได้ว่าปุ่มไหนเปิดอะไร */
    .tb-lo-act { font-weight:600; border-width:2px; border-radius:2rem; padding:.4rem 1rem; }
    .tb-lo-act-tb  { background:#0f766e; border-color:#0f766e; color:#fff; }
    .tb-lo-act-tb:hover, .tb-lo-act-tb:focus { background:#115e59; border-color:#115e59; color:#fff; }
    .tb-lo-act-inh { background:#fff; border-color:#be123c; color:#be123c; }
    .tb-lo-act-inh:hover, .tb-lo-act-inh:focus { background:#be123c; color:#fff; }
    .tb-lo-act-ext { background:#fff; border-color:#4338ca; color:#4338ca; }
    .tb-lo-act-ext:hover, .tb-lo-act-ext:focus { background:#4338ca; color:#fff; }
    .tb-lo-panel { background:#fffbf5; border:1px solid #fde4c3; border-radius:.75rem; padding:.75rem .9rem; }
    .tb-lo-panel-title { font-weight:700; color:#92400e; font-size:.9rem; margin-bottom:.5rem; }
    .tb-chip { display:inline-flex; align-items:center; gap:.35rem; border-radius:999px; padding:.25rem .7rem; font-size:.85rem; border:1px solid; }
    .tb-chip-sub { font-weight:400; opacity:.9; }
    .tb-chip-spec { background:#eff6ff; border-color:#bfdbfe; color:#1e3a8a; }
    .tb-chip-wait { background:#fefce8; border-color:#fde68a; color:#854d0e; }
    .tb-chip-done { background:#ecfdf5; border-color:#a7f3d0; color:#065f46; }

    /* popup */
    .tb-lo-modal .modal-content { border:0; border-radius:1rem; overflow:hidden; box-shadow:0 1.5rem 3rem rgba(124,45,18,.25); }
    .tb-lo-modal .modal-header { background:linear-gradient(135deg,#b45309 0%,#f59e0b 100%); color:#fff; border:0; padding:1rem 1.25rem; }
    .tb-lo-modal .modal-header .btn-close { filter:invert(1) grayscale(1) brightness(2); opacity:.9; }
    .tb-lo-modal .tb-lo-head-icon { width:2.6rem; height:2.6rem; border-radius:.75rem; background:rgba(255,255,255,.2);
                                    display:inline-flex; align-items:center; justify-content:center; font-size:1.25rem; }
    .tb-lo-modal .modal-body { background:#fffaf3; }
    .tb-lo-modal .modal-footer { background:#fff; border-top:1px solid #fde4c3; }
    .tb-lo-section, .tb-lo-modal [data-lab-group] {
        background:#fff; border:1px solid #fde4c3; border-radius:.75rem; padding:.85rem 1rem; margin-bottom:.85rem;
        box-shadow:0 1px 2px rgba(146,64,14,.06);
    }
    .tb-lo-section-title { font-weight:700; color:#92400e; margin-bottom:.6rem; }
    /* ช่องชุดเดียวกับหน้าตรวจ — ใน popup ไม่ต้องมีกรอบฟ้าซ้อนอีกชั้น */
    .tb-lo-modal #tb-lab-fields { background:transparent !important; border:0 !important; padding:0 !important; }
    .tb-lo-modal .tb-lab-fields-title { display:none; }
    .tb-lo-modal .tb-lo-focus { animation: tbLoFlash 1.6s ease-out 1; }
    @keyframes tbLoFlash { 0% { box-shadow:0 0 0 .3rem rgba(245,158,11,.55); } 100% { box-shadow:0 1px 2px rgba(146,64,14,.06); } }
    .tb-lo-loading { text-align:center; color:#92400e; padding:3rem 0; }
    /* ปฏิทินต้องอยู่เหนือ popup (modal = 1055) — _tabs ตั้ง .datepicker ไว้ 1090 อยู่แล้ว ย้ำไว้ตรงนี้กันคนแก้ที่เดียว */
    .datepicker.dropdown-menu { z-index:1090 !important; }
    .tb-lo-toast-wrap { position:fixed; right:1rem; bottom:1rem; z-index:1100; }
</style>

{{-- ===== popup 1: สั่งตรวจ / ลงผล (ส่งตรวจทางห้องปฏิบัติการ) ===== --}}
<div class="modal fade tb-lo-modal" id="tbLabModal" tabindex="-1" aria-labelledby="tbLabModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" id="tbLabModalForm" method="POST" action="{{ route('tb.labOrder', $reg->id) }}"
              data-lab-ajax="1" data-load="{{ route('tb.labOrder.part', [$reg->id, 'form']) }}" autocomplete="off" novalidate>
            @csrf
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="tb-lo-head-icon"><i class="fa fa-vial"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="tbLabModalTitle">สั่งตรวจ / ลงผล — ส่งตรวจทางห้องปฏิบัติการ</h5>
                        <div class="small" style="opacity:.92;">
                            {{ $__who ?: '-' }} · HN {{ $__pt->code ?? '-' }}
                            @if($reg->tb_no) · TB No. {{ $reg->tb_no }} @endif
                            @if($__tv) · Visit #{{ $__tv->id }} วันที่ {{ \App\Utillity::db2thDate($__tv->date) }} @endif
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body js-lo-body">
                <div class="tb-lo-loading"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลด…</div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa fa-circle-info me-1"></i>บันทึกเฉพาะส่วนที่แก้ · ลงใน visit เดียวกับหน้าตรวจ OPD</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn tb-lo-btn-main js-lo-save"><i class="fa fa-save me-1"></i> บันทึก</button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ===== popup 2: Lab ภายนอก (ExternalLabController ตัวเดียวกับหน้าตรวจ) ===== --}}
<div class="modal fade tb-lo-modal" id="tbExtLabModal" tabindex="-1" aria-labelledby="tbExtLabModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" data-load="{{ route('tb.labOrder.part', [$reg->id, 'external']) }}">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="tb-lo-head-icon"><i class="fa fa-truck-medical"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="tbExtLabModalTitle">Lab ส่งตรวจภายนอก</h5>
                        <div class="small" style="opacity:.92;">
                            {{ $__who ?: '-' }} · HN {{ $__pt->code ?? '-' }}
                            @if($reg->tb_no) · TB No. {{ $reg->tb_no }} @endif
                            @if($__tv) · Visit #{{ $__tv->id }} วันที่ {{ \App\Utillity::db2thDate($__tv->date) }} @endif
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body js-lo-body">
                <div class="tb-lo-loading"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลด…</div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa fa-circle-info me-1"></i>ผลจากแล็บนอกไม่ต้องรอห้อง Lab อนุมัติ · ราคากรอกเอง เข้าบิลอัตโนมัติ</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
                    <button type="submit" form="tbExtRowsForm" class="btn tb-lo-btn-main js-ext-save d-none"><i class="fa fa-save me-1"></i> บันทึกผล / ราคา</button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ===== popup 3: Lab ในคลินิก (HIV ฯลฯ) — LabSubController@quickOrder กติกาเดียวกับหน้าสั่ง Lab เต็ม ===== --}}
<div class="modal fade tb-lo-modal" id="tbInhLabModal" tabindex="-1" aria-labelledby="tbInhLabModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content" data-load="{{ route('tb.labOrder.part', [$reg->id, 'inhouse']) }}">
            <div class="modal-header">
                <div class="d-flex align-items-center gap-3">
                    <span class="tb-lo-head-icon"><i class="fa fa-flask-vial"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="tbInhLabModalTitle">Lab ในคลินิก — ตรวจ HIV / เลือด</h5>
                        <div class="small" style="opacity:.92;">
                            {{ $__who ?: '-' }} · HN {{ $__pt->code ?? '-' }}
                            @if($reg->tb_no) · TB No. {{ $reg->tb_no }} @endif
                            @if($__tv) · Visit #{{ $__tv->id }} วันที่ {{ \App\Utillity::db2thDate($__tv->date) }} @endif
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body js-lo-body">
                <div class="tb-lo-loading"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลด…</div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa fa-circle-info me-1"></i>ส่งเข้าห้อง Lab ทันที · ผลลงโดยห้อง Lab</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn tb-lo-btn-main js-inh-save" data-panel="#tbInhLabModal .js-inh-panel">
                        <i class="fa fa-save me-1"></i> บันทึก
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="tb-lo-toast-wrap" id="tbLoToastWrap" aria-live="polite" aria-atomic="true"></div>
