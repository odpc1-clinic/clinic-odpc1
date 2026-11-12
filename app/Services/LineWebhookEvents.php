<?php

namespace App\Services;

use App\Models\Patient;

/**
 * จัดการเหตุการณ์จาก LINE (เพิ่มเพื่อน / บล็อก / พิมพ์ข้อความ) — ใช้ร่วม 2 ทาง (2569-10-31)
 *
 *   1. webhook ตรง (LineWebhookController) — LINE ยิงเข้า server เอง ตรวจลายเซ็นแล้ว
 *   2. ผ่านที่พัก Google Apps Script (line:pull-webhook) — server อยู่วงในของกรม LINE ยิงเข้าไม่ถึง
 *      และ IT เปิดทางเข้าให้ไม่ได้ จึงให้ LINE ส่งไปพักที่ Apps Script แล้ว server ออกไปดึงเอง
 *
 * ทาง 2 ตรวจลายเซ็นของ LINE ไม่ได้ (Apps Script อ่าน header ไม่ได้) จึงกันของปลอมด้วยการถาม LINE เอง:
 * destination ต้องเป็นบัญชีของเรา และ userId ต้องเป็นเพื่อนกับบัญชีจริง (ดู LineWebhookRelayGuard ใน command)
 * ทาง 2 ตอบช้าได้ถึง ~1 นาที — reply token อาจหมดอายุ ตอบไม่ได้แล้วส่งแบบ push แทน (นับโควตา)
 */
class LineWebhookEvents
{
    public function __construct(private LineMessagingService $line) {}

