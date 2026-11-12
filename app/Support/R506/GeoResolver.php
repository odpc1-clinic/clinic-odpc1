<?php

namespace App\Support\R506;

/**
 * แปลงชื่อ จังหวัด/อำเภอ/ตำบล (จากตารางที่อยู่ของโปรแกรม STI)
 * ให้เป็นรหัส ADDRCODE 8 หลัก (PPAATTVV) ตามมาตรฐาน 506
 * โดยเทียบกับตาราง TPRO/TAMP/TTUM ของโปรแกรม FoxPro 506
 *
 * ชื่ออาจไม่ตรงเป๊ะ (เช่น "เมืองเชียงใหม่" vs "เมือง", "วัดเกต" vs "วัดเกตุ")
 * จึงใช้ normalize + fuzzy (levenshtein) ช่วย
 */
class GeoResolver
{
    protected array $pro = [];   // [ ['code'=>, 'name'=>, 'norm'=>], ... ]
    protected array $amp = [];   // [ ['pro'=>, 'code'=>, 'name'=>, 'norm'=>], ... ]
    protected array $tum = [];   // [ ['pro'=>, 'amp'=>, 'code'=>, 'name'=>, 'norm'=>], ... ]

    public int $matched = 0;
    public int $unmatched = 0;

    public function __construct(string $dataDir)
    {
        $this->pro = $this->load($dataDir, 'TPRO', ['PRO_CODE', 'PRO_NAME']);
        $this->amp = $this->load($dataDir, 'TAMP', ['AMP_CODE', 'PRO_CODE', 'AMP_NAME']);
        $this->tum = $this->load($dataDir, 'TTUM', ['TUM_CODE', 'AMP_CODE', 'PRO_CODE', 'TUM_NAME']);
    }

    /** หาไฟล์ล่าสุด TXXXyyyy.DBF แล้วอ่านเข้ามาเป็น list */
    protected function load(string $dir, string $prefix, array $cols): array
    {
        $cands = glob(rtrim($dir, "\\/") . DIRECTORY_SEPARATOR . $prefix . '*.[Dd][Bb][Ff]') ?: [];
        if (!$cands) {
            return [];
        }
        rsort($cands); // ปีล่าสุดก่อน
        $data = Dbf::read($cands[0]);
        $out = [];
        foreach ($data['rows'] as $row) {
            $name = trim($row[$cols[count($cols) - 1]] ?? '');
            if ($name === '') {
                continue;
            }
            $rec = ['name' => $name, 'norm' => self::norm($name)];
            if ($prefix === 'TPRO') {
                $rec['code'] = trim($row['PRO_CODE'] ?? '');
            } elseif ($prefix === 'TAMP') {
                $rec['code'] = trim($row['AMP_CODE'] ?? '');
                $rec['pro']  = trim($row['PRO_CODE'] ?? '');
            } else {
                $rec['code'] = trim($row['TUM_CODE'] ?? '');
                $rec['amp']  = trim($row['AMP_CODE'] ?? '');
                $rec['pro']  = trim($row['PRO_CODE'] ?? '');
            }
            $out[] = $rec;
        }
        return $out;
    }

    /** normalize ชื่อไทยสำหรับเทียบ: ตัดคำนำหน้า/ช่องว่าง */
    public static function norm(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', '', $s);
        $s = preg_replace('/^(จังหวัด|อำเภอ|ตำบล|แขวง|เขต|อ\.|ต\.|จ\.)/u', '', $s);
        return $s;
    }

    /**
     * คืน ADDRCODE 8 หลัก หรือ '' ถ้าจับคู่ไม่ได้
     */
    public function resolve(?string $province, ?string $district, ?string $subDistrict): string
    {
        $pn = self::norm((string) $province);
        $dn = self::norm((string) $district);
        $sn = self::norm((string) $subDistrict);

        // จังหวัด
        $proCode = $this->pick($this->pro, $pn)['code'] ?? null;
        if ($proCode === null) {
            $this->unmatched++;
            return '';
        }

        // อำเภอ (ในจังหวัดนั้น)
        $ampList = array_filter($this->amp, fn ($a) => $a['pro'] === $proCode);
        $amp = $this->pick($ampList, $dn);
        if (!$amp) {
            $this->unmatched++;
            return '';
        }

        // ตำบล (ในอำเภอนั้น)
        $tumList = array_filter($this->tum, fn ($t) => $t['pro'] === $proCode && $t['amp'] === $amp['code']);
        $tum = $this->pick($tumList, $sn);
        if (!$tum) {
            $this->unmatched++;
            return '';
        }

        $this->matched++;
        return str_pad($tum['code'], 6, '0', STR_PAD_LEFT) . '00'; // TUM_CODE(6) + village(00)
    }

    /** เลือกตัวที่ตรงที่สุด: exact -> prefix/contains -> fuzzy(levenshtein) */
    protected function pick(iterable $list, string $needle): ?array
    {
        if ($needle === '') {
            return null;
        }
        $prefixBest = null;
        $prefixLen  = -1;                 // เลือก prefix ที่ทับซ้อนยาวสุด
        $fuzzyBest  = null;
        $fuzzyDist  = PHP_INT_MAX;

        foreach ($list as $item) {
            $n = $item['norm'];
            if ($n === '') {
                continue;
            }
            if ($n === $needle) {
                return $item;             // ตรงเป๊ะ
            }
            // ชื่อหนึ่งเป็น prefix ของอีกชื่อ (เช่น "เมือง" ขึ้นต้น "เมืองเชียงใหม่")
            // ต้องยาว >= 3 อักษร กันจับมั่ว — ยอมรับได้แม้ความยาวต่างมาก
            if (mb_strlen($n) >= 3 && (mb_strpos($needle, $n) === 0 || mb_strpos($n, $needle) === 0)) {
                $overlap = min(mb_strlen($n), mb_strlen($needle));
                if ($overlap > $prefixLen) {
                    $prefixBest = $item;
                    $prefixLen  = $overlap;
                }
                continue;
            }
            $d = levenshtein($n, $needle);
            if ($d < $fuzzyDist) {
                $fuzzyBest = $item;
                $fuzzyDist = $d;
            }
        }

        if ($prefixBest !== null) {
            return $prefixBest;
        }
        // ยอมรับ fuzzy เฉพาะที่ใกล้มาก (ต่างได้ ~1 อักษรไทย = 3 ไบต์)
        if ($fuzzyBest !== null && $fuzzyDist <= 3) {
            return $fuzzyBest;
        }
        return null;
    }
}
