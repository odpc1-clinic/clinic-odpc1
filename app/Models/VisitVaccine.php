<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

/**
 * ประวัติการฉีดวัคซีน (คลินิก TMC)
 *
 * ผูกกับ "คนไข้" เป็นหลัก ส่วน visit_id ว่างได้ เพราะต้องเก็บประวัติที่ฉีดมาจากที่อื่น
 * หรือที่นำเข้าจากระบบเดิม (Medivel) ซึ่งไม่มี visit ในระบบนี้
 *
 * ชื่อ/lot/ผู้ผลิต เก็บเป็น snapshot ไม่ผูกกับตาราง medicines ตลอดไป
 * เพราะใบรับรองที่ออกไปแล้วต้องคงข้อความเดิมเสมอ ถึงจะแก้ชื่อยาภายหลังก็ตาม
 */
class VisitVaccine extends Model
{
    use Auditable;

    protected static function booted()
    {
        // ลงว่าคลินิกให้วัคซีนใน visit นี้ → ติ๊ก "ต้องการฉีดวัคซีน" ในสาเหตุการมาให้ (คลินิกขอ 2569-11-03)
        static::created(function (VisitVaccine $v) {
            try {
                if ($v->visit_id && self::isGiven($v->source)) { \App\Models\Visit::ensureTmcVaccineReason($v->visit_id); }
            } catch (\Throwable $e) { report($e); }
        });
    }

    protected $fillable = [
        'patient_id', 'visit_id', 'medicine_id', 'medicine_lot_id',
        'name', 'lot_number', 'manufacturer',
        'dose_number', 'site', 'route',
        'vaccinated_date', 'vaccinated_time', 'next_due_date',
        'source', 'injected_by', 'note',
    ];

    protected $casts = [
        'vaccinated_date' => 'date',
        'next_due_date'   => 'date',
    ];

    /** 1 = ฉีดที่คลินิกนี้, 2 = ประวัติเดิม/ฉีดที่อื่น, 3 = คลินิกจ่ายให้ไปทานเอง (วัคซีนชนิดกิน) */
    public const HERE    = 1;
    public const HISTORY = 2;
    /**
     * วัคซีนชนิดกิน (อหิวาตกโรค Euvichol-Plus) เข็ม 2 ที่จ่ายให้คนไข้ไปทานเองที่บ้าน (คลินิกตัดสิน 2569-11-03)
     * ผูกกับ visit ที่มารับ · ตัดสต๊อกและคิดเงินตอนจ่าย (ราคาต่อโดส — ซื้อ 2 โดสจ่าย 2 โดส)
     * ใบรับรองขึ้นในส่วนที่คลินิกให้ พร้อมหมายเหตุว่าคนไข้ทานเอง · รายงาน TMC นับในเดือนที่จ่าย
     * เดิมพยาบาลลงเป็น "ประวัติเดิม" ทำให้ใบรับรองบอกว่าศูนย์ไม่ได้ให้ และไม่ตัดสต๊อก
     */
    public const TAKE_HOME = 3;

    /** คลินิกเป็นผู้ให้วัคซีนเข็มนี้ (ฉีดเอง หรือจ่ายให้ไปทาน) — ตัดสต๊อก/ขึ้นใบรับรอง */
    public static function isGiven($source): bool
    {
        return in_array((int) $source, [self::HERE, self::TAKE_HOME], true);
    }

