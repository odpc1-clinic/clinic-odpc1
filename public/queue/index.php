<?php
define('SECURE', true);
include 'config/db_connect.php';
// ดึงคิวล่าสุดจากฐานข้อมูล
$lastQueueResult = $conn->query("SELECT queue_number FROM queues ORDER BY id DESC LIMIT 1");
$lastQueue = $lastQueueResult->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรแกรมเรียกคิว</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<!-- <script src="https://code.responsivevoice.org/responsivevoice.js?key=5Q7OYjbC"></script> -->
<div class="container mt-4">
    <h1 class="text-center">โปรแกรมเรียกคิว</h1>

    <!-- ปุ่มสลับส่วน -->
    <div class="mb-4 text-center">
        <button class="btn btn-primary mx-2" onclick="showSection('both')">ห้องบัตร</button>
        <button class="btn btn-success mx-2" onclick="showSection('part2')">ห้องตรวจ</button>
    </div>

    <!-- ส่วนที่ 1 -->
    <div id="section1" class="section">
        <h2>ส่วนที่ 1: ห้องบัตร</h2>
        <!-- ส่วนเนื้อหาเดิมของ Section 1 -->
        <form id="addQueueForm" class="row g-3 mb-4">
            <div class="col-auto">
                <label for="queue_number" class="form-label">Queue Number</label>
                <input type="number" name="queue_number" id="queue_number" class="form-control" placeholder="Auto" readonly>
            </div>
            <div class="col-auto">
                <label for="room_number" class="form-label">เลือกห้องตรวจ:</label>
                <select name="room_number" id="room_number" class="form-control" required>
                    <option value="" disabled selected>เลือกห้องตรวจเพื่อออกคิว</option>
                    <option value="4">Room 4 M</option>
                    <option value="5">Room 5 F</option>
                    <option value="6">Room 6 Tx</option>
                    <option value="7">Room 7 TMC</option>
                    <option value="2">Room 2 TB</option>
                </select>
            </div>
            <div class="col-auto d-flex">
                <button 
                    type="submit" 
                    class="btn btn-primary btn-md w-100" 
                    onclick="return confirmAddQueue()">
                    Add Queue
                </button>
                <input type="text" id="last_queue" class="form-control" value="<?= isset($lastQueue['queue_number']) ? 'คิวก่อนหน้า #' . $lastQueue['queue_number'] : 'N/A'; ?>" readonly>
            </div>
        </form>
        <!-- <div class="col-auto d-flex align-items-end">
    <a href="terminal.php" class="btn btn-primary btn-md w-100" onclick="openTerminal(event)">
        <i class="fas fa-desktop"></i> เปิดหน้าจอ Terminal
    </a>
</div>

<script>
function openTerminal(e) {
    e.preventDefault(); // ป้องกันการโหลดลิงก์ตามปกติ

    const url = "terminal.php";
    const width = screen.availWidth;
    const height = screen.availHeight;

    // เปิด popup เต็มจอ ไม่มี scrollbar/toolbar
    window.open(
        url,
        "_blank",
        `width=${width},height=${height},top=0,left=0,fullscreen=yes,toolbar=no,scrollbars=no,resizable=no`
    );
}
</script> -->
    <div class="col-auto d-flex align-items-end">
    <a href="terminal.php" class="btn btn-primary btn-md w-100" target="_blank">
        <i class="fas fa-desktop"></i> เปิดหน้าจอ Terminal
    </a>
</div><br>
<h4>เลือกภาษา Print คิว</h4>
<select id="langSelect" class="form-select form-select-sm w-auto mb-3">
  <option value="th">ไทย</option>
  <option value="en">English</option>
