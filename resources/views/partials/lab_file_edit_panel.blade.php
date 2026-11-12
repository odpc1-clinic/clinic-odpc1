{{-- ปุ่ม "แก้" + ช่องแก้ข้อมูลกำกับไฟล์ผลตรวจ (วันที่ / ตรวจที่ / รายการ / หมายเหตุ) — พิมพ์ผิดตอนอัป (2569-10-08)
     ใช้ทั้งรายการในหน้าตรวจ (โหลดผ่าน AJAX ได้) และบัตร TB 01 → ห้ามมี <script>/<form> ในไฟล์นี้
     ช่องไม่มี name (ไม่ไปกับฟอร์มหลัก) · ตัวจัดการอยู่ใน partials/lab_file_edit_js ผูกที่ document
     ตัวแปร: $f (TbLabFile), $url (route update) --}}
<button type="button" class="btn btn-outline-secondary btn-sm js-lfe-open" title="แก้ข้อมูล (พิมพ์ผิด)"><i class="fa fa-pen"></i></button>
<div class="js-lfe-panel d-none w-100 mt-1 p-1 rounded" style="background:#f8fafc; border:1px solid #e2e8f0;" data-url="{{ $url }}">
    <input type="text" class="form-control form-control-sm mb-1" data-f="test_date" placeholder="วันที่ตรวจ วว/ดด/ปปปป"
           value="{{ $f->test_date ? \App\Support\ThaiDate::show($f->test_date) : '' }}">
    <input type="text" class="form-control form-control-sm mb-1" data-f="source" maxlength="150" list="lab-file-sources" autocomplete="off" placeholder="ตรวจที่" value="{{ $f->source }}">
    <input type="text" class="form-control form-control-sm mb-1" data-f="title" maxlength="150" list="lab-file-titles" autocomplete="off" placeholder="รายการ" value="{{ $f->title }}">
    <input type="text" class="form-control form-control-sm mb-1" data-f="note" maxlength="500" placeholder="หมายเหตุ" value="{{ $f->note }}">
    <div class="d-flex gap-1">
        <button type="button" class="btn btn-primary btn-sm flex-fill js-lfe-save"><i class="fa fa-save"></i> บันทึก</button>
        <button type="button" class="btn btn-outline-secondary btn-sm js-lfe-cancel">ยกเลิก</button>
    </div>
</div>
