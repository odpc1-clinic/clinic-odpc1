<?php

namespace App\Http\Controllers;

use App\Services\LineMessagingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * หน้าตรวจสุขภาพระบบแจ้งเตือนนัดหมายทาง LINE
 * เช็คทุกจุดที่ต้อง "เชื่อมต่อได้" ระบบถึงจะส่งข้อความถึงคนไข้ได้จริง
 * โดยยิงทดสอบของจริง (ไม่ใช่แค่ดูว่ามีค่าใน config)
 */
class LineHealthController extends Controller
{
    public function index(LineMessagingService $line)
    {
        // หน้านี้เปิดเผยสถานะ token/การเชื่อมต่อ -> จำกัดเฉพาะ Admin (กันเดา URL เข้าตรงๆ)
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403, 'เฉพาะผู้ดูแลระบบเท่านั้น');

        $checks = [];

        // 1) Channel Access Token — ใช้ยิงข้อความออก
        $token = config('services.line.channel_access_token', '');
        $checks[] = $this->check(
            'LINE Channel Access Token',
            'กุญแจสำหรับส่งข้อความออก ถ้าไม่มี = ส่งไม่ได้เลย',
            !empty($token),
            !empty($token) ? 'ตั้งค่าแล้ว (' . strlen($token) . ' ตัวอักษร)' : 'ยังไม่ได้ตั้งค่า LINE_CHANNEL_ACCESS_TOKEN ใน .env',
            'ใส่ LINE_CHANNEL_ACCESS_TOKEN ใน .env แล้วรัน php artisan config:cache'
        );

        // 2) Channel Secret — ใช้ตรวจลายเซ็น webhook (กันคนปลอมยิงเข้ามา)
        $secret = config('services.line.channel_secret', '');
        $checks[] = $this->check(
            'LINE Channel Secret',
            'ใช้ตรวจว่า webhook มาจาก LINE จริง (กันของปลอม)',
            !empty($secret),
            !empty($secret) ? 'ตั้งค่าแล้ว' : 'ยังไม่ได้ตั้งค่า LINE_CHANNEL_SECRET ใน .env',
            'ใส่ LINE_CHANNEL_SECRET ใน .env แล้วรัน php artisan config:cache'
        );

        // 3) LINE Messaging API — ต่อออกอินเทอร์เน็ตถึง LINE ได้ไหม + token ใช้ได้จริงไหม
        $apiOk = false;
        $apiDetail = 'ข้ามการทดสอบ เพราะยังไม่มี Access Token';
        $quota = null;
        if (!empty($token)) {
            try {
                $res = Http::withToken($token)->timeout(8)->get('https://api.line.me/v2/bot/info');
                $apiOk = $res->successful();
                if ($apiOk) {
                    $bot = $res->json();
                    $apiDetail = 'เชื่อมต่อสำเร็จ · บัญชี: ' . ($bot['displayName'] ?? '-')
                        . (isset($bot['basicId']) ? ' (' . $bot['basicId'] . ')' : '');
                } elseif ($res->status() === 401) {
                    $apiDetail = 'Token ไม่ถูกต้องหรือหมดอายุ (HTTP 401)';
                } else {
                    $apiDetail = 'ต่อไม่สำเร็จ HTTP ' . $res->status();
                }
            } catch (\Throwable $e) {
                $apiDetail = 'ต่อ LINE ไม่ได้: ' . $e->getMessage();
            }
        }
        $checks[] = $this->check(
            'เชื่อมต่อ LINE Messaging API',
            'ทดสอบยิงจริงไปที่ LINE ว่าเน็ต/โทเคนใช้งานได้',
            $apiOk,
            $apiDetail,
            'เช็คอินเทอร์เน็ต/ไฟร์วอลล์ของเครื่อง และตรวจว่า Token ยังไม่หมดอายุ'
        );