</select>
    </div>
    <!-- ส่วนที่ 2 -->
    <div id="section2" class="section">
        <h2>ส่วนที่ 2: ห้องตรวจ</h2>
        <!-- ส่วนเนื้อหาเดิมของ Section 2 -->
        <div class="mb-4 border-box">
            <div class="form-control">
                <label for="room" class="form-label">Select Room เลือกห้องที่ท่านทำงาน</label>
                <select id="room" class="form-select" onchange="loadQueues()">
                    <option value="">All</option>
                    <option value="4">Room 4 M</option>
                    <option value="5">Room 5 F</option>
                    <option value="6">Room 6 Tx</option>
                    <option value="7">Room 7 TMC</option>
                    <option value="2">Room 2 TB</option>
                </select>
            </div>
        </div>
        <div id="queue-list" class="border-box">
            <!-- ตารางคิวจะถูกโหลดที่นี่ -->
        </div>
        <div class="mb-4 border-box">
        <h3>คิวที่จบงานแล้ว</h3>
        <div id="completed-queue-list" class="border-box">
        <!-- คิวที่จบแล้วจะแสดงที่นี่ -->
    </div>
</div>

    </div>
</div>

<script>
    // ฟังก์ชันสำหรับแสดงส่วนต่างๆ
    function showSection(section) {
        const section1 = document.getElementById('section1');
        const section2 = document.getElementById('section2');

        if (section === 'both') {
            section1.style.display = 'block';
            section2.style.display = 'block';
        } else if (section === 'part2') {
            section1.style.display = 'none';
            section2.style.display = 'block';
        }
    }

    // ตั้งค่าเริ่มต้นให้แสดงทั้งสองส่วน
    showSection('part2');
</script>
<style>
    .queue-table tr:hover {
        background-color: #f0f0f0 !important;
        transition: 0.3s;
    }
</style>

<style>

/* เปลี่ยนสีพื้นหลังของรายการคิวเมื่อชี้เมาส์ */
.list-group-item:hover {
    background-color: #f1f1f1; /* สีพื้นหลังเมื่อ hover */
}

/* เปลี่ยนสีของปุ่มเมื่อชี้เมาส์ */
.restore-btn:hover {
    background-color: #f39c12; /* สีพื้นหลังเมื่อ hover */
    color: white; /* เปลี่ยนสีข้อความ */
    border-color: #e67e22; /* เปลี่ยนสีของขอบปุ่ม */
}

    .section {
        display: none; /* ซ่อนส่วนต่าง ๆ โดยเริ่มต้น */
        margin-bottom: 20px;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px;
    }
</style>

