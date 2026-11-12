<?php
define('SECURE', true);
include 'config/db_connect.php';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $queueId = $_POST['id'];

    // อัปเดตสถานะคิวให้กลับมาใช้งาน
    $sql = "UPDATE queues SET status = 'waiting' WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $queueId);
    
    if ($stmt->execute()) {
        echo "คิวถูกเรียกคืนเรียบร้อยแล้ว!";
    } else {
        echo "เกิดข้อผิดพลาดในการเรียกคืนคิว!";
    }

    $stmt->close();
}

$conn->close();
?>
