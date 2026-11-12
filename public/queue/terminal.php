<?php
// เวอร์ชันไฟล์เสียง = เวลาแก้ไขล่าสุดของไฟล์ .mp3 ในโฟลเดอร์เสียง
// ใช้ต่อท้าย URL เสียง (?v=) เพื่อบังคับให้เบราว์เซอร์โหลดใหม่เมื่ออัดทับไฟล์ (กัน cache เล่นเสียงเก่า)
$soundVer = 0;
foreach (array_merge(
    glob(__DIR__ . '/assets/sounds/*.mp3') ?: [],
    glob(__DIR__ . '/assets/sounds/th/*.mp3') ?: [],
    glob(__DIR__ . '/assets/sounds/en/*.mp3') ?: []
) as $f) {
    $m = @filemtime($f);
    if ($m && $m > $soundVer) $soundVer = $m;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal Display (Full Effects + Sound)</title>
    <!-- jQuery -->
    <script src="<?php echo ! empty($__muteSound) ? 'assets/js/jquery-3.6.0.min.js' : 'https://code.jquery.com/jquery-3.6.0.min.js'; ?>"></script>
    
    <style>
        /* --- ฟอนต์และสไตล์พื้นฐาน --- */
        @font-face {
            font-family: 'Noto Sans Thai';
            src: url('assets/fonts/NotoSansThai-Regular.woff2') format('woff2'),
                 url('assets/fonts/NotoSansThai-Regular.woff') format('woff');
        }
        body {
            font-family: "Noto Sans Thai", sans-serif;
            background: linear-gradient(135deg, #1e3c72, rgb(2, 27, 71));
            color: #fff;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 20px;
            overflow: hidden;
        }
        h1, h2, h3, h4 { margin: 0; padding: 0; }
        h3 { font-size: 2em; font-weight: 400; }
        h4 { font-size: 1.2em; font-weight: 300; }

        /* --- Layout หลัก --- */
        .main-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }

        /* --- ส่วน Header --- */
        .header-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            flex-shrink: 0;
        }
        .logo-container img {
            height: 95px;
            filter: drop-shadow(0px 0px 8px rgba(255, 255, 255, 0.6));
        }
        .text-container {
            text-align: left;
        }

        /* --- ส่วนแสดงห้อง --- */
        #room-container {
            background-color: rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: row;
            justify-content: center;
            border-radius: 20px;
            padding: 20px;
            gap: 20px;
            flex-grow: 1;
            overflow-x: auto;
        }
        .room {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 14px;
            /* แบ่งพื้นที่เท่ากันอัตโนมัติ พอดีจอเสมอไม่ว่ากี่ห้อง (กันล้นเมื่อเพิ่มห้อง) */
            flex: 1 1 0;
            min-width: 200px;
            max-width: 340px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
        }
        .room h2, .room h3 {
            font-size: 1.6em;
            margin-bottom: 15px;
        }
        .queue-list {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
            overflow-y: auto;
            scrollbar-width: none;
        }
        .queue-list::-webkit-scrollbar {
            display: none;
        }
        .queue {
            display: flex;
            justify-content: space-between;
            padding: 12px;
            border-radius: 8px;
            background-color: #2a2a2a;
            font-size: 1.2em;
        }
        .queue.empty {
            justify-content: center;
            align-items: center;
            height: 100%;
            font-style: italic;
            opacity: 0.7;
        }
        .queue.waiting { background-color: #c24b4b; }
        .queue.called { background-color: #838383; color: #000; }
/* --- คิวกำลังเรียก (Blinking Animation) --- */

/* Style for the 'Calling' queue item */
.blinking-calling {
    padding: 1rem;
    border-radius: 12px;
    font-weight: bold;
    text-align: center;
    border: 5px solid; /* Border color will be set by animation */
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    margin-bottom: 8px;
    transition: all 0.2s ease-in-out;
    /* This line applies the new animation */
    animation: greenWhiteBlink 1s infinite step-end;
}

/* Style for the queue number text inside the blinking box - SIZE INCREASED */
.blinking-calling span:first-child {
    font-size: 3.2em; /* <-- เพิ่มขนาดตัวอักษร */
}

/* Style for the status text ('Calling / กำลังเรียก') */
.blinking-calling span:last-child {
    font-size: 2em;
    opacity: 0.9;
}

/* Keyframes for the 'greenWhiteBlink' animation */
@keyframes greenWhiteBlink {
    0%, 49% {
        background-color: #00c853; /* Green background */
        color: #fff; /* White text */
        border-color: #00c853;
    }
    50%, 100% {
        background-color: #ffffff; /* White background */
        color: #1a1a1a; /* Dark text for contrast */
        border-color: #ffffff;
    }
}
        
        /* --- ส่วนล่าง (Footer) --- */
        .footer-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 50px;
            width: 100%;
            flex-shrink: 0;
            padding: 10px 0;
        }
        .time-display {
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 15px;
            padding: 15px 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            text-align: center;
        }
        #clock-container { font-size: 4.5rem; }
        #date-container { font-size: 2rem; }

        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .qr-code-container img {
            height: 120px;
            border-radius: 12px;
            background: white;
            padding: 8px;
        }
        .qr-code-container p {
            font-size: 1.5em;
            font-weight: bold;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.5);
        }

        /* --- ข้อความวิ่ง --- */
        .scrolling-text {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background-color: rgba(0,0,0,0.5);
            white-space: nowrap;
            overflow: hidden;
            box-sizing: border-box;
            color: #fff;
            font-size: 2rem;
            font-weight: bold;
            padding: 10px 0;
        }
        .scrolling-text span {
            display: inline-block;
            padding-left: 100%;
            animation: scroll 60s linear infinite;
        }
        @keyframes scroll {
            to { transform: translateX(-100%); }
        }

        /* --- Modal เรียกคิว --- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: white;
            border-radius: 15px;
            padding: 50px;
            text-align: center;
            color: #000;
        }
        .modal-content h1 { font-size: 10rem; font-weight: bold; }
        .modal-content h2 { font-size: 6rem; font-weight: normal; }
        #modal-queue-number { color: green; }
        #modal-room-number { color: red; }

        /* --- หน้าจอเริ่มต้น --- */
        #start-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #1e3c72;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            cursor: pointer;
            text-align: center;
        }
        
    </style>
