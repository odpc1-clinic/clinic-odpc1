{{-- ปุ่ม "สั่งแล็บ" เปิดตัวเลือกแคตตาล็อกรวม (2569-10-02) — ปุ่มอย่างเดียว
     หน้าที่ใช้ต้อง include partials/lab_catalog_picker + partials/lab_catalog_picker_js เองหนึ่งครั้ง
     (ปุ่มนี้อยู่ในกล่องสรุปบัตร TB 01 ที่ถูกสลับด้วย innerHTML ถ้า popup ติดมาด้วยจะได้ popup ซ้ำ id เดียวกัน)
     ตัวแปร: $visit (null = ปิดปุ่ม) · $context ('opd' หน้าตรวจ / 'tb01' บัตร) · $tbReg (id บัตร TB — ให้เซิร์ฟเวอร์เช็คว่าเป็น visit วันนี้)
             $disabledTitle (ข้อความตอนปิดปุ่ม) · $btnClass
     ⚠️ ไม่มี <form> — ใช้ข้างในฟอร์มหลักของหน้าตรวจได้ (popup ถูกย้ายไปต่อกับ body ด้วยสคริปต์) --}}
<button type="button" class="btn {{ $btnClass ?? 'btn-primary' }} js-lcat-open" data-bs-toggle="modal" data-bs-target="#labCatalogModal"
        @if($visit)
            data-picker-url="{{ route('lab-catalog.picker', $visit->id) }}"
            data-order-url="{{ route('lab-catalog.order', $visit->id) }}"
            data-context="{{ $context ?? '' }}"
            data-page="{{ request('page', 1) }}"
            @if(! empty($scope)) data-scope="{{ $scope }}" @endif
            @if(! empty($tbReg)) data-tb-reg="{{ $tbReg }}" @endif
        @else
            disabled title="{{ $disabledTitle ?? 'ลงทะเบียนเปิด visit ก่อน' }}"
        @endif>
    <i class="fa fa-flask me-1"></i> {{ $label ?? 'สั่งแล็บ' }}
</button>
