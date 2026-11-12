<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * พจนานุกรมรหัสโรค ICD-10
 *
 * ชุดแรกเป็นรหัสวัณโรค 24 ตัวที่หมอของคลินิกให้มา (ดู Icd10TbSeeder)
 * เติมกลุ่มโรคอื่นได้โดยเพิ่มแถวในตาราง ไม่ต้องแก้โค้ด
 */
class Icd10Code extends Model
{
    protected $table = 'icd10_codes';

    protected $fillable = [
        'code', 'source', 'description_en', 'description_th', 'group_type',
        'is_secondary', 'sort', 'active',
    ];

    protected $casts = [
        'is_secondary' => 'boolean',
        'active'       => 'boolean',
    ];

    /**
     * หน้านี้มีตัวเลือก ICD-10 หรือเปล่า — ตัวเลือกตั้งค่าเป็น true แล้ว layout ค่อยโหลดพจนานุกรม+สคริปต์
     * (รีวิว 2569-09-30: เดิมโหลดทุกหน้า รวมจอคิวที่รีเฟรชตลอด) · Blade รันหน้าลูกก่อน layout ค่าจึงทันเสมอ
     */
    public static $pickerUsed = false;

    /**
     * รหัสที่หมอคัดไว้โหลดครั้งเดียวต่อคำขอ — ป้ายรหัสหลายแถวในหน้าเดียวไม่ต้อง query ทีละตัว
     * ชุดเต็ม ICD-10-TM (~15,700 แถว) ไม่โหลดทั้งก้อน ค้นทีละรหัสตอนเจอแล้วจำไว้ (2569-10-30)
     */
    private static $infoCache = null;

    public static function info(string $code): ?self
    {
        if (self::$infoCache === null) {
            self::$infoCache = self::where('source', 'clinic')->get()->keyBy('code');
        }
        $code = strtoupper(trim($code));
        if (! self::$infoCache->has($code)) {
            self::$infoCache->put($code, self::where('code', $code)->first());
        }
        return self::$infoCache->get($code);
    }

    /** รายการที่ส่งให้ตัวเลือกฝั่งเบราว์เซอร์ (คีย์สั้นเพื่อลดขนาด JSON) */
    public function toPicker(): array
    {
        return [
            'c' => $this->code,
            't' => (string) $this->description_th,
            'e' => (string) $this->description_en,
            'g' => (string) $this->group_type,
            's' => (int) $this->is_secondary,
        ];
    }