    /**
     * Lot ที่พยาบาลเคยพิมพ์ไว้ (คลินิก TMC ขอ 2569-10-14 — ห้องคลังยายังไม่ได้ลงล็อตในระบบ)
     * ต่อยา: ล่าสุดก่อน ไม่ซ้ำ สูงสุด 5 ค่า · รวมทั้งวัคซีนที่ฉีด (ไม่นับประวัติจากที่อื่น) และรายการยาที่ลง Lot
     * คืน [medicine_id => [lot, ...]]
     */
    public static function recentLots(?array $medicineIds = null): array
    {
        $vv = self::query()->whereNotNull('medicine_id')->whereNotNull('lot_number')->where('lot_number', '!=', '')
            ->where(fn ($q) => $q->where('source', '!=', self::HISTORY)->orWhereNull('source'));
        $vm = VisitMedicine::query()->whereNotNull('lot_number')->where('lot_number', '!=', '');
        if ($medicineIds !== null) { $vv->whereIn('medicine_id', $medicineIds); $vm->whereIn('medicine_id', $medicineIds); }
        $rows = $vv->get(['medicine_id', 'lot_number', 'created_at'])
            ->concat(VisitMedicine::lotReady() ? $vm->get(['medicine_id', 'lot_number', 'created_at']) : collect())
            ->sortByDesc('created_at');
        $out = [];
        foreach ($rows as $r) {
            $lot = trim((string) $r->lot_number);
            $k = (string) $r->medicine_id;
            if ($lot === '' || in_array($lot, $out[$k] ?? [], true) || count($out[$k] ?? []) >= 5) { continue; }
            $out[$k][] = $lot;
        }
        return $out;
    }

    public static $sourceOption = [self::HERE => 'ฉีดที่คลินิกนี้', self::TAKE_HOME => 'จ่ายให้ไปทานเอง (ชนิดกิน)', self::HISTORY => 'ประวัติเดิม/ที่อื่น'];

    /**
     * ชื่อที่พิมพ์บนใบรับรองวัคซีน — ชุดมาตรฐานที่ TMC กำหนด (2569-11-10): ไม่มีคำว่า vaccine ต่อท้าย ไม่ใส่ยี่ห้อ
     * (ยกเว้น "Pneumococcal Conjugate Vaccine" ที่คลินิกเขียนแบบนี้เอง)
     * ยี่ห้อต่างกันแต่วัคซีนเดียวกัน (Engerix-B / Hepatitis B vaccine) จึงรวมเป็นแถวเดียวในใบ
     * คีย์ = ส่วนภาษาอังกฤษของชื่อที่บันทึก (หลัง " / ") ตัวพิมพ์เล็ก · ไม่มีในรายการ = พิมพ์ตามชื่อที่บันทึก
     * มีทั้งชื่อในคลังยา และชื่อประวัติที่อื่นรุ่นแรก (ใช้วันเดียว 2569-11-10) ที่อาจบันทึกไปแล้ว
     * เพิ่มวัคซีนใหม่ในคลัง ให้เพิ่มบรรทัดที่นี่ด้วย
     */
    public const CERT_NAMES = [
        // ---- ชื่อในคลังยา ----
        'havrix 1440 (hepatitis a vaccine)'  => 'Hepatitis A (Inactivated)',
        'engerix-b vaccine'                  => 'Hepatitis B',
        'hepatitis b vaccine'                => 'Hepatitis B',
        'vaccine hepatitis b'                => 'Hepatitis B',
        'twinrix(hepa+b)'                    => 'Combined hepatitis A and B',
        'dt'                                 => 'Diphtheria and Tetanus (dT)',
        'tdap (boostagen)'                   => 'Tetanus, Diphtheria and acellular Pertussis (Tdap)',
        'recombinant zoster: rzv'            => 'Recombinant Zoster (RZV)',
        'rabies (chirorab)'                  => 'Rabies',
        'hpv (ไม่ระบุสายพันธุ์ - ประวัติเก่า)'   => 'HPV',
        'hpv4 (gardasil-4)'                  => 'HPV 4 strains',
        'hpv9 (gardasil-9)'                  => 'HPV 9 Strains',
        'mmr'                                => 'Measles Mumps Rubella (MMR)',
        'cholera (euvichol-plus)'            => 'Oral Cholera',
        'respiratory syncytial virus: rsv (arexvy)' => 'Respiratory Syncytial Virus (RSV)',
        'meningococcal meningitis (menactra)' => 'Meningococcal ACYW-135 Conjugate',
        'meningococcal type b (bexsero)'     => 'Meningococcal group B',
        'je'                                 => 'Japanese Encephalitis (JE)',
        'influenza (skycellflu)'             => 'Influenza',
        'influenza (influvac abbott)'        => 'Influenza',
        'polio vaccine (imovax polio)'       => 'Inactivated Polio (IPV)',
        'dengue (qdenga)'                    => 'Dengue',
        'yellow fever (stamaril)'            => 'Yellow Fever',
        // ---- ชื่อประวัติที่อื่นรุ่นแรก ----
        'bcg vaccine'                        => 'BCG',
        'diphtheria, tetanus and pertussis vaccine (dtp)' => 'Diphtheria, Tetanus and Pertussis',
        'poliomyelitis vaccine (opv/ipv)'    => 'Polio (OPV/IPV)',
        'haemophilus influenzae type b vaccine (hib)' => 'Haemophilus influenzae Type b (Hib)',
        'diphtheria, tetanus and pertussis, hepatitis b, poliomyelitis and haemophilus type b conjugate vaccine'
                                             => 'Diphtheria, Tetanus and Pertussis, Hepatitis B, Poliomyelitis and Haemophilus Type b conjugate',
        'diphtheria, tetanus and pertussis, poliomyelitis and haemophilus type b conjugate vaccine'
                                             => 'Diphtheria, Tetanus and Pertussis, Poliomyelitis and Haemophilus Type b conjugate',
        'diphtheria, tetanus and pertussis, poliomyelitis vaccine' => 'Diphtheria, Tetanus and Pertussis, Poliomyelitis',
        'pneumococcal conjugate vaccine (pcv)' => 'Pneumococcal Conjugate Vaccine',
        'pneumococcal polysaccharide vaccine (ppsv23)' => 'Pneumococcal Polysaccharide (PPSV23)',
        'rotavirus vaccine'                  => 'Rotavirus',
        'japanese encephalitis vaccine'      => 'Japanese Encephalitis (JE)',
        'varicella vaccine'                  => 'Varicella (Live attenuated)',
        'hepatitis a vaccine'                => 'Hepatitis A (Inactivated)',
        'influenza vaccine'                  => 'Influenza',
        'typhoid vaccine'                    => 'Typhoid',
        'human papillomavirus vaccine (hpv)' => 'HPV',
        'dengue vaccine'                     => 'Dengue',
        'meningococcal acyw-135 conjugate vaccine' => 'Meningococcal ACYW-135 Conjugate',
        'meningococcal group b vaccine'      => 'Meningococcal group B',
        'rabies vaccine'                     => 'Rabies',
    ];

