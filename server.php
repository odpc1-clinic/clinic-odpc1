<?php
require __DIR__ . '/vendor/autoload.php'; // ตรวจสอบให้แน่ใจว่า autoload.php อยู่ตรงนี้

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\App;

class WebSocketServer implements MessageComponentInterface {
    protected $clients;

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message from {$from->resourceId}: $msg\n";

        // ส่งข้อความกลับไปหาผู้ส่ง
        foreach ($this->clients as $client) {
            if ($client !== $from) {
                $client->send("Client {$from->resourceId}: $msg");
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
}

// รัน WebSocket Server บนพอร์ต 8080
$server = new App('localhost', 8080);
$server->route('/chat', new WebSocketServer, ['*']);
$server->run();
