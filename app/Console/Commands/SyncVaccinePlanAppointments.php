<?php

namespace App\Console\Commands;

use App\Models\Visit;
use App\Models\VisitAppointment;
use Illuminate\Console\Command;

/**
 * ลงวันในแผนวัคซีน (TMC) ที่ยังไม่ถึง เป็นนัดในตารางวันนัด — ใช้ครั้งเดียวกับแผนที่วางไว้ก่อนมีระบบลงนัดให้ (2569-10-28)
 * แผนที่แก้หลังจากนี้ ระบบลงให้เองตอนบันทึกแผน (VisitAppointment::syncVaccinePlan)
 *   php artisan clinic:sync-vaccine-plan-appts          ดูอย่างเดียว ว่าจะลงกี่นัด
 *   php artisan clinic:sync-vaccine-plan-appts --apply  ลงจริง
 */
class SyncVaccinePlanAppointments extends Command
{
    protected $signature = 'clinic:sync-vaccine-plan-appts {--apply : ลงนัดจริง (ไม่ใส่ = ดูอย่างเดียว)}';
    protected $description = 'ลงวันในแผนวัคซีน TMC ที่ยังไม่ถึง เป็นนัดในตารางวันนัด';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $today = VisitAppointment::todayBe();
        $total = 0;
        foreach (Visit::whereNotNull('tmc_vaccine_plan')->orderBy('id')->get() as $v) {
            $plan = \App\Models\VisitVaccine::normalizePlan($v->tmc_vaccine_plan);
            $todo = [];
            foreach ($plan as $r) {
                $be = trim((string) ($r['date'] ?? '')) !== '' ? \App\Utillity::th2dbDate($r['date']) : null;
                if (! $be || $be < $today) { continue; }
                $has = VisitAppointment::where('visit_id', $v->id)->where('appointment_date', $be)->whereNull('closed_at')->exists();
                if (! $has) { $todo[] = $r['date']; }
            }
            if (! $todo) { continue; }
            $total += count($todo);
            $this->line('visit ' . $v->id . ' (HN ' . optional($v->patient2)->code . '): ' . implode(', ', $todo));
            if ($apply) { VisitAppointment::syncVaccinePlan($v); }
        }
        $this->info(($apply ? 'ลงนัดแล้ว ' : 'จะลงนัด ') . $total . ' นัด' . ($apply ? '' : ' — ใส่ --apply เพื่อลงจริง'));
        return 0;
    }
}