    /** $viaRelay = มาจากที่พัก Apps Script (ตอบช้า ต้องมีทางสำรอง push) */
    public function handle(array $event, bool $viaRelay = false): void
    {
        // บัญชีนี้มีเจ้าหน้าที่ตอบแชทไหม (ดู config/services.php -> line.staffed)
        // ตอนย้ายไป OA หลักให้ตั้ง LINE_STAFFED=true แล้วบอทจะเงียบให้พยาบาลคุยเอง
        $staffed = (bool) config('services.line.staffed', false);

        $type = $event['type'] ?? null;
        $userId = $event['source']['userId'] ?? null;
        $replyToken = $event['replyToken'] ?? null;

        if (!$userId) {
            return;
        }

        $say = function (string $text, array $context) use ($replyToken, $userId, $viaRelay) {
            $ok = $replyToken ? $this->line->reply($replyToken, $text, $context) : false;
            if (!$ok && $viaRelay) {
                $this->line->push($userId, $text, $context + ['line_user_id' => $userId]);
            }
        };

        if ($type === 'follow') {
            // มีฟอร์มกรอกรหัสใน LINE แล้ว (LIFF) — บอกไว้อีกทาง เผื่อพิมพ์ในแชทแล้วไม่ตอบ
            $form = LineAccountLinker::formUrl();
            $formLine = $form
                ? "\n\n📝 หรือกรอกรหัสในฟอร์มนี้ / Or enter the code here:\n{$form}"
                : '';

            $say(($staffed
                // OA หลัก: มีทั้งคนไข้และคนทั่วไปกดติดตาม จึงไม่สั่งให้ทุกคนพิมพ์รหัส
                ? "สวัสดีค่ะ 🙏 ยินดีต้อนรับสู่ศูนย์บริการเวชศาสตร์ป้องกัน\n\n💬 สอบถามข้อมูลได้ที่นี่ เจ้าหน้าที่จะตอบกลับในเวลาทำการค่ะ\n\n🔔 หากต้องการรับแจ้งเตือนนัดหมายอัตโนมัติ\nกรุณาพิมพ์รหัส 6 หลัก ที่เจ้าหน้าที่ให้ไว้ตอนมารับบริการ"
                    . LineMessagingService::askResultsLine()
                    . "\n— — — — —\nHello 🙏 Welcome to the Preventive Medicine Service Center\n\n💬 Feel free to message us — our staff will reply during office hours.\n\n🔔 To receive automatic appointment reminders, please enter the 6-digit code given to you by our staff."
                    . LineMessagingService::askResultsLineEn()
                // บัญชีแจ้งเตือนล้วน: บอกตรง ๆ ว่าใช้ทำอะไร จะได้ไม่มีใครนั่งรอคำตอบ
                : "สวัสดีค่ะ 🙏\nกรุณาพิมพ์รหัสเชื่อมต่อ 6 หลัก ที่เจ้าหน้าที่ให้ไว้ตอนลงทะเบียน เพื่อผูกบัญชี LINE นี้กับระบบ สำหรับรับการแจ้งเตือนนัดหมาย\n— — — — —\nHello 🙏\nPlease enter the 6-digit connection code given by our staff at registration, to link this LINE account for appointment reminders.") . $formLine,
                ['line_user_id' => $userId, 'purpose' => 'greeting']);
            return;
        }

        if ($type === 'unfollow') {
            Patient::where('line_user_id', $userId)->update(['line_user_id' => null, 'line_linked_at' => null]);
            return;
        }

        if ($type !== 'message' || ($event['message']['type'] ?? null) !== 'text') {
            return;
        }

        $text = trim($event['message']['text'] ?? '');

        // นับเป็น "รหัสผูกบัญชี" เฉพาะเมื่อทั้งข้อความเป็นตัวเลข 6 หลักเท่านั้น
        //
        // ของเดิมใช้ preg_replace('/\D/','',$text) คือดึงตัวเลขออกมาจากทั้งประโยค
        // แล้วถามว่าได้ 6 หลักพอดีไหม ซึ่งไม่สนใจเลยว่าเลขนั้นอยู่ปนกับอะไร ผลคือ:
        //   "ผมอายุ 53 ปี เกิดปี 2515 ครับ"  ->  532515  -> บอทตอบ "รหัสไม่ถูกต้อง"
        //   "เลข 123 456 ค่ะ"                ->  123456  -> บอทตอบ "รหัสไม่ถูกต้อง"
        // แทรกกลางบทสนทนาที่พยาบาลกำลังคุยกับคนไข้อยู่ (พยาบาลแจ้งเข้ามา 21 ส.ค. 2569)
        //
        // อันตรายกว่านั้นคือขาผูกสำเร็จก็ใช้เลขชุดเดียวกัน ถ้าเลขที่ปนออกมาบังเอิญ
        // ตรงกับรหัสที่ยังไม่หมดอายุของคนไข้อีกราย ก็จะผูก LINE เข้ากับคนผิดทันที
        //
        // ยอมให้มีเว้นวรรคหรือขีดคั่นได้ เพราะคนพิมพ์ "123 456" หรือ "123-456" กันจริง
        // รหัสผูก LINE ผู้ดูแลรับแจ้งเตือนระบบล่ม (#xxxxxxxx 8 หลัก — คนละรูปแบบกับรหัสคนไข้ 6 หลัก)
        $sys = \App\Support\HealthAlert::tryLink($text, (string) $userId);
        if ($sys !== null) {
            $say($sys
                ? "✅ ผูกรับแจ้งเตือนระบบคลินิกแล้ว\nจะแจ้งที่ LINE นี้เมื่อระบบใช้งานไม่ได้และเมื่อกลับมาใช้ได้"
                : "❌ รหัสไม่ถูกต้องหรือหมดอายุ (10 นาที) — สร้างรหัสใหม่ในหน้า Dashboard งานพัฒนา",
                ['line_user_id' => $userId, 'purpose' => 'health_alert_link']);
            return;
        }

        $isCodeAttempt = (bool) preg_match('/^[\d\s-]+$/u', $text)
                         && preg_match_all('/\d/', $text) === 6;
        $code = $isCodeAttempt ? preg_replace('/\D/', '', $text) : '';

        // ต้องเป็นรหัส OTP ที่เจ้าหน้าที่สร้างให้เท่านั้น (ไม่ใช้ HN ตรงๆ กันคนอื่นสวมสิทธิ์เอา HN คนไข้คนอื่นมาผูกแทน)
        $linker = app(LineAccountLinker::class);
        $patient = $code !== '' ? $linker->patientForCode($code) : null;

        if ($patient) {
            $linker->link($patient, $userId);
            $say($linker->successMessage($patient),
                ['line_user_id' => $userId, 'patient_id' => $patient->id, 'purpose' => 'link_success']);
        } elseif ($isCodeAttempt) {
            // พิมพ์เลข 6 หลักมาแต่ผูกไม่ได้ = ตั้งใจผูกแล้วรหัสผิด/หมดอายุ ต้องบอกให้รู้ (ทั้งสองโหมด)
            $say(LineAccountLinker::failedCodeMessage(),
                ['line_user_id' => $userId, 'purpose' => 'link_failed']);
        } elseif (!$staffed) {
            // บัญชีแจ้งเตือนล้วน ไม่มีคนตอบ -> ต้องบอกทางไป OA หลัก ไม่งั้นคนไข้นั่งรอคำตอบที่ไม่มีวันมา
            $oa = config('services.line.main_oa_url');
            $say("ขออภัยค่ะ บัญชีนี้ใช้สำหรับแจ้งเตือนนัดหมายอัตโนมัติเท่านั้น ไม่มีเจ้าหน้าที่คอยตอบข้อความ\n\nหากต้องการสอบถามข้อมูล กรุณาติดต่อผ่าน LINE Official ของศูนย์ได้ที่นี่ค่ะ\n{$oa}\n\n(หากต้องการผูกบัญชีแจ้งเตือนนัดหมาย กรุณาพิมพ์รหัส 6 หลักที่เจ้าหน้าที่ให้ไว้)\n— — — — —\nSorry, this account is for automated appointment reminders only and is not monitored by staff.\n\nFor any inquiries, please contact our center's main LINE Official Account here:\n{$oa}\n\n(To link your account for reminders, please type the 6-digit code given by our staff.)",
                ['line_user_id' => $userId, 'purpose' => 'inquiry_redirect']);
        }
        // $staffed = true -> บอทเงียบ ปล่อยให้พยาบาลตอบเองในแชท LINE OA
        // (ถ้าบอทตอบทุกข้อความ จะไปทับบทสนทนาที่พยาบาลกำลังคุยกับคนไข้)
    }
}
