{{-- ปุ่ม/ป้าย "สังเกตอาการ 30 นาที" — ใช้ร่วมกันทุกหน้า (ตารางห้อง, โซนวัคซีน TMC, แท็บยา)
     ตัวนาฬิกาจริงอยู่ที่ partials/obs_timer (โหลดใน layout) ที่นี่เป็นแค่หน้าตา
     JS กลางจะสลับปุ่ม <-> ป้ายเวลาให้เอง ไม่ต้องเขียน handler ซ้ำในแต่ละหน้า

     ตัวแปรที่ต้องส่ง:
       $obsPatientId  - id คนไข้ (ตัวผูกนาฬิกา)
       $obsVisitId    - id visit (ใช้ทำลิงก์ตอนป้ายลอยเด้ง)
       $obsName       - ชื่อที่จะโชว์ในป้ายเตือน
       $obsBlock      - true = ปุ่มเต็มความกว้าง (หน้า visit) · false/ไม่ส่ง = ปุ่มเล็กในตาราง --}}
@php
    $__obsBlock = $obsBlock ?? false;
    $__obsBtnCls = $__obsBlock ? 'btn btn-outline-primary' : 'btn btn-outline-primary btn-sm py-0 px-2';
@endphp
<div class="obs-cell {{ $__obsBlock ? 'd-grid mb-2' : 'mt-1' }}"
     data-pid="{{ $obsPatientId }}"
     data-vid="{{ $obsVisitId }}"
     data-name="{{ $obsName }}">
    <button type="button" class="{{ $__obsBtnCls }} obs-start">
        <i class="fa-solid fa-stopwatch me-1"></i>
        {{ $__obsBlock ? 'เริ่มจับเวลาสังเกตอาการ 30 นาที' : 'สังเกตอาการ 30 น.' }}
    </button>
    <span class="obs-live d-none align-items-center justify-content-center gap-2">
        <span class="badge obs-badge {{ $__obsBlock ? 'fs-6 py-2 px-3' : '' }}"></span>
        <a href="#" class="obs-stop text-decoration-none text-muted {{ $__obsBlock ? '' : 'small' }}"
           title="ยกเลิกการจับเวลา">&times;</a>
    </span>
</div>
