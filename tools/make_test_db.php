<?php
/**
 * สร้าง/รีเฟรชฐานข้อมูลระบบทดสอบ (2569-10-19)
 *   clinic        -> clinic_test        (แล้วปิดตัวตนคนไข้ด้วย php artisan clinic:anonymize-test-db)
 *   queue_system  -> queue_system_test
 *
 * วิธีรัน (ผู้ดูแลระบบรันเอง):  D:\xampp\php\php.exe D:\xampp\clinic-test\tools\make_test_db.php
 * ฐานจริงถูกอ่านอย่างเดียว (mysqldump --single-transaction ไม่ล็อกตาราง) · ฐาน *_test ถูกลบแล้วสร้างใหม่ทุกครั้ง
 */
$env = [];
foreach (file('D:/xampp/htdocs/.env', FILE_IGNORE_NEW_LINES) as $l) {
    if (preg_match('/^([A-Z_]+)=(.*)$/', $l, $m)) { $env[$m[1]] = trim($m[2], "\"'"); }
}
$bin  = 'C:/Program Files/MySQL/MySQL Server 8.0/bin';
$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3307';
$user = $env['DB_USERNAME'] ?? 'root';
$pass = $env['DB_PASSWORD'] ?? '';
putenv('MYSQL_PWD=' . $pass);   // ไม่ใส่รหัสผ่านในบรรทัดคำสั่ง

$pairs = ['clinic' => 'clinic_test', 'queue_system' => 'queue_system_test'];
foreach ($pairs as $src => $dst) {
    if (! str_ends_with($dst, '_test')) { exit("ปลายทางต้องลงท้าย _test\n"); }
    echo "== {$src} -> {$dst}\n";
    $mysql = sprintf('"%s/mysql.exe" -h%s -P%s -u%s', $bin, $host, $port, $user);
    passthru($mysql . ' -e "DROP DATABASE IF EXISTS `' . $dst . '`; CREATE DATABASE `' . $dst . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"', $rc);
    if ($rc) { exit("สร้างฐาน {$dst} ไม่สำเร็จ\n"); }
    $dump = sprintf('"%s/mysqldump.exe" -h%s -P%s -u%s --single-transaction --routines --no-tablespaces %s', $bin, $host, $port, $user, $src);
    passthru('cmd /c "' . $dump . ' | ' . $mysql . ' ' . $dst . '"', $rc);
    if ($rc) { exit("คัดลอก {$src} ไม่สำเร็จ\n"); }
}

echo "== ปิดตัวตนคนไข้ในฐานทดสอบ\n";
chdir('D:/xampp/clinic-test');
passthru('D:\\xampp\\php\\php.exe artisan clinic:anonymize-test-db --force', $rc);
passthru('D:\\xampp\\php\\php.exe artisan config:clear');
echo $rc ? "ปิดตัวตนไม่สำเร็จ — อย่าเปิดใช้ระบบทดสอบจนกว่าจะรันผ่าน\n" : "เสร็จ\n";
