{{-- ตัวจัดการปุ่มลบรายการ Lab (.js-ls-delete)

     ต้อง include ไฟล์นี้จาก "หน้าที่โหลดเต็ม" เท่านั้น ห้ามไปวางใน lab-sub/table.blade.php
     เพราะตารางนั้นถูกดึงเข้ามาด้วย AJAX ในบางหน้า และเบราว์เซอร์ไม่รัน <script> ที่มากับ innerHTML

     สร้าง <form> ต่อกับ body ตอนกด เพราะตาราง Lab อยู่ในฟอร์มหลักของหน้าตรวจ
     ถ้าวาง <form> ซ้อนตรงนั้น เบราว์เซอร์จะทิ้งทิ้งแล้วปุ่มไปกดบันทึกฟอร์มหลักแทน --}}
<script>
(function () {
    if (window.__lsDeleteInit) return;
    window.__lsDeleteInit = true;

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-ls-delete');
        if (!btn || btn.disabled) return;
        e.preventDefault();
        if (!confirm('ยืนยันลบรายการตรวจนี้?')) return;

        btn.disabled = true;

        var f = document.createElement('form');
        f.method = 'POST';
        f.action = btn.dataset.url;
        f.style.display = 'none';
        f.innerHTML = '<input type="hidden" name="_method" value="DELETE">'
                    + '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                    + '<input type="hidden" name="page" value="' + (btn.dataset.page || '1') + '">';
        document.body.appendChild(f);
        f.submit();
    });
})();
</script>
