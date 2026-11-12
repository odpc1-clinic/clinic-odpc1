<?php
define('SECURE', true);
// เริ่มการเชื่อมต่อกับ WebSocket Server
require_once __DIR__ . '/vendor/autoload.php';

use Workerman\Connection\AsyncTcpConnection;

$websocket_address = 'ws://127.0.0.1:8080'; // ที่อยู่ WebSocket server

// ฟังก์ชันส่งข้อมูลไปยัง WebSocket server
function sendDataToWebSocket($data) {
    global $websocket_address;

    // สร้างการเชื่อมต่อไปยัง WebSocket server
    $connection = new AsyncTcpConnection($websocket_address);
    $connection->send(json_encode($data)); // ส่งข้อมูลในรูปแบบ JSON
    $connection->close();
}
// การเชื่อมต่อฐานข้อมูล
include 'config/db_connect.php';

// รับค่าหมายเลขห้องจาก GET (ถ้าไม่มีห้อง ให้ใช้ห้อง 1 เป็นค่าเริ่มต้น)
$selectedRoom = isset($_GET['room']) ? intval($_GET['room']) : 1;

// สร้างเงื่อนไข SQL สำหรับการกรองห้อง
$sqlCondition = $selectedRoom === 1 ? "" : "AND room_number = $selectedRoom";

// ดึงข้อมูลคิวจากฐานข้อมูล
$sql = "SELECT *, TIMESTAMPDIFF(SECOND, created_at, NOW()) AS waiting_time FROM queues WHERE status <> 'Completed' AND DATE(created_at) = CURDATE() $sqlCondition ORDER BY id DESC";
$result = $conn->query($sql);

// แปลงผลลัพธ์จาก $result เป็น array
$queueData = [];
while ($row = $result->fetch_assoc()) {
    $queueData[] = $row;
}

// จัดเรียงสถานะ waiting ให้อยู่ด้านบน และเรียง Wait Time จากมากไปน้อยสำหรับ waiting
usort($queueData, function ($a, $b) {
    // จัดลำดับสถานะให้ 'waiting' อยู่ด้านบน
    if ($a['status'] === 'waiting' && $b['status'] !== 'waiting') {
        return -1; // ให้ $a อยู่ก่อน $b
    }
    if ($a['status'] !== 'waiting' && $b['status'] === 'waiting') {
        return 1; // ให้ $b อยู่ก่อน $a
    }

    // ถ้าทั้งคู่เป็น 'waiting' ให้เรียงตาม Wait Time จากมากไปน้อย
    if ($a['status'] === 'waiting' && $b['status'] === 'waiting') {
        $waitTimeA = isset($a['waiting_time']) ? (int)$a['waiting_time'] : 0;
        $waitTimeB = isset($b['waiting_time']) ? (int)$b['waiting_time'] : 0;
        return $waitTimeB - $waitTimeA; // เรียงจากมากไปน้อย
    }

    // ถ้าทั้งคู่ไม่ใช่ 'waiting' ให้ลำดับตามค่าดั้งเดิม
    return 0;
});

// แสดงผลตารางคิว
echo '<table class="table table-bordered queue-table">';
echo '<thead>
        <tr>
            <th class="text-center">Queue Number</th>
            <th class="text-center">Room</th>
            <th class="text-center">Status</th>
            <th class="text-center">Wait Time</th>
            <th>Action</th>
        </tr>
      </thead>';
echo '<tbody>';