        // 4) Cloudflare Tunnel — "ทางเข้า" ให้ LINE ยิง webhook กลับมาถึงเครื่องในคลินิก
        //    เครื่องอยู่หลัง NAT ไม่มี public IP จึงใช้ quick tunnel ซึ่งได้ URL สุ่มใหม่ทุกครั้งที่รีสตาร์ท
        //    (C:\cloudflared\start-tunnel.ps1 จะตั้ง URL ใหม่ให้ LINE อัตโนมัติ)
        //    ถ้าท่อนี้ล่ม = คนไข้พิมพ์รหัสผูกบัญชีแล้วไม่มีอะไรเกิดขึ้น (แต่การส่งเตือนออกยังทำงานปกติ)
        $tunnelOk = false;
        $tunnelDetail = 'ข้ามการทดสอบ เพราะยังไม่มี Access Token';
        if (!empty($token)) {
            try {
                $res = Http::withToken($token)->timeout(8)
                    ->get('https://api.line.me/v2/bot/channel/webhook/endpoint');

                if (!$res->successful()) {
                    $tunnelDetail = 'ถาม LINE ไม่ได้ว่า webhook ตั้งไว้ที่ไหน (HTTP ' . $res->status() . ')';
                } else {
                    $endpoint = (string) $res->json('endpoint');
                    $active   = (bool) $res->json('active');
                    $host     = parse_url($endpoint, PHP_URL_HOST) ?: $endpoint;
                    $relayUrl = (string) config('services.line.link.relay_url');

                    // ตั้งแต่ 2569-10-31: LINE ส่งไปพักที่ Google Apps Script แล้ว server ดึงเอง (line:pull-webhook)
                    // ไม่มีท่อ Cloudflare แล้ว — ตรวจว่า LINE ชี้ที่พักจริง + ตัวดึงยังทำงาน
                    if ($relayUrl !== '' && rtrim($endpoint, '/') === rtrim($relayUrl, '/')) {
                        $f  = storage_path('app/line-webhook-last-pull.txt');
                        $at = is_file($f) ? \Carbon\Carbon::parse(trim((string) @file_get_contents($f))) : null;
                        $fresh = $at && $at->gt(now()->subMinutes(5));
                        $tunnelOk = $active && $fresh;
                        $tunnelDetail = 'LINE ส่งไปที่พัก Google Apps Script'
                            . ' · ' . ($at ? 'ดึงล่าสุด ' . $at->format('H:i:s') . ' น.' . ($fresh ? '' : ' (ควรดึงทุกนาที)') : 'ยังไม่เคยดึงสำเร็จ')
                            . ($active ? '' : ' · LINE ปิดการใช้งาน webhook นี้อยู่');
                        goto relayDone;
                    }

                    // ยิงกลับเข้ามาทาง Cloudflare จริง เพื่อพิสูจน์ว่าท่อยังเปิดอยู่
                    // (ยิงหน้าแรก ไม่ใช่ /line/webhook จะได้ไม่เกิด log ข้อความปลอม)
                    $base = preg_replace('#/line/webhook/?$#', '', $endpoint);
                    $t0 = microtime(true);
                    try {
                        $ping      = Http::timeout(12)->withoutVerifying()->withoutRedirecting()->get($base);
                        $ms        = (int) round((microtime(true) - $t0) * 1000);
                        $reachable = $ping->status() < 500;   // 200/302 = Laravel ตอบแล้ว, 502/530 = ท่อขาด
                        $pingNote  = $reachable ? "ตอบกลับใน {$ms} ms" : 'ท่อขาด (HTTP ' . $ping->status() . ')';
                    } catch (\Throwable $e) {
                        $reachable = false;
                        $pingNote  = 'ต่อกลับผ่าน Cloudflare ไม่ได้';
                    }

                    $tunnelOk = $active && $reachable;
                    $tunnelDetail = ($tunnelOk ? 'ท่อเปิดอยู่' : 'ท่อมีปัญหา')
                        . ' · ' . $host
                        . ' · ' . $pingNote
                        . ($active ? '' : ' · LINE ปิดการใช้งาน webhook นี้อยู่');
                    relayDone:
                }
            } catch (\Throwable $e) {
                $tunnelDetail = 'ตรวจไม่ได้: ' . $e->getMessage();
            }
        }
        // ทางเดียวที่คนไข้ผูกบัญชีได้ (พิมพ์รหัสในแชท) ตั้งแต่ปิดฟอร์ม 2569-10-31 — ล่ม = ผูกไม่ได้ จึงไม่ใช่ข้อไม่บังคับ
        $formOn = (bool) config('services.line.link.form_enabled') && (bool) config('services.line.link.liff_id');
        $checks[] = $this->check(
            'ทางรับข้อความแชทจากคนไข้ (webhook)',
            'LINE ส่งข้อความคนไข้ (รหัสผูกบัญชี / เพิ่มเพื่อน / บล็อก) ไปพักที่ Google แล้วเครื่องนี้ดึงทุกนาที — ล่ม = คนไข้พิมพ์รหัสแล้วไม่มีอะไรเกิดขึ้น (การส่งเตือนนัดออกยังปกติ)',
            $tunnelOk,
            $tunnelDetail,
            'ตัวตั้งเวลา (Scheduler) ต้องทำงาน · ลองสั่ง php artisan line:pull-webhook ดูข้อความ error · ถ้า LINE ชี้ที่อื่น: php artisan line:set-webhook --relay',
            $formOn
        );