    /** ชื่อบนใบรับรอง: ตัดภาษาไทยหน้า " / " ออก แล้วแปลงตาม CERT_NAMES */
    public static function certName(?string $name): string
    {
        $n = trim((string) $name);
        if (str_contains($n, ' / ')) { $n = trim(substr($n, strpos($n, ' / ') + 3)); }
        return self::CERT_NAMES[mb_strtolower($n)] ?? $n;
    }

    /**
     * ชื่อวัคซีนกลางสำหรับลง "ประวัติเดิม/ที่อื่น" — ไม่ผูกคลังยา ไม่คิดเงิน (เก็บแค่ชื่อ medicine_id ว่าง)
     * คนไข้มาขอใบรับรองโดยถือสมุดวัคซีนเด็กมา (BCG, Rota, วัคซีนรวม ฯลฯ) ซึ่งคลินิกไม่ได้มีในคลัง (TMC ขอ 2569-11-10)
     * ส่วนอังกฤษ = ชื่อมาตรฐานบนใบรับรองตามที่ TMC กำหนด (พิมพ์ตรง ๆ ไม่ต้องแปลง)
     * ตัวที่คลังมีอยู่แล้ว (Hepatitis B, MMR, HPV 4/9, Combined A+B) เลือกจากคลังได้ ไม่ซ้ำไว้ที่นี่
     */
    public const HISTORY_NAMES = [
        'วัณโรค / BCG',
        'คอตีบ บาดทะยัก ไอกรน / Diphtheria, Tetanus and Pertussis',
        'วัคซีนรวม 4 โรค / Diphtheria, Tetanus and Pertussis, Poliomyelitis',
        'วัคซีนรวม 5 โรค / Diphtheria, Tetanus and Pertussis, Poliomyelitis and Haemophilus Type b conjugate',
        'วัคซีนรวม 6 โรค / Diphtheria, Tetanus and Pertussis, Hepatitis B, Poliomyelitis and Haemophilus Type b conjugate',
        'ฮิบ / Haemophilus influenzae Type b (Hib)',
        'โปลิโอชนิดฉีด / Inactivated Polio (IPV)',
        'โปลิโอชนิดกิน / Oral Polio (OPV)',
        'นิวโมคอคคัส ชนิดคอนจูเกต / Pneumococcal Conjugate Vaccine',
        'นิวโมคอคคัส ชนิดโพลีแซคคาไรด์ / Pneumococcal Polysaccharide (PPSV23)',
        'โรต้า / Rotavirus',
        'ไข้สมองอักเสบเจอี / Japanese Encephalitis (JE)',
        'อีสุกอีใส / Varicella (Live attenuated)',
        'ตับอักเสบเอ / Hepatitis A (Inactivated)',
        'ไข้หวัดใหญ่ / Influenza',
        'ไทฟอยด์ / Typhoid',
        'เอชพีวี (ไม่ทราบสายพันธุ์) / HPV',
        'ไข้เลือดออก / Dengue',
        'ไข้กาฬหลังแอ่น / Meningococcal ACYW-135 Conjugate',
        'ไข้กาฬหลังแอ่น กลุ่มบี / Meningococcal group B',
        'พิษสุนัขบ้า / Rabies',
    ];

