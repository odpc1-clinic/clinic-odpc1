<?php
define('SECURE', true);
include 'config/db_connect.php';

// ดึงคิวที่จบไปแล้ว เฉพาะของวันนี้ (ไม่เอาของวันก่อนๆ มาปนในลิสต์)
$sql = "SELECT * FROM queues WHERE status = 'completed' AND DATE(created_at) = CURDATE() ORDER BY id DESC";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<ul class='list-group'>";
    while ($row = $result->fetch_assoc()) {
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
        echo "Queue #{$row['queue_number']} - Room {$row['room_number']}";
        echo "<button class='btn btn-warning btn-sm restore-btn' data-id='{$row['id']}'>เรียกคืน</button>";
        echo "</li>";
    }
    echo "</ul>";
} else {
    echo "<p class='text-muted'>ไม่มีคิวที่จบงาน</p>";
}

$conn->close();
?>