<?php if (! empty($__muteSound)): ?>
    <!-- จอทีวี (terminal2.php): ตัดเอฟเฟกต์ที่กินเครื่องออก — ทีวีช้า (คลินิกแจ้ง 2569-10-28)
         ไม่กะพริบ · ไม่มีข้อความวิ่ง · ไม่มีเงา/เบลอ/ไล่สี · นาฬิกาเดินทีละนาที -->
    <style>
        body { background: #10306a !important; }
        .logo-container img { filter: none !important; }
        * { text-shadow: none !important; box-shadow: none !important; transition: none !important; }
        /* คิวที่กำลังเรียกกะพริบกลับมา (คลินิกขอ 2569-11-02 ให้รู้ว่าเรียกอยู่) — แบบเบาเครื่อง:
           สลับสีพื้นทีละจังหวะ (step-end ไม่มีเฟด/เงา) ช้ากว่าจอมีเสียงนิดหน่อย ทีวีวาดใหม่แค่กล่องเดียว */
        .blinking-calling { animation: tvCallBlink 1.2s infinite step-end !important; }
        @keyframes tvCallBlink {
            0%, 49%   { background-color: #00c853; color: #fff;    border-color: #ffeb3b; }
            50%, 100% { background-color: #ffeb3b; color: #10306a; border-color: #00c853; }
        }
        .time-display { backdrop-filter: none !important; background: rgba(255, 255, 255, 0.08) !important; }
        .scrolling-text span { animation: none !important; padding-left: 0 !important; display: block !important;
                               overflow: hidden; text-overflow: ellipsis; text-align: center; }
        #start-screen { display: none !important; }
    </style>
<?php endif; ?>
</head>
<body>

    <div id="start-screen">
        <div>
            <h1>ระบบแสดงผลคิว</h1>
            <h2>กรุณาแตะที่หน้าจอเพื่อเริ่มต้น</h2>
            <h3>Please tap the screen to start</h3>
        </div>
    </div>

    <div class="main-container">
        <!-- Header -->
        <div class="header-container">
            <div class="logo-container">
                <img src="assets/logo-กรม-2566.png" alt="Logo 1">
                <img src="assets/50 ปี-01.png" alt="Logo 2">
            </div>
            <div class="text-container">
                <h3>ศูนย์บริการเวชศาสตร์ป้องกัน</h3>
                <h3>Preventive Medicine Service Center</h3>
                <h4>สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ | Office of Disease Prevention and Control 1 Chiang Mai</h4>
            </div>
        </div>

        <!-- บล็อกห้อง -->
        <div id="room-container">
            <div class="room" data-room-number="4">
                <h2>Room 4 Male<br> ห้องตรวจ 4 ชาย</h2>
                <div class="queue-list"></div>
            </div>
            <div class="room" data-room-number="5">
                <h2>Room 5 Female<br> ห้องตรวจ 5 หญิง</h2>
                <div class="queue-list"></div>
            </div>
            <div class="room" data-room-number="6">
                <h3>Room 6 Tx<br> ห้องตรวจ 6 เจาะเลือด/ฉีดยา</h3>
                <div class="queue-list"></div>
            </div>
            <div class="room" data-room-number="7">
                <h2>Room 7 TMC<br> ห้องตรวจ 7 TMC</h2>
                <div class="queue-list"></div>
            </div>
            <div class="room" data-room-number="2">
                <h2>Room 2 TB<br> ห้องตรวจ 2 วัณโรค</h2>
                <div class="queue-list"></div>
            </div>
            <div class="room" data-room-number="10">
                <h2>Pharmacy / Cashier<br> ห้องยา / การเงิน</h2>
                <div class="queue-list"></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-container">
            <div class="time-display">
                <div id="clock-container">00:00:00</div>
                <div id="date-container">Friday, 12 September 2025</div>
            </div>
            <div class="qr-code-container">
                <img src="assets/@352bavoo.png" alt="Line QR Code">
                <p>แอดไลน์เพิ่มเพื่อน</p>
            </div>
        </div>
    </div>
    
    <!-- Text Ticker -->
    <div class="scrolling-text">
        <span>ศูนย์บริการเวชศาสตร์ป้องกัน | Preventive Medicine Service Center | โทรศัพท์/tel: 053-140774 ถึง/to 6 | Line: @352bavoo</span>
    </div>

    <!-- Modal -->
    <div id="queue-modal" class="modal-overlay">
        <div class="modal-content">
            <div class="logo-container">
                <img src="assets/logo-กรม-2566.png" alt="Logo 1">
                <img src="assets/50 ปี-01.png" alt="Logo 2">
            </div>
            <h1 id="modal-queue-number">Queue 1</h1>
            <h2 id="modal-room-number">Room 1</h2>
        </div>
    </div>

<script>
// เวอร์ชันไฟล์เสียง (ต่อท้าย ?v= ทุกไฟล์เสียง) — เปลี่ยนเองเมื่อมีการอัดทับ mp3 ใหม่ กัน browser เล่นของเก่า
const SOUND_VER = <?php echo $soundVer; ?>;
// terminal2.php (จอทีวี) = ไม่มีเสียง · terminal.php = มีเสียงเหมือนเดิม
const MUTE_SOUND = <?php echo ! empty($__muteSound) ? 'true' : 'false'; ?>;
$(document).ready(function() {

    // ==========================================================
    //  Start Screen Handler
    // ==========================================================
    $('#start-screen').on('click', function() {
        const elem = document.documentElement;
        if (elem.requestFullscreen) {
            elem.requestFullscreen().catch(err => console.error(`Fullscreen error: ${err.message}`));
        }
        const silentAudio = new Audio('data:audio/mp3;base64,SUQzBAAAAAABEVRYWFgAAAAtAAADY29tbWVudABCaWdTb3VuZEJhbmsuY29tIC8gTGFTb25vdGhlcXVlLm9yZwBURU5DAAAAHQAAA1N3aXRjaCBQbHVzIMKpIE5DSCBTb2Z0d2FyZQBUSVQyAAAABgAAAzIyMzUAVFNTRQAAAA8AAANMYXZmNTcuODMuMTAwZQ==');
        silentAudio.play().catch(e => console.log("User interaction registered."));
        
        $(this).hide();
    });

    let isFirstLoad = true;
    let initialCallCounts = {};
    let lastCallCounts = {};

    // =================================================================
    //  Sound System (Stable Async/Await Version)
    // =================================================================
    const thaiAnnouncementSpeed = 1.1;
    const englishAnnouncementSpeed = 1.0;
    const announcementLanguage = 'both';
    let soundQueue = [];
    let isSoundPlaying = false;

    function playSound(queueNumber, roomNumber) {
        if (MUTE_SOUND) return;   // จอทีวีไม่มีเสียง
        soundQueue.push({ queueNumber, roomNumber });
        if (!isSoundPlaying) {
            processSoundQueue();
        }
    }

    async function processSoundQueue() {
        if (soundQueue.length === 0 || isSoundPlaying) return;
        isSoundPlaying = true;
        
        while (soundQueue.length > 0) {
            const { queueNumber, roomNumber } = soundQueue.shift();
            const audioPlaylist = createAnnouncementPlaylist(queueNumber, roomNumber);
            
            showQueueModal(queueNumber, roomNumber);
            await playPlaylistSequentially(audioPlaylist);
            hideQueueModal();
        }
        
        isSoundPlaying = false;
    }

    function createAnnouncementPlaylist(queueNumber, roomNumber) {
        const playlist = [];
        playlist.push({ file: 'assets/sounds/notification.mp3', lang: 'th' });
        if (announcementLanguage === 'th' || announcementLanguage === 'both') {
            playlist.push(...buildLanguageSegment(queueNumber, roomNumber, 'th'));
        }
        if (announcementLanguage === 'en' || announcementLanguage === 'both') {
            playlist.push(...buildLanguageSegment(queueNumber, roomNumber, 'en'));
        }
        return playlist;
    }

    function buildLanguageSegment(queueNumber, roomNumber, lang) {
        const prefix = lang === 'th' ? 'th_prefix.mp3' : 'en_prefix.mp3';
        const infix = lang === 'th' ? 'th_infix.mp3' : 'en_infix.mp3';
        const numberWord = lang === 'th' ? 'th_number.mp3' : 'en_prefix.mp3';

        return [
            { file: `assets/sounds/${prefix}`, lang: lang },
            ...convertNumberToSpeechFiles(queueNumber, lang),
            { file: `assets/sounds/${numberWord}`, lang: lang },
            ...convertNumberToSpeechFiles(queueNumber, lang),
            { file: `assets/sounds/${infix}`, lang: lang },
            ...convertNumberToSpeechFiles(roomNumber, lang)
        ];
    }
    
    async function playPlaylistSequentially(playlist) {
        for (const soundObject of playlist) {
            try {
                await playSoundFile(soundObject);
            } catch (error) {
                console.error(error.message);
            }
        }
    }

    function playSoundFile(soundObject) {
        return new Promise((resolve, reject) => {
            const audio = new Audio(soundObject.file + (soundObject.file.indexOf('?') === -1 ? '?' : '&') + 'v=' + SOUND_VER);
            const speed = soundObject.lang === 'th' ? thaiAnnouncementSpeed : englishAnnouncementSpeed;
            let settled = false;

            const finish = (fn) => {
                if (settled) return;
                settled = true;
                clearTimeout(hardTimeout);
                fn();
            };

            // Timeout สำรองแบบไม่พึ่ง event ใดๆ เลย (บาง TV browser ไม่ยิง onloadedmetadata/onended/onerror
            // ให้ครบ ถ้าไม่มีตัวนี้ คิวเสียงทั้งระบบจะค้างถาวรเพราะเล่นเสียงแบบ sequential ทีละไฟล์)
            const hardTimeout = setTimeout(() => finish(resolve), 8000);

            audio.onloadedmetadata = () => {
                audio.playbackRate = speed;
                audio.play().catch(e => finish(() => reject(new Error(`Play() rejected: ${e}`))));
            };
            audio.onended = () => finish(resolve);
            audio.onerror = () => finish(() => reject(new Error(`Failed to play ${soundObject.file}`)));
        });
    }

    function convertNumberToSpeechFiles(number, lang) {
        const num = parseInt(number);
        if (!isNaN(num) && num >= 0 && num <= 50) {
            return [{ file: `assets/sounds/${lang}/${num}.mp3`, lang: lang }];
        }
        return String(number).split('').map(char => ({ file: `assets/sounds/${lang}/${char}.mp3`, lang: lang }));
    }


    // ==========================================================
    //  Room Update Function (Rewritten for Correct Sorting)
    // ==========================================================
    function updateRooms(data) {
        $(".room").each(function() {
            const roomDiv = $(this);
            const roomNumber = roomDiv.data("room-number");
            const queueList = roomDiv.find(".queue-list");
            const newQueues = data[roomNumber] || [];

            const callingQueues = newQueues.filter(q => q.is_calling == 1);
            const waitingQueues = newQueues.filter(q => q.status === 'waiting' && q.is_calling != 1);
            const calledQueues = newQueues.filter(q => q.status === 'called' && q.is_calling != 1);
            
            const allSortedQueues = [...callingQueues, ...waitingQueues, ...calledQueues];

            queueList.empty();

            if (allSortedQueues.length > 0) {
                allSortedQueues.forEach(queue => {
                    let { statusClass, statusText } = getQueueStatus(queue);
                    const newContent = `<span>Queue ${queue.queue_number}</span><span>${statusText}</span>`;
                    const queueDiv = $(`<div class="${statusClass}" data-queue-number="${queue.queue_number}">${newContent}</div>`);
                    queueList.append(queueDiv);

                    if (!isFirstLoad && queue.status === "called" && queue.call_count > 0) {
                        if ((lastCallCounts[roomNumber]?.[queue.queue_number] || 0) !== queue.call_count) {
                            if (queue.call_count > (initialCallCounts[roomNumber]?.[queue.queue_number] || 0)) {
                                playSound(queue.queue_number, roomNumber);
                            }
                            if (!lastCallCounts[roomNumber]) lastCallCounts[roomNumber] = {};
                            lastCallCounts[roomNumber][queue.queue_number] = queue.call_count;
                        }
                    }
                });
            } else {
                queueList.html('<div class="queue empty">- คิวว่าง -</div>');
            }
        });
        
        if (isFirstLoad) {
            for (const roomNum in data) {
                if (data.hasOwnProperty(roomNum)) {
                    initialCallCounts[roomNum] = {};
                    data[roomNum].forEach(queue => {
                        initialCallCounts[roomNum][queue.queue_number] = queue.call_count;
                    });
                }
            }
            isFirstLoad = false;
        }
    }

    function getQueueStatus(queue) {
        if (queue.is_calling == 1) return { statusClass: "blinking-calling", statusText: `Calling / กำลังเรียก` };
        if (queue.status === "waiting") return { statusClass: "queue waiting", statusText: `Waiting / รอคิว` };
        if (queue.status === "called") return { statusClass: "queue called", statusText: `Called / เรียกแล้ว` };
        return { statusClass: "queue", statusText: ""};
    }

    // --- Main Loops and Timers ---
    let lastResponse = null;
    function checkUpdates() {
        $.get("fetch_queues_terminal.php", function(response) {
            if (MUTE_SOUND && response === lastResponse) return;
            lastResponse = response;
            try {
                const data = JSON.parse(response);
                updateRooms(data);
            } catch (e) {
                console.error("Error parsing JSON:", e, response);
            }
        });
    }
    // ใช้ Web Worker เป็นตัวจับเวลาแทน setInterval ตรงๆ บน main thread
    // เพราะเบราว์เซอร์จะหน่วง (throttle) setInterval ของหน้าเว็บที่ถูกย่อ/สลับไปหน้าต่างอื่น
    // ทำให้พลาดการแจ้งเตือนเรียกคิวแบบเรียลไทม์ ส่วน Worker ไม่ถูกหน่วงแบบเดียวกัน
    const pollWorkerCode = "setInterval(() => postMessage('tick'), 5000);";
    const pollWorkerBlob = new Blob([pollWorkerCode], { type: 'application/javascript' });
    const pollWorker = new Worker(URL.createObjectURL(pollWorkerBlob));
    pollWorker.onmessage = checkUpdates;
    checkUpdates();

    function clockLoop() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        $('#clock-container').text(MUTE_SOUND ? `${hours}:${minutes}` : `${hours}:${minutes}:${seconds}`);
    }
    setInterval(clockLoop, MUTE_SOUND ? 15000 : 1000);
    clockLoop();

    function updateDate() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        $('#date-container').text(now.toLocaleDateString('en-GB', options));
    }
    setInterval(updateDate, 60000);
    updateDate();
    
    // --- Modal Functions ---
    function showQueueModal(queueNumber, roomNumber) {
        const roomNames = { 10: 'ห้องยา / การเงิน' };
        $("#modal-queue-number").text(`Queue ${queueNumber}`);
        $("#modal-room-number").text(roomNames[roomNumber] || `Room/ห้อง ${roomNumber}`);
        $("#queue-modal").fadeIn(500);
    }
    function hideQueueModal() {
        $("#queue-modal").fadeOut(500);
    }
});
</script>

</body>
</html>