    /**
     * ค้นชุดเต็มตอนผู้ใช้พิมพ์ — รหัสที่ขึ้นต้นตรงกันมาก่อน แล้วค่อยชื่อโรค
     * พิมพ์ "a539" ก็เจอ A53.9 (คนพิมพ์รหัสมักไม่ใส่จุด) · หลายคำ = ต้องเจอทุกคำ
     */
    public static function search(string $q, int $limit = 30)
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q));
        if (mb_strlen($q) < 2) { return collect(); }

        $codeQ = strtoupper(str_replace('.', '', $q));
        $qry = self::where('active', true);
        foreach (explode(' ', $q) as $w) {
            $wc = strtoupper(str_replace('.', '', $w));
            $qry->where(function ($x) use ($w, $wc) {
                $x->whereRaw("REPLACE(code, '.', '') LIKE ?", [$wc . '%'])
                  ->orWhere('description_th', 'LIKE', '%' . $w . '%')
                  ->orWhere('description_en', 'LIKE', '%' . $w . '%');
            });
        }
        return $qry->orderByRaw("REPLACE(code, '.', '') LIKE ? DESC", [$codeQ . '%'])
            ->orderByRaw("source = 'clinic' DESC")
            ->orderByRaw('CHAR_LENGTH(code)')
            ->orderBy('code')
            ->limit($limit)->get();
    }

    /** ข้อความที่เอาไปโชว์ในรายการให้เลือก */
    public function label(): string
    {
        return trim($this->code . ' — ' . ($this->description_th ?: $this->description_en));
    }

    /**
     * เก็บรหัสที่ถูกเลือกไว้ของเอกสารหนึ่งใบ
     *
     * $codes = อาเรย์รหัส ที่ตัวแรกถือเป็นรหัสหลัก
     * ลบของเดิมแล้วใส่ใหม่ทั้งชุด เพราะฟอร์มส่งสถานะสุดท้ายมาเสมอ
     * แต่ถ้าชุดรหัสเหมือนเดิมทุกตัวจะไม่แตะเลย (บันทึกหน้าตรวจบ่อย ไม่ต้องลบ-เขียนทุกครั้ง)
     *
     * ครอบ transaction ไว้ ไม่งั้นถ้าใส่ไม่สำเร็จกลางคัน เอกสารจะเหลือรหัสไม่ครบ
     * โดยไม่มีใครรู้ — แย่กว่าไม่บันทึกอะไรเลย
     */
    public static function syncFor(string $ownerType, int $ownerId, array $codes): void
    {
        $clean = [];
        foreach ($codes as $c) {
            $c = strtoupper(trim((string) $c));
            if ($c !== '' && ! in_array($c, $clean, true)) {
                $clean[] = $c;
            }
        }

        // พจนานุกรมว่าง (เครื่องใหม่ยังไม่ได้ seed Icd10TbSeeder) = ตรวจรหัสไม่ได้ ห้ามแตะของเดิม
        // ไม่งั้นกดบันทึกครั้งเดียวรหัสที่เลือกไว้หายหมด (รีวิว 2569-09-30)
        if (! static::query()->exists()) {
            return;
        }

        // รับเฉพาะรหัสที่มีอยู่จริงในพจนานุกรม กันค่าที่ยิงมาจากฟอร์มที่ถูกแก้
        if ($clean) {
            $known = static::whereIn('code', $clean)->pluck('code')->all();
            $clean = array_values(array_intersect($clean, $known));
        }

        // รหัสหลัก = รหัสแรกที่ไม่ใช่ U84.x (รหัสรองบอกการดื้อยา เป็นรหัสหลักไม่ได้)
        // เลือก U84 ก่อนรหัสโรคก็ยังได้รหัสโรคเป็นรหัสหลัก (รีวิว 2569-09-30)
        $primary = 0;
        foreach ($clean as $i => $code) {
            if (! str_starts_with($code, 'U84')) { $primary = $i; break; }
        }
        if ($primary > 0) {
            $p = $clean[$primary];
            array_splice($clean, $primary, 1);
            array_unshift($clean, $p);
        }

        // รหัสเหมือนเดิมทุกตัว (รวมลำดับ) = ไม่ต้องลบแล้วเขียนใหม่
        $current = DB::table('icd10_entries')->where('owner_type', $ownerType)->where('owner_id', $ownerId)
            ->orderByDesc('is_primary')->orderBy('id')->pluck('code')->all();
        if ($current === $clean) {
            return;
        }

        DB::transaction(function () use ($ownerType, $ownerId, $clean) {
            DB::table('icd10_entries')
                ->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();

            if (! $clean) {
                return;
            }

            $now  = now();
            $rows = [];
            foreach ($clean as $i => $code) {
                $rows[] = [
                    'owner_type' => $ownerType,
                    'owner_id'   => $ownerId,
                    'code'       => $code,
                    'is_primary' => $i === 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('icd10_entries')->insert($rows);
        });
    }

    /** รหัสที่เอกสารใบนั้นเลือกไว้ เรียงรหัสหลักขึ้นก่อน */
    /** ลบรหัสของเอกสารหลายใบทีเดียว — ใช้ตอนเอกสารถูกลบตาม FK CASCADE ที่ไม่ผ่าน hook ของโมเดล */
    public static function purgeFor(string $ownerType, array $ids): void
    {
        if (! $ids) { return; }
        DB::table('icd10_entries')->where('owner_type', $ownerType)->whereIn('owner_id', $ids)->delete();
    }

    /** ประเภทการวินิจฉัย (ใบคัดกรองวัณโรค 2569-10-01) */
    public const DX_TYPES = [
        'principal'    => 'Principal Diagnosis — โรคหลัก',
        'comorbidity'  => 'Comorbidity — โรคร่วม',
        'complication' => 'Complication — โรคแทรกซ้อน',
        'other'        => 'Other Diagnosis — อื่น ๆ',
    ];

    /** แถววินิจฉัยของเอกสาร พร้อมประเภท: [['type','code','text'], ...] — โรคหลักขึ้นก่อน */
    public static function typedEntriesFor(string $ownerType, ?int $ownerId): array
    {
        if (! $ownerId) { return []; }
        $order = array_flip(array_keys(self::DX_TYPES));
        return DB::table('icd10_entries')->where('owner_type', $ownerType)->where('owner_id', $ownerId)
            ->orderBy('id')->get()
            ->map(fn ($r) => ['type' => $r->dx_type ?: ($r->is_primary ? 'principal' : 'other'), 'code' => $r->code, 'text' => $r->custom_text])
            ->sortBy(fn ($r) => $order[$r['type']] ?? 9)->values()->all();
    }

    /**
     * บันทึกการวินิจฉัยแบบมีประเภท — รับ [['type' => ..., 'value' => "A15.0 — ..." หรือข้อความที่หมอพิมพ์เอง], ...]
     * ค่าที่ขึ้นต้นด้วยรหัสในพจนานุกรม = เก็บเป็นรหัส · ไม่ใช่ = เก็บเป็นข้อความ (code = NULL)
     * is_primary = แถว Principal แถวแรก (รายงานที่นับโรคเดียวต่อเคสยังใช้ช่องนี้ได้เหมือนเดิม)
     */
    public static function syncTyped(string $ownerType, int $ownerId, array $rows): void
    {
        // รหัสตัวแรกของแต่ละแถว เช็คกับพจนานุกรมทีเดียว (ชุดเต็มมีหมื่นกว่ารหัส ไม่ดึงทั้งหมดมาเทียบ)
        $toks = [];
        foreach ($rows as $r) {
            $toks[] = strtoupper(trim(preg_split('/\s|—/u', trim((string) ($r['value'] ?? '')))[0] ?? ''));
        }
        $known = static::whereIn('code', array_filter($toks))->pluck('code')->all();
        $clean = [];
        foreach ($rows as $r) {
            $val  = trim(preg_replace('/\s+/u', ' ', (string) ($r['value'] ?? '')));
            if ($val === '') { continue; }
            $type = array_key_exists($r['type'] ?? '', self::DX_TYPES) ? $r['type'] : 'other';
            $tok  = strtoupper(trim(preg_split('/\s|—/u', $val)[0] ?? ''));
            $clean[] = in_array($tok, $known, true)
                ? ['dx_type' => $type, 'code' => $tok, 'custom_text' => null]
                : ['dx_type' => $type, 'code' => null, 'custom_text' => mb_substr($val, 0, 255)];
        }
        // โรคหลักขึ้นก่อน แล้วตามลำดับที่กรอก
        $order = array_flip(array_keys(self::DX_TYPES));
        usort($clean, fn ($a, $b) => ($order[$a['dx_type']] ?? 9) <=> ($order[$b['dx_type']] ?? 9));

        DB::transaction(function () use ($ownerType, $ownerId, $clean) {
            DB::table('icd10_entries')->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();
            $now = now();
            $primaryDone = false;
            foreach ($clean as $c) {
                $isPrimary = ! $primaryDone && $c['dx_type'] === 'principal' && $c['code'] !== null;
                if ($isPrimary) { $primaryDone = true; }
                DB::table('icd10_entries')->insert($c + [
                    'owner_type' => $ownerType, 'owner_id' => $ownerId, 'is_primary' => $isPrimary,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        });
    }

    public static function codesFor(string $ownerType, ?int $ownerId): array
    {
        if (! $ownerId) {
            return [];
        }
        // แถวที่หมอพิมพ์เอง (ไม่มีรหัส) ไม่นับเป็นรหัส
        return DB::table('icd10_entries')
            ->where('owner_type', $ownerType)->where('owner_id', $ownerId)->whereNotNull('code')
            ->orderByDesc('is_primary')->orderBy('id')
            ->pluck('code')->all();
    }

    /**
     * ข้อความสรุปรหัสของเอกสารใบหนึ่ง ใช้โชว์ในใบสรุป/รายงาน
     * เช่น "A15.0, U84.7" หรือพร้อมคำแปลถ้าสั่ง $withText
     */
    public static function summaryFor(string $ownerType, ?int $ownerId, bool $withText = false): string
    {
        $codes = static::codesFor($ownerType, $ownerId);
        if (! $codes) {
            return '';
        }
        if (! $withText) {
            return implode(', ', $codes);
        }
        $map = static::whereIn('code', $codes)->get()->keyBy('code');
        $out = [];
        foreach ($codes as $c) {
            $out[] = isset($map[$c]) ? $map[$c]->label() : $c;
        }
        return implode(' · ', $out);
    }
}