        // 4.1) ฟอร์มผูกบัญชีใน LINE (LIFF) — ปิดใช้แล้ว (2569-10-31) แสดงเฉพาะเมื่อเปิดกลับ
        if ($formOn) {
            foreach ($this->formChecks() as $fc) { $checks[] = $fc; }
        }

        // 5) โควตาข้อความคงเหลือของเดือนนี้
        if ($apiOk) {
            $quota = $line->getQuotaStatus();
        }
        $quotaOk = true;
        $quotaDetail = 'ตรวจไม่ได้ (ต้องเชื่อมต่อ API ได้ก่อน)';
        if ($quota) {
            $limit = (int) ($quota['limit'] ?? 0);
            $used  = (int) ($quota['used'] ?? 0);
            $left  = max($limit - $used, 0);
            $pct   = $limit > 0 ? round($used / $limit * 100) : 0;
            $quotaOk = $limit === 0 || $pct < 90;   // limit=0 คือไม่จำกัด
            $quotaDetail = $limit > 0
                ? "ใช้ไป {$used}/{$limit} ข้อความ ({$pct}%) · เหลือ {$left}"
                : "ใช้ไป {$used} ข้อความ (แพ็กเกจไม่จำกัดจำนวน)";
        }
        $checks[] = $this->check(
            'โควตาข้อความเดือนนี้',
            'ถ้าโควตาหมด ข้อความจะส่งไม่ออก',
            $quotaOk,
            $quotaDetail,
            'อัปเกรดแพ็กเกจ LINE OA หรือรอรอบเดือนใหม่'
        );

        // 6) Scheduler — ตัวสั่งให้ส่งอัตโนมัติตามเวลา
        //    วัดจาก "ชีพจร" ที่ schedule:run เขียนทุกนาที (ดู Console\Kernel)
        //    ห้ามวัดจากข้อความล่าสุดที่ส่ง: ถ้าไม่มีคนไข้ผูก LINE ก็ไม่มีข้อความ
        //    ทั้งที่ตัวตั้งเวลาปกติดี — เกณฑ์เดิมทำให้ขึ้นแดงหลอกมาแล้ว (2026-08-03)
        $beatFile = storage_path('app/scheduler-heartbeat.txt');
        $beat     = is_file($beatFile) ? trim((string) @file_get_contents($beatFile)) : '';
        $beatAt   = null;
        if ($beat !== '') {
            try { $beatAt = \Carbon\Carbon::parse($beat); } catch (\Throwable $e) { $beatAt = null; }
        }
        // เผื่อเครื่องช้า/เพิ่งรีสตาร์ต ให้ผ่อนถึง 5 นาที
        $schedulerOk = $beatAt && $beatAt->gt(now()->subMinutes(5));

