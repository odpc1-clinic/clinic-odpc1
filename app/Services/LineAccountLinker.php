<?php

namespace App\Services;

use App\Models\Patient;

/**
 * ผูก LINE กับคนไข้ — ใช้ร่วมกันทั้งทางพิมพ์รหัสในแชท (webhook) ฟอร์มใน LINE (line:pull-links)
 * และพยาบาลกดจับคู่เอง ให้ทุกทางทำเหมือนกันทุกขั้น
 */
class LineAccountLinker
{
    /** รหัส 6 หลักที่เจ้าหน้าที่ออกให้ (ยังไม่หมดอายุ) */
    public function patientForCode(string $code): ?Patient
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        return Patient::where('line_link_code', $code)
            ->where('line_link_code_expires_at', '>', now())
            ->first();
    }

    public function link(Patient $patient, string $lineUserId): void
    {
        // LINE 1 บัญชี ผูกได้กับคนไข้ 1 รายเท่านั้น (line_user_id เป็น unique)
        // ถ้าบัญชีนี้เคยผูกกับคนไข้รายอื่นอยู่แล้ว ให้ย้ายการผูกมาที่รายใหม่ (ปลดของเดิมออกก่อน)
        // เพื่อไม่ให้ save() ชน unique constraint แล้ว crash (เดิม webhook จะตอบ 500 และ LINE ยิงซ้ำไม่หยุด)
        Patient::where('line_user_id', $lineUserId)
            ->where('id', '!=', $patient->id)
            ->update(['line_user_id' => null, 'line_linked_at' => null]);

        $patient->line_user_id = $lineUserId;
        $patient->line_linked_at = now();
        $patient->line_link_code = null;
        $patient->line_link_code_expires_at = null;
        $patient->save();
    }

    public function successMessage(Patient $patient): string
    {
        // ผูกเสร็จแล้วบอกนัดที่ยังมาไม่ถึงไปเลย คนไข้จะได้เห็นประโยชน์ทันทีตั้งแต่ข้อความแรก
        $upcoming = app(AppointmentLineNotifier::class)->upcomingSummary($patient);

        // ชื่อ + HN ในข้อความนี้ข้อความเดียว — เจ้าหน้าที่ดูในแชต OA แล้วรู้ว่าผูกถูกคน ไม่ต้องถ่ายรูปบัตรคนไข้ส่งเข้าแชต (คลินิกขอ 2569-10-29)
        // ข้อความนี้ส่งตอนคนไข้ยืนผูกอยู่หน้าเคาน์เตอร์ ถือมือถืออยู่เอง · ข้อความนัด/เตือนนัดที่เด้งทีหลังยังใช้ HN อย่างเดียวเหมือนเดิม (ดู patientRef)
        $th = trim(trim((string) $patient->name) . ' ' . trim((string) $patient->surname));
        $en = trim(trim((string) $patient->name_en) . ' ' . trim((string) $patient->surname_en)) ?: $th;
        $hn = LineMessagingService::patientRef($patient->code ?? '');
        $who   = $th !== '' ? "\n👤 {$th}" . ($hn !== '' ? " ({$hn})" : '') : ($hn !== '' ? "\n{$hn}" : '');
        $whoEn = $en !== '' ? "\n👤 {$en}" . ($hn !== '' ? " ({$hn})" : '') : ($hn !== '' ? "\n{$hn}" : '');

        return "✅ ผูกบัญชีสำเร็จ" . $who . "\nระบบจะแจ้งเตือนนัดหมายให้ทาง LINE นี้ค่ะ"
            . $upcoming
            . LineMessagingService::askResultsLine()
            . "\n— — — — —\n✅ Account linked successfully" . $whoEn . "\nYou will now receive appointment reminders through this LINE account."
            . LineMessagingService::askResultsLineEn();
    }

    /**
     * รหัสผิด/หมดอายุ — คนไข้หลายคนไม่รู้ว่ารหัสคืออะไร ส่งรหัสเก่าที่เคยได้หรือเลขมั่วมา (คลินิกแจ้ง 2569-10-31)
     * จึงบอกให้ชัดว่ารหัสให้เฉพาะต่อหน้าเจ้าหน้าที่ ไม่ได้รับมา = ไม่ต้องพิมพ์ ไม่ต้องกังวล แชทกับแอดมินได้ตามปกติ
     */
    public static function failedCodeMessage(): string
    {
        return "❌ รหัสนี้ใช้ไม่ได้ (ไม่ถูกต้อง หรือหมดอายุแล้ว)\n\n"
            . "ℹ️ ไม่ต้องพิมพ์รหัสนะคะ หากเจ้าหน้าที่ไม่ได้ให้รหัสกับท่านในวันนี้\n"
            . "รหัสผูกบัญชีจะให้เฉพาะตอนมารับบริการ ต่อหน้าเจ้าหน้าที่เท่านั้น และใช้ได้ 10 นาที\n"
            . "รหัสที่เคยได้ครั้งก่อนใช้ซ้ำไม่ได้ค่ะ\n\n"
            . "หากเจ้าหน้าที่เพิ่งให้รหัส กรุณาขอรหัสใหม่ แล้วพิมพ์อีกครั้งค่ะ"
            . "\n— — — — —\n"
            . "❌ This code cannot be used (invalid or expired)\n\n"
            . "ℹ️ No need to enter a code unless our staff gave you one today.\n"
            . "Codes are given only in person at the clinic, and are valid for 10 minutes.\n"
            . "A code from a previous visit cannot be reused.\n\n"
            . "If staff just gave you a code, please ask for a new one and enter it again.";
    }

    /** ลิงก์ฟอร์มผูกบัญชีใน LINE — null ถ้ายังไม่ได้ตั้ง LIFF หรือปิดใช้ฟอร์ม (ค่าตั้งต้น ตั้งแต่ 2569-10-31) */
    public static function formUrl(): ?string
    {
        if (! config('services.line.link.form_enabled')) { return null; }
        $id = config('services.line.link.liff_id');
        return $id ? "https://liff.line.me/{$id}" : null;
    }
}
