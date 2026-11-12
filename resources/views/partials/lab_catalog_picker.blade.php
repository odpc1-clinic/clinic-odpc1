{{-- ตัวเลือก "สั่งแล็บ" จากแคตตาล็อกรวม (2569-10-02) — ใช้ได้ทุกคลินิก คลินิกใหม่ไม่ต้องทำหน้าสั่งแล็บใหม่
     ซ้าย = หมวด (จำนวน) · ขวา = ค้นหา/ติ๊กรายการ (ค้นข้ามทุกหมวด) · ล่าง = รายการที่จะสั่ง (ราคา/ยกเว้น/ตำแหน่ง/วันที่ส่ง)
     กด "ยืนยันสั่ง" = คำขอเดียว เซิร์ฟเวอร์ส่งแต่ละรายการไปทางเดิมของมัน (App\Services\LabCatalogOrder)

     ⚠️ ไฟล์นี้อาจถูก include ข้างในฟอร์มหลักของหน้าตรวจ (#visit_edit_form) — จึง:
        ไม่มี <form> · ช่องกรอกไม่มี name= (ไม่ถูกส่งไปกับปุ่ม Save และไม่นับใน _touched) · data-no-dirty
        และสคริปต์ (partials/lab_catalog_picker_js) ย้าย popup นี้ไปต่อกับ body ตอนโหลดหน้า
     เนื้อรายการสร้างด้วย JS จากข้อมูลที่โหลดใหม่ทุกครั้งที่เปิด (อีกห้องอาจเพิ่งสั่ง)
     ปุ่มเปิด: data-bs-toggle="modal" data-bs-target="#labCatalogModal" + data-picker-url / data-order-url / data-context / data-tb-reg --}}
@once
<style>
    /* Bootstrap 5.1 ไม่มีคลาส *-subtle — กำหนดสีเองทั้งหมด */
    .lcat-modal .modal-content { border:0; border-radius:1rem; overflow:hidden; }
    .lcat-modal .modal-header { background:linear-gradient(135deg,#1e3a8a 0%,#2563eb 100%); color:#fff; border:0; }
    .lcat-modal .modal-header .btn-close { filter:invert(1) grayscale(1) brightness(2); }
    .lcat-modal .modal-body { background:#f8fafc; }
    .lcat-cats .list-group-item { cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:.5rem; font-size:.9rem; padding:.45rem .7rem; }
    .lcat-cats .list-group-item.active { background:#1d4ed8; border-color:#1d4ed8; }
    .lcat-cats .lcat-n { background:#e2e8f0; color:#334155; border-radius:999px; padding:0 .5rem; font-size:.75rem; }
    .lcat-cats .active .lcat-n { background:#fff; color:#1d4ed8; }
    .lcat-list { max-height:46vh; overflow:auto; background:#fff; border:1px solid #e2e8f0; border-radius:.6rem; }
    .lcat-item { display:flex; align-items:center; gap:.6rem; padding:.45rem .7rem; border-bottom:1px solid #f1f5f9; margin:0; cursor:pointer; }
    .lcat-item:hover { background:#f8fafc; }
    .lcat-item.is-on { background:#eff6ff; }
    .lcat-item.is-done { opacity:.6; cursor:not-allowed; }
    /* DTX / EKG ที่สั่งแล้ว — ลงผลตรงนี้ จึงไม่ทำจาง */
    .lcat-item.is-poc { opacity:1; cursor:default; flex-wrap:wrap; }
    .lcat-poc { display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; width:100%; padding-left:1.6rem; margin-top:.25rem; }
    .lcat-poc input { width:6.5rem; text-align:center; font-weight:600; }
    .lcat-item .lcat-name { flex:1 1 auto; min-width:0; }
    .lcat-item .lcat-price { white-space:nowrap; font-size:.85rem; color:#334155; text-align:right; }
    .lcat-cat-head { position:sticky; top:0; z-index:1; background:#f1f5f9; color:#475569; font-weight:700; font-size:.8rem; padding:.25rem .7rem; }
    .lcat-badge { display:inline-block; border-radius:999px; padding:.05rem .5rem; font-size:.72rem; font-weight:600; border:1px solid; white-space:nowrap; }
    .lcat-k-in_house   { background:#ecfdf5; border-color:#6ee7b7; color:#065f46; }
    .lcat-k-external   { background:#eef2ff; border-color:#a5b4fc; color:#3730a3; }
    .lcat-k-tb_sendout { background:#fff7ed; border-color:#fdba74; color:#9a3412; }
    .lcat-done-chip { background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; border-radius:999px; padding:.05rem .5rem; font-size:.72rem; white-space:nowrap; }
    .lcat-warn { color:#b45309; font-size:.78rem; }
    .lcat-pending { background:#f0fdf4; border:1px solid #86efac; border-radius:.6rem; padding:.5rem .6rem; }
    .lcat-pending tr.is-bad td { background:#fef2f2; }
    .lcat-results { background:#ecfdf5; border:1px solid #6ee7b7; border-radius:.6rem; padding:.5rem .75rem; }
    .lcat-modal .datepicker.dropdown-menu, .datepicker.dropdown-menu { z-index:1090 !important; }
</style>
<div class="modal fade lcat-modal" id="labCatalogModal" tabindex="-1" aria-labelledby="labCatalogTitle" aria-hidden="true" data-no-dirty>
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold mb-0" id="labCatalogTitle"><i class="fa fa-flask me-1"></i> สั่งแล็บ</h5>
                    <div class="small js-lcat-who" style="opacity:.92;"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="js-lcat-loading text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2"></span> กำลังโหลดรายการ…</div>
                <div class="js-lcat-main d-none">
                    <div class="lcat-results mb-2 d-none js-lcat-results"></div>
                    <div class="row g-3">
                        <div class="col-lg-3">
                            <div class="list-group lcat-cats js-lcat-cats"></div>
                            <div class="small text-muted mt-2">
                                <span class="lcat-badge lcat-k-in_house">ในคลินิก</span> ห้อง Lab เราตรวจ ·
                                <span class="lcat-badge lcat-k-external">ส่งนอก</span> ส่งแล็บภายนอก ·
                                <span class="lcat-badge lcat-k-tb_sendout">ส่งตรวจวัณโรค</span>
                            </div>
                        </div>
                        <div class="col-lg-9">
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text"><i class="fa fa-search"></i></span>
                                <input type="text" class="form-control js-lcat-search" autocomplete="off"
                                       placeholder="ค้นหาชื่อหรือรหัส ทุกหมวด เช่น HIV, AFB, N620, CBC, ไทรอยด์">
                                <span class="input-group-text js-lcat-found small"></span>
                            </div>
                            <div class="lcat-list js-lcat-list"></div>
                        </div>
                    </div>

                    <div class="lcat-pending mt-3 d-none js-lcat-pending">
                        <div class="fw-semibold small mb-1" style="color:#166534;">
                            <i class="fa fa-list-check me-1"></i> รายการที่จะสั่ง (<span class="js-lcat-n">0</span>) — ยังไม่บันทึกจนกว่าจะกด "ยืนยันสั่ง"
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th style="min-width:200px;">รายการ</th>
                                        <th style="min-width:180px;">ตำแหน่ง / สิ่งส่งตรวจ</th>
                                        <th style="min-width:170px;">ราคา</th>
                                        <th style="width:130px;">วันที่ส่ง</th>
                                        <th style="width:40px;"></th>
                                    </tr>
                                </thead>
                                <tbody class="js-lcat-pending-body"></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="alert alert-danger py-2 px-3 mt-2 mb-0 d-none js-lcat-error"></div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <span class="small text-muted"><i class="fa fa-circle-info me-1"></i>ผลยังลงที่เดิม: Lab ในคลินิก = ห้อง Lab · ส่งนอก/วัณโรค = ช่องผลเดิม</span>
                <div>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">ปิด</button>
                    <button type="button" class="btn btn-primary js-lcat-confirm" disabled>
                        <i class="fa fa-check me-1"></i> ยืนยันสั่ง <span class="js-lcat-n">0</span> รายการ
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endonce
