{{-- ============================================================
     หน้าทำงานคลินิก TMC (เวชศาสตร์การเดินทางและท่องเที่ยว)
     แสดงเฉพาะ visit ที่ clinic = 3 — ดูจุด include ใน visit/form/index.blade.php
     ส่วนนี้ = สัญญาณชีพ + ซักประวัติการเดินทาง (โซนฉีดวัคซีนแยกไป tmc_vaccine อยู่ "หลัง Lab")

     ⚠️ ห้ามใช้ <form> ในไฟล์นี้เด็ดขาด — include อยู่ข้างในฟอร์มหลัก (#visit_edit_form)
     ============================================================ --}}
<style>
    [data-tabtoggle][href="#tmcVital"]   { background: #0d9488 !important; }
    [data-tabtoggle][href="#tmcHistory"] { background: #2563eb !important; color:#fff !important; }
    [data-tabtoggle][href="#tmcHistory"] font { color:#fff !important; }
    .tmc-vital input { text-align: center; font-weight: 600; }
</style>

{{-- นัดครั้งนี้มาเพื่ออะไร (วัคซีนที่นัด/เหตุผลนัด จาก visit ก่อน) — 2569-10-14 --}}
@include('visit.form._tmc_this_visit')

{{-- สัญญาณชีพย้ายไป partial กลาง (คลินิก TB ใช้ร่วม) --}}
@include('visit.form.vitals')

{{-- ---------- ซักประวัติการเดินทาง (ตามใบซักประวัติ TMC) ---------- --}}
@include('visit.form.tmc_history')
