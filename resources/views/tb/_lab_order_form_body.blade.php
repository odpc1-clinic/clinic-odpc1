{{-- เนื้อใน popup "สั่งตรวจ / ลงผล" ของบัตร TB 01 — โหลดด้วย fetch ทุกครั้งที่เปิด (ค่าล่าสุดในฐาน)
     ⚠️ ห้ามมี <script> และห้ามมี <form> — ฟอร์มคือตัว popup เอง (#tbLabModalForm ใน tb/_lab_order_modals)
     ช่องชุดเดียวกับหน้าตรวจ OPD (tb/_lab_fields) บันทึกที่ TbController@saveLabOrder -> TbScreening::saveLabSection
     ตัวแปร: $reg · $tv (visit วันนี้) · $sc (ใบคัดกรองของ visit นี้ หรือใบเปล่า) --}}
<input type="hidden" name="visit_id" value="{{ $tv->id }}">
<div class="alert alert-danger py-2 d-none js-lo-errors" role="alert"></div>
@include('tb._lab_fields', ['sc' => $sc, 'prefix' => 'tb_lab'])
