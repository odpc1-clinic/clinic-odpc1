{{--
    ล็อกวันหยุดในปฏิทินเลือกวันนัด

    ใช้คู่กับ bootstrap-datepicker: เรียก holidayLockOptions() แล้วส่งผลลัพธ์เข้า .datepicker(...)
    เช่น  $('#appointment').datepicker(holidayLockOptions({ language: 'th-th' }));

    ทำไมต้องมีไฟล์นี้แทนที่จะเขียนซ้ำในแต่ละหน้า: หน้าไหนพลาดไปหน้านั้นจะนัดวันหยุดได้เงียบๆ
    แล้วระบบจะส่ง LINE เตือนคนไข้ให้มาวันที่คลินิกปิด ซึ่งไม่มีอะไรฟ้องจนกว่าคนไข้จะมาถึงหน้าประตู
--}}
@once
@php
    // ดึงเองตรงนี้ ไม่ต้องให้ทุก controller ส่งมาให้ — controller ไหนลืมส่ง หน้านั้นจะล็อกไม่ทำงาน
    // แปลงคีย์จาก พ.ศ. เป็น ค.ศ. เพราะ datepicker คิดภายในเป็น ค.ศ. เสมอ
    // (ตัวแปลงปีไทยของ bootstrap-datepicker ทำงานแค่ตอนแสดงผลเท่านั้น)
    $__holidayMap = [];
    foreach (\App\Models\Holiday::closedMap() as $__be => $__name) {
        $__holidayMap[\App\Utillity::BE2AD($__be)] = $__name;
    }
@endphp
<script>
(function () {
    // แผนที่วันหยุด: 'ค.ศ.-ดด-วว' => 'ชื่อวันหยุด'
    window.CLINIC_HOLIDAYS = @json($__holidayMap);

    function ymd(d) {
        return d.getUTCFullYear() + '-'
             + String(d.getUTCMonth() + 1).padStart(2, '0') + '-'
             + String(d.getUTCDate()).padStart(2, '0');
    }

    window.holidayName = function (date) {
        return window.CLINIC_HOLIDAYS[ymd(date)] || null;
    };

    /**
     * ใส่ beforeShowDay ให้ options ที่ส่งมา แล้วคืน options กลับไป
     * ถ้าหน้านั้นมี beforeShowDay ของตัวเองอยู่แล้ว จะเรียกของเดิมต่อ ไม่ทับทิ้ง
     */
    window.holidayLockOptions = function (opts) {
        opts = opts || {};
        var prev = opts.beforeShowDay;

        opts.beforeShowDay = function (date) {
            var base = prev ? prev.call(this, date) : undefined;
            if (typeof base === 'boolean') base = { enabled: base };
            else if (typeof base === 'string') base = { classes: base };
            else base = base || {};

            if (base.enabled === false) return base;      // ของเดิมปิดไปแล้ว ไม่ต้องทำอะไรต่อ

            var dow = date.getUTCDay();
            if (dow === 0 || dow === 6) {
                return { enabled: false, classes: 'clinic-closed', tooltip: 'คลินิกปิด (เสาร์-อาทิตย์)' };
            }

            var name = window.holidayName(date);
            if (name) {
                return { enabled: false, classes: 'clinic-holiday', tooltip: 'คลินิกปิด — ' + name };
            }
            return base;
        };
        return opts;
    };
})();
</script>
<style>
    /* วันที่กดไม่ได้ ต้องดูออกว่า "ปิด" ไม่ใช่ "จอค้าง" — สีเทาจางอย่างเดียวไม่พอ */
    .datepicker td.clinic-holiday.disabled,
    .datepicker td.clinic-holiday.disabled:hover {
        background: #ffe3e3;
        color: #c92a2a !important;
        text-decoration: line-through;
        cursor: not-allowed;
    }
    .datepicker td.clinic-closed.disabled,
    .datepicker td.clinic-closed.disabled:hover {
        color: #adb5bd !important;
        cursor: not-allowed;
    }
</style>
@endonce
