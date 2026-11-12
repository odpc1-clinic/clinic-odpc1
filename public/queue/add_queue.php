<?php
define('SECURE', true);
include 'config/db_connect.php';
// ฟังก์ชันอัปเดตสถานะ
function updateQueueStatus($conn, $queueId, $status) {
    if ($status === 'called') {
        // เมื่อคิวถูกเรียก เปลี่ยนสถานะเป็น called และบันทึก call_time
        $stmt = $conn->prepare("UPDATE queues SET status = ?, call_time = NOW() WHERE id = ?");
        $stmt->bind_param("si", $status, $queueId);
    } else {
        // สำหรับสถานะอื่นๆ
        $stmt = $conn->prepare("UPDATE queues SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $queueId);
    }

    if ($stmt->execute()) {
        return true;
    } else {
        return "Error updating status: " . $stmt->error;
    }
}

// กรณีเพิ่มคิวใหม่
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_number'])) {
    // ดึงหมายเลขคิวล่าสุด เฉพาะของวันนี้ (รีเซ็ตเลขคิวใหม่ทุกวัน โดยไม่ลบข้อมูลเก่าทิ้ง)
    $result = $conn->query("SELECT MAX(queue_number) AS last_queue FROM queues WHERE DATE(created_at) = CURDATE()");
    $row = $result->fetch_assoc();
    $lastQueue = $row['last_queue'] ? $row['last_queue'] : 0;

    // กำหนดหมายเลขคิวใหม่
    $queueNumber = $lastQueue + 1;
    $roomNumber = intval($_POST['room_number']);

    // เพิ่มคิวใหม่ลงฐานข้อมูลพร้อมสถานะ waiting และบันทึกเวลาปัจจุบันใน updated_at
    $stmt = $conn->prepare("INSERT INTO queues (queue_number, room_number, status) VALUES (?, ?, 'waiting')");
    $stmt->bind_param("ii", $queueNumber, $roomNumber);

    if ($stmt->execute()) {
        echo "Queue $queueNumber added successfully!";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// กรณีอัปเดตสถานะคิว (เปลี่ยนเป็น called หรือ complete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['queue_id'])) {
    $queueId = intval($_POST['queue_id']);
    $newStatus = $_POST['update_status']; // expected values: 'waiting', 'called', 'complete'

    $result = updateQueueStatus($conn, $queueId, $newStatus);
    if ($result === true) {
        echo "Queue ID $queueId updated to $newStatus successfully!";
    } else {
        echo $result;
    }
}

// กรณีเปลี่ยนห้อง
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_room'], $_POST['queue_id'], $_POST['room_number'])) {
    $queueId = intval($_POST['queue_id']);
    $newRoom = intval($_POST['room_number']);

    // เปลี่ยนห้องและตั้งสถานะเป็น waiting
    $stmt = $conn->prepare("UPDATE queues SET room_number = ?, status = 'waiting' WHERE id = ?");
    $stmt->bind_param("ii", $newRoom, $queueId);

    if ($stmt->execute()) {
        echo "Queue ID $queueId moved to room $newRoom and status reset to waiting.";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>