        $lastSent = DB::table('line_message_logs')->where('type', 'push')->max('created_at');
        $sentNote = $lastSent
            ? ' · ส่งข้อความล่าสุด ' . \Carbon\Carbon::parse($lastSent)->format('d/m/Y H:i') . ' น.'
            : ' · ยังไม่เคยส่งข้อความ';

        $checks[] = $this->check(
            'ตัวตั้งเวลาส่งอัตโนมัติ (Scheduler)',
            (PHP_OS_FAMILY === 'Windows' ? 'Windows Task' : 'crontab') . ' ต้องรัน php artisan schedule:run ทุกนาที — ตัวส่งเตือนนัดและตัวดึงรหัสจากฟอร์มใน LINE อาศัยตัวนี้',
            $schedulerOk,
            $beatAt
                ? ($schedulerOk
                    ? 'ทำงานปกติ — ตรวจสัญญาณล่าสุด ' . $beatAt->format('H:i:s') . ' น.' . $sentNote
                    : 'ไม่มีสัญญาณตั้งแต่ ' . $beatAt->format('d/m/Y H:i') . ' น. (ควรมีทุกนาที)' . $sentNote)
                : 'ยังไม่พบสัญญาณ — ถ้าเพิ่งอัปเดตระบบ รอ 1-2 นาทีแล้วรีเฟรชอีกครั้ง' . $sentNote,
            PHP_OS_FAMILY === 'Windows'
                ? 'ตรวจ Task Scheduler ว่ามี "ClinicLaravelScheduler" และสถานะ Ready '
                  . '(ห้ามเปิด "Laravel Scheduler - Clinic" ที่ปิดไว้ — เป็นตัวซ้ำ ทำให้ส่งซ้ำ 2 ข้อความ)'
                : 'ตรวจ crontab ของผู้ใช้เว็บ (crontab -l) ว่ามีบรรทัด schedule:run ทุกนาที'
        );

        // 7) ฐานข้อมูลระบบคิว/นัด + จำนวนคนไข้ที่ผูก LINE ไว้
        $linked = DB::table('patients')->whereNotNull('line_user_id')->where('line_user_id', '!=', '')->count();
        $checks[] = $this->check(
            'คนไข้ที่ผูก LINE แล้ว',
            'ส่งได้เฉพาะคนที่ผูกบัญชีไว้เท่านั้น',
            $linked > 0,
            $linked > 0 ? "ผูกแล้ว {$linked} ราย" : 'ยังไม่มีคนไข้ผูก LINE เลย — ส่งเตือนไม่ได้',
            'ให้เจ้าหน้าที่กดปุ่ม "รหัสผูก LINE" ที่หน้าคนไข้ แล้วทำตามขั้นตอนใน popup (สแกน QR → กรอกรหัส)',
            true
        );

        // ===== สรุปงานนัดวันนี้ (ให้เห็นภาพว่าวันนี้ส่งครบไหม) =====
        $todayBE = (now()->year + 543) . now()->format('-m-d');
        $today = [
            'appt'     => DB::table('visits')->where('appointment', $todayBE)->count(),
            'linked'   => DB::table('visits')
                ->join('patients', 'patients.id', '=', 'visits.patient_id')
                ->where('visits.appointment', $todayBE)
                ->whereNotNull('patients.line_user_id')->where('patients.line_user_id', '!=', '')
                ->count(),
            'reminded' => DB::table('visits')->where('appointment', $todayBE)
                ->whereNotNull('reminder_sent_at')->count(),
        ];