foreach ($queueData as $row) {
    $statusClass = getStatusClass($row['status']); // กำหนดคลาสสีตามสถานะ
    $statusColor = getStatusColor($row['status']); // กำหนดสีพื้นหลังตามสถานะ
    $textColor = getStatusTextColor($row['status']); // กำหนดสีข้อความตามสถานะ
    $waitingTime = isset($row['waiting_time']) ? (int)$row['waiting_time'] : 0;

    // ตรวจสอบสถานะว่าเป็น 'waiting' หรือไม่
    if ($row['status'] === 'waiting') {
        // คำนวณเวลาที่รอเป็นนาที:วินาที
        $minutes = floor($waitingTime / 60);
        $seconds = $waitingTime % 60;
        $formattedWaitTime = sprintf("%02d:%02d", $minutes, $seconds);
    } else {
        // ถ้าไม่ใช่ 'waiting' ให้แสดงเป็น - หรือไม่แสดงเวลา
        $formattedWaitTime = '-';
    }

    echo '<tr>';
    echo '<td align="center" style="font-size: 2em; background-color: ' . getRoomColor($row['room_number']) . ';">'
         . $row['queue_number'] . '</td>';
    echo '<td align="center" style="background-color: ' . getRoomColor($row['room_number']) . '; font-size: 1.5em;">'
         . $row['room_number'] . '</td>';
    echo '<td align="center" class="' . $textColor . '" style="background-color: ' . $statusColor . '; color: ' . $textColor . ';">'
         . ucfirst($row['status']) . '</td>';
    echo '<td align="center">' . $formattedWaitTime . '</td>'; // แสดงเวลารอเฉพาะเมื่อสถานะเป็น 'waiting'
    echo '<td>
        <button class="btn btn-success call-btn" data-id="' . $row['id'] . '" data-wait-time="' . $waitingTime . '" disabled>Call/เรียกคิว</button>
        <button class="btn btn-primary transfer-btn" data-id="' . $row['id'] . '">Transfer/ส่งต่อ</button>
        <button class="btn btn-danger complete-btn" data-id="' . $row['id'] . '">จบงาน/ผู้ป่วยกลับบ้าน</button>
        <button class="btn btn-info print-btn" 
            data-id="' . $row['id'] . '" 
            data-number="' . $row['queue_number'] . '" 
            data-room="' . $row['room_number'] . '" 
            data-status="' . $row['status'] . '">Print/พิมพ์คิว</button>
      </td>';
    echo '</tr>';
}
echo '</tbody>';
echo '</table>';




// ปิดการเชื่อมต่อฐานข้อมูล
$conn->close();

// กำหนดเขตเวลาให้เป็นของประเทศไทย
date_default_timezone_set('Asia/Bangkok');

// กำหนดรูปแบบวันที่ DD/MM/YYYY
$currentDate = date("d/M/Y");
/**
 * ฟังก์ชันกำหนดสีพื้นหลังของเลขห้อง
 */
function getRoomColor($roomNumber) {
    switch ($roomNumber) {
        case 4:
            return '#ADD8E6'; // สีฟ้าอ่อน
        case 5:
            return '#98FB98'; // สีเขียวอ่อน
        case 6:
            return '#FFFF99'; // สีเหลืองอ่อน
        case 7:
            return '#FFFFFF'; // สีขาว
        case 2:
            return '#F7CCFF'; // สีม่วง
        default:
            return '#FFFFFF'; // สีขาว
    }
}
/**
 * ฟังก์ชันกำหนดสีพื้นหลังสำหรับสถานะ
 */
function getStatusColor($status) {
    switch ($status) {
        case 'called':
            return '#28a745'; // สีเขียว
        case 'waiting':
            return '#ffc107'; // สีส้ม
        case 'completed':
            return '#dc3545'; // สีแดง
        default:
            return '#6c757d'; // สีเทา
    }
}
/**
 * ฟังก์ชันกำหนดสีข้อความสำหรับสถานะ
 */
function getStatusTextColor($status) {
    switch ($status) {
        case 'called':
            return '#ffffff'; // ข้อความสีขาว
        case 'waiting':
            return '#000000'; // ข้อความสีดำ
        case 'completed':
            return '#ffffff'; // ข้อความสีขาว
        default:
            return '#ffffff'; // ข้อความสีขาว
    }
}
/**
 * ฟังก์ชันกำหนดคลาสสีสำหรับสถานะ
 */
function getStatusClass($status) {
    switch ($status) {
        case 'called':
            return 'text-success'; // สถานะ "เรียกแล้ว" สีเขียว
        case 'waiting':
            return 'text-warning'; // สถานะ "กำลังรอ" สีส้ม
        case 'completed':
            return 'text-danger'; // สถานะ "เสร็จแล้ว" สีแดง
        default:
            return 'text-secondary'; // สถานะอื่น สีเทา
    }
}
?>

<script>

function confirmAddQueue() {
    return confirm("ต้องการเพิ่มคิวใช่หรือไม่?");
}
 
