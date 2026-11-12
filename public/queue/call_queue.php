<?php
define('SECURE', true);
include 'config/db_connect.php';

$queueId = intval($_POST['id']);

$stmt = null;

    try {
    // กรณีเปลี่ยนห้อง
    // กรณีเปลี่ยนห้อง
if (isset($_POST['room'])) {
    $newRoom = intval($_POST['room']);
    // เปลี่ยนห้อง, ตั้งสถานะเป็น 'waiting', และรีเซ็ต is_calling = 0
    // created_at = วันออกบัตรด้วย (add_queue.php ใช้หาเลขคิวล่าสุดของวันนี้) — ตั้งเวลาใหม่เฉพาะคิวของวันนี้
    // คิวเมื่อวานถ้าถูกตั้งเป็นวันนี้ ห้องบัตรจะออกเลขต่อจากเมื่อวาน (2569-10-29)
    $stmt = $conn->prepare("UPDATE queues SET room_number = ?, status = 'waiting', is_calling = 0, created_at = IF(DATE(created_at) = CURDATE(), NOW(), created_at) WHERE id = ?");
    $stmt->bind_param("ii", $newRoom, $queueId);

    if ($stmt->execute()) {
        echo "Queue $queueId transferred to Room $newRoom! Status reset to 'waiting'.";
    } else {
        echo "Failed to transfer queue.";
    }
}
// กรณีจบงาน (Completed)
elseif (isset($_POST['complete'])) {
    // ตั้งสถานะเป็น completed และรีเซ็ต is_calling = 0 ด้วย
    $stmt = $conn->prepare("UPDATE queues SET status = 'completed', is_calling = 0 WHERE id = ?");
    $stmt->bind_param("i", $queueId);

    if ($stmt->execute()) {
        echo "Queue $queueId completed successfully!";
    } else {
        throw new Exception("Failed to complete queue.");
    }

    } else {
        // ดึง room_number ของคิวที่เรียกก่อน เพื่อรู้ห้อง
        $stmt = $conn->prepare("SELECT room_number FROM queues WHERE id = ?");
        $stmt->bind_param("i", $queueId);
        $stmt->execute();
        $stmt->bind_result($roomNumber);
        if (!$stmt->fetch()) {
            throw new Exception("Queue not found.");
        }
        $stmt->close();

        // ปิด is_calling ของคิวที่กำลังเรียกในห้องนั้นก่อน (is_calling = 1)
        $stmt = $conn->prepare("UPDATE queues SET is_calling = 0 WHERE room_number = ? AND is_calling = 1");
        $stmt->bind_param("i", $roomNumber);
        $stmt->execute();
        $stmt->close();

        // อัปเดตคิวนี้ให้ status = 'called', call_count เพิ่ม 1, และ is_calling = 1
        $stmt = $conn->prepare("UPDATE queues SET status = 'called', call_count = call_count + 1, is_calling = 1 WHERE id = ?");
        $stmt->bind_param("i", $queueId);
        if ($stmt->execute()) {
            $stmt->close();

            // ดึงข้อมูลคิวใหม่ที่ถูกเรียก
            $stmt = $conn->prepare("SELECT queue_number, room_number, call_count FROM queues WHERE id = ?");
            $stmt->bind_param("i", $queueId);
            $stmt->execute();
            $stmt->bind_result($queueNumber, $roomNumber, $callCount);

            if ($stmt->fetch()) {
                echo "Queue $queueNumber is being called! Room $roomNumber. Call Count: $callCount";
            } else {
                throw new Exception("Queue not found.");
            }
        } else {
            throw new Exception("Failed to call queue.");
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
} finally {
    if ($stmt) {
        $stmt->close();
    }
    $conn->close();
}
?>
