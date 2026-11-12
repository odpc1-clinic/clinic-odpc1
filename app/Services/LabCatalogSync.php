<?php

namespace App\Services;

use App\Models\ExternalLabTest;
use App\Models\LabCatalogItem;
use App\Models\LabSub;

/**
 * เติมแคตตาล็อกแล็บรวมจากแหล่งเดิมทั้ง 3 ที่ (2569-10-02)
 *
 *   - วิธีตรวจในคลินิก   LabSub::$methodOption
 *   - แล็บนอก           external_lab_tests (N Health / แล็บอื่น)
 *   - ส่งตรวจวัณโรค      ช่อง lab_* ของ TbScreening
 *
 * รันซ้ำได้: จับคู่ด้วย target (in_house:6 / external:41 / tb:afb) — รายการที่มีแล้ว "ไม่แตะ"
 * เพราะผู้ดูแลอาจแก้ชื่อ/หมวด/ราคา/คลินิกในหน้า /lab-catalog ไปแล้ว ถ้าเขียนทับงานที่แก้จะหาย
 * $refresh = true ค่อยเขียนทับ (ใช้ตอนติดตั้งใหม่หรือรีเซ็ตทั้งชุดเท่านั้น)
 * แล็บนอกที่เพิ่มใหม่ในหน้า "รายการ Lab นอก" ทีหลัง -> รันคำสั่งนี้อีกครั้งก็ได้รายการเพิ่มเอง
 */
class LabCatalogSync
{
    /** clinic 1=STI 2=TB 3=TMC */
    private const ALL = [1, 2, 3];

    public static function run(bool $refresh = false): array
    {
        $rows = array_merge(self::inHouseRows(), self::externalRows(), self::tbRows());
        $out = ['created' => 0, 'updated' => 0, 'kept' => 0];

        foreach ($rows as $r) {
            $item = LabCatalogItem::where('target', $r['target'])->first();
            if ($item && ! $refresh) { $out['kept']++; continue; }
            if (! $item) { $item = new LabCatalogItem; $out['created']++; } else { $out['updated']++; }
            $item->fill($r);
            // เงียบ ๆ — ไม่ต้องเขียน log การแก้ไขเป็นร้อยแถวตอนเติมแคตตาล็อก
            $item->saveQuietly();
        }
        return $out;
    }

    /** วิธีตรวจในคลินิก — หมวด/คลินิกตามที่ใช้จริงวันนี้ */
    public static function inHouseRows(): array
    {
        // เลือด: STI / TMC ใช้หน้าสั่ง Lab เต็ม, TB ใช้แผงตรวจเลือด (LabSub::$tbQuickMethods) -> ทุกคลินิกเห็น
        // สิ่งคัดหลั่ง (Gram/Wet/Culture/PCR): วันนี้มีแค่หน้าสั่ง Lab เต็มที่ STI/TMC ใช้ -> ไม่โชว์ใน TB (ผู้ดูแลเปิดเองได้)
        $cat = [
            6 => 'serology', 7 => 'serology', 8 => 'serology', 11 => 'serology', 12 => 'serology', 14 => 'serology',
            1 => 'sti_swab', 2 => 'sti_swab', 3 => 'sti_swab', 4 => 'sti_swab', 5 => 'sti_swab',
            13 => 'cytology', 10 => 'other',
            15 => 'chemistry',   // DTX — พยาบาลเจาะ/ลงผลเอง (LabSub::$pocMethods) 2569-10-22
            16 => 'cardio',      // EKG — ทำที่ห้องตรวจ ลงผล QTcF ในหน้าต่างสั่ง (หมวดหัวใจ) หรือสัญญาณชีพ
        ];
        // เลขลำดับ = ลำดับในหมวด (HIV ก่อน — คลินิก TB ขอ "Lab ในคลินิก มีตรวจ HIV ด้วย")
        $sort = [6 => 1, 7 => 2, 8 => 3, 11 => 4, 12 => 5, 14 => 6, 1 => 1, 2 => 2, 4 => 3, 5 => 4, 3 => 9, 13 => 1, 10 => 1];

        $rows = [];
        foreach (LabSub::$methodOption as $m => $label) {
            if ($m === '' || ! isset($cat[$m])) { continue; }
            $m = (int) $m;
            $isBlood = in_array($cat[$m], ['serology', 'other'], true);
            // ปิดไว้: 13/14 ย้ายไปสั่งที่แล็บนอกแล้ว (LabSub::$retiredMethods) · KOH ไม่มีในหน้าสั่ง Lab เต็มมาหลายปี
            $active = ! in_array($m, LabSub::$retiredMethods, true) && $m !== 3;
            $rows[] = [
                'target'       => 'in_house:' . $m,
                'kind'         => LabCatalogItem::IN_HOUSE,
                'name'         => $m === 10 ? 'อื่น ๆ (พิมพ์ชื่อการตรวจ)' : ($m === 15 ? 'DTX น้ำตาลปลายนิ้ว (พยาบาลลงผลเอง)' : ($m === 16 ? 'EKG / ECG (คลื่นไฟฟ้าหัวใจ · QTcF)' : ltrim($label, '# '))),
                'code'         => null,
                'category'     => $cat[$m],
                'lab_method'   => $m,
                'lab_specimen' => null,
                'default_price'=> null,          // แล็บในคลินิกอ่านราคาสดจาก lab_prices (ดู LabCatalogItem::priceInfo)
                'price_note'   => null,
                // DTX ใช้ที่คลินิก TB เท่านั้น (คลินิกขอ) · ลงผลที่แผง Lab ในคลินิก ไม่ส่งห้อง Lab
                'clinics'      => LabSub::isPoc($m) ? [2] : ($isBlood ? self::ALL : [1, 3]),
                'active'       => $active,
                'sort'         => $sort[$m] ?? 50,
            ];
        }
        return $rows;
    }

