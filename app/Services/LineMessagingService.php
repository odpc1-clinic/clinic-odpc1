<?php

namespace App\Services;

use App\Models\LineMessageLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingService
{
    protected string $accessToken;
    protected string $channelSecret;

    public function __construct()
    {
        $this->accessToken = config('services.line.channel_access_token', '');
        $this->channelSecret = config('services.line.channel_secret', '');
    }

    /**
     * ท้ายข้อความ: คนที่ "ถูกนัดให้ฟังผลทาง LINE" ถามได้ที่นี่เลย
     *
     * ต้องเจาะจงว่า "กรณีที่นัดฟังผลทาง LINE" เท่านั้น ห้ามเขียนกว้างๆ ว่าถามผลอะไรก็ได้
     * เพราะ **ผลตรวจ HIV คนไข้ต้องมาฟังเองที่คลินิก** ถ้าชวนให้ถามทาง LINE แบบเหมารวม
     * จะกลายเป็นบอกผิดวิธีให้คนที่ไม่ควรได้ผลทางนี้
     *
     * และห้ามเอ่ยชื่อโรคในข้อความ — คลินิกนี้เป็นคลินิกโรคติดต่อทางเพศสัมพันธ์
     * ข้อความเด้งขึ้นหน้าจอโทรศัพท์ให้คนรอบตัวคนไข้เห็นได้ (เหตุผลเดียวกับที่
     * AppointmentLineNotifier ห้ามใช้ appointment_staff_note)
     *
     * เขียนไว้ที่เดียวแล้วเรียกใช้ทุกที่ ไม่ก็อปไปวางซ้ำ 4 จุด จะได้แก้คำที่เดียวจบ
     *
     * $whenTh คือวันนัด ใส่มาเมื่อรู้ว่านัดวันไหน เพื่อบอกคนไข้ว่าถามได้วันไหน
     * ที่ต้องรับเป็นพารามิเตอร์ ไม่ฮาร์ดโค้ด "พรุ่งนี้" ลงไปตรง ๆ เพราะ 4 จุดที่เรียกใช้
     * ห่างจากวันนัดไม่เท่ากัน:
     *   - แจ้งเตือนล่วงหน้า      -> พรุ่งนี้ (หรือวันจริง ถ้าคั่นด้วยวันหยุดยาว)
     *   - เตือนซ้ำเช้าวันนัด     -> วันนี้
     *   - ยืนยันนัดตอนกลับบ้าน   -> อาจอีกหลายเดือน
     *   - ตอนกดติดตาม/ผูกบัญชี   -> ยังไม่รู้ว่ามีนัดไหม ไม่ต้องใส่
     *
     * ขึ้นเฉพาะตอน LINE_STAFFED=true — บัญชีที่ไม่มีคนเฝ้า ถ้าไปบอกให้ "ถามได้ที่นี่"
     * คนไข้จะนั่งรอคำตอบที่ไม่มีวันมา
     */
    public static function askResultsLine(?string $whenTh = null): string
    {
        if (! config('services.line.staffed', false)) {
            return '';
        }

        // มีวันนัดให้อ้างอิง -> ระบุวันไปเลย จะได้ไม่มีใครถามก่อนถึงวันที่ผลออก
        // ไม่มี (เช่น ตอนคนไข้เพิ่งกดติดตาม/เพิ่งผูกบัญชี) -> พูดกว้างไว้
        // ห้ามฮาร์ดโค้ดคำว่า "พรุ่งนี้" ลงตรงนี้ เพราะข้อความนี้ถูกส่งจาก 4 ที่
        // ที่ไม่ได้เกิดขึ้นก่อนวันนัด 1 วันเสมอไป ดู docblock ข้างบน
        if ($whenTh !== null && $whenTh !== '') {
            $phrase = self::dayPhraseTh($whenTh);
            // ลงท้ายด้วยตัวเลข (เช่น "...ที่ 05/01/2570") ต้องเว้นวรรคก่อน "ค่ะ"
            // ไม่งั้นอ่านติดกันเป็น "2570ค่ะ" ส่วน "วันพรุ่งนี้ค่ะ" เขียนติดกันถูกอยู่แล้ว
            $tail = preg_match('/\d$/', $phrase) ? ' ค่ะ' : 'ค่ะ';

            return "\n\n"
                 . "📋 กรณีที่นัดฟังผลทาง LINE สามารถสอบถามได้ใน{$phrase}{$tail}"
                 . "\n"
                 . "เจ้าหน้าที่จะตอบกลับในวันและเวลาราชการ";
        }

        return "\n\n"
             . "📋 กรณีที่นัดฟังผลทาง LINE สอบถามได้ที่นี่เลยค่ะ"
             . "\n"
             . "เจ้าหน้าที่จะตอบกลับในวันและเวลาราชการ";
    }

    /**
     * บรรทัดอ้างอิงตัวผู้รับบริการในข้อความ LINE — ใช้ HN ไม่ใช่ชื่อ
     *
     * ข้อความ LINE เด้งขึ้นหน้าจอล็อกให้คนรอบตัวเห็นได้ ชื่อ-สกุลคู่กับชื่อคลินิก
     * จึงระบุตัวคนได้ทันทีโดยที่เจ้าตัวไม่รู้ตัว ส่วน HN คนที่ไม่มีตารางจับคู่
     * มองแล้วไม่ได้อะไร (การแฝงข้อมูล — เป็นมาตรการที่ PDPA รับรอง)
     *
     * ยังต้องมีตัวอ้างอิงอยู่ ไม่ตัดทิ้งไปเลย เพราะ OA มีเจ้าหน้าที่ตอบแล้ว
     * คนไข้ทักมาถามจะได้อ้าง HN ได้ และกันกรณีมือถือเครื่องเดียวใช้หลายคน
     *
     * คืนค่าว่างเมื่อไม่มี HN — ปล่อยให้ข้อความไม่มีบรรทัดนี้ ดีกว่าขึ้น "HN " ลอย ๆ
     */
    public static function patientRef(?string $hn): string
    {
        $hn = trim((string) $hn);
        return $hn === '' ? '' : "HN {$hn}";
    }

    /**
     * เติมคำว่า "วัน" ให้อ่านต่อจาก "ได้ใน..." ได้ลื่น
     * ตัวที่ส่งเข้ามามีได้ 3 แบบ: "พรุ่งนี้" · "วันนี้" · "วันศุกร์ ที่ 28/08/2569"
     * สองแบบหลังมี "วัน" นำอยู่แล้ว เติมซ้ำจะกลายเป็น "ในวันวันนี้"
     */
    protected static function dayPhraseTh(string $whenTh): string
    {
        return strpos($whenTh, 'วัน') === 0 ? $whenTh : 'วัน' . $whenTh;
    }

    /** เวอร์ชันภาษาอังกฤษ ต่อท้ายบล็อกอังกฤษของข้อความสองภาษา */
    public static function askResultsLineEn(?string $whenEn = null): string
    {
        if (! config('services.line.staffed', false)) {
            return '';
        }

        // $whenEn มาแบบพร้อมใช้แล้ว ("tomorrow" / "today" / "on Friday 28 August 2569")
        if ($whenEn !== null && $whenEn !== '') {
            return "\n\n"
                 . "📋 If your appointment was to receive results via LINE, you may ask us {$whenEn}."
                 . "\n"
                 . "Our staff will reply during official working hours.";
        }

        return "\n\n"
             . "📋 If your appointment was to receive results via LINE, you may ask here."
             . "\n"
             . "Our staff will reply during official working hours.";
    }

    /** ส่งข้อความเดียวกันนี้ให้คนคนนี้ไปแล้วหรือยังในช่วง $seconds วินาทีที่ผ่านมา */
    protected function sentRecently(string $lineUserId, string $text, int $seconds = 120): bool
    {
        try {
            // เทียบกับข้อความที่เก็บใน log ซึ่งถูกตัดอิโมจิออกแล้ว (utf8mb3) จึงต้องตัดแบบเดียวกันก่อนเทียบ
            $stored = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text);

            return LineMessageLog::where('line_user_id', $lineUserId)
                ->where('success', true)
                ->where('created_at', '>=', now()->subSeconds($seconds))
                ->where('message', $stored)
                ->exists();
        } catch (\Throwable $e) {
            // เช็คไม่ได้ก็ปล่อยส่ง ดีกว่าคนไข้ไม่ได้ข้อความเลย
            Log::error('LINE duplicate check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    // บันทึก audit log ของข้อความที่ส่ง (ห่อ try/catch ไม่ให้ปัญหาการ log ไปกระทบการส่งจริง)
    protected function logMessage(string $type, string $text, bool $success, array $context = [], ?string $error = null): void
    {
        try {
            LineMessageLog::create([
                'patient_id' => $context['patient_id'] ?? null,
                'line_user_id' => $context['line_user_id'] ?? null,
                'type' => $type,
                'purpose' => $context['purpose'] ?? null,
                // ตัดอักขระ 4 ไบต์ (อิโมจิ) ก่อนเก็บ เพราะ DB connection เป็น utf8mb3 —
                // มิฉะนั้นอิโมจินำหน้าจะทำให้ log ถูกตัดเหลือว่าง (ข้อความจริงที่ส่งให้คนไข้ยังครบทุกตัว)
                'message' => preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $text),
                'success' => $success,
                'error' => $error,
            ]);
        } catch (\Throwable $e) {
            Log::error('LINE message log write failed', ['error' => $e->getMessage()]);
        }
    }

    // ตรวจลายเซ็นของ webhook ว่ามาจาก LINE จริง (กัน request ปลอม)
    public function verifySignature(string $body, ?string $signatureHeader): bool
    {
        if (!$this->channelSecret || !$signatureHeader) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $body, $this->channelSecret, true));

        return hash_equals($expected, $signatureHeader);
    }

    // ส่งข้อความหาผู้ใช้เฉพาะราย (ใช้สำหรับแจ้งเตือนคิว/นัดหมาย)
    public function push(string $lineUserId, string $text, array $context = []): bool
    {
        $context['line_user_id'] = $context['line_user_id'] ?? $lineUserId;

        // คนไข้ปฏิเสธรับข้อความ LINE (PDPA) — กันที่ทางผ่านเดียวของทุกข้อความ ไม่ต้องไล่กันทุกจุดที่ส่ง (2569-10-29)
        // ยังไม่ได้ถาม = ส่งได้ (ผูก LINE เองแปลว่าประสงค์รับ) · userId ที่ไม่ใช่คนไข้ (แจ้งเตือนเจ้าหน้าที่) ไม่โดน
        // ไม่ลง log ข้อความว่า "ล้มเหลว" — หน้าสุขภาพ LINE จะนับเป็นระบบเสีย
        $__pid = \App\Models\Patient::where('line_user_id', $lineUserId)->value('id');
        if ($__pid && ! \App\Models\PatientConsent::allows((int) $__pid, 'line')) {
            Log::info('LINE push skipped: คนไข้ไม่ยินยอมรับข้อความ (PDPA)', ['patient_id' => $__pid, 'purpose' => $context['purpose'] ?? null]);
            return false;
        }

        // ข้อความเดียวกัน คนเดียวกัน ภายใน 2 นาที = ส่งซ้ำโดยไม่ตั้งใจ ไม่ใช่เจตนาของเจ้าหน้าที่
        // (สองคำขอชนกัน / กดปุ่มรัว / refresh หน้าแล้ว submit ซ้ำ)
        // กันไว้ที่นี่ด้วย เพราะเป็นทางผ่านของทุกข้อความ และโควตา LINE มีจำกัด 300 ข้อความ/เดือน
        if ($this->sentRecently($lineUserId, $text)) {
            Log::warning('LINE push skipped: ข้อความซ้ำภายใน 2 นาที', ['userId' => $lineUserId, 'purpose' => $context['purpose'] ?? null]);
            return true;   // ถือว่าสำเร็จ คนไข้ได้ข้อความนี้ไปแล้วจริง ๆ
        }

        if (!$this->accessToken) {
            Log::warning('LINE push skipped: channel access token not configured');
            $this->logMessage('push', $text, false, $context, 'channel access token not configured');
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post('https://api.line.me/v2/bot/message/push', [
                    'to' => $lineUserId,
                    'messages' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('LINE push request failed', ['userId' => $lineUserId, 'error' => $e->getMessage()]);
            $this->logMessage('push', $text, false, $context, $e->getMessage());
            return false;
        }

        if (!$response->successful()) {
            Log::error('LINE push failed', ['userId' => $lineUserId, 'response' => $response->body()]);
        }

        $this->logMessage('push', $text, $response->successful(), $context, $response->successful() ? null : $response->body());

        return $response->successful();
    }

    // อัปเดต Webhook URL ของ channel (ใช้ตอน Cloudflare quick tunnel ได้ URL ใหม่ทุกครั้งที่รีสตาร์ท)
    public function setWebhookEndpoint(string $webhookUrl): bool
    {
        if (!$this->accessToken) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->put('https://api.line.me/v2/bot/channel/webhook/endpoint', [
                    'endpoint' => $webhookUrl,
                ]);
        } catch (\Throwable $e) {
            Log::error('LINE set webhook endpoint failed', ['error' => $e->getMessage()]);
            return false;
        }

        if (!$response->successful()) {
            Log::error('LINE set webhook endpoint rejected', ['status' => $response->status(), 'body' => $response->body()]);
        }

        return $response->successful();
    }

    // เช็คโควต้าข้อความ push รายเดือน (channel นี้อยู่แผน Free 300 ข้อความ/เดือน)
    public function getQuotaStatus(): ?array
    {
        if (!$this->accessToken) {
            return null;
        }

        try {
            $quota = Http::withToken($this->accessToken)->get('https://api.line.me/v2/bot/message/quota');
            $consumption = Http::withToken($this->accessToken)->get('https://api.line.me/v2/bot/message/quota/consumption');
        } catch (\Throwable $e) {
            Log::error('LINE quota check failed', ['error' => $e->getMessage()]);
            return null;
        }

        if (!$quota->successful() || !$consumption->successful()) {
            return null;
        }

        $limit = $quota->json('value');
        $used = $consumption->json('totalUsage');

        if (!is_numeric($limit) || !is_numeric($used) || $limit <= 0) {
            return null;
        }

        return ['limit' => (int) $limit, 'used' => (int) $used];
    }

    // ตอบกลับข้อความในแชท (ใช้ replyToken จาก webhook event ต้องตอบภายใน 30 วินาที)
    /**
     * userId ของบัญชี LINE OA นี้ (ช่อง destination ใน webhook) — ใช้ตรวจเหตุการณ์ที่มาทางที่พัก Apps Script
     * ว่ามาจาก LINE ของเราจริง (2569-10-31) · จำไว้ 1 วัน · ต่อ LINE ไม่ได้ = null
     */
    public function botUserId(): ?string
    {
        if (!$this->accessToken) { return null; }
        return \Illuminate\Support\Facades\Cache::remember('line_bot_user_id', 86400, function () {
            try {
                $r = Http::withToken($this->accessToken)->timeout(10)->get('https://api.line.me/v2/bot/info');
                return $r->successful() ? ($r->json('userId') ?: null) : null;
            } catch (\Throwable $e) {
                return null;
            }
        }) ?: null;
    }

    /**
     * ผู้ใช้นี้เป็นเพื่อนกับบัญชีเราอยู่ไหม — true / false (ไม่ใช่เพื่อน หรือ userId ปลอม) / null (ต่อ LINE ไม่ได้)
     * userId ที่แต่งขึ้นเองจะได้ 404 — ใช้กันเหตุการณ์ปลอมที่ยิงเข้าที่พัก Apps Script ตรง ๆ
     */
    public function friendStatus(string $lineUserId): ?bool
    {
        if (!$this->accessToken || !preg_match('/^U[0-9a-f]{32}$/', $lineUserId)) {
            return $this->accessToken ? false : null;
        }
        try {
            $r = Http::withToken($this->accessToken)->timeout(10)->get('https://api.line.me/v2/bot/profile/' . $lineUserId);
        } catch (\Throwable $e) {
            return null;
        }
        if ($r->successful()) { return true; }
        return in_array($r->status(), [400, 404], true) ? false : null;
    }

    public function reply(string $replyToken, string $text, array $context = []): bool
    {
        if (!$this->accessToken) {
            $this->logMessage('reply', $text, false, $context, 'channel access token not configured');
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken)
                ->post('https://api.line.me/v2/bot/message/reply', [
                    'replyToken' => $replyToken,
                    'messages' => [
                        ['type' => 'text', 'text' => $text],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('LINE reply request failed', ['error' => $e->getMessage()]);
            $this->logMessage('reply', $text, false, $context, $e->getMessage());
            return false;
        }

        $this->logMessage('reply', $text, $response->successful(), $context, $response->successful() ? null : $response->body());

        return $response->successful();
    }
}
