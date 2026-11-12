<?php

namespace App\Services;

use App\Http\Controllers\ExternalLabController;
use App\Models\ExternalLabTest;
use App\Models\Lab;
use App\Models\LabCatalogItem;
use App\Models\LabSub;
use App\Models\TbScreening;
use App\Models\Visit;
use App\Models\VisitExternalLab;
use App\Utillity;
use Illuminate\Support\Facades\DB;

/**
 * สั่งแล็บหลายรายการจากตัวเลือกแคตตาล็อกรวม แล้วส่งแต่ละรายการไปทางเดิมของมัน (2569-10-02)
 *
 * ไม่มีตรรกะสั่งแล็บของตัวเอง — เรียกของเดิมทั้งหมด ผลที่ได้จึงเหมือนสั่งจากหน้าจอเดิมทุกช่อง:
 *   in_house   -> LabSubOrder::orderFromTb()  (applyDelta + ส่งใบเข้าห้อง Lab — ตัวเดียวกับแผง Lab ในคลินิก)
 *   external   -> ExternalLabController::rowAttributes() + syncBilling()  (ตัวเดียวกับปุ่มยืนยันสั่ง Lab นอก)
 *   tb_sendout -> TbScreening::saveLabSection()  (ตัวเดียวกับหน้าตรวจ / popup ในบัตร TB 01)
 *
 * ตรวจทุกรายการก่อน ถ้ามีรายการไหนผิด = ไม่บันทึกอะไรเลย (พยาบาลจะได้ไม่ต้องมาไล่ว่าอันไหนลงไปแล้ว)
 * ผ่านแล้วเขียนทั้งหมดใน transaction เดียว
 */
class LabCatalogOrder
{
    /** visit นี้สั่งอะไรไปแล้วบ้าง — target => ['full' => สั่งครบแล้ว(ห้ามติ๊กซ้ำ), 'text' => คำอธิบาย] */
    public static function orderedState(Visit $visit): array
    {
        $out = [];

        $lab  = Lab::where('visit_id', $visit->id)->first();
        $subs = $lab ? LabSub::where('lab_id', $lab->id)->get() : collect();
        foreach ($subs->groupBy(fn ($s) => (int) $s->method) as $m => $rows) {
            $needsSite = in_array((int) $m, LabCatalogItem::INHOUSE_NEEDS_SPECIMEN, true);
            if ($needsSite) {
                // ตำแหน่งอื่นยังสั่งเพิ่มได้ (Gram ปากมดลูก + ท่อปัสสาวะ) จึงไม่ปิดรายการ แค่บอกว่าสั่งตำแหน่งไหนไปแล้ว
                $out['in_house:' . $m] = ['full' => false, 'text' => 'สั่งแล้ว: ' . $rows->map(fn ($s) => $s->specimenFrom())->unique()->implode(', ')];
            } elseif ((int) $m === 10) {
                $out['in_house:10'] = ['full' => false, 'text' => 'สั่งแล้ว: ' . $rows->pluck('method_other')->filter()->implode(', ')];
            } elseif (LabSub::isPoc($m)) {
                // DTX / EKG ลงผลในหน้าต่างนี้ได้เลย (ไม่มีห้อง Lab) — ส่งค่าปัจจุบัน + ช่วงที่รับ
                [$unit, $min, $max] = LabSub::$pocMethods[(int) $m];
                $val = trim((string) $rows->first()->result);
                $flag = LabSub::pocFlag($m, $val);
                $out['in_house:' . $m] = ['full' => true, 'text' => $val !== '' ? 'ผล ' . $val . ' ' . $unit : 'สั่งแล้ว รอลงผล',
                    'poc' => ['method' => (int) $m, 'unit' => $unit, 'min' => $min, 'max' => $max, 'value' => $val,
                              'label' => (int) $m === 16 ? 'QTcF' : 'ผล', 'flag' => $flag]];
            } else {
                $out['in_house:' . $m] = ['full' => true, 'text' => 'สั่งแล้ว'];
            }
        }

        foreach (VisitExternalLab::where('visit_id', $visit->id)->whereNotNull('external_lab_test_id')->get() as $r) {
            $out['external:' . $r->external_lab_test_id] = ['full' => true, 'text' => 'สั่งแล้ว' . ($r->hasResult() ? ' · มีผล' : '')];
        }

        $sc = TbScreening::where('visit_id', $visit->id)->first();
        if ($sc) {
            foreach (self::tbOrderedKeys($sc) as $k) { $out['tb:' . $k] = ['full' => true, 'text' => 'สั่งแล้ว']; }
        }
        return $out;
    }

