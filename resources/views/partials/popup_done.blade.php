<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <title>บันทึกแล้ว</title>
</head>
{{-- ดู App\Http\Middleware\PopupFrame — หน้าตรวจ (iframe parent) เห็น data-popup-done แล้วปิด popup + โหลดตัวเองใหม่ --}}
<body data-popup-done="1" style="font-family: sans-serif; text-align: center; padding-top: 3rem; color: #198754;">
    ✓ บันทึกแล้ว
    <script>
        // เปิดนอก popup (แท็บเดี่ยว) = ไปหน้าตรวจตามที่ระบบตั้งใจไว้แต่แรก
        if (window.top === window) { location.replace(@json($target)); }
    </script>
</body>
</html>
