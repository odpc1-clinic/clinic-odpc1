<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // เฝ้าระบบ (clinic:health-watch) — ค่าเริ่มต้นเฝ้าตัวเอง · ตั้ง HEALTH_WATCH_URL เพื่อเฝ้าเครื่องอื่น (เช่น server สำนักงาน)
    'health' => [
        'watch_url' => env('HEALTH_WATCH_URL', rtrim((string) env('APP_URL', 'https://127.0.0.1'), '/') . '/up'),
    ],

    // R506: โฟลเดอร์ตาราง lookup ของ FoxPro (ว่าง = เลือกให้เอง ดู R506Exporter::defaultDataDir)
    'r506' => [
        'data_dir' => env('R506_DATA_DIR', ''),
    ],

    'line' => [
        'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN'),
        'channel_secret' => env('LINE_CHANNEL_SECRET'),

        // บัญชีนี้มีเจ้าหน้าที่นั่งตอบแชทหรือไม่
        //   false = บัญชีแจ้งเตือนอัตโนมัติล้วน (ไม่มีคนตอบ) -> บอทต้องตอบบอกทางไป OA หลัก
        //   true  = OA หลักที่พยาบาลตอบเอง                  -> บอทต้องเงียบ ไม่งั้นไปตอบทับพยาบาล
        // ตั้ง LINE_STAFFED=true ใน .env ตอนย้ายไปใช้ OA หลัก (รออนุมัติงบ)
        'staffed' => env('LINE_STAFFED', false),

        // ลิงก์ OA หลักของศูนย์ ใช้ในข้อความบอกทางตอนบัญชีนี้ยังไม่มีคนตอบ
        'main_oa_url' => env('LINE_MAIN_OA_URL', 'https://lin.ee/VwOdpI5'),

        // ไอดี + รูป QR ของบัญชีที่ให้คนไข้แอดเพื่อน (โชว์ในป๊อปอัป "รหัสผูก LINE")
        // รวมไว้ที่เดียว เพราะตอนย้าย OA ครั้งก่อนมีไอดีเก่าค้างอยู่หลายหน้าจนหาไม่เจอ
        'oa_id'     => env('LINE_OA_ID', '@352bavoo'),
        'oa_qr'     => env('LINE_OA_QR', '/queue/assets/@352bavoo.png'),

        // ฟอร์มผูกบัญชีใน LINE (LIFF) + ที่พัก Apps Script (docs/line-link-relay.gs) — 2569-10-22
        // server ใหม่อยู่วงใน LINE ยิงเข้าไม่ได้ ฟอร์มจึงส่งไปพักที่ Google (เข้ารหัสแล้ว) ให้ server มาดึง
        'link' => [
            // เลิกใช้ฟอร์มแล้ว (2569-10-31): คนไข้ใช้พิมพ์รหัสในแชท (27 ครั้ง vs ฟอร์ม 2 ครั้งที่เป็นการทดสอบ)
            // และคนไข้ที่เป็นเพื่อนอยู่แล้วไม่ได้รับลิงก์ฟอร์มอยู่ดี · เปิดกลับ: LINE_LINK_FORM_ENABLED=true
            'form_enabled' => (bool) env('LINE_LINK_FORM_ENABLED', false),
            'liff_id'      => env('LINE_LIFF_ID'),            // จาก LINE Login channel → LIFF
            'liff_qr'      => env('LINE_LIFF_QR', '/images/LineLIFF.png'),   // QR ของ https://liff.line.me/<liff_id> (โชว์ใน popup รหัสผูก LINE)
            'relay_url'    => env('LINE_LINK_RELAY_URL'),     // URL ของ Apps Script web app
            'relay_secret' => env('LINE_LINK_RELAY_SECRET'),
            // หน้าฟอร์มบน GitHub Pages (Endpoint URL ของ LIFF) — หน้า /line/health เปิดตรวจว่ายังขึ้นอยู่
            'form_url'     => env('LINE_LINK_FORM_URL', 'https://odpc1-clinic.github.io/clinic-line-form/'),
        ],
    ],

    /*
     | ที่พักข้อความ DOT (Google Apps Script)
     |
     | เครื่องคลินิกเปิด จ.-ศ. 8.30-16.30 และต้อง login captive portal ก่อนออกเน็ต
     | คลิป DOT ที่คนไข้ส่งนอกเวลาจึงตกหล่น เพราะ LINE ไม่เก็บ webhook ไว้ข้ามวัน
     |
     | Apps Script รับ webhook 24 ชม. เก็บไฟล์ไว้ใน Google Drive ของคลินิกวัณโรค
     | แล้วเครื่องคลินิกดึงมาเก็บแบบเข้ารหัสในเครื่องตอนเปิด (artisan tb:pull-dot)
     | ดึงสำเร็จแล้วสั่งลบไฟล์ใน Drive ทันที ข้อมูลคนไข้จึงไม่ค้างอยู่ข้างนอก (2569-09-29)
     */
    'tb_dot_relay' => [
        'url'    => env('TB_DOT_RELAY_URL'),      // URL ของ Apps Script web app
        'secret' => env('TB_DOT_RELAY_SECRET'),   // รหัสลับที่ตั้งตรงกันสองฝั่ง
    ],

];
