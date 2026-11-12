<?php

namespace App\Support\R506;

use App\Utillity;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * แหล่งรวม logic การส่งออกรายงาน 506 (ใช้ร่วมกันทั้ง artisan command และหน้าเว็บ)
 * - map โรค/อาชีพ/สถานภาพ STI -> รหัส 506
 * - ดึงเคสผู้ป่วย STI, สร้าง record ตาม layout H05YY, เขียนไฟล์ DBF
 */
class R506Exporter
{
    /** โฟลเดอร์ตาราง lookup ของ FoxPro (TPRO/TAMP/TTUM) — สำรองไว้ที่ C: */
    public const DEFAULT_DATA_DIR = 'C:\\r506\\databackup';

    /** รหัสสถานพยาบาล (HSERV/OFFICEID) */
    public const FACILITY = '50010502';

    /** STI diagnoses.disease => รหัสโรค 506 (อ้างตาราง NDIS ของ FoxPro) */
    public const DISEASE_MAP = [
        1  => '37', // Syphilis
        2  => '38', // Gonorrhea
        17 => '38', // Egasp 1 (เฝ้าระวังหนองในดื้อยา)
        18 => '38', // Egasp 2
        19 => '39', // Chlamydia -> N.S.U./V
        3  => '39', // Non-Gonococcal urethritis
        5  => '40', // Chancroid
        4  => '41', // LGV
        12 => '79', // Herpes simplex (เริมอวัยวะเพศ)
        10 => '80', // Anogenital warts (หูดหงอนไก่)
        6  => '81', // Bacterial vaginosis -> อื่นๆ
        9  => '81', // Molluscum contagiosum -> อื่นๆ
        11 => '81', // Other
        8  => '86', // Trichomoniasis
        // ไม่รายงาน: 7 candidiasis, 13 pre-test, 14 post-test, 15 ตรวจไม่พบโรค, 16 GF
    ];

    /** ชื่อโรค 506 (ไว้แสดงในตาราง) */
    public const CODE506_NAME = [
        '37' => 'Syphilis', '38' => 'หนองใน', '39' => 'หนองในเทียม/Chlamydia',
        '40' => 'แผลริมอ่อน', '41' => 'ฝีมะม่วง(LGV)', '79' => 'เริมอวัยวะเพศ',
        '80' => 'หูดหงอนไก่', '81' => 'STD อื่นๆ', '86' => 'Trichomoniasis',
    ];

    /** STI marital_status => รหัส 506 (1โสด 2คู่ 3หม้าย 4หย่า) */
    public const MARITAL_MAP = [1 => '1', 4 => '2', 2 => '3', 3 => '4'];

    /** term_syphilis => COMPLICA (ชนิด syphilis ของ 506) */
    public const SYPHILIS_COMPLICA = [1 => '1', 2 => '2', 3 => '4', 4 => '4', 5 => '9'];

    /** STI occupation => รหัสอาชีพ 506 */
    public const OCCUPAT_MAP = [
        1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6', 7 => '7',
        9 => '15', 10 => '14', 11 => '10',
    ];

    protected string $dataDir;
    protected string $facility;
    protected string $metropol;
    protected string $result;
    protected ?GeoResolver $geo = null; // โหลดตาราง geo ครั้งเดียว (อย่าสร้างใหม่ต่อแถว)

    /**
     * โฟลเดอร์ตาราง lookup (TPRO/TAMP/TTUM*.DBF) — R506_DATA_DIR ใน .env ก่อน
     * ไม่ตั้ง: ใช้ของโปรแกรม R506 ในเครื่อง (C:\r506\databackup) ถ้ามี ไม่งั้นใช้ชุดที่แนบมากับโค้ด resources/r506
     * (server Linux ไม่มีโปรแกรม R506 — 2569-10-20 · ตารางพวกนี้เป็นรหัสพื้นที่สาธารณะ ไม่มีข้อมูลคนไข้)
     */
    public static function defaultDataDir(): string
    {
        $env = (string) config('services.r506.data_dir');
        if ($env !== '') { return $env; }
        return is_dir(self::DEFAULT_DATA_DIR) ? self::DEFAULT_DATA_DIR : resource_path('r506');
    }