        // ผูกผ่านฟอร์มใน LINE วันนี้ (สำเร็จ / รหัสผิด)
        $today['form'] = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('line_link_requests')) {
            $f = DB::table('line_link_requests')->where('created_at', '>=', now()->startOfDay())
                ->selectRaw("SUM(status = 'linked') ok, SUM(status = 'failed') bad")->first();
            $today['form'] = ['ok' => (int) ($f->ok ?? 0), 'bad' => (int) ($f->bad ?? 0)];
        }

        // ข้อความล่าสุด 5 รายการ (ไว้ดูว่าส่งออกจริงไหม)
        $recent = DB::table('line_message_logs')
            ->leftJoin('patients', 'patients.id', '=', 'line_message_logs.patient_id')
            ->orderByDesc('line_message_logs.id')->limit(5)
            ->get([
                'line_message_logs.created_at', 'line_message_logs.type', 'line_message_logs.purpose',
                'line_message_logs.success', 'line_message_logs.error', 'patients.code as hn',
            ]);

        // ภาพรวมนับเฉพาะจุดที่ "ต้องใช้" — จุดไม่บังคับมีปัญหาแสดงเป็นสีเหลือง ไม่ทำให้หัวหน้าแดง
        $allOk    = collect($checks)->every(fn ($c) => $c['ok'] || $c['optional']);
        $failed   = collect($checks)->filter(fn ($c) => ! $c['ok'] && ! $c['optional'])->count();
        $warned   = collect($checks)->filter(fn ($c) => ! $c['ok'] && $c['optional'])->count();
        $purposeLabel = self::PURPOSE;

        return view('line.health', compact('checks', 'today', 'recent', 'allOk', 'failed', 'warned', 'purposeLabel'));
    }

    /** ชื่อวัตถุประสงค์ของข้อความเป็นภาษาคน (ตรงกับหน้าประวัติข้อความ LINE) */
    private const PURPOSE = [
        'appointment_reminder' => 'แจ้งเตือนนัด', 'link_success' => 'ผูกบัญชีสำเร็จ', 'link_failed' => 'รหัสผูกผิด/หมดอายุ',
        'greeting' => 'ทักทาย/แอดเพื่อน', 'inquiry_redirect' => 'ตอบ/ส่งต่อ OA หลัก', 'health_alert' => 'แจ้งเตือนระบบล่ม',
        'health_alert_link' => 'ผูกรับแจ้งเตือนระบบ',
    ];

    /**
     * ฟอร์มผูกบัญชีใน LINE (LIFF) — 3 จุดที่ต้องต่อได้ ฟอร์มถึงจะผูกให้คนไข้ได้จริง (2569-10-22)
     * หน้าเว็บบน GitHub Pages · ที่พักรหัสบน Google Apps Script · ตัวดึงของเครื่องนี้ (line:pull-links ทุกนาที)
     * ยังไม่ตั้งค่า (เครื่องทดสอบ) = แจ้งว่ายังไม่เปิดใช้ ไม่นับเป็นปัญหา
     */
    private function formChecks(): array
    {
        $liff  = config('services.line.link.liff_id');
        $relay = config('services.line.link.relay_url');
        $sec   = config('services.line.link.relay_secret');
        if (! $liff || ! $relay || ! $sec) {
            return [$this->check('ฟอร์มผูกบัญชีใน LINE', 'คนไข้สแกน QR แล้วกรอกรหัสในฟอร์ม — ไม่ต้องมีท่อรับข้อความแชท',
                false, 'ยังไม่เปิดใช้บนเครื่องนี้ (ไม่มี LINE_LIFF_ID / LINE_LINK_RELAY_* ใน .env)',
                'ถ้าเครื่องนี้เป็นระบบจริง: คัดลอกค่า LINE_* และไฟล์ storage/app/private/line-link/private.pem จากเครื่องเดิม แล้ว php artisan config:cache', true)];
        }

        $out = [];

        // หน้าฟอร์ม — ต้องเป็นรุ่นที่มี LIFF ID ของเรา
        $page = (string) config('services.line.link.form_url');
        try {
            $r = Http::timeout(8)->get($page);
            $ok = $r->successful() && str_contains($r->body(), $liff);
            $d = $ok ? 'เปิดได้ · ' . $page : ($r->successful() ? 'เปิดได้แต่ไม่ใช่ฟอร์มของ LIFF ' . $liff : 'HTTP ' . $r->status());
        } catch (\Throwable $e) {
            $ok = false; $d = 'เปิดไม่ได้: ' . $e->getMessage();
        }
        $out[] = $this->check('ฟอร์มผูกบัญชี (หน้าเว็บ)', 'หน้าที่เปิดขึ้นเมื่อคนไข้สแกน QR — ฝากไว้ที่ GitHub Pages (odpc1-clinic/clinic-line-form)',
            $ok, $d, 'เปิด GitHub repo odpc1-clinic/clinic-line-form ดูว่า Pages ยังเปิดอยู่ · ถ้าสร้างกุญแจใหม่ ต้อง php artisan line:link-setup แล้วอัปโหลด index.html ใหม่');

        // ที่พักรหัส — ถามจำนวนที่รอ (ไม่ดึงออก ไม่ลบ)
        try {
            $r = Http::timeout(15)->get($relay, ['secret' => $sec, 'action' => 'list']);
            $j = $r->json();
            $ok = $r->ok() && ! empty($j['ok']);
            $n = $ok ? count($j['items'] ?? []) : 0;
            $d = $ok ? 'ต่อได้ · รอดึง ' . $n . ' รายการ' . ($n ? ' (ปกติจะถูกดึงภายใน 1 นาที)' : '')
                     : 'ต่อไม่สำเร็จ: ' . ($j['error'] ?? ('HTTP ' . $r->status()));
        } catch (\Throwable $e) {
            $ok = false; $d = 'ต่อไม่ได้: ' . $e->getMessage();
        }
        $out[] = $this->check('ที่พักรหัส (Google Apps Script)', 'ฟอร์มฝากรหัสที่เข้ารหัสแล้วไว้ที่นี่ไม่เกิน 15 นาที เครื่องนี้ออกไปดึงเอง',
            $ok, $d, 'unauthorized = รหัสลับไม่ตรงกับ RELAY_SECRET ใน Script properties · อื่น ๆ = เช็คเน็ตขาออกไป script.google.com');

        // ตัวดึง — ชีพจรจาก line:pull-links + กุญแจถอดรหัส
        $f = storage_path('app/line-link-last-pull.txt');
        $at = is_file($f) ? \Carbon\Carbon::parse(trim((string) @file_get_contents($f))) : null;
        $key = \App\Services\LineLinkCrypto::exists();
        $ok = $key && $at && $at->gt(now()->subMinutes(5));
        $last = \Illuminate\Support\Facades\Schema::hasTable('line_link_requests')
            ? DB::table('line_link_requests')->orderByDesc('id')->first(['status', 'created_at']) : null;
        $d = (! $key ? 'ไม่พบกุญแจถอดรหัส storage/app/private/line-link/private.pem' :
               ($at ? 'ดึงล่าสุด ' . $at->format('H:i:s') . ' น.' . ($ok ? '' : ' (ควรดึงทุกนาที)') : 'ยังไม่เคยดึงสำเร็จ'))
           . ($last ? ' · ผูกผ่านฟอร์มล่าสุด ' . \Carbon\Carbon::parse($last->created_at)->format('d/m H:i') . ' (' . ($last->status === 'linked' ? 'สำเร็จ' : 'รหัสผิด') . ')' : ' · ยังไม่มีใครผูกผ่านฟอร์ม');
        $out[] = $this->check('ตัวดึงรหัสจากฟอร์ม', 'php artisan line:pull-links ทุกนาที — ถอดรหัส ผูกบัญชี แล้วส่ง "ผูกบัญชีสำเร็จ" ให้คนไข้',
            $ok, $d, $key ? 'ตัวตั้งเวลา (Scheduler) ต้องทำงาน — ดูข้อตัวตั้งเวลาด้านล่าง · ลองสั่ง php artisan line:pull-links ดูข้อความ error'
                          : 'คัดลอกไฟล์กุญแจจากเครื่องเดิม (ต้องเป็นชุดเดียวกับที่ฝังในหน้าฟอร์ม)');

        return $out;
    }

    private function check(string $name, string $why, bool $ok, string $detail, string $fix, bool $optional = false): array
    {
        return compact('name', 'why', 'ok', 'detail', 'fix', 'optional');
    }
}
