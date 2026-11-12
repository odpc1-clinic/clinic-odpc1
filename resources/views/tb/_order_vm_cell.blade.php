{{-- คอลัมน์ "ในรายการยา · ราคา" ของกล่องสั่งยาแบบตารางเดียว (หน้าตรวจ คลินิกขอ 2569-10-14)
     ตัวแปร: $vm = ข้อมูลแถวใน visit_medicines จาก TbVisitOrder::visitOrderBoxData (null = ยังไม่ลงรายการยา) · $editLink = โชว์ปุ่มแก้
     ปุ่มลบเป็น type=button + js-row-delete (partials/row-delete สร้างฟอร์มต่อกับ body) — กล่องอยู่ในฟอร์มหลัก ห้ามมี <form> --}}
@if($vm)
    <div class="small text-nowrap">
        ลงแล้ว <b>{{ $vm['amount'] !== '' ? $vm['amount'] : '—' }}</b> {{ $vm['unit'] }}
    </div>
    <div class="small text-nowrap">
        @if($vm['price'] > 0)
            {{ number_format($vm['price'], 2) }}/หน่วย · รวม <b>{{ number_format($vm['total'], 2) }}</b>
        @else
            <span class="badge bg-secondary" title="ยังไม่ได้ตั้งราคายาตัวนี้ในคลังยา">ยังไม่ตั้งราคา</span>
        @endif
    </div>
    <div class="d-flex gap-1 mt-1">
        @if(! empty($editLink))
            <a href="{{ $vm['edit'] }}" class="btn btn-primary btn-sm py-0" title="แก้รายการยานี้"><i class="fa fa-pencil"></i></a>
        @endif
        <button type="button" class="btn btn-outline-danger btn-sm py-0 js-row-delete" data-action="{{ $vm['del'] }}"
                title="ลบยาตัวนี้ออกจากรายการยาของ visit"><i class="fa fa-trash"></i></button>
    </div>
@else
    <span class="small text-muted">ยังไม่ลงรายการยา<br>(กดบันทึกด้านล่าง)</span>
@endif
