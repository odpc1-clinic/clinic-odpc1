<?php
define('SECURE', true);
include 'config/db_connect.php';
// ดึงข้อมูลคิวที่มีสถานะ 'waiting' หรือ 'called' โดยให้สถานะ 'called' ขึ้นมาก่อน
$sql = "SELECT room_number, queue_number, status, call_count, is_calling 
        FROM queues 
        WHERE status IN ('waiting', 'called') AND DATE(created_at) = CURDATE()
        ORDER BY room_number, FIELD(status, 'called') ASC, call_count DESC, queue_number";

$result = $conn->query($sql);

$rooms = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $rooms[$row['room_number']][] = $row; // เก็บข้อมูลคิวแยกตามห้อง
    }
}

// ส่งข้อมูลเป็น JSON
echo json_encode($rooms);

$conn->close();
?>
