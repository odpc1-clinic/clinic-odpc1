<?php
define('SECURE', true);
// เชื่อมต่อฐานข้อมูล
include 'config/db_connect.php';

// ดึงคิวล่าสุดจากฐานข้อมูล
$result = $conn->query("SELECT queue_number FROM queues ORDER BY id DESC LIMIT 1");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo "คิวก่อนหน้า #" . $row['queue_number'];
} else {
    echo "ไม่มีคิวก่อนหน้า";
}

$conn->close();
?>
