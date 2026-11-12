<?php

namespace App\Console\Commands;

use App\Models\Holiday;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Services\LineMessagingService;
use App\Utillity;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    protected $signature = 'reminders:send-appointments {--pretend : แสดงว่าจะส่งอะไรบ้างโดยไม่ส่งจริง}';

    protected $description = 'ส่งแจ้งเตือนนัดหมายทาง LINE — เตือนวันทำการก่อนหน้า และเตือนซ้ำเช้าวันนัด โดยข้ามวันที่คลินิกปิด';

    /**
     * เผื่อหาวันทำการถอยหลังได้ไกลสุดกี่วัน
     * วันหยุดยาวที่สุดของไทยคือสงกรานต์ 5 วัน + เสาร์อาทิตย์คร่อมหัวท้าย = 9 วัน
     * ตั้ง 14 ไว้เผื่อ ครม. ประกาศหยุดพิเศษเพิ่ม ซึ่งเกิดขึ้นจริงเกือบทุกปี
     */
    private const MAX_LOOKBACK_DAYS = 14;

    public function handle(LineMessagingService $line)
    {
        $today = Carbon::today();

        // คลินิกปิดวันนี้ -> ไม่ส่งอะไรทั้งนั้น
        // ข้อความบอกให้ "ติดต่อเจ้าหน้าที่" ซึ่งวันหยุดไม่มีใครรับสาย
        if (Holiday::isClinicClosed($today)) {
            $why = Holiday::nameForBE(Utillity::AD2BE($today->format('Y-m-d'))) ?? 'เสาร์-อาทิตย์';
            $this->info("วันนี้คลินิกปิด ({$why}) — ไม่ส่งแจ้งเตือนนัดหมาย");
            return 0;
        }

        $pretend = (bool) $this->option('pretend');
        $sent = 0;

        // ---------- 1) เตือนล่วงหน้าสำหรับ "วันทำการถัดไป" ----------
        // ของเดิมเตือน "พรุ่งนี้" ตายตัว ซึ่งหายไปทั้งดุ้นเมื่อพรุ่งนี้เป็นวันหยุด
        // เช่น ศุกร์ก่อนหยุดยาว 5 วัน คนไข้ที่นัดวันเปิดวันแรกจะไม่ได้รับอะไรเลย
        // เพราะรอบที่ควรเตือน (วันหยุดวันสุดท้าย) เครื่องปิดอยู่ ไม่มีใครรัน
        $nextOpen = $this->nextOpenDay($today);
        if ($nextOpen) {
            $sent += $this->sendReminders(
                $line, Utillity::AD2BE($nextOpen->format('Y-m-d')),
                $this->whenTh($today, $nextOpen), $this->whenEn($today, $nextOpen), $pretend
            );
        }

        // ---------- 2) เตือนซ้ำเช้าวันนัด เฉพาะนัดที่ข้ามวันหยุดมา ----------
        // เดิมทำเฉพาะวันจันทร์ ตอนนี้ทำทุกวันเปิดที่ "เมื่อวานคลินิกปิด"
        // ครอบคลุมทั้งจันทร์หลังเสาร์อาทิตย์ และวันเปิดวันแรกหลังหยุดยาวทุกแบบ
        // เหตุผลเดิมยังใช้ได้: เว้นวันหยุด คนไข้ลืมง่าย พยาบาลจึงขอให้เตือน 2 รอบ
        $yesterday = $today->copy()->subDay();
        if (Holiday::isClinicClosed($yesterday)) {
            $sent += $this->sendReminders(
                $line, Utillity::AD2BE($today->format('Y-m-d')), 'วันนี้', 'today', $pretend
            );
        }

        $this->info(($pretend ? '[ลองดูเฉยๆ] ' : '') . "ส่งแจ้งเตือนนัดหมายแล้ว {$sent} ราย");

        // เช็คโควต้าเฉพาะรอบที่มีการส่งจริง เพื่อไม่เปลือง API call
        if ($sent > 0 && ! $pretend) {
            $this->checkQuota($line);
        }

        return 0;
    }

    /**
     * วันทำการถัดไปนับจาก $from (ไม่รวมตัว $from เอง)
     * คืน null ถ้าหาไม่เจอใน MAX_LOOKBACK_DAYS วัน ซึ่งแปลว่าตารางวันหยุดกรอกผิด
     */
    private function nextOpenDay(Carbon $from): ?Carbon
    {
        $d = $from->copy();
        for ($i = 0; $i < self::MAX_LOOKBACK_DAYS; $i++) {
            $d->addDay();
            if (! Holiday::isClinicClosed($d)) {
                return $d;
            }
        }
        Log::warning('หาวันทำการถัดไปไม่เจอใน ' . self::MAX_LOOKBACK_DAYS . ' วัน — ตรวจตารางวันหยุดราชการ');
        return null;
    }

    /** คำบอกช่วงเวลาภาษาไทย — "พรุ่งนี้" ถ้าติดกันจริง ไม่งั้นบอกวันไปเลยจะได้ไม่เข้าใจผิด */
    private function whenTh(Carbon $today, Carbon $target): string
    {
        if ((int) $today->diffInDays($target, true) === 1) {
            return 'พรุ่งนี้';
        }
        $days = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        return 'วัน' . $days[(int) $target->format('w')]
             . ' ที่ ' . $target->format('d/m/') . ($target->year + 543);
    }

    private function whenEn(Carbon $today, Carbon $target): string
    {
        return (int) $today->diffInDays($target, true) === 1
            ? 'tomorrow'
            : 'on ' . $target->format('l j F Y');
    }

    /**
     * ส่งแจ้งเตือนให้คนไข้ที่มีนัดตรงวันที่กำหนด (เฉพาะที่ผูก LINE)
     * ข้อความ 2 ภาษา (ไทย + อังกฤษ) เผื่อคนไข้ต่างชาติ
     *
     * กันส่งซ้ำแบบ "วันละครั้ง" ไม่ใช่ "ครั้งเดียวจบ" — นัดที่ข้ามวันหยุดต้องได้ 2 รอบ
     * แต่ command รันทุก 30 นาที จึงต้องไม่ส่งซ้ำภายในวันเดียวกัน
     */
    private function sendReminders(
        LineMessagingService $line, string $appointmentBE,
        string $whenTh, string $whenEn, bool $pretend = false
    ): int {
        // กันพลาดชั้นสุดท้าย: ถ้าวันนัดเป็นวันหยุด อย่าเพิ่งส่ง
        // นัดเก่าที่ลงไว้ก่อนมีระบบวันหยุดยังค้างอยู่ในฐานข้อมูลจริง (หน้า /holiday แสดงให้เห็น)
        if (Holiday::isClosedBE($appointmentBE)) {
            $name = Holiday::nameForBE($appointmentBE);
            $this->warn("ข้ามนัดวันที่ {$appointmentBE} — ตรงกับ {$name} คลินิกปิด กรุณาเลื่อนนัดให้คนไข้");
            return 0;
        }

        // อ่านจากตารางนัด (นัดหลายวันต่อการมาหนึ่งครั้ง) แล้ว "รวมตามคนไข้"
        // คนไข้ที่มีหลายนัดในวันเดียวกันต้องได้ข้อความเดียว ไม่ใช่ข้อความละนัด
        // โควต้า LINE คิดเป็นจำนวนข้อความ ไม่คิดตามความยาว — รวมแล้วประหยัดโดยไม่เสียอะไร
        $appts = VisitAppointment::open()
            ->where('appointment_date', $appointmentBE)
            ->where(function ($q) {
                $q->whereNull('reminder_sent_at')
                  ->orWhereDate('reminder_sent_at', '<', now()->toDateString());
            })
            ->whereHas('patient', function ($q) {
                $q->whereNotNull('line_user_id');
            })
            ->with('patient')
            ->orderBy('id')
            ->get()
            ->groupBy('patient_id');

        // นัดของ "วันนี้" — ถ้าคนไข้ลงทะเบียนเข้ามาแล้ว ไม่ต้องเตือนซ้ำ
        //
        // เคสจริง 2569-08-31: คนไข้มาถึง 08:37 ตรวจเสร็จ พยาบาลบันทึกจบ 08:44
        // ระบบส่ง "ยืนยันนัดครั้งหน้า" ทันที แล้วรอบเตือน 09:01 ยังส่ง
        // "อย่าลืมมาวันนี้" ตามไปอีก ทั้งที่คนไข้กลับบ้านไปแล้ว
        // คนไข้เห็นนัดปีหน้ามาก่อนเตือนนัดวันนี้ อ่านแล้วงง
        //
        // ตัวกรอง open() ไม่พอ เพราะพยาบาลกดปิดนัดตอน 09:23 ซึ่งช้ากว่ารอบเตือน
        // "มีการลงทะเบียนของวันนี้" เป็นหลักฐานที่มาถึงก่อนและแน่นอนกว่า
        //
        // เช็คเฉพาะนัดวันนี้เท่านั้น — เตือนล่วงหน้าต้องส่งตามปกติ
        // ถึงคนไข้จะบังเอิญมาคลินิกวันนี้ด้วยเรื่องอื่นก็ตาม
        if ($appointmentBE === Utillity::AD2BE(now()->format('Y-m-d')) && $appts->isNotEmpty()) {
            // ->keys() คืน Collection ส่ง whereIn ตรง ๆ แล้ว Eloquent ตีความผิด ต้อง ->all()
            $cameToday = Visit::where('date', $appointmentBE)
                ->whereIn('patient_id', $appts->keys()->all())
                ->pluck('patient_id')
                ->unique()
                ->values();

            if ($cameToday->isNotEmpty()) {
                foreach ($cameToday as $pid) {
                    $rows = $appts->get($pid);
                    if (! $rows) { continue; }
                    // ถือว่าเตือนแล้ว จะได้ไม่วนมาถามใหม่ทุกรอบ 30 นาที
                    VisitAppointment::whereIn('id', $rows->pluck('id'))
                        ->update(['reminder_sent_at' => now()]);
                    $this->line('  ข้าม ' . ($rows->first()->patient->code ?? $pid) . ' — ลงทะเบียนมาแล้ววันนี้');
                }
                // ใช้ reject ไม่ใช่ except — Eloquent\Collection::except() เรียก getKey()
                // ของแต่ละสมาชิก แต่สมาชิกที่นี่เป็น "กลุ่มของนัด" ไม่ใช่โมเดล จะระเบิด
                $appts = $appts->reject(function ($rows, $pid) use ($cameToday) {
                    return $cameToday->contains($pid);
                });
            }
        }

        $sent = 0;

        foreach ($appts as $rows) {
            $patient = $rows->first()->patient;
            // ใช้ HN แทนชื่อ-สกุล — ข้อความเด้งขึ้นหน้าจอล็อกให้คนรอบตัวเห็นได้
            // เหตุผลเต็มอยู่ที่ LineMessagingService::patientRef()
            $ref = LineMessagingService::patientRef($patient->code ?? '');

            // นัดหลายรายการในวันเดียว -> ลิสต์ให้ครบในข้อความเดียว
            // เว้นวรรคหน้าจุดกลม เพื่อให้อ่านง่ายบนมือถือ
            $reasons = $rows->pluck('reason')->filter(function ($r) { return trim((string) $r) !== ''; })
                            ->map(function ($r) { return '• ' . trim($r, " ,"); })->implode("\n");

            $text = "🔔 แจ้งเตือนนัดหมาย\n"
                . ($ref !== '' ? "{$ref}\n" : '')
                . "ท่านมีนัดที่ศูนย์บริการเวชศาสตร์ป้องกัน {$whenTh}\n"
                . ($reasons !== '' ? "{$reasons}\n" : '')
                . "กรุณามาตามนัดหมาย หากติดขัดกรุณาติดต่อเจ้าหน้าที่"
                . LineMessagingService::askResultsLine($whenTh)
                . "\n"
                . "— — — — —\n"
                . "🔔 Appointment Reminder\n"
                . ($ref !== '' ? "{$ref}\n" : '')
                . "You have an appointment at the Preventive Medicine Service Center {$whenEn}.\n"
                . "Please arrive on time. For any concerns, please contact our staff."
                . LineMessagingService::askResultsLineEn($whenEn);

            if ($pretend) {
                $this->line("  [ลองดู] {$ref} — {$whenTh} (" . $rows->count() . " นัด)");
                $sent++;
                continue;
            }

            if ($line->push($patient->line_user_id, $text, ['patient_id' => $patient->id, 'purpose' => 'appointment_reminder'])) {
                // mark รายนัด ไม่ใช่ราย visit — visit หนึ่งใบมีหลายนัดคนละวันได้
                VisitAppointment::whereIn('id', $rows->pluck('id'))->update(['reminder_sent_at' => now()]);
                $sent++;
            }
        }

        return $sent;
    }

    private function checkQuota(LineMessagingService $line): void
    {
        $quota = $line->getQuotaStatus();
        if (! $quota) {
            return;
        }

        Cache::put('line_quota_status', [
            'limit' => $quota['limit'],
            'used' => $quota['used'],
            'checked_at' => now(),
        ], now()->addDays(35));

        $percent = $quota['limit'] > 0 ? $quota['used'] / $quota['limit'] * 100 : 0;
        if ($percent >= 80) {
            Log::warning("LINE push quota ใกล้เต็มแล้ว: ใช้ไป {$quota['used']}/{$quota['limit']} ข้อความ ({$percent}%) เดือนนี้");
        }
    }
}