    /**
     * วิธีให้วัคซีน (Route) — เก็บเป็นรหัสสั้น ID / SC / IM / PO ในคอลัมน์ route
     * รหัสสั้นเพราะข้อมูลเดิมเก็บ "IM" อยู่แล้ว และใบรับรองภาษาอังกฤษใช้รหัสนี้ได้ตรง ๆ
     * (TMC ขอ 2569-09-17)
     */
    public static $routeOption = [
        'ID' => 'ฉีดเข้าในชั้นผิวหนัง (Intradermal - ID)',
        'SC' => 'ฉีดเข้าใต้ชั้นผิวหนัง (Subcutaneous - SC / SubQ)',
        'IM' => 'ฉีดเข้าชั้นกล้ามเนื้อ (Intramuscular - IM)',
        'PO' => 'รับประทาน (Oral - PO)',   // วัคซีนชนิดกิน เช่น อหิวาตกโรค Euvichol-Plus
    ];

    /**
     * แปลงค่าที่พิมพ์กันมาแบบต่าง ๆ ให้เป็นรหัสมาตรฐาน
     * แคตตาล็อกวัคซีนมีทั้ง "IM", "im", "IM stat" — ต้องจับให้ตรงก่อนเลือกค่าเริ่มต้นให้ dropdown
     * ไม่รู้จัก = null (ไม่เดา)
     */
    public static function normalizeRoute(?string $raw): ?string
    {
        $r = strtoupper(trim((string) $raw));
        if ($r === '') { return null; }
        if (preg_match('/^(ID|INTRADERMAL)\b/', $r)) { return 'ID'; }
        if (preg_match('/^(SC|SQ|SUBQ|SUB-Q|SUBCUTANEOUS)\b/', $r)) { return 'SC'; }
        if (preg_match('/^(IM|INTRAMUSCULAR)\b/', $r)) { return 'IM'; }
        if (preg_match('/^(PO|ORAL)\b/', $r) || $r === 'รับประทาน') { return 'PO'; }
        return null;
    }

    public static $siteOption = [
        'Deltoid ซ้าย', 'Deltoid ขวา', 'ต้นขาซ้าย', 'ต้นขาขวา', 'สะโพก', 'รับประทาน',
    ];

    /** เข็มที่/dose สำหรับ "แผนนัดฉีดวัคซีน" ที่แพทย์วางล่วงหน้า (visits.tmc_vaccine_plan) */
    // ใช้คำว่า Dose แทน "เข็มที่" — วัคซีนบางตัวเป็นแบบกิน (อหิวาตกโรค) เรียกว่าเข็มไม่ได้ (คลินิกขอ 2569-10-05)
    public static $planDoseOption = [
        'Dose 1', 'Dose 2', 'Dose 3', 'Dose 4', 'Dose 5', 'Booster',
    ];