    /** รายการส่งตรวจวัณโรคที่ติ๊กไว้แล้วในใบคัดกรอง (tb_key ของแคตตาล็อก) */
    public static function tbOrderedKeys(TbScreening $sc): array
    {
        $keys = [];
        $methods = (array) ($sc->lab_methods ?? []);
        if (in_array('afb', $methods, true)) { $keys[] = 'afb'; }
        if (in_array('culture_dst', $methods, true)) { $keys[] = 'culture_dst'; }
        if (in_array('molecular', $methods, true)) {
            foreach ((array) ($sc->lab_molecular ?? []) as $m) { $keys[] = 'mol:' . $m; }
        }
        if ($sc->tst_done)  { $keys[] = 'tst'; }
        if ($sc->cytb_done) { $keys[] = 'cytb'; }
        if ($sc->igra_done) { $keys[] = 'igra'; }
        return $keys;
    }

    /** d/m/Y ปี พ.ศ. หรือว่าง — คืนข้อความ error หรือ null */
    private static function dateError($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') { return null; }
        if (! preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m) || ! checkdate((int) $m[2], (int) $m[1], (int) $m[3] - 543)) {
            return 'วันที่ส่งต้องเป็น วว/ดด/ปปปป';
        }
        if ((int) $m[3] < 2500) { return 'วันที่ส่งต้องเป็นปี พ.ศ. (พิมพ์ ' . $m[3] . ' มา)'; }
        return null;
    }

    /**
     * $items = [['id' => catalog id, 'specimen' => .., 'specimen_other' => .., 'text' => .., 'price' => .., 'waive' => 0/1, 'sent_date' => d/m/Y], ...]
     * คืน ['ok' => bool, 'errors' => [index => ข้อความ], 'results' => [...], 'message' => ...]
     */
    public static function order(Visit $visit, array $items): array
    {
        $clinic  = (int) ($visit->clinic ?? 1);
        $catalog = LabCatalogItem::whereIn('id', array_map(fn ($i) => (int) ($i['id'] ?? 0), $items))->get()->keyBy('id');
        $state   = self::orderedState($visit);

        $errors = []; $plan = [];
        foreach (array_values($items) as $i => $in) {
            $c = $catalog->get((int) ($in['id'] ?? 0));
            if (! $c || ! $c->active) { $errors[$i] = 'ไม่พบรายการนี้ในแคตตาล็อก (อาจถูกปิดไปแล้ว) — ปิดแล้วเปิดตัวเลือกใหม่'; continue; }
            if (is_array($c->clinics) && ! in_array($clinic, array_map('intval', $c->clinics), true)) {
                $errors[$i] = $c->name . ': ไม่ได้เปิดให้คลินิกนี้สั่ง'; continue;
            }
            if (($state[$c->target]['full'] ?? false)) {
                $errors[$i] = $c->name . ': สั่งไปแล้วใน visit นี้'; continue;
            }

            if ($c->kind === LabCatalogItem::IN_HOUSE) {
                $attrs = ['method' => (int) $c->lab_method];
                if ($c->needsText()) {
                    $t = trim((string) ($in['text'] ?? ''));
                    if ($t === '' || mb_strlen($t) > 255) { $errors[$i] = $c->name . ': พิมพ์ชื่อการตรวจ (ไม่เกิน 255 ตัวอักษร)'; continue; }
                    $attrs['method_other'] = $t;                     // แบบเดียวกับแผง Lab ในคลินิก ("10|ข้อความ")
                } elseif ($c->needsSpecimen()) {
                    $sp = (string) ($in['specimen'] ?? '');
                    if ($sp === '' || ! array_key_exists((int) $sp, $c->specimenOptions())) { $errors[$i] = $c->name . ': เลือกตำแหน่งที่เก็บสิ่งส่งตรวจ'; continue; }
                    $other = (int) $sp === 10 ? trim((string) ($in['specimen_other'] ?? '')) : '';
                    if ((int) $sp === 10 && ($other === '' || mb_strlen($other) > 255)) { $errors[$i] = $c->name . ': ระบุตำแหน่ง (อื่น ๆ)'; continue; }
                    // ค่าเดียวกับที่หน้าสั่ง Lab เต็มเก็บ (saveConfig): PCR มีแค่ method+ตำแหน่ง ตัวอื่นมีช่องว่างพ่วง
                    $attrs = (int) $c->lab_method === 5
                        ? ['method' => 5, 'specimen_from' => (int) $sp] + ((int) $sp === 10 ? ['specimen_from_other' => $other] : [])
                        : ['method' => (int) $c->lab_method, 'method_other' => '', 'specimen_from' => (int) $sp, 'specimen_from_other' => $other];
                }
                $plan[$i] = ['kind' => 'in_house', 'item' => $c, 'attrs' => $attrs];
                continue;
            }

            if ($c->kind === LabCatalogItem::EXTERNAL) {
                if (! ExternalLabTest::find($c->external_lab_test_id)) { $errors[$i] = $c->name . ': รายการแล็บนอกนี้ถูกลบไปแล้ว'; continue; }
                $waive = ! empty($in['waive']);
                $price = trim((string) ($in['price'] ?? ''));
                if (! $waive && $price !== '' && (! is_numeric($price) || (float) $price < 0)) { $errors[$i] = $c->name . ': ราคาต้องเป็นตัวเลขไม่ติดลบ'; continue; }
                if ($e = self::dateError($in['sent_date'] ?? '')) { $errors[$i] = $c->name . ': ' . $e; continue; }
                // ยกเว้น = ราคา 0 — ระบบบิลแล็บนอกข้ามแถวที่ราคา ≤ 0 อยู่แล้ว (Billing::syncExternalLabItems) ไม่มีช่อง "ยกเว้น" แยก
                $row = ExternalLabController::rowAttributes($visit, [
                    'external_lab_test_id' => $c->external_lab_test_id,
                    'price'     => $waive ? 0 : ($price === '' ? null : $price),
                    'sent_date' => $in['sent_date'] ?? null,
                ]);
                $plan[$i] = ['kind' => 'external', 'item' => $c, 'row' => $row, 'waive' => $waive];
                continue;
            }

            // tb_sendout
            $spec = null;
            if ($c->needsSpecimen()) {
                $type = (string) ($in['specimen'] ?? '');
                if (! isset(TbScreening::$specimenOption[$type])) { $errors[$i] = $c->name . ': เลือกสิ่งส่งตรวจ (เช่น เสมหะ Spot)'; continue; }
                $other = trim((string) ($in['specimen_other'] ?? ''));
                if (mb_strlen($other) > 255) { $errors[$i] = $c->name . ': สิ่งส่งตรวจ (ระบุ) ยาวเกิน'; continue; }
                if ($e = self::dateError($in['sent_date'] ?? '')) { $errors[$i] = $c->name . ': ' . $e; continue; }
                $spec = ['type' => $type, 'other' => $other, 'date' => trim((string) ($in['sent_date'] ?? ''))];
            }
            $plan[$i] = ['kind' => 'tb', 'item' => $c, 'spec' => $spec];
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors, 'results' => [],
                    'message' => 'ยังไม่ได้สั่ง — แก้รายการที่มีปัญหาก่อน (ยังไม่มีรายการไหนถูกบันทึก)'];
        }

        $results = DB::transaction(function () use ($visit, $plan) {
            $results = [];

            // ---- ในคลินิก: ส่งทีเดียวทั้งชุด (ใบ Lab เดียว ส่งเข้าห้อง Lab ครั้งเดียว) ----
            $inh = array_filter($plan, fn ($p) => $p['kind'] === 'in_house');
            if ($inh) {
                $lab = LabSubOrder::labFor($visit);
                $have = LabSub::where('lab_id', $lab->id)->get()->map(fn ($s) => LabSubOrder::keyOf($s))->all();
                $res = LabSubOrder::orderFromTb($visit, array_values(array_map(fn ($p) => $p['attrs'], $inh)), []);
                foreach ($inh as $i => $p) {
                    $a = $p['attrs'];
                    $k = LabSubOrder::key($a['method'] ?? null, $a['specimen_from'] ?? null, $a['specimen_from_other'] ?? null, $a['method_other'] ?? null);
                    $results[$i] = ['name' => $p['item']->name, 'kind' => 'in_house',
                                    'status' => in_array($k, $have, true) ? 'skipped' : 'ok',
                                    'message' => in_array($k, $have, true) ? 'มีอยู่แล้ว ไม่สร้างซ้ำ' : 'ส่งเข้าห้อง Lab'];
                }
            }

            // ---- แล็บนอก: สร้างแถว แล้วคิดบิลครั้งเดียว (เหมือนปุ่มยืนยันสั่งหลายรายการ) ----
            $ext = array_filter($plan, fn ($p) => $p['kind'] === 'external');
            foreach ($ext as $i => $p) {
                VisitExternalLab::create($p['row']);
                $results[$i] = ['name' => $p['item']->name, 'kind' => 'external', 'status' => 'ok',
                                'message' => $p['waive'] ? 'ส่งนอก · ยกเว้นค่าตรวจ' : ($p['row']['price'] !== null ? 'ส่งนอก · ' . number_format((float) $p['row']['price'], 2) . ' บาท' : 'ส่งนอก · ยังไม่ใส่ราคา')];
            }
            if ($ext) { ExternalLabController::syncBilling($visit); }

            // ---- ส่งตรวจวัณโรค: รวมกับที่ติ๊กไว้แล้ว ส่งเฉพาะกลุ่มที่เปลี่ยน ----
            $tb = array_filter($plan, fn ($p) => $p['kind'] === 'tb');
            if ($tb) {
                $in = self::tbInput($visit, array_map(fn ($p) => [$p['item']->tb_key, $p['spec']], $tb));
                if ($in) { TbScreening::saveLabSection($visit, $in); }
                foreach ($tb as $i => $p) {
                    $results[$i] = ['name' => $p['item']->name, 'kind' => 'tb_sendout', 'status' => 'ok', 'message' => 'ติ๊กในส่วนส่งตรวจวัณโรคแล้ว'];
                }
            }

            ksort($results);
            return array_values($results);
        });

        $n = count(array_filter($results, fn ($r) => $r['status'] === 'ok'));
        return ['ok' => true, 'errors' => [], 'results' => $results,
                'message' => 'สั่งแล้ว ' . $n . ' รายการ' . ($n < count($results) ? ' (' . (count($results) - $n) . ' รายการมีอยู่แล้ว)' : '')];
    }

    /**
     * สร้างข้อมูลส่วนส่งตรวจวัณโรค (รูปแบบเดียวกับฟอร์ม tb_lab[...]) ที่ "รวมของเดิม + ที่สั่งเพิ่ม"
     *
     * fillLabInput เขียนทั้งกลุ่มเมื่อได้รับตัวบอกกลุ่ม (lab_sec2_on ฯลฯ) — ถ้าส่งแค่ตัวใหม่ ตัวเดิมและผลที่ลงไว้จะหาย
     * จึงต้องยกค่าเดิมของกลุ่มมาทั้งหมด (วิธีตรวจ + ผล) แล้วเติมตัวใหม่ และส่งเฉพาะกลุ่มที่เปลี่ยนจริง
     *
     * $orders = [[tb_key, spec|null], ...]  spec = ['type','other','date' (d/m/Y)]
     */
    public static function tbInput(Visit $visit, array $orders): array
    {
        $sc = TbScreening::where('visit_id', $visit->id)->first() ?: new TbScreening;

        // ---- 1) สิ่งส่งตรวจ: แถวเดิม (วันที่กลับเป็น d/m/Y ตามที่ฟอร์มส่ง) + ชนิดที่ยังไม่มี ----
        $specs = array_map(fn ($r) => [
            'type'  => $r['type'] ?? '',
            'other' => $r['other'] ?? '',
            'date'  => ! empty($r['date']) ? Utillity::db2thDate($r['date']) : '',
        ], (array) ($sc->lab_specimens ?? []));
        if (! $specs && $sc->lab_specimen) {
            $specs = [['type' => $sc->lab_specimen, 'other' => $sc->lab_specimen_other ?? '',
                       'date' => $sc->lab_sent_date ? Utillity::db2thDate($sc->lab_sent_date) : '']];
        }
        $haveTypes = array_column($specs, 'type');
        $specChanged = false;

        $methods = (array) ($sc->lab_methods ?? []);
        $mol     = (array) ($sc->lab_molecular ?? []);
        $sec2Changed = false;
        $sec3 = ['tst_done' => (bool) $sc->tst_done, 'cytb_done' => (bool) $sc->cytb_done, 'igra_done' => (bool) $sc->igra_done];
        $sec3Changed = false;

        foreach ($orders as [$key, $spec]) {
            if ($spec && ! in_array($spec['type'], $haveTypes, true)) {
                $specs[] = $spec;
                $haveTypes[] = $spec['type'];
                $specChanged = true;
            }
            if ($key === 'afb' || $key === 'culture_dst') {
                if (! in_array($key, $methods, true)) { $methods[] = $key; $sec2Changed = true; }
            } elseif (strpos((string) $key, 'mol:') === 0) {
                $m = substr($key, 4);
                if (! in_array('molecular', $methods, true)) { $methods[] = 'molecular'; $sec2Changed = true; }
                if (! in_array($m, $mol, true)) { $mol[] = $m; $sec2Changed = true; }
            } elseif (in_array($key, ['tst', 'cytb', 'igra'], true)) {
                if (! $sec3[$key . '_done']) { $sec3[$key . '_done'] = true; $sec3Changed = true; }
            }
        }

        $in = [];
        if ($specChanged) {
            $in['lab_specimens_on'] = '1';
            $in['lab_specimens'] = $specs;
        }
        if ($sec2Changed) {
            $in['lab_sec2_on']          = '1';
            $in['lab_methods']          = $methods;
            $in['lab_molecular']        = $mol;
            // ไม่ส่งช่องผลกลับไป — fillLabInput คงผลเดิมของวิธีที่ยังติ๊กอยู่เอง (2569-10-05)
            // ถ้าส่งค่าในใบกลับไป จะไปทับผลที่พยาบาลเพิ่งแก้ในตารางเสมหะของบัตร TB 01 (TbSputumExam::syncFromLab)
        }
        if ($sec3Changed) {
            $in['lab_sec3_on']     = '1';
            $in['tst_done']        = $sec3['tst_done'] ? '1' : null;
            $in['tst_mm']          = $sc->tst_mm;
            $in['cytb_done']       = $sec3['cytb_done'] ? '1' : null;
            $in['cytb_mm']         = $sc->cytb_mm;
            $in['skin_other_name'] = $sc->skin_other_name;
            $in['skin_other_mm']   = $sc->skin_other_mm;
            $in['igra_done']       = $sec3['igra_done'] ? '1' : null;
            $in['igra_result']     = $sc->igra_result;
        }
        return $in;
    }

    /** ค่าปัจจุบันของช่องส่งตรวจวัณโรค — หน้าตรวจ OPD ใช้ติ๊กช่องในหน้าให้ตรงกับที่เพิ่งสั่ง (ไม่ต้องโหลดหน้าใหม่) */
    public static function tbState(Visit $visit): ?array
    {
        $sc = TbScreening::where('visit_id', $visit->id)->first();
        if (! $sc) { return null; }
        return [
            'specimens' => array_map(fn ($r) => [
                'type' => $r['type'] ?? '', 'other' => $r['other'] ?? '',
                'date' => ! empty($r['date']) ? Utillity::db2thDate($r['date']) : '',
            ], (array) ($sc->lab_specimens ?? [])),
            'methods'   => (array) ($sc->lab_methods ?? []),
            'molecular' => (array) ($sc->lab_molecular ?? []),
            'tst'  => (bool) $sc->tst_done,
            'cytb' => (bool) $sc->cytb_done,
            'igra' => (bool) $sc->igra_done,
        ];
    }
}
