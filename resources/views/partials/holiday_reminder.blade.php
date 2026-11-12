{{--
    เตือนให้เพิ่มวันหยุดราชการของปีถัดไป

    ปฏิทินนัดหมายล็อกจากตาราง holidays อย่างเดียว ถ้าไม่มีข้อมูลปีหน้า วันหยุดปีหน้าจะ "กดเลือกได้"
    ทั้งหมดโดยไม่มีอะไรฟ้อง — และคนที่นัดข้ามปีก็คือคนที่นัดล่วงหน้า 3-6 เดือนซึ่งมีอยู่จริง
    จึงต้องมีคนมาบอก ไม่ใช่รอให้ไปเจอเอาตอนคนไข้มาถึงหน้าประตู
--}}
@auth
@php
    $__beThisYear = (int) date('Y') + 543;
    $__beNextYear = $__beThisYear + 1;
    $__month      = (int) date('n');

    // วันหยุดในอนาคตที่ยังเหลืออยู่ (เทียบเป็นสตริง พ.ศ. ได้ตรงๆ เพราะรูปแบบ Y-m-d เรียงตามเวลาอยู่แล้ว)
    $__todayBE     = \App\Utillity::AD2BE(date('Y-m-d'));
    $__futureCount = \Illuminate\Support\Facades\Cache::remember(
        'holiday_future_count_' . $__todayBE, now()->addHours(6),
        fn () => \App\Models\Holiday::where('holiday_date', '>=', $__todayBE)->count()
    );
    $__nextYearCount = \Illuminate\Support\Facades\Cache::remember(
        'holiday_count_' . $__beNextYear, now()->addHours(6),
        fn () => \App\Models\Holiday::where('holiday_date', 'like', $__beNextYear . '-%')->count()
    );

    // เตือนตั้งแต่พฤศจิกายน ถ้ายังไม่ได้ใส่ปีหน้า · หรือเตือนทุกเดือนถ้าของหมดเกลี้ยงแล้ว
    $__warnNextYear = $__month >= 11 && $__nextYearCount === 0;
    $__warnEmpty    = $__futureCount === 0;
@endphp

@if ($__warnEmpty || $__warnNextYear)
  <div class="alert {{ $__warnEmpty ? 'alert-danger' : 'alert-warning' }} d-flex align-items-start gap-2">
    <i class="fa-solid fa-calendar-xmark mt-1"></i>
    <div class="flex-grow-1">
      @if ($__warnEmpty)
        <b>ไม่มีวันหยุดราชการในระบบเลยสักวัน</b> —
        ตอนนี้ปฏิทินนัดหมายล็อกแค่เสาร์-อาทิตย์ วันหยุดอื่นยังกดนัดได้หมด
      @else
        <b>อย่าลืมเพิ่มวันหยุดราชการปี {{ $__beNextYear }}</b> —
        ยังไม่มีสักวันในระบบ ถ้ามีคนนัดข้ามปีไว้ ปฏิทินจะปล่อยให้เลือกวันหยุดได้เลย
      @endif
      <a href="{{ route('holiday.index', ['year' => $__warnEmpty ? $__beThisYear : $__beNextYear]) }}"
         class="alert-link ms-1">เข้าไปเพิ่มที่นี่</a>
    </div>
  </div>
@endif
@endauth
