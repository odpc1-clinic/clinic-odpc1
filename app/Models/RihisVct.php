<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * หนึ่งแถวของทะเบียน RIHIS-VCT
 * ตัวเลือกทุกชุดคัดลอกตรงจากหัวตารางในไฟล์ฟอร์ม RIHIS-VCT v1.07
 * (ห้ามเปลี่ยนรหัสตัวเลขเอง — ต้องตรงกับที่ส่วนกลางกำหนด ไม่งั้นส่งรายงานแล้วแปลผิด)
 */
class RihisVct extends Model
{
    use Auditable;

    protected $table = 'rihis_vct';

    protected $guarded = ['id'];

    public function auditLabel(): string
    {
        return 'RIHIS-VCT: ' . trim($this->name . ' ' . $this->surname) . ' (HN ' . $this->hn . ')';
    }

    // ===== ตัวเลือกตามฟอร์ม RIHIS =====

    public static $sexOption = [1 => 'ชาย', 2 => 'หญิง'];

    public static $maritalOption = [
        1 => 'โสด', 2 => 'มีคู่ อยู่ด้วยกัน', 3 => 'มีคู่ แยกกันอยู่',
        4 => 'หย่าร้าง/เลิกกัน', 5 => 'หม้าย',
    ];

    public static $nationalityOption = [
        1 => 'ไทย', 2 => 'พม่า', 3 => 'ลาว', 4 => 'กัมพูชา', 5 => 'อื่นๆ',
    ];

    public static $targetGroupOption = [
        1 => 'MSM', 2 => 'FSW', 3 => 'PWID', 4 => 'Migrants',
        5 => 'Prisoner', 6 => 'เยาวชน', 7 => 'ประชาชนทั่วไป',
        8 => 'คู่ประจำของกลุ่มประชากรหลัก',
    ];

    /** กลุ่มย่อย MSW — ดึงจาก MSM/TG ที่ทำอาชีพพิเศษ */
    public static $msmSubOption = [1 => 'MSM', 2 => 'TG', 3 => 'MSW', 4 => 'TGSW'];

    public static $swModeOption = [1 => 'Venue', 2 => 'Non Venue'];

    public static $referralOption = [0 => 'ไม่มี', 1 => 'มี'];

    /** ประวัติการตรวจ HIV (คอลัมน์ U ของฟอร์ม) */
    public static $pastServiceOption = [
        0 => 'ไม่เคยตรวจในชีวิต',
        1 => 'ใหม่ (เคยรับบริการ แต่เป็นครั้งแรกในรอบปี)',
        2 => 'เก่า (ครั้งที่ 2 เป็นต้นไปในรอบปี)',
    ];

    public static $yesNoOption = [0 => 'ไม่ได้', 1 => 'ได้'];

    public static $postTestOption = [0 => 'ยังไม่ได้ฟังผล', 1 => 'ได้ฟังผล'];

    public static $bloodResultOption = [1 => 'ผลเลือดลบ', 2 => 'ผลเลือดบวก', 3 => 'ผลไม่แน่ชัด'];

    public static $referralTypeOption = [
        1 => 'เครือข่ายบริการเชิงรุก', 2 => 'สื่อสังคมอิเลคทรอนิคส์',
        3 => 'เครือข่ายร้านขายยา', 4 => 'อื่นๆ',
    ];

    public static $referrerOption = [
        1 => 'มาเองโดยไม่มีใครแนะนำ', 2 => 'สื่อ Buddy station', 3 => 'เครือข่ายร้านขายยา',
        4 => 'แผ่นพับ ป้ายประชาสัมพันธ์', 5 => 'เครือข่ายบริการสุขภาพ GO',
        6 => 'เพื่อน/คนรู้จัก/ครอบครัวแนะนำ', 7 => 'เครือข่ายบริการเชิงรุก NGO แกนนำ/อาสาสมัคร',
        8 => 'อื่นๆ (ระบุ)',
    ];

    public static $testMethodOption = [
        1 => 'ตรวจแบบทราบผลในวันเดียว (SDR)',
        2 => 'ตรวจแบบกลับมาฟังผลหลังวันที่ตรวจ',
    ];

    public static $sentOption = [0 => 'ไม่ได้ส่ง', 1 => 'ได้ส่ง'];

    public static $apptOption = [0 => 'ไม่ได้นัดซ้ำ', 1 => 'นัดตรวจซ้ำ'];

    /**
     * ช่องที่ยัง "รอกรอก" — เอาไปทำแถบสีในตาราง จะได้รู้ว่าต้องตามอะไรต่อ
     *
     * กติกาที่ผู้ใช้ให้มา: ถ้ายังไม่ได้ฟังผลเลือด (post_test = 0)
     * ให้มาร์กช่องผลเลือด/วันฟังผลไว้ว่ารอ ไม่ใช่ปล่อยว่างเงียบๆ
     */
    public function pendingFields(): array
    {
        $pending = [];

        foreach (['service_date', 'name', 'surname', 'hn', 'sex', 'target_group'] as $f) {
            if ($this->$f === null || $this->$f === '') { $pending[] = $f; }
        }

        if ($this->post_test === null || (int) $this->post_test === 0) {
            // ยังไม่ฟังผล = ผลเลือดกับวันฟังผลยังไม่ควรมี ให้ค้างไว้เป็น "รอ"
            $pending[] = 'post_test';
            $pending[] = 'blood_result';
            $pending[] = 'result_date';
        } else {
            if (!$this->blood_result) { $pending[] = 'blood_result'; }
            if (!$this->result_date)  { $pending[] = 'result_date'; }
        }

        // ผลบวก = ต้องมีการติดตามต่อเสมอ มาร์กช่องตั้งแต่ผลเลือดไปจนจบแถว
        // ให้เห็นว่ายังกรอกไม่ครบ (พี่อิ๋วขอ 2569-08-05)
        if ((int) $this->blood_result === 2) {
            foreach (['test_method', 'refer_other', 'cd4_sent', 'repeat_date', 'note'] as $f) {
                if ($this->$f === null || $this->$f === '') { $pending[] = $f; }
            }
            // ค่า CD4 จะรอกรอกก็ต่อเมื่อบอกว่าส่งตรวจ CD4 แล้ว
            if ((int) $this->cd4_sent === 1 && trim((string) $this->cd4) === '') {
                $pending[] = 'cd4';
            }
        }

        return array_values(array_unique($pending));
    }

    /** UIC — ผู้ใช้ยืนยันว่าใช้ชื่อ-สกุลเต็มได้ ไม่ต้องเข้ารหัสแบบในไฟล์ Excel */
    public function uicText(): string
    {
        return trim((string) $this->uic) !== ''
            ? $this->uic
            : trim(($this->name ?? '') . ' ' . ($this->surname ?? ''));
    }
}
