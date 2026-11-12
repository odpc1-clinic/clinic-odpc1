{{--
    หน้าตรวจ OPD คลินิกวัณโรค: กล่อง "สั่งยาวัณโรคครั้งนี้" ตัวเดียวกับบัตร TB 01 (คลินิกขอ 2569-10-05)
    แทนตารางยาวัณโรคแบบอ่านอย่างเดียวเดิม (_tb_drugs ยังเก็บไฟล์ไว้)
    กดบันทึก = ลงรายการยาของ visit (การเงิน/ห้องยาเห็นตามปกติ) + ลงบัตร TB 01 ถ้าคนไข้มีบัตรที่ยังรักษาอยู่
    อยู่ในฟอร์มหลักของหน้าตรวจ — กล่องไม่ใช่ <form> และช่องทุกช่องไม่เป็นของฟอร์มหลัก (ดูหัวไฟล์ tb/_order)
--}}
@php $__box = $__box ?? app(\App\Http\Controllers\TbController::class)->visitOrderBoxData($visit); @endphp
@if($__box['embed']['registered'])
    <div class="small mt-3 mb-1">
        <a href="{{ route('tb.show', ['id' => $__box['reg']->id, 'from' => 'visit', 'visit' => $visit->id, 'page' => request('page', 1)]) }}#tb-order"
           target="_blank" class="text-decoration-none"><i class="fa-solid fa-lungs me-1"></i> เปิด{{ $__box['reg']->cardName() }} (ใบสรุปการรักษา / ประวัติสูตร)</a>
    </div>
@endif
@php
    // คนไข้ยังไม่มีบัตร TB 01 (ส่วนใหญ่เป็นเคสคัดกรอง ไม่ได้ให้ยาวัณโรค) — กล่องใหญ่ขึ้นทุกเคสแล้วพยาบาลงงว่าต้องทำอะไร
    // พับเป็นปุ่มไว้ กดแล้วค่อยกาง (คลินิกเลือกแบบนี้ 2569-10-08) · visit นี้เคยลงยาจากกล่องแล้ว = กางให้เห็นเหมือนเดิม
    $__boxFold = ! $__box['embed']['registered'] && (int) ($__box['embed']['boxCount'] ?? 0) === 0;
@endphp
@if($__boxFold)
    {{-- กดแล้วต้องพับกลับได้ (เดิมกดแล้วปุ่มหาย — แจ้ง 2569-10-08) --}}
    <div class="mt-3 js-tb-order-fold">
        <button type="button" class="btn btn-outline-warning fw-bold" style="color:#7c2d12;border-color:#ea580c;"
                onclick="var w=this.closest('.js-tb-order-fold'), box=w.querySelector('.js-tb-order-wrap'), open=box.classList.toggle('d-none') === false;
                         this.querySelector('.js-lbl').textContent = open ? 'ซ่อนกล่องสั่งยาวัณโรค' : 'สั่งยาวัณโรค';
                         w.querySelector('.js-tb-order-hint').style.display = open ? 'none' : '';">
            <i class="fa fa-prescription me-1"></i> <span class="js-lbl">สั่งยาวัณโรค</span>
        </button>
        <span class="small text-muted ms-2 js-tb-order-hint">คนไข้ยังไม่มีบัตร TB 01 — เคสคัดกรองที่ไม่ได้ให้ยาวัณโรค ไม่ต้องกด · ยาอื่นสั่งด้วยปุ่ม Add New ด้านบนตามปกติ</span>
        <div class="js-tb-order-wrap d-none">
@endif
@include('tb._order', ['reg' => $__box['reg'], 'order' => $__box['order'], 'embed' => $__box['embed']])
@if($__boxFold)
        </div>
    </div>
@endif
