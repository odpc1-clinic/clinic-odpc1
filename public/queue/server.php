<?php
use Workerman\Worker;
require_once __DIR__ . '/vendor/autoload.php';

// สร้าง WebSocket Server
$ws_worker = new Worker("websocket://0.0.0.0:8080");

$ws_worker->count = 4; // จำนวน process ที่ต้องการ

// เก็บการเชื่อมต่อทั้งหมด
$connections = [];

// เมื่อมี Client เชื่อมต่อ
$ws_worker->onConnect = function($connection) use ($connections) {
    echo "New connection\n";
    $connections[] = $connection;
};

// เมื่อได้รับข้อความจาก Client
$ws_worker->onMessage = function($connection, $data) {
    echo "Message received: $data\n";
    $connection->send("Server received: $data");
};

// เมื่อ Client ตัดการเชื่อมต่อ
$ws_worker->onClose = function($connection) use ($connections) {
    echo "Connection closed\n";
    $key = array_search($connection, $connections);
    if ($key !== false) {
        unset($connections[$key]);
    }
};

// ฟังก์ชันในการตรวจสอบการอัปเดตฐานข้อมูล
function checkForDatabaseUpdates() {
    // เช็คการอัปเดตฐานข้อมูล (สามารถใช้ query SQL เพื่อดึงข้อมูลใหม่)
    // สมมุติว่า fetchLatestData() คือฟังก์ชันที่ดึงข้อมูลใหม่จากฐานข้อมูล
    return fetchLatestData();  // ข้อมูลล่าสุดจากฐานข้อมูล
}

// ฟังก์ชันในการส่งข้อมูลไปยัง Client
function sendUpdatesToClients($new_data) {
    global $connections;
    foreach ($connections as $connection) {
        $connection->send(json_encode($new_data)); // ส่งข้อมูลใหม่ไปยัง Client
    }
}

// ฟังก์ชันที่จะเรียกเมื่อมีการอัปเดตฐานข้อมูล
function notifyClientsOnUpdate() {
    $new_data = checkForDatabaseUpdates();
    if ($new_data) {
        sendUpdatesToClients($new_data); // ส่งข้อมูลใหม่ไปยังทุกๆ Client
    }
}

// ใช้ Timer เพื่อตรวจสอบฐานข้อมูลทุกๆ 5 วินาที (หรือกำหนดตามที่ต้องการ)
Timer::add(5, 'notifyClientsOnUpdate');

// เริ่มเซิร์ฟเวอร์
Worker::runAll();