// ฟังก์ชันคำนวณเวลารอ
function calculateWaitingTime(createAt) {
    const createdDate = new Date(createAt);
    const now = new Date();
    const diffMs = now - createdDate; // ความแตกต่างเป็นมิลลิวินาที
    const diffMinutes = Math.floor(diffMs / 60000); // แปลงเป็นนาที
    const diffHours = Math.floor(diffMinutes / 60); // แปลงเป็นชั่วโมง
    const minutes = diffMinutes % 60;

    if (diffHours > 0) {
        return `${diffHours} ชั่วโมง ${minutes} นาที`;
    } else {
        return `${minutes} นาที`;
    }
}
document.querySelectorAll('.call-btn').forEach(function (button) {
    const initialWaitTime = parseInt(button.getAttribute('data-wait-time'), 10);

    if (initialWaitTime >= 60) {
        // ถ้าเวลารอเริ่มต้นถึง 30 วิแล้ว เปิดปุ่มทันที
        button.disabled = false;
    } else {
        // คำนวณเวลาที่เหลือจนถึง 30 วินาที
        const timeToEnable = 60 - initialWaitTime;

        // นับถอยหลังเพื่อเปิดใช้งานปุ่ม
        let remainingTime = timeToEnable;

        const timer = setInterval(() => {
            if (remainingTime > 0) {
                remainingTime--;
            } else {
                clearInterval(timer);
                button.disabled = false; // เปิดใช้งานปุ่ม
                button.textContent = 'Call/เรียกคิว'; // เปลี่ยนข้อความบนปุ่มกลับ
            }
        }, 1000);

        // แสดงเวลาที่เหลือบนปุ่มระหว่างนับถอยหลัง
        button.textContent = `Call (${remainingTime}s)`;
    }

    // เพิ่ม event listener เมื่อปุ่มถูกกด
    button.addEventListener('click', function () {
        alert('เรียกคิวสำเร็จ!');
        // เพิ่มโค้ดสำหรับการเรียกใช้งาน เช่น ส่งคำขอไปยังเซิร์ฟเวอร์
    });
});


// อัปเดตเวลารอในทุกแถว
function updateWaitingTimes() {
    document.querySelectorAll('.waiting-time').forEach(cell => {
        const createdAt = cell.getAttribute('data-created-at');
        if (createdAt) {
            cell.textContent = calculateWaitingTime(createdAt);
        }
    });
}

// เรียกฟังก์ชันอัปเดตทุก 1 วินาที
setInterval(updateWaitingTimes, 3000);

// เรียกครั้งแรกเมื่อหน้าโหลด
updateWaitingTimes();
    
