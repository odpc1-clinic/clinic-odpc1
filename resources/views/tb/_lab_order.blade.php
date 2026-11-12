{{-- กล่อง "สั่งตรวจครั้งนี้" ในบัตร TB 01 (คลินิกอนุมัติ 2569-10-02)
     สั่ง/ลงผล Lab ให้ visit TB ของวันนี้ผ่าน popup บนบัตรเลย — เคส F/U ไม่ต้องกลับไปหน้าตรวจ OPD
     เคส walk-in ยังสั่งที่หน้าตรวจเหมือนเดิม ทั้งสองที่เขียนลง visit/ใบคัดกรองเดียวกัน

     กล่องนี้เป็นแค่สรุป + ปุ่ม (ไม่มีฟอร์ม) — เนื้อในอยู่ที่ tb/_lab_order_summary (สลับในที่ได้หลังบันทึก)
     popup อยู่ที่ tb/_lab_order_modals (ท้ายหน้า นอกฟอร์มอื่นทั้งหมด) · สคริปต์อยู่ที่ tb/_lab_order_js --}}
<div class="card border-0 shadow-sm mb-3" id="tb-lab-order">
    <div class="card-body" id="tb-lab-order-body" data-summary-url="{{ route('tb.labOrder.summary', $reg->id) }}">
        @include('tb._lab_order_summary', ['reg' => $reg])
    </div>
</div>