    public function __construct(?string $dataDir = null, string $facility = self::FACILITY, string $metropol = '1', string $result = '3')
    {
        $this->dataDir  = $dataDir ?: self::defaultDataDir();
        $this->facility = $facility;
        $this->metropol = $metropol;
        $this->result   = $result;
    }

    /** GeoResolver ตัวเดียว (lazy) — อ่านไฟล์ DBF แค่ครั้งแรก */
    protected function geo(): GeoResolver
    {
        return $this->geo ??= new GeoResolver($this->dataDir);
    }

    /** รหัสโรค 506 ของ diagnosis (null = ไม่ใช่โรคที่รายงาน 506) */
    public static function code506(int $disease): ?string
    {
        return self::DISEASE_MAP[$disease] ?? null;
    }

    /** query ฐาน: join diagnosis+visit+patient+address (คลินิก STI) — ผู้เรียกเติม where เอง */
    public static function baseQuery()
    {
        return DB::table('diagnoses as d')
            ->join('visits as v', 'v.id', '=', 'd.visit_id')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->leftJoin('addresses as a', 'a.patient_id', '=', 'p.id')
            ->leftJoin('provinces as pr', 'pr.id', '=', 'a.province')
            ->leftJoin('districts as ds', 'ds.id', '=', 'a.district')
            ->leftJoin('sub_districts as sb', 'sb.id', '=', 'a.sub_district')
            ->where('v.clinic', 1)
            ->whereIn('d.disease', array_keys(self::DISEASE_MAP))
            ->select(
                'd.id as diag_id', 'd.visit_id', 'd.disease', 'd.term_syphilis',
                'd.report_506', 'd.report_506_sent_at',
                'v.date as vdate', 'v.onset_date',
                'p.id as patient_id', 'p.code', 'p.name', 'p.surname', 'p.sex', 'p.birth_date',
                'p.id_card_number', 'p.passport', 'p.nationality', 'p.marital_status', 'p.occupation',
                'pr.name as prov_name', 'ds.name as dist_name', 'sb.name as sub_name', 'a.house_no'
            );
    }

    /** นิยามฟิลด์ตาม layout H05YY (recLen=441) — ห้ามสลับลำดับ/ความยาว */
    public static function fieldDefs(): array
    {
        $c = fn ($n, $l) => ['name' => $n, 'type' => 'C', 'len' => $l, 'dec' => 0];
        $n = fn ($n) => ['name' => $n, 'type' => 'N', 'len' => 20, 'dec' => 0];
        $d = fn ($n) => ['name' => $n, 'type' => 'D', 'len' => 8, 'dec' => 0];

        return [
            $n('E0'), $n('E1'), $n('PE0'), $n('PE1'),
            $c('DISEASE', 2), $c('NAME', 50), $c('HN', 10), $c('NMEPAT', 30),
            $c('SEX', 1), $n('AGEY'), $n('AGEM'), $n('AGED'),
            $c('MARIETAL', 1), $c('RACE', 1), $c('RACE1', 1), $n('OCCUPAT'),
            $c('ADDRESS', 30), $c('ADDRCODE', 8), $c('METROPOL', 1), $c('HOSPITAL', 1),
            $c('TYPE', 1), $c('RESULT', 1), $c('HSERV', 8), $c('CLASS', 20), $c('SCHOOL', 40),
            $d('DATESICK'), $d('DATEDEFINE'), $d('DATEDEATH'), $d('DATERECORD'), $d('DATEREACH'),
            ['name' => 'INTIME', 'type' => 'L', 'len' => 1, 'dec' => 0],
            $c('ORGANISM', 2), $c('COMPLICA', 2), $c('IDCARD', 13), $c('ICD10', 8), $c('OFFICEID', 8),
        ];
    }

