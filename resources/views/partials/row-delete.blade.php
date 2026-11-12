{{-- ปุ่มลบแถวที่อยู่ "ข้างในฟอร์มหลัก" ของหน้าตรวจ
     สร้างฟอร์ม DELETE ชั่วคราวต่อท้าย <body> แล้ว submit
     (ห้ามวาง <form> ไว้ในฟอร์มหลัก เพราะ </form> ตัวในจะไปปิดฟอร์มหลัก
      ทำให้ช่องที่อยู่ถัดไป เช่น สถานะ/วันนัด ไม่ถูกส่งตอนกด Save) --}}
<script>
(function () {
    if (window.__rowDeleteInit) return;
    window.__rowDeleteInit = true;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-row-delete');
        if (!btn) return;
        e.preventDefault();
        if (!confirm('ยืนยันการลบรายการนี้?')) return;

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.action;
        f.style.display = 'none';

        [['_token', '{{ csrf_token() }}'], ['_method', 'DELETE']].forEach(function (kv) {
            var i = document.createElement('input');
            i.type = 'hidden'; i.name = kv[0]; i.value = kv[1];
            f.appendChild(i);
        });

        document.body.appendChild(f);
        f.submit();
    });
})();
</script>
