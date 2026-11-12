<?php
define('SECURE', true);
include 'config/db_connect.php';

$conn->query("TRUNCATE TABLE queues");

header("Location: index.php");
exit;