    /**
     * สร้าง record จากผลลัพธ์ query — E0 (=E1) นับ 1 ใหม่ทุกปี (ตามปี พ.ศ. ของ visit)
     * @param bool $continueFromDb นับต่อจากเลขที่ให้ไปแล้วในปีนั้น (จริง=ส่งจริง, เท็จ=ดัมป์ทดสอบเริ่ม 1)
     * @return array ['records'=>[], 'diagSeq'=>[diagId=>['year'=>,'seq'=>]], 'matched'=>int, 'noAddr'=>int,
     *                'seqFirst'=>int, 'seqLast'=>int, 'year'=>int]
     */
    public function buildRecords(iterable $rows, bool $continueFromDb = true): array
    {
        $geo = $this->geo();
        $records = [];
        $diagSeq = [];   // diag_id => ['year'=>, 'seq'=>]
        $seen = [];      // visit_id-code => ['year'=>, 'seq'=>] (กันซ้ำ + แชร์ seq)
        $seqByYear = []; // ปี พ.ศ. => เลขล่าสุดที่ให้ไปแล้ว
        $noAddr = 0;
        $seqFirst = null; $seqLast = null; $lastYear = null;

        foreach ($rows as $r) {
            $code506 = self::code506((int) $r->disease);
            if ($code506 === null) {
                continue;
            }
            $beYear = (int) substr((string) $r->vdate, 0, 4);
            $lastYear = $beYear;
            $key = $r->visit_id . '-' . $code506;

            if (isset($seen[$key])) {
                // diagnosis อื่นของเคสเดียวกัน -> ใช้ seq เดิม (ไว้ stamp ให้ครบ)
                $diagSeq[$r->diag_id] = $seen[$key];
                continue;
            }

            // เลข E0 ของปีนี้ (นับต่อจากที่ส่งไปแล้วในปีเดียวกัน)
            if (!isset($seqByYear[$beYear])) {
                $seqByYear[$beYear] = $continueFromDb
                    ? (int) DB::table('diagnoses')->where('report_506_year', $beYear)->max('report_506_seq')
                    : 0;
            }
            $seq = ++$seqByYear[$beYear];
            $seen[$key] = ['year' => $beYear, 'seq' => $seq];
            $diagSeq[$r->diag_id] = $seen[$key];
            if ($seqFirst === null || $seq < $seqFirst) $seqFirst = $seq;
            if ($seqLast === null || $seq > $seqLast) $seqLast = $seq;

            $adDate = self::toAdYmd($r->vdate);
            [$ay, $am, $ad] = self::ageAt($r->birth_date, $r->vdate);
            $addrcode = $geo->resolve($r->prov_name, $r->dist_name, $r->sub_name);
            if ($addrcode === '') {
                $noAddr++;
            }
            $idcard = trim((string) $r->id_card_number) ?: trim((string) $r->passport);

            $records[] = [
                'E0'         => (string) $seq,
                'E1'         => (string) $seq,
                'PE0'        => '',
                'PE1'        => '',
                'DISEASE'    => $code506,
                'NAME'       => self::fullName($r->name, $r->surname),
                'HN'         => trim((string) $r->code),
                'NMEPAT'     => '',
                'SEX'        => trim((string) $r->sex),
                'AGEY'       => $ay,
                'AGEM'       => $am,
                'AGED'       => $ad,
                'MARIETAL'   => self::MARITAL_MAP[(int) $r->marital_status] ?? '',
                'RACE'       => self::isThai($r->nationality, $idcard) ? '1' : '8',
                'RACE1'      => '',
                'OCCUPAT'    => self::OCCUPAT_MAP[(int) $r->occupation] ?? '11',
                'ADDRESS'    => self::houseNumber($r->house_no),
                'ADDRCODE'   => $addrcode,
                'METROPOL'   => $this->metropol,
                'HOSPITAL'   => '4',
                'TYPE'       => '1',
                'RESULT'     => $this->result,
                'HSERV'      => $this->facility,
                'CLASS'      => '',
                'SCHOOL'     => '',
                // วันเริ่มป่วย: ใช้ที่พยาบาลลงไว้กับ visit (ตรงประวัติการเจ็บป่วยปัจจุบัน) ถ้าไม่ได้ลงค่อยใช้วันที่มารับบริการ
                'DATESICK'   => (trim((string) $r->onset_date) !== '' ? self::toAdYmd($r->onset_date) : $adDate),
                'DATEDEFINE' => $adDate,
                'DATEDEATH'  => '',
                'DATERECORD' => $adDate,
                'DATEREACH'  => $adDate,
                'INTIME'     => 'T',
                'ORGANISM'   => '',
                'COMPLICA'   => $code506 === '37' ? (self::SYPHILIS_COMPLICA[(int) $r->term_syphilis] ?? '') : '',
                'IDCARD'     => $idcard,
                'ICD10'      => 'ไม่ระบุ',
                'OFFICEID'   => $this->facility,
            ];
        }

        return [
            'records'  => $records,
            'diagSeq'  => $diagSeq,   // diag_id => ['year','seq'] (ไว้ stamp)
            'matched'  => $geo->matched,
            'noAddr'   => $noAddr,
            'seqFirst' => $seqFirst ?? 0,
            'seqLast'  => $seqLast ?? 0,
            'year'     => $lastYear,
        ];
    }