    /** หมวดของแล็บนอกจากรหัสในใบเสนอราคา N Health (ตัวอักษรนำหน้า = แผนกของแล็บ) */
    public static function externalCategory(string $code): string
    {
        $code = strtoupper(trim($code));
        $serology = ['N390', 'N620', 'N625', 'N630', 'N635', 'N651', 'N655', 'N660'];
        $tb       = ['E091', 'E230', 'E240', 'E258', 'E330', 'E335', 'E337', 'N247'];
        $hormone  = ['N005', 'N006', 'N010', 'N025', 'N030', 'N771'];
        if (in_array($code, $serology, true)) { return 'serology'; }
        if (in_array($code, $tb, true) || strpos($code, 'TB-') === 0) { return 'tb'; }
        if (in_array($code, $hormone, true)) { return 'hormone'; }
        $p = substr($code, 0, 1);
        if ($p === 'A' || $p === 'K') { return 'hematology'; }
        if ($p === 'C') { return 'chemistry'; }
        if ($p === 'D') { return 'urine'; }
        if ($p === 'E') { return 'micro'; }
        if ($p === 'G' || $p === 'M') { return 'cytology'; }
        if ($p === 'P' || $code === 'N665') { return 'immunity'; }
        return 'other';
    }

    public static function externalRows(): array
    {
        $rows = [];
        foreach (ExternalLabTest::orderBy('code')->get() as $i => $t) {
            $rows[] = [
                'target'       => 'external:' . $t->id,
                'kind'         => LabCatalogItem::EXTERNAL,
                'name'         => $t->name,
                'code'         => $t->code,
                'category'     => self::externalCategory((string) $t->code),
                'lab_method'   => null,
                'lab_specimen' => null,
                'external_lab_test_id' => $t->id,
                // ราคาเก็บคนไข้ของแล็บนอก "กรอกเอง" มาตั้งแต่ต้น — ราคาใบเสนอราคาเป็นต้นทุน ไม่เติมให้เอง
                // ผู้ดูแลตั้งราคาเก็บคนไข้ในหน้า /lab-catalog ได้ ถ้าตั้งแล้วตัวเลือกจะเติมให้
                'default_price'=> null,
                'price_note'   => null,
                'clinics'      => self::ALL,
                'active'       => (bool) $t->active,
                'sort'         => 100 + $i,
            ];
        }
        return $rows;
    }

    /** ส่งตรวจวัณโรค — ช่องเดียวกับ tb/_lab_fields (ไม่มีค่าใช้จ่ายเข้าบิล) */
    public static function tbRows(): array
    {
        $items = [
            ['afb',         'AFB (ย้อมเสมหะ)',          'AFB'],
            ['mol:xpert',   'Xpert MTB/RIF',            'Xpert'],
            ['mol:lpa',     'LPA (Line Probe Assay)',   'LPA'],
            ['mol:pcr',     'PCR วัณโรค',               'PCR'],
            ['culture_dst', 'Culture / DST (เพาะเชื้อ / ความไวต่อยา)', 'Culture'],
            ['tst',         'TST (ทดสอบทางผิวหนัง)',    'TST'],
            ['cytb',        'Cy-TB (ทดสอบทางผิวหนัง)',  'Cy-TB'],
            ['igra',        'IGRA (ตรวจเลือดหาการติดเชื้อวัณโรค)', 'IGRA'],
        ];
        $rows = [];
        foreach ($items as $i => [$key, $name, $code]) {
            $rows[] = [
                'target'       => 'tb:' . $key,
                'kind'         => LabCatalogItem::TB_SENDOUT,
                'name'         => $name,
                'code'         => $code,
                'category'     => 'tb',
                'tb_key'       => $key,
                'default_price'=> null,
                'price_note'   => 'ไม่คิดเงิน · ลงผลที่ช่องส่งตรวจวัณโรค',
                'clinics'      => [2],
                'active'       => true,
                'sort'         => $i + 1,
            ];
        }
        return $rows;
    }
}