document.querySelectorAll('.print-btn').forEach(function (button) {
    button.addEventListener('click', function () {
        const queueNumber = this.getAttribute('data-number');
        const roomNumber = this.getAttribute('data-room');
        const lang = document.getElementById("langSelect") ? document.getElementById("langSelect").value : 'th';

        // ตั้งค่าลิงก์แบบประเมินความพึงพอใจ
        const surveyLink = "https://shorturl-ddc.moph.go.th/pCM2P"; 
        const qrUrl = "assets/Survey.png";

        const translations = {
            th: {
                queue: "คิวหมายเลข",
                room: "ห้องตรวจ",
                wait: "กรุณารอเรียกคิว",
                wait2: "สำหรับเจ้าหน้าที่",
                male: "ห้องชาย",
                female: "ห้องหญิง",
                treatment: "Treatment",
                tmc: "TMC",
                tb: " TB",
                center: "สำหรับผู้รับบริการ",
                date: "วันที่",
                survey: "แบบประเมินความพึงพอใจ\r\nผู้รับบริการต่อระบบเรียกคิวด้วยคอมพิวเตอร์"
            },
            en: {
                queue: "QUEUE",
                room: "Room",
                wait: "Please wait for your queue to be called.",
                wait2: "For Medical Staff",
                male: "Male Room",
                female: "Female Room",
                treatment: "Treatment Room",
                tmc: "TMC",
                tb: " TB",
                center: "For Service Recipients",
                date: "Date",
                survey: "Customer Satisfaction Survey Form for the Computerized Queueing System"
            }
        };

        const t = translations[lang];
        
        // กำหนด Format วันที่แบบ DD/MMM/YYYY
        const d = new Date();
        const day = String(d.getDate()).padStart(2, '0');
        const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        const month = monthNames[d.getMonth()];
        const year = d.getFullYear();
        const today = `${day}/${month}/${year}`;

        // ตั้งค่า checked เฉพาะห้องที่กำหนด
        const isMale = roomNumber === "4";
        const isTreatment = roomNumber === "6";
        const isFemale = roomNumber === "5";
        const isTmc = roomNumber === "7";
        const isTb = roomNumber === "2";
        // ส่วน checkbox บน (ให้ห้องตรวจ)
        const testCheckboxes = `
            <div class="qchecks">
                <label><input type="checkbox"> HBsAg</label>
                <label><input type="checkbox"> Anti-HCV</label>
                <label><input type="checkbox"> ตรวจ B+C แล้ว</label>
                <label><input type="checkbox"> UC</label>
                <label><input type="checkbox"> SSO</label>
                <label><input type="checkbox"> Cash</label>
                <label><input type="checkbox"> เบิกราชการ</label>
            </div>`;

        // ส่วน checkbox ล่าง (ให้คนไข้) + ตั้งค่า checked ตามเงื่อนไข
        const roomCheckboxes = `
            <div class="qchecks">
                <label><input type="checkbox" ${isMale ? "checked" : ""}> ${t.male}</label>
                <label><input type="checkbox" ${isFemale ? "checked" : ""}> ${t.female}</label>
                <label><input type="checkbox" ${isTreatment ? "checked" : ""}> ${t.treatment}</label>
                <label><input type="checkbox" ${isTmc ? "checked" : ""}> ${t.tmc}</label>
                <label><input type="checkbox" ${isTb ? "checked" : ""}> ${t.tb}</label>
            </div>`;

        // ลำดับพิมพ์กลับด้าน (QR -> คนไข้ -> ห้องตรวจ) เพราะเครื่องพิมพ์ม้วน feed ออกมากลับหัว
        // ตอนฉีกจะได้เรียงถูก: ห้องตรวจ, คนไข้, QR
        const printContent = `
        <!-- ปุ่มควบคุม (ไม่พิมพ์) อยู่บนสุด กดง่าย -->
        <div class="print-controls">
            <button class="btn btn-primary" onclick="window.print();">Print / พิมพ์</button>
            <button class="btn btn-danger" onclick="window.close();">Close</button>
        </div>

        <!-- พิมพ์ก่อน: QR ประเมินความพึงพอใจ (จะอยู่ล่างสุดตอนฉีก) -->
        <div class="qticket">
            <div class="qsurvey"><b>${t.survey}</b></div>
            <img class="qqr" src="${qrUrl}">
        </div>

        <!-- ให้คนไข้ -->
        <div class="qticket">
            <div class="qcenter">${t.center}</div>
            <div class="qbox">${t.queue} ${queueNumber}</div>
            <div class="qdate">Line: @352bavoo | ${t.date}: ${today}</div>
            <div class="qroom">${t.room}: ${roomNumber}</div>
            ${roomCheckboxes}
            <div class="qwait">${t.wait}</div>
        </div>

        <!-- พิมพ์ท้าย: ให้ห้องตรวจ (จะอยู่บนสุดตอนฉีก) -->
        <div class="qticket">
            <b class="qcopy">สำหรับห้องตรวจ</b>
            <div class="qbox">${t.queue} ${queueNumber}</div>
            <div class="qroom">${t.room}: ${roomNumber}</div>
            <div class="qwait">${t.wait2}</div>
            <div class="qdate">${t.date}: ${today}</div>
            ${testCheckboxes}
        </div>

        `;

        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <html>
            <head>
                <title>Print</title>
                <meta charset="utf-8">
                <style>
                    /* สูตรพิมพ์ Zebra ZD220: ไม่ล็อกขนาดหน้า, ชิดซ้าย, แต่ละใบสูง 4" ตรงไดรเวอร์, แยกหน้า */
                    @page { margin: 0; }
                    * { box-sizing: border-box; }
                    html, body { margin: 0; padding: 0; }
                    body { font-family: 'Sarabun','TH Sarabun New',Arial,sans-serif; color: #000; }

                    .qticket { width: 2.1in; height: 4in; margin: 0 0 0 0.1in; padding: 4px; text-align: center;
                               overflow: hidden; display: flex; flex-direction: column; align-items: center;
                               justify-content: center; page-break-after: always; break-after: page; }
                    .qticket:last-of-type { page-break-after: auto; break-after: auto; }

                    .qcopy { font-size: 10pt; font-weight: 700; border: 1px solid #000; border-radius: 3px; padding: 0 8px; }
                    .qcenter { font-size: 9pt; font-weight: bold; line-height: 1.05; }
                    .qbox { font-size: 30pt; font-weight: 800; border: 3px solid #000; border-radius: 6px;
                            padding: 2px 10px; margin: 4px 0 2px; }
                    .qroom { font-size: 16pt; font-weight: 700; }
                    .qwait { font-size: 10pt; font-weight: 700; margin-top: 2px; }
                    .qdate { font-size: 8.5pt; color: #222; }
                    .qsurvey { font-size: 10pt; margin-bottom: 4px; }
                    .qqr { width: 1.3in; height: 1.3in; }
                    .qchecks { margin-top: 3px; }
                    .qchecks label { font-size: 8pt; display: inline-block; margin: 0 3px; white-space: nowrap; }
                    .qchecks input { transform: scale(1.05); margin-right: 2px; }

                    @media print { .print-controls { display: none; } }
                    .print-controls { text-align: center; padding: 10px; position: sticky; top: 0;
                                      background: #fff; border-bottom: 1px solid #ddd; z-index: 10; }
                    .print-controls button { font-size: 1em; padding: 8px 18px; margin: 4px; cursor: pointer; }
                </style>
            </head>
            <body>
                ${printContent}
            </body>
            </html>
        `);
        printWindow.document.close();
    });
});

</script>