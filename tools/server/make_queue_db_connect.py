# สร้าง public/queue/config/db_connect.php บน server ใหม่ จากค่าใน .env (รหัสไม่ผ่านแชท/เครื่องอื่น)
env = {}
for l in open(".env", encoding="utf-8").read().splitlines():
    if "=" in l and not l.startswith("#"):
        k, v = l.split("=", 1)
        env[k.strip()] = v.strip().strip('"')

php = """<?php
if (!defined('SECURE')) {
    die("Direct access not allowed");
}
// server ใหม่ (Linux) — ต้องเป็น 127.0.0.1 ไม่ใช่ localhost (localhost = ต่อผ่าน socket ไม่สนพอร์ต) · ผู้ใช้ฐานคิวแยกจากฐานหลัก
$servername = "127.0.0.1";
$username = "%s";
$password = "%s";
$database = "%s";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $database, $port);
if ($conn->connect_error) {
    die("Connection failed");   // ไม่โชว์รายละเอียด error (มีชื่อผู้ใช้/โฮสต์)
}
$conn->set_charset('utf8mb4');
""" % (env["DB_QUEUE_USERNAME"], env["DB_QUEUE_PASSWORD"], env["DB_QUEUE_DATABASE"])
open("public/queue/config/db_connect.php", "w", encoding="utf-8").write(php)
print("written")