    /** ตรวจว่าที่อยู่ครบพอจะได้ ADDRCODE ไหม (ใช้เตือนในหน้าเว็บ) */
    public function addrStatus(?string $province, ?string $district, ?string $subDistrict, ?string $houseNo): string
    {
        $house = trim((string) $houseNo);
        $hasProvince = trim((string) $province) !== '';
        if (!$hasProvince && $house === '') {
            return 'none';       // ไม่มีที่อยู่เลย
        }
        $code = $this->geo()->resolve($province, $district, $subDistrict);
        return $code === '' ? 'partial' : 'ok';
    }

    public function write(string $path, array $records): void
    {
        Dbf::write($path, self::fieldDefs(), $records);
    }

    // ---------- helpers ----------

    protected static function toAdYmd(?string $beDate): string
    {
        if (!$beDate) {
            return '';
        }
        return preg_replace('/\D/', '', (string) Utillity::BE2AD($beDate));
    }

    protected static function ageAt(?string $birthBE, ?string $visitBE): array
    {
        if (!$birthBE || !$visitBE) {
            return ['', '', ''];
        }
        try {
            $b = Carbon::parse(Utillity::BE2AD($birthBE));
            $v = Carbon::parse(Utillity::BE2AD($visitBE));
            if ($b->greaterThan($v)) {
                return ['', '', ''];
            }
            $diff = $b->diff($v);
            return [(string) $diff->y, (string) $diff->m, (string) $diff->d];
        } catch (\Throwable $e) {
            return ['', '', ''];
        }
    }

    protected static function isThai(?string $n, string $idcard = ''): bool
    {
        $n = trim(mb_strtoupper((string) $n));
        if (in_array($n, ['ไทย', 'THA', 'THAI', 'TH', 'ไท'], true)) {
            return true;
        }
        if (in_array($n, ['', '-', 'N/A', 'ไม่มี', 'ไม่ระบุ'], true) && preg_match('/^\d{13}$/', $idcard)) {
            return true;
        }
        return false;
    }

    protected static function fullName(?string $name, ?string $surname): string
    {
        $name = trim((string) $name);
        $surname = trim((string) $surname);
        if ($surname === '-' || $surname === '--' || !preg_match('/[\p{L}\p{N}]/u', $surname)) {
            $surname = '';
        }
        return trim($name . ' ' . $surname);
    }

    protected static function houseNumber(?string $full): string
    {
        $full = trim((string) $full);
        if ($full === '' || in_array($full, ['-', '--', 'ไม่มี', 'N/A'], true)) {
            return 'ไม่ระบุบ้านเลขที่';
        }
        $first = preg_split('/\s+/u', $full)[0] ?? $full;
        if (preg_match('/\d/', $first)) {
            return mb_substr($first, 0, 30);
        }
        return mb_substr($full, 0, 30);
    }

    /** ชื่อไฟล์มาตรฐาน H05YY_<e1a>_<e1b>.dbf (YY = 2 หลักท้ายปี พ.ศ.) */
    public static function fileName(int $beYear, int $e1First, int $e1Last): string
    {
        $yy = substr((string) $beYear, 2, 2);
        return "H05{$yy}_{$e1First}_{$e1Last}.dbf";
    }
}
