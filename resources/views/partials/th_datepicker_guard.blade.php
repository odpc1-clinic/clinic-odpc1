<script>
// ผูกปฏิทิน พ.ศ. ให้ช่องวันที่ — รอจนไฟล์ภาษา th-th ของ bootstrap-datepicker โหลดเสร็จก่อนค่อยผูก (2569-10-02)
// ไฟล์ปฏิทินโหลดท้ายหน้า ถ้าผูกก่อน (สคริปต์ที่รันระหว่างแปลง HTML) จะได้ปฏิทินผิดตัว/ไม่มีภาษาไทย
// วันที่ที่เลือกออกมาเป็น mm/dd/ปี ค.ศ. แล้วบันทึกเพี้ยน — เช็ค dates['th-th'] แทนแค่ว่ามี .datepicker
// แยกเป็นไฟล์ (2569-10-02) เพราะบัตร TB 01 ก็ใช้ด้วย (ช่องวันที่ในกล่องสั่งตรวจครั้งนี้)
window.bindThDatepicker = window.bindThDatepicker || function (target, opts, tries) {
    tries = tries || 0;
    var ready = window.jQuery && jQuery.fn.datepicker && (jQuery.fn.datepicker.dates || {})['th-th'];
    if (!ready) {
        if (tries < 150) { setTimeout(function () { window.bindThDatepicker(target, opts, tries + 1); }, 200); }
        return;
    }
    jQuery(target).datepicker(jQuery.extend({ language: 'th-th' }, opts || {}));
};
</script>
