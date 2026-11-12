<?php
define('SECURE', true);
include 'config/db_connect.php';

$queueNumber = intval($_POST['queue_number']);
$roomNumber = intval($_POST['room_number']);

$stmt = $conn->prepare("INSERT INTO queues (queue_number, room_number) VALUES (?, ?)");
$stmt->bind_param("ii", $queueNumber, $roomNumber);
$stmt->execute();

header("Location: index.php");
exit;
?>