<!-- CSS -->
<style>
        .queue-table tr:hover {
        background-color:rgb(0, 132, 255) !important;
        transition: 0.3s;
    }
    .border-box {
        border: 2px solid #ccc; /* สีและความหนาของกรอบ */
        border-radius: 10px; /* มุมโค้ง */
        padding: 15px; /* ระยะห่างด้านใน */
        margin-bottom: 20px; /* ระยะห่างด้านล่าง */
        background-color: #f9f9f9; /* สีพื้นหลัง */
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* เงา */
    }

    .form-control {
        margin-bottom: 10px; /* เพิ่มพื้นที่ด้านล่าง */
    }

    .form-select {
        border: 1px solid #888; /* สีกรอบของ select */
    }

    .btn {
        font-weight: bold;
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function confirmReset() {
    if (confirm("คุณต้องการรีเซ็ตคิวทั้งหมดหรือไม่? การกระทำนี้จะลบคิวทั้งหมด")) {
        document.getElementById("resetQueueForm").submit();
    }
}

// ฟังก์ชันโหลดคิว
function loadQueues() {
    const selectedRoom = $("#room").val();  // รับค่าห้องที่เลือก
    const url = selectedRoom ? "fetch_queues.php?room=" + selectedRoom : "fetch_queues.php"; 

    $.get(url)
        .done(function(data) {
            $("#queue-list").html(data); // อัปเดตตารางคิวปัจจุบัน
        })
        .fail(function() {
            alert("Failed to load queues. Please try again.");
        });

    // โหลดคิวที่จบไปแล้ว
    $.get("fetch_completed_queues.php")
        .done(function(data) {
            $("#completed-queue-list").html(data); // อัปเดตตารางคิวที่จบไปแล้ว
        })
        .fail(function() {
            alert("Failed to load completed queues. Please try again.");
        });
}

// ฟังก์ชันส่งต่อคิว (Transfer Queue)
$(document).on("click", ".transfer-btn", function() {
    const queueId = $(this).data("id");
    const newRoom = prompt("ระบุหมายเลขห้อง :");
    if (newRoom) {
        $.post("call_queue.php", { id: queueId, room: newRoom })
            .done(function(response) {
                alert(response); // แจ้งผลสำเร็จ
                loadQueues(); // โหลดคิวใหม่
            })
            .fail(function() {
                alert("Failed to transfer queue. Please try again.");
            });
    }
});

// ฟังก์ชันจบคิว (Complete Queue)
$(document).on("click", ".complete-btn", function() {
    const queueId = $(this).data("id");
    if (confirm("Mark this queue as completed?")) {
        $.post("call_queue.php", { id: queueId, complete: true })
            .done(function(response) {
                alert(response); // แจ้งผลสำเร็จ
                loadQueues(); // โหลดคิวใหม่
            })
            .fail(function() {
                alert("Failed to complete queue. Please try again.");
            });
    }
});
//เรียกคิวคืน
$(document).on("click", ".restore-btn", function() {
    const queueId = $(this).data("id");
    if (confirm("คุณต้องการเรียกคืนคิวนี้หรือไม่?")) {
        $.post("restore_queue.php", { id: queueId })
            .done(function(response) {
                alert(response); // แจ้งผล
                loadQueues(); // โหลดข้อมูลใหม่
            })
            .fail(function() {
                alert("Failed to restore queue. Please try again.");
            });
    }
});


// โหลดคิวครั้งแรก
loadQueues();

// ตั้งเวลาโหลดคิวใหม่ทุก 3 วินาที
setInterval(loadQueues, 1000);

// ฟังก์ชันอัปเดตคิวล่าสุด
function updateLastQueue() {
    $.get("fetch_last_queue.php", function(data) {
        // แสดงคิวล่าสุดในฟิลด์
        $("#last_queue").val(data);
    }).fail(function() {
        console.error("Failed to fetch the latest queue.");
    });
}

// ฟังก์ชันเพิ่มคิวใหม่
$("#addQueueForm").submit(function(e) {
    e.preventDefault(); // ป้องกันการรีเฟรชหน้า
    const roomNumber = $("#room_number").val();
    $.post("add_queue.php", { room_number: roomNumber })
        .done(function(response) {
            alert(response); // แจ้งผลสำเร็จ
            $("#room_number").val(""); // ล้างช่องเลือกห้อง
            updateLastQueue(); // อัปเดตคิวล่าสุด
            loadQueues(); // โหลดข้อมูลคิวใหม่
        })
        .fail(function() {
            alert("Failed to add queue. Please try again.");
        });
});

// โหลดคิวล่าสุดครั้งแรก
updateLastQueue();

// เรียกใช้ฟังก์ชัน
$(document).on("click", ".call-btn", function () {
    const queueId = $(this).data("id");

    // แสดงกล่องยืนยันการเรียกคิว
    const confirmation = confirm("คุณแน่ใจหรือไม่ว่าต้องการเรียกคิวนี้?");

    if (confirmation) {
        // หากยืนยันการเรียกคิวแล้ว ให้ดำเนินการ
        $.post("call_queue.php", { id: queueId })
            .done(function(response) {
                alert(response);

                // ดึงข้อมูลเลขคิวและเลขห้องจาก response
                const queueMatch = response.match(/Queue (\d+)/); // ดึงเลขคิว
                const roomMatch = response.match(/Room (\d+)/);   // ดึงเลขห้อง

                // ตรวจสอบว่าได้ข้อมูลเลขคิวและห้องหรือไม่
                if (queueMatch && roomMatch) {
                    const queueNumber = queueMatch[1];
                    const roomNumber = roomMatch[1];
                    // ลบการเรียกฟังก์ชัน speakQueue ออกไป
                }

                loadQueues(); // โหลดคิวใหม่
            })
            .fail(function() {
                alert("Failed to call queue. Please try again.");
            });
    } else {
        // ถ้าไม่ยืนยัน ไม่ทำอะไร
        alert("การเรียกคิวถูกยกเลิก");
    }
});


</script>

</body>
</html>