    /** ข้อความ dose แบบเดิมที่เก็บไว้ในแผน ("เข็มที่ 2" / "เข็มกระตุ้น") -> แบบใหม่ ให้ตัวเลือกและการเทียบนัดตรงกัน */
    public static function doseLabel(string $dose): string
    {
        $dose = trim($dose);
        if (preg_match('/^เข็มที่\s*(\d+)$/u', $dose, $m)) { return 'Dose ' . $m[1]; }
        if ($dose === 'เข็มกระตุ้น') { return 'Booster'; }
        return $dose;
    }

    /** จำนวนวัคซีนสูงสุดต่อ 1 นัด — เดิมตั้ง 3 แล้วพยาบาลแจ้งว่าไม่พอ (2026-08-05) */
    public const PLAN_ITEMS_PER_VISIT = 6;

    /** จำนวนนัดสูงสุดในแผน */
    public const PLAN_MAX_ROWS = 5;

    /**
     * อ่านแผนนัดให้เป็นรูปแบบเดียวเสมอ: [['date'=>..., 'items'=>[['name'=>..,'dose'=>..], ...]], ...]
     * รองรับรูปแบบเก่า ['date'=>..., 'vaccines'=>['ชื่อ', ...]] ที่ยังไม่มี dose
     */
    public static function normalizePlan($plan): array
    {
        if (!is_array($plan)) { return []; }
        $out = [];
        foreach ($plan as $row) {
            if (!is_array($row)) { continue; }
            $items = [];
            if (isset($row['items']) && is_array($row['items'])) {
                foreach ($row['items'] as $it) {
                    if (!is_array($it)) { continue; }
                    $name = trim((string) ($it['name'] ?? ''));
                    if ($name === '') { continue; }
                    $items[] = ['name' => $name, 'dose' => self::doseLabel((string) ($it['dose'] ?? ''))];
                }
            } elseif (isset($row['vaccines']) && is_array($row['vaccines'])) {
                foreach ($row['vaccines'] as $name) {
                    $name = trim((string) $name);
                    if ($name !== '') { $items[] = ['name' => $name, 'dose' => '']; }
                }
            }
            $date = trim((string) ($row['date'] ?? ''));
            if ($date === '' && !$items) { continue; }
            $r = ['date' => $date, 'items' => $items];
            // นัดไปฉีดที่อื่น (หมอนัดให้ไปฉีดที่ รพ./คลินิกอื่น — 2569-10-09) · ไม่มีคีย์ = ฉีดที่คลินิกนี้
            if (($row['site'] ?? '') === 'other') {
                $r['site'] = 'other';
                $r['place'] = trim((string) ($row['place'] ?? ''));
            } elseif (($row['site'] ?? '') === 'self') {
                $r['site'] = 'self';   // วัคซีนชนิดกิน รับไปทานเอง (2569-10-27)
            }
            $out[] = $r;
        }
        return $out;
    }

    /** ข้อความต่อท้ายนัดที่ไปฉีดที่อื่น เช่น " (ฉีดที่ รพ.นครพิงค์)" — ว่าง = ฉีดที่คลินิกนี้ */
    public static function planSiteText(array $row): string
    {
        if (($row['site'] ?? '') === 'self') { return ' (รับไปทานเอง)'; }
        if (($row['site'] ?? '') !== 'other') { return ''; }
        $p = trim((string) ($row['place'] ?? ''));
        return ' (ฉีดที่' . ($p !== '' ? ' ' . $p : 'อื่น') . ')';
    }

    /** "Rabies เข็มที่ 2, HPV เข็มที่ 3" — ใช้ทั้งใบนัดและหน้าจอ */
    public static function planItemsText(array $items): string
    {
        $parts = [];
        foreach ($items as $it) {
            $name = trim((string) ($it['name'] ?? ''));
            if ($name === '') { continue; }
            $dose = trim((string) ($it['dose'] ?? ''));
            $parts[] = $dose !== '' ? ($name . ' ' . $dose) : $name;
        }
        return implode(', ', $parts);
    }

