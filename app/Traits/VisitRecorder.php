<?php
namespace App\Traits;

use App\Models\Visit;

trait VisitRecorder
{
    /**
     * ปั๊มชื่อผู้บันทึกตามกติกา
     * ctx:
     *   - source: 'registration'|'nurse'|'doctor'|null   (ถ้า null จะเดาจาก role)
     *   - status: int|null   (เช่น 2,8,3 สำหรับห้องตรวจ/ฉีดยา)
     *   - sets_lab: bool     (true ถ้าคือการ "ส่งแล็บ" โดยพยาบาล)
     *   - overwrite: bool    (true=เขียนทับ, false=เขียนเฉพาะถ้ายังว่าง)
     */
    protected function stampByRules(Visit $visit, array $ctx = []): void
    {
        if (!auth()->check()) return;

        $u   = auth()->user();
        $has = fn(string $role) => method_exists($u,'hasRole') ? $u->hasRole($role) : false;

        $status    = $ctx['status']   ?? $visit->status;
        $setsLab   = (bool)($ctx['sets_lab'] ?? false);
        $overwrite = array_key_exists('overwrite',$ctx) ? (bool)$ctx['overwrite'] : true;

        // ตีความ source (ถ้าไม่ได้ส่งมา)
        $source = $ctx['source'] ?? null;
        if (!$source) {
            if ($has('Registrar')) $source = 'registration';
            // ถ้ามีทั้ง Nurse และ Doctor — ให้ nurse มาก่อน (เพื่อเคส sets_lab)
            if ($has('Nurse'))     $source = 'nurse';
            if ($has('Doctor') && !$has('Nurse')) $source = 'doctor';
        }

        $payload = [];
        $put = function(string $attr, $val) use ($visit, $overwrite, &$payload) {
            $cur = $visit->getAttribute($attr);
            if ($overwrite || is_null($cur)) $payload[$attr] = $val;
        };

        if ($source === 'registration') {
            $put('registration_by', $u->id);
        }

        // เฉพาะห้อง 2 / 8 / 3 เท่านั้นที่นับเป็นงานพยาบาล/แพทย์
        $isClinicRoom = in_array((int)$status, [2,8,3], true);

        if ($isClinicRoom) {
            if ($source === 'nurse' && $has('Nurse')) {
                $put('nurse_by', $u->id);
                if ($setsLab) {
                    // เคสส่งแล็บ: ใช้พยาบาลเป็นผู้ส่ง
                    $put('lab_by', $u->id);
                }
            }
            if ($source === 'doctor' && $has('Doctor')) {
    $put('doctor_by', $u->id);
}
        }

        // คนที่แตะล่าสุดเสมอ
        $payload['handled_by'] = $u->id;

        if ($payload) {
            $visit->fill($payload);
            $visit->save();
        }
    }
    
}
