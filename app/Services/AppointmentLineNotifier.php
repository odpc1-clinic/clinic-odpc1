<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Services\LineMessagingService;
use Illuminate\Support\Facades\Log;

/**
 * แจ้งนัดหมายทาง LINE "ทันที" (ยืนยันนัด) — คนละอย่างกับ SendAppointmentReminders
 * ซึ่งเป็นตัวเตือนล่วงหน้าตามรอบเวลา
 *
 * ยิงเมื่อ:
 *   1) คนไข้พิมพ์รหัสผูก LINE สำเร็จ  -> บอกนัดที่ยังมาไม่ถึงให้รู้ทันที
 *   2) พยาบาลบันทึก/แก้วันนัดในหน้า visit -> เด้งยืนยันให้คนไข้เลย
 *
 * ทุก method ห่อ try/catch: LINE ล่มหรือเน็ตมีปัญหา ต้องไม่ทำให้ Save ของพยาบาลพัง
 */
class AppointmentLineNotifier
{
    /** ชื่อวันไทย (0 = อาทิตย์ ตาม Carbon::dayOfWeek) */
    private const TH_DAYS = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];

    private const TH_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    public function __construct(private LineMessagingService $line) {}

    /**
     * sendOnDischarge แบบไม่ให้เจ้าหน้าที่รอ (2569-10-20) — ปุ่มกลับบ้านตอบทันที แล้วค่อยยิง LINE หลังส่งหน้าเสร็จ
     * เดิมต้องรอ LINE API ตอบ (ไป-กลับต่างประเทศ ช้าเมื่อเน็ตช้า) ก่อนหน้าถึงจะเปลี่ยน
     * เงื่อนไข/กันส่งซ้ำยังอยู่ใน sendOnDischarge ที่เดียว · อ่าน visit ใหม่ตอนส่งจริง จะได้ค่าล่าสุดหลังบันทึกเสร็จทั้งหมด
     */
    public function sendOnDischargeLater(Visit $visit): void
    {
        if (! \App\Enums\VisitStatus::Home->is($visit->status) || $visit->appointment_confirm_sent_at) { return; }   // ไม่ต้องส่ง — ไม่ต้องจองงานเบื้องหลัง
        $id = $visit->id;
        $run = function () use ($id) {
            if ($v = Visit::find($id)) { $this->sendOnDischarge($v); }
        };
        app()->runningInConsole() ? $run() : defer($run);
    }

    /**
     * ยิงยืนยันนัดตอน "กดกลับบ้าน" (status 6)
     *
     * เลือกจังหวะนี้เพราะข้อมูลนิ่งแล้ว — ถ้ายิงตอนบันทึกวันนัด พยาบาล/หมอแก้วันนัดกี่รอบ
     * คนไข้ก็ได้ข้อความเท่านั้นรอบ สับสนเอง แถมโควตา LINE มีจำกัด (ผู้ใช้เลือกเอง 2026-08-05)
     *
     * กันส่งซ้ำด้วย appointment_confirm_sent_at เพราะปุ่มกลับบ้านกดซ้ำได้
     * (ค่านี้ถูกล้างเมื่อวันนัดเปลี่ยน จึงยืนยันนัดใหม่ได้อีกครั้ง)
     */
    public function sendOnDischarge(Visit $visit): bool
    {
        try {
            if (! \App\Enums\VisitStatus::Home->is($visit->status))      { return false; }   // ยังไม่กลับบ้าน
            if ($visit->appointment_confirm_sent_at) { return false; }   // ยืนยันนัดนี้ไปแล้ว

            // จองสิทธิ์ส่งก่อน แล้วค่อยส่งจริง
            //
            // ของเดิมเช็คธงแล้วค่อยส่ง แล้วจึงเขียนธง ระหว่างนั้นมีช่องว่างให้คำขออีกอันแทรกได้
            // เช่น ห้องยากดกลับบ้าน พร้อมกับหน้าตรวจกด Save (หรือกดปุ่มรัว ๆ) — ทั้งคู่เห็นธงว่าง
            // เลยส่งคนละรอบ คนไข้ได้ข้อความยืนยันนัดสองครั้งติดกัน (เจอจริง ห่างกัน 1 วิ)
            //
            // UPDATE ... WHERE ยังว่างอยู่ ทำให้ MySQL ตัดสินให้ว่าใครได้สิทธิ์ส่ง คนที่แพ้ได้ 0 แถวและถอยออก
            // ใช้ query builder ตรง ๆ ไม่ผ่าน Eloquent เพราะ Visit มี model event ที่ไปซิงก์คิว/บิลต่อ
            $claimed = \Illuminate\Support\Facades\DB::table('visits')
                ->where('id', $visit->id)
                ->whereNull('appointment_confirm_sent_at')
                ->update(['appointment_confirm_sent_at' => now()]);
            if (!$claimed) { return false; }   // อีกคำขอหนึ่งชิงส่งไปแล้ว

            if (!$this->sendConfirmation($visit)) {
                // ส่งไม่ออก (LINE ล่ม / คนไข้ยังไม่ผูก / ไม่มีนัด) -> คืนธงให้รอบหน้าลองใหม่ได้
                \Illuminate\Support\Facades\DB::table('visits')
                    ->where('id', $visit->id)
                    ->update(['appointment_confirm_sent_at' => null]);
                return false;
            }

            $visit->appointment_confirm_sent_at = now();   // ให้ object ในมือผู้เรียกตรงกับใน DB
            return true;
        } catch (\Throwable $e) {
            Log::warning('ส่งยืนยันนัดตอนกลับบ้านไม่สำเร็จ: ' . $e->getMessage(), ['visit_id' => $visit->id ?? null]);
            return false;
        }
    }

    /**
     * ส่งยืนยันนัดของ visit หนึ่งใบ
     * คืน true เมื่อส่งจริง
     */
    public function sendConfirmation(Visit $visit): bool
    {
        try {
            $patient = $visit->patient2;
            if (!$patient || empty($patient->line_user_id)) { return false; }   // ยังไม่ผูก LINE

            $when = $this->parseBe($visit->appointment);
            if (!$when || $when->lt(\Carbon\Carbon::today())) { return false; } // ไม่มีนัด/นัดผ่านไปแล้ว

            // ใช้ HN แทนชื่อ-สกุล — ข้อความเด้งขึ้นหน้าจอล็อกให้คนรอบตัวเห็นได้
            // เหตุผลเต็มอยู่ที่ LineMessagingService::patientRef()
            $ref = LineMessagingService::patientRef($patient->code ?? '');

            // ใช้ appointment_reason (ช่องที่มีตัวเลือกสำเร็จรูป คนไข้อ่านได้)
            // ห้ามใช้ appointment_staff_note เด็ดขาด — ช่องนั้นพยาบาลเขียนส่งเวรกันเอง
            // และคลินิกนี้เป็นคลินิกโรคติดต่อทางเพศสัมพันธ์ ข้อความจึงอาจมีเรื่องละเอียดอ่อน
            // ที่เด้งขึ้นหน้าจอโทรศัพท์คนไข้ให้คนรอบตัวเห็นได้
            // นัดหลายวันต่อการมาหนึ่งครั้ง -> ลิสต์ให้ครบใน "ข้อความเดียว"
            // ไม่ใช่ยิงข้อความละนัด เพราะโควต้า LINE คิดตามจำนวนข้อความ (300/เดือน)
            // แต่ไม่คิดตามความยาว การลิสต์เพิ่มจึงไม่มีค่าใช้จ่าย
            $appts = VisitAppointment::open()->where('visit_id', $visit->id)
                ->where('appointment_date', '>=', VisitAppointment::todayBe())
                ->orderBy('appointment_date')->orderBy('id')->get();

            $lines = [];
            foreach ($appts as $a) {
                $d = $this->parseBe($a->appointment_date);
                if (!$d) { continue; }
                // ห้ามเอา staff_note มาต่อเด็ดขาด ช่องนั้นพยาบาลเขียนส่งเวรกันเอง
                $r = trim((string) ($a->reason ?? ''), " ,");
                $lines[] = '• วัน' . $this->thaiFullDate($d) . ($r !== '' ? " — {$r}" : '');
            }
            $note = implode("\n", $lines);

            // นัดเดียว -> พูดเป็นประโยคเหมือนเดิม อ่านลื่นกว่า
            // หลายนัด -> ขึ้นหัวว่ามีกี่รายการ แล้วลิสต์ ไม่งั้นวันแรกจะซ้ำสองรอบ
            $many  = $appts->count() > 1;
            $head  = $many
                ? 'ท่านมีนัด ' . $appts->count() . ' รายการ'
                : 'ท่านมีนัดวัน' . $this->thaiFullDate($when);
            $headEn = $many
                ? 'You have ' . $appts->count() . ' upcoming appointments.'
                : 'Your appointment is on ' . $when->format('d/m/Y') . '.';

            $text = "📅 ยืนยันนัดหมาย\n"
                . ($ref !== '' ? "{$ref}\n" : '')
                . "{$head}\n"
                . ($note !== '' ? "{$note}\n" : '')
                . "ณ ศูนย์บริการเวชศาสตร์ป้องกัน\n"
                . "ระบบจะแจ้งเตือนอีกครั้งก่อนถึงวันนัดค่ะ"
                . LineMessagingService::askResultsLine('วัน' . $this->thaiFullDate($when))
                . "\n"
                . "— — — — —\n"
                . "📅 Appointment Confirmed\n"
                . ($ref !== '' ? "{$ref}\n" : '')
                . "{$headEn}\n"
                . "Preventive Medicine Service Center. We will remind you again before the date."
                . LineMessagingService::askResultsLineEn('on ' . $when->format('d/m/Y'));

            return $this->line->push($patient->line_user_id, $text, [
                'patient_id' => $patient->id,
                'visit_id'   => $visit->id,
                'purpose'    => 'appointment_confirmation',
            ]);
        } catch (\Throwable $e) {
            Log::warning('ส่งยืนยันนัดทาง LINE ไม่สำเร็จ: ' . $e->getMessage(), ['visit_id' => $visit->id ?? null]);
            return false;
        }
    }

    /**
     * ส่งสรุป "นัดที่ยังมาไม่ถึง" ทั้งหมดของคนไข้ (ใช้ตอนเพิ่งผูก LINE สำเร็จ)
     * คืนข้อความที่ส่ง หรือ '' ถ้าไม่มีนัด — ผู้เรียกเอาไปต่อท้ายข้อความผูกสำเร็จได้
     */
    public function upcomingSummary(Patient $patient): string
    {
        try {
            $todayBe = (now()->year + 543) . now()->format('-m-d');

            $visits = Visit::where('patient_id', $patient->id)
                ->whereNotNull('appointment')
                ->where('appointment', '>=', $todayBe)
                ->orderBy('appointment')
                ->limit(3)
                // ไม่ดึง appointment_staff_note มาด้วย — ข้อความนี้ส่งเข้า LINE คนไข้
                // เหตุผลเดียวกับใน sendConfirmation() ช่องนั้นพยาบาลใช้ส่งเวรกันเอง
                ->get(['id', 'appointment', 'appointment_reason']);

            if ($visits->isEmpty()) { return ''; }

            $lines = [];
            foreach ($visits as $v) {
                $when = $this->parseBe($v->appointment);
                if (!$when) { continue; }
                $note = trim((string) ($v->appointment_reason ?? ''));
                $lines[] = '• วัน' . $this->thaiFullDate($when) . ($note !== '' ? " ({$note})" : '');
            }
            if (!$lines) { return ''; }

            return "\n\n📅 นัดหมายของคุณ\n" . implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('อ่านนัดหมายเพื่อแจ้งตอนผูก LINE ไม่สำเร็จ: ' . $e->getMessage());
            return '';
        }
    }

    /** "จันทร์ที่ 10 สิงหาคม 2569" — อ่านง่ายกว่าเลขล้วน ลดโอกาสคนไข้อ่านวันผิด */
    private function thaiFullDate(\Carbon\Carbon $d): string
    {
        return self::TH_DAYS[$d->dayOfWeek] . 'ที่ ' . $d->day . ' '
             . (self::TH_MONTHS[$d->month] ?? '') . ' ' . ($d->year + 543);
    }

    /** วันที่ พ.ศ. (Y-m-d หรือ d/m/Y) -> Carbon ค.ศ. ; คืน null ถ้าว่าง/เสีย */
    private function parseBe($value): ?\Carbon\Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0000-00-00') { return null; }

        $y = $m = $d = null;
        if (strpos($raw, '/') !== false) {
            $p = explode('/', $raw);
            if (count($p) === 3) { [$d, $m, $y] = [(int) $p[0], (int) $p[1], (int) $p[2]]; }
        } elseif (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $mm)) {
            [$y, $m, $d] = [(int) $mm[1], (int) $mm[2], (int) $mm[3]];
        }
        if (!$y || !$m || !$d) { return null; }

        if ($y > 2400) { $y -= 543; }
        try { return \Carbon\Carbon::create($y, $m, $d, 0, 0, 0); } catch (\Throwable $e) { return null; }
    }
}
