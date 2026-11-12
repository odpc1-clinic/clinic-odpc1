setInterval(() => {
    fetch('server.php') // ดึงข้อมูลจากเซิร์ฟเวอร์
        .then(response => response.json())
        .then(data => postMessage(data)); // ส่งข้อมูลไปให้ main script
}, 3000); // อัปเดตทุก 3 วินาที