    /**
     * เป็นวัคซีนไข้เหลืองหรือไม่ — ดูจากชื่อ (snapshot ตอนฉีด) และชื่อในแคตตาล็อก
     * จับทั้งไทย/อังกฤษ/ชื่อการค้า เพราะประวัติที่นำเข้า/พิมพ์เอง เขียนกันหลายแบบ
     */
    public static function isYellowFever(?string ...$names): bool
    {
        foreach ($names as $n) {
            if ($n !== null && preg_match('/yellow\s*fever|stamaril|ไข้เหลือง|\bYF\b/iu', $n)) { return true; }
        }
        return false;
    }

    /**
     * เข็มไข้เหลืองทั้งหมดของคนไข้ (ทั้งฉีดที่นี่และประวัติจากที่อื่น) เรียงเก่า -> ใหม่
     * ตั้งแต่ 11 ก.ค. 2016 WHO ให้ใบรับรองไข้เหลือง 1 เข็มมีผลตลอดชีพ — เจ้าหน้าที่ต้องเห็นทันทีว่าไม่ต้องฉีดซ้ำ
     */
    public static function yellowFeverShots(int $patientId, $shots = null)
    {
        $shots = $shots ?? self::with('medicine:id,name')->where('patient_id', $patientId)->get();
        return $shots->filter(fn ($s) => self::isYellowFever($s->name, optional($s->medicine)->name))
            ->sortBy(fn ($s) => [$s->vaccinated_date ? $s->vaccinated_date->format('Y-m-d') : '9999', $s->id])
            ->values();
    }

    /**
     * สรุปวัคซีนรายคน: กลุ่มตามชื่อวัคซีน -> เข็มเรียงตามวันที่ (วันที่/เข็มที่/lot/ที่มา)
     * ไว้ให้เจ้าหน้าที่ดูทีเดียว ไม่ต้องเปิดทีละ visit
     */
    public static function summaryFor(int $patientId, $shots = null): array
    {
        $shots = $shots ?? self::where('patient_id', $patientId)->get();
        $out = [];
        foreach ($shots->sortBy(fn ($s) => [$s->vaccinated_date ? $s->vaccinated_date->format('Y-m-d') : '9999', $s->id]) as $s) {
            $key = trim((string) $s->name) ?: '(ไม่ระบุชื่อ)';
            $out[$key][] = [
                'date'   => $s->vaccinated_date_th,
                'dose'   => $s->dose_number,
                'lot'    => $s->lot_number,
                'source' => (int) $s->source,
            ];
        }
        return $out;
    }

    public function patient()  { return $this->belongsTo(Patient::class); }
    public function visit()    { return $this->belongsTo(Visit::class); }
    public function medicine() { return $this->belongsTo(Medicine::class); }
    public function injector() { return $this->belongsTo(User::class, 'injected_by'); }

    // ===== วันที่ =====
    // คอลัมน์วันที่ในตารางนี้เก็บเป็น ค.ศ. (ต่างจาก visits.date ที่เป็น พ.ศ.)
    // เพราะต้องเอาไปคำนวณวันครบกำหนดเข็มถัดไปเทียบกับ now() — ดูเหตุผลเดียวกันใน MedicineLot

    /** d/m/Y (พ.ศ.) -> Y-m-d (ค.ศ.) สำหรับเก็บลง DB */
    public static function toDbDate($value): ?string
    {
        $v = trim((string) $value);
        if ($v === '' || $v === '0000-00-00') { return null; }

        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            $year = (int) $m[3];
            if ($year > 2400) { $year -= 543; }
            return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $v, $m)) {
            $year = (int) $m[1];
            if ($year > 2400) { $year -= 543; }
            return sprintf('%04d-%s-%s', $year, $m[2], $m[3]);
        }
        return null;
    }

    /** ค.ศ. -> d/m/Y (พ.ศ.) สำหรับแสดงผล */
    public static function toThDate($date): ?string
    {
        if (!$date) { return null; }
        $c = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);
        return $c->format('d/m/') . ($c->year + 543);
    }

    public function getVaccinatedDateThAttribute(): ?string { return self::toThDate($this->vaccinated_date); }
    public function getNextDueDateThAttribute(): ?string    { return self::toThDate($this->next_due_date); }

    /** ถึงกำหนดเข็มถัดไปแล้วหรือยัง */
    public function isDue(): bool
    {
        return $this->next_due_date && $this->next_due_date->isPast();
    }
}
