<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Billing;
use App\Models\BillingItem;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\TbRegistration;
use App\Models\Visit;
use App\Models\VisitAppointment;
use App\Models\VisitMedicine;
use App\Services\TbPharmacyMap;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * กล่อง "สั่งยาครั้งนี้" ในหน้าตรวจ OPD คลินิกวัณโรค (คลินิกขอ 2569-10-05)
 *
 * เดิมหน้าตรวจโชว์ยาวัณโรคแบบอ่านอย่างเดียว ต้องไปสั่งในบัตร TB 01 แล้วยาไม่เข้ารายการยาของ visit
 * การเงินจึงไม่เห็นค่ายา ห้องยาไม่เห็นว่าต้องจ่ายอะไร/ตัดสต๊อกไม่ได้
 * ตอนนี้ใช้กล่องเดียวกับบัตร TB 01 (tb/_order) กดบันทึกครั้งเดียว:
 *   - ลงรายการยาของ visit ทุกครั้ง (ทั้งยาวัณโรคและยาอื่น) → การเงินคิดเงิน · ห้องยาจ่าย + ตัดสต๊อก FEFO ตามปกติ
 *   - มีบัตร TB 01 ที่ยังรักษาอยู่ → บันทึกรอบ/วิธีใช้/เปลี่ยนสูตรลงบัตรเหมือนกดในบัตร (ทรานแซกชันเดียวกัน)
 *   - ไม่มีบัตร → เลือกสูตร/คิดขนาดยาได้ แต่ไม่เขียนอะไรของบัตร
 * กดบันทึกซ้ำ = แก้เฉพาะแถวที่กล่องนี้สร้าง · ยาที่เพิ่มด้วยปุ่ม Add New ไม่ถูกแตะ
 * การเงินคิดค่ายา/รับเงิน หรือห้องยาจ่ายยาไปแล้ว = ห้ามแก้ (ยอดเงิน/สต๊อกจะไม่ตรงกับใบสั่ง)
 *
 * ใช้ใน TbController (เรียกตัวช่วย private ของกล่องสั่งยาในบัตรได้ตรง ๆ ไม่ต้องคัดลอกตรรกะ)
 */
trait TbVisitOrder
{
    /** บัตร TB ที่ยังรักษาอยู่ของคนไข้ — ตัวเดียวกับที่หน้าตรวจเคยโชว์ยาวัณโรค (_tb_drugs) */
    public static function activeRegistrationFor($patientId, ?Visit $visit = null): ?TbRegistration
    {
        // visit ย้อนหลัง = บัตรที่ครอบวันนั้น แม้ปิดบัตรไปแล้ว (2569-10-27) — ดู TbRegistration::forVisit
        if ($visit) {
            $r = TbRegistration::forVisit($visit);
            return $r ? $r->load(['patient', 'monthlyDoses']) : null;
        }
        return TbRegistration::with(['patient', 'monthlyDoses'])
            ->where('patient_id', $patientId)->whereNull('outcome')->orderByDesc('id')->first();
    }

    /**
     * เกณฑ์ขนาดยาตามอายุ: 18 ปีขึ้นไป = ผู้ใหญ่ · ต่ำกว่า 18 = เด็ก (คลินิกขอ 2569-10-05 · เดิมต่ำกว่า 15 = เด็ก และสลับเองได้)
     * ไม่รู้วันเกิด = ผู้ใหญ่ — Patient::age() เดิมคืน 0 ปีเมื่อไม่มีวันเกิด ทำให้ถูกนับเป็นเด็ก
     * คืน [adult|ped, อายุ(ปี)|null]
     */
    public static function doseTypeFor($patient): array
    {
        $bd = trim((string) optional($patient)->getRawOriginal('birth_date'));
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $bd, $m) || (int) $m[1] < 1800) { return ['adult', null]; }
        $y = (int) $m[1];
        if ($y >= 2400) { $y -= 543; }   // เก็บเป็น พ.ศ. · ข้อมูลเก่าบางแถวเป็น ค.ศ.
        try {
            $birth = \Carbon\Carbon::create($y, (int) $m[2], (int) $m[3])->startOfDay();
        } catch (\Throwable $e) {
            return ['adult', null];
        }
        if ($birth->isFuture()) { return ['adult', null]; }
        $age = (int) $birth->diffInYears(now(), true);
        if ($age > 120) { return ['adult', null]; }

        return [$age >= 18 ? 'adult' : 'ped', $age];
    }

    /**
     * กล่องสั่งยาใช้กับ visit นี้ได้ไหม (ไม่นับล็อกการเงิน) — null = ได้
     * visit วันนี้ หรือ visit ย้อนหลังที่เพิ่งสร้าง (ภายใน 7 วัน) = พยาบาลกำลังลงประวัติการรักษาเก่าเข้าระบบ
     * เดิมล็อกทุก visit ที่ไม่ใช่วันนี้ ลงย้อนหลังแล้วแก้สูตร BDQ ไม่ได้เลย (คลินิกแจ้ง 2569-10-22)
     * visit เก่าที่สร้างไว้นานแล้วยังล็อก — กันเปิดประวัติดูแล้วเผลอแก้รอบยาในบัตร
     */
    private static function visitDateLock(Visit $visit): ?string
    {
        if (self::visitDateBe($visit) === VisitAppointment::todayBe()) { return null; }
        if ($visit->created_at && $visit->created_at->gte(now()->subDays(7))) { return null; }
        return 'visit นี้ไม่ใช่ของวันนี้ — สั่งยาจากกล่องนี้ได้เฉพาะ visit วันนี้ หรือ visit ย้อนหลังที่เพิ่งลงภายใน 7 วัน (ยาย้อนหลังแก้ที่บัตร TB 01 หรือปุ่ม Add New)';
    }

    /** visit ย้อนหลัง → วันที่ของ visit (ใช้เป็น "วันนี้" ของกล่อง) · visit วันนี้ → null */
    private static function visitAsOf(Visit $visit): ?string
    {
        $d = self::visitDateBe($visit);
        return $d === VisitAppointment::todayBe() ? null : $d;
    }

    /** วันที่ของ visit เป็น พ.ศ. Y-m-d (ข้อมูลยุคแรกบางแถวเก็บเป็น ค.ศ.) */
    private static function visitDateBe(Visit $visit): string
    {
        $d = (string) $visit->getRawOriginal('date');
        if (preg_match('/^(\d{4})(-\d{2}-\d{2})/', $d, $m)) {
            $y = (int) $m[1];
            return ($y < 2500 ? $y + 543 : $y) . $m[2];
        }
        return $d;
    }

    /**
     * บัตรจำลองในหน่วยความจำ (ไม่บันทึก) สำหรับคนไข้ที่ยังไม่มีบัตร TB 01
     * ให้เกณฑ์ขนาดยา/ตัวเลือกสูตร/ตัวคำนวณใช้โค้ดชุดเดียวกับบัตรจริง
     * id = 0: ตัวช่วยที่ค้นตามเลขบัตรได้ผลว่าง ไม่ไปเจอข้อมูลของบัตรอื่น · ห้ามเรียก save() (persistOrder กันไว้)
     */
    private static function unregisteredReg(Visit $visit): TbRegistration
    {
        $reg = new TbRegistration();
        $reg->forceFill(['patient_id' => $visit->patient_id, 'registry_type' => TbRegistration::TYPE_TB]);
        $reg->id = 0;
        $reg->setRelation('monthlyDoses', collect());
        $reg->setRelation('patient', Patient::find($visit->patient_id));

        return $reg;
    }

    /** ข้อมูลกล่องสั่งยาสำหรับหน้าตรวจ (เรียกจาก visit/form/_tb_order_box) */
    public function visitOrderBoxData(Visit $visit): array
    {
        $reg = self::activeRegistrationFor($visit->patient_id, $visit);
        $registered = (bool) $reg;
        if (! $reg) { $reg = self::unregisteredReg($visit); }

        // ช่วงเดือนเหมือนหน้าบัตร (show) — ตัวเลือกรอบจะได้ตรงกัน
        $maxMonth = $registered
            ? min(31, max(9, $reg->monthsOnTreatment(), (int) $reg->sputumExams()->max('month_no')))
            : 9;
        // visit ย้อนหลัง: แถวรอบที่ยังว่างเติมให้ในช่วงสูตรของวันนั้น (2HRZE ปี 68 ไม่ใช่ BPaL ที่ใช้อยู่)
        $asOf = self::visitAsOf($visit);
        $doseRows = $this->doseRows($reg, $maxMonth, $registered && $asOf ? $reg->segmentIndexFor($asOf) : null);
        [$w, $wSrc] = $reg->lastKnownWeight($visit->weight ?: null);
        [$doseType, $age] = self::doseTypeFor($reg->patient);

        $order = $this->orderBoxData($reg, $doseRows, $maxMonth, $w, $wSrc, $doseType, self::visitAsOf($visit));
        $order['doseAge'] = $age;

        // ยาแต่ละตัว (และแต่ละความแรง) ตรงกับยาตัวไหนในคลังยา — หน้าจอเตือนตัวที่ยังไม่มี ไม่ต้องรอกดบันทึก
        $pharm = [];
        foreach ($order['rows'] as $k => $r) {
            foreach (($r['kind'] === 'strength' && $r['strengths'] ? $r['strengths'] : [null]) as $s) {
                $m = TbPharmacyMap::resolve((string) $k, $s ? (float) $s : null, $why);
                $pharm[$k][$s ? self::fmtNum($s) : '*'] = $m ? ['id' => $m->id, 'name' => $m->name] : ['why' => $why];
            }
        }

        // ยาวัณโรคที่หมอสั่งผ่านปุ่ม Add New (คลินิกขอ 2569-10-12 "สั่งได้ 2 ที่") — เติมครั้งละ/ความถี่/เวลาลงแถวในกล่องให้
        // กดบันทึกกล่องแล้ว writeVisitMedicines รับแถวนั้นมาเป็นของกล่อง (ไม่ลงซ้ำ) + ลงบัตร TB 01
        // แถวที่กล่องสั่งไว้แล้ววันนี้ (qty มีค่า) ไม่ทับ · ยาที่สั่งเป็น มก. คิดเป็นเม็ดไม่ได้ ข้าม
        $byMed = [];
        foreach ($pharm as $k => $byStr) {
            foreach ($byStr as $s => $m) {
                if (isset($m['id'])) { $byMed[(string) $m['id']][] = [(string) $k, (string) $s]; }
            }
        }
        // ตารางยาตารางเดียว (คลินิกขอ 2569-10-14): ทุกแถวยาของ visit ต้องโผล่ในกล่อง — แถวยาวัณโรค / แถวยาอื่น / แถวที่กล่องรับไม่ได้ (อ่านอย่างเดียว)
        // vmInfo = ข้อมูลแถวในรายการยา (จำนวน · ราคา · ปุ่มแก้/ลบ) ให้แต่ละแถวในกล่องโชว์คอลัมน์ราคาได้
        $vid = (int) $visit->id;
        $allVm = VisitMedicine::where('visit_id', $vid)->orderBy('id')->get();
        $medsById = \App\Models\Medicine::whereIn('id', $allVm->pluck('medicine_id')->filter()->unique())->get()->keyBy('id');
        $page = request('page', 1);
        $vmInfo = function (VisitMedicine $vm) use ($medsById, $page, $vid) {
            $m = $medsById[$vm->medicine_id] ?? null;
            $price = $m ? (float) $m->price : 0.0;
            return [
                'id' => $vm->id, 'name' => $m->name ?? ('#' . $vm->medicine_id), 'unit' => $m->unit ?? '',
                'amount' => (string) $vm->amount, 'price' => $price, 'total' => $price * (float) ($vm->amount ?: 0),
                'hasMed' => (bool) $m, 'sigText' => $vm->sigText(), 'box' => self::isBoxRow($vm, $vid),
                'edit' => route('visit-medicine.edit', ['visit_medicine' => $vm->id, 'page' => $page]),
                'del' => route('visit-medicine.destroy', ['visit_medicine' => $vm->id, 'page' => $page]),
            ];
        };
        $boxByKey = $allVm->filter(fn ($vm) => self::isBoxRow($vm, $vid))->keyBy(fn ($vm) => self::boxKey($vm));
        $manualRows = $allVm->reject(fn ($vm) => self::isBoxRow($vm, $vid))->values();

        $fromManual = [];
        $linked = [];      // คีย์แถวในกล่อง ('Bdq' / 'other:Para') => vmInfo
        $usedVm = [];
        foreach ($boxByKey as $key => $vm) { $linked[(string) $key] = $vmInfo($vm); $usedVm[$vm->id] = true; }
        // วิธีใช้ที่บันทึกกับ visit นี้ (แถวที่กล่องลงรายการยาไว้) มาก่อนวิธีใช้ในบัตร (คลินิกแจ้ง 2569-10-27)
        // visit ย้อนหลังในช่วงสูตรเก่าไม่เขียนวิธีใช้ลงบัตร — เดิมเปิดกล่องใหม่แล้วช่วงเวลา/ยาทาเด้งกลับเป็นค่าในบัตร
        // (ติ๊กเช้า-เย็นแล้วหาย · balm หลอด/ทา กลายเป็นเม็ด/กิน) กดบันทึกซ้ำก็ทับของจริงด้วยค่าผิด
        $fromVm = function (array $base, VisitMedicine $vm, bool $other) {
            $ms = (array) $vm->sig;
            foreach (['freq' => TbRegistration::$sigFreq, 'route' => TbRegistration::$sigRoute] as $f => $opts) {
                if (array_key_exists((string) ($ms[$f] ?? ''), $opts)) { $base[$f] = (string) $ms[$f]; }
            }
            $t = (string) ($ms['timing'] ?? '');
            if ($t === '' || array_key_exists($t, TbRegistration::$sigTiming)) { $base['timing'] = $t; }
            $base['period'] = array_values((array) ($ms['period'] ?? []));
            // ยาวัณโรคที่สั่งเป็น มก. มีขนาดยาเติมหน้าหมายเหตุในรายการยา — หมายเหตุเอาเฉพาะยาอื่น
            if ($other) {
                $base['note'] = (string) ($ms['note'] ?? '');
                if (in_array((string) ($ms['unit'] ?? ''), TbRegistration::$sigUnit, true)) { $base['unit'] = (string) $ms['unit']; }
            }
            return $base;
        };
        foreach ($boxByKey as $key => $vm) {
            if (isset($order['rows'][(string) $key])) {
                $order['rows'][(string) $key]['sig'] = $fromVm((array) $order['rows'][(string) $key]['sig'], $vm, false);
            }
        }
        // แถวยาวัณโรคที่โชว์ตามสูตรที่เลือกอยู่ — ยาที่ Add New มาแต่ไม่อยู่ในสูตร (เช่น B6 กับสูตร RZE-Lfx) ต้องไปเป็น "ยาอื่น"
        // ไม่งั้นไปผูกกับแถวที่ซ่อนอยู่ ยาหายจากตาราง พยาบาลเห็นว่าเพิ่มยาอื่นไม่ได้ (คลินิกแจ้ง 2569-10-26)
        $shownKeys = (array) ($order['cur']['curSet'] ?? []);
        if (TbRegistration::needsB6($shownKeys)) { $shownKeys[] = 'B6'; }
        foreach ($manualRows as $vm) {
            foreach ($byMed[(string) $vm->medicine_id] ?? [] as [$k, $s]) {
                $r = $order['rows'][$k] ?? null;
                if (! $r || isset($linked[$k]) || ! in_array($k, $shownKeys, true)) { continue; }
                $linked[$k] = $vmInfo($vm);
                $usedVm[$vm->id] = true;
                // แถวที่กล่องสั่งไว้แล้ววันนี้ (qty มีค่า) ไม่ทับ · ยาที่สั่งเป็น มก. คิดเป็นเม็ดไม่ได้ — ผูกแถวไว้อย่างเดียว
                $ms = (array) $vm->sig;
                $q = self::qtyNumber($ms['qty'] ?? '');
                if (($r['qty'] !== null && $r['qty'] !== '') || $r['kind'] === 'mg' || ! $q) { break; }
                $r['qty'] = self::fmtNum($q);
                foreach (['freq' => TbRegistration::$sigFreq, 'route' => TbRegistration::$sigRoute, 'timing' => TbRegistration::$sigTiming] as $f => $opts) {
                    if (array_key_exists((string) ($ms[$f] ?? ''), $opts)) { $r['sig'][$f] = (string) $ms[$f]; }
                }
                if (! empty($ms['period'])) { $r['sig']['period'] = array_values((array) $ms['period']); }
                if (trim((string) ($ms['note'] ?? '')) !== '') { $r['sig']['note'] = (string) $ms['note']; }
                if ($s !== '*') { $r['strength'] = (float) $s; $r['sig']['strength'] = $s; }
                $order['rows'][$k] = $r;
                $fromManual[$k] = optional($medsById[$vm->medicine_id] ?? null)->name ?: $k;
                break;
            }
        }

        // ยาอื่นที่กล่องสั่งไว้วันนี้ ผูกกับแถวในรายการยาด้วยชื่อ (คีย์ของกล่อง = other:ชื่อ 20 ตัวอักษร)
        foreach ($order['others'] as $i => $ot) {
            $key = 'other:' . mb_substr((string) $ot['name'], 0, 20);
            if (isset($boxByKey[$key])) {
                $order['others'][$i]['vm'] = $linked[$key];
                $order['others'][$i]['sig'] = $fromVm((array) ($ot['sig'] ?? []), $boxByKey[$key], true);
                // จำนวนจ่ายที่บันทึกไว้ของยาที่คิดจากวันไม่ได้ (เมื่อมีอาการ / หลอด / ขวด เช่น balm 3 หลอด) ไม่ให้กล่องคิดใหม่ทับ
                $os = $order['others'][$i]['sig'];
                if ((string) $boxByKey[$key]->amount !== '' && (($os['freq'] ?? '') === 'PRN' || in_array($os['unit'] ?? '', ['หลอด', 'ขวด'], true))) {
                    $order['others'][$i]['sig']['dispense'] = (string) $boxByKey[$key]->amount;
                }
            }
        }
        // ยาอื่นที่สั่งผ่าน Add New (Para ฯลฯ) — หน่วย/ความถี่ที่กล่องรองรับ = เป็นแถวยาอื่นในกล่อง (กดบันทึกแล้วกล่องรับแถวไป ไม่ลงซ้ำ)
        // จำนวนที่หมอกรอกไว้ใส่เป็น "จ่ายทั้งหมด" ไม่ให้กล่องคิดใหม่ทับ · ที่เหลือ (vial, STAT, วัคซีน ...) โชว์อ่านอย่างเดียวท้ายตาราง
        $extra = [];
        foreach ($manualRows as $vm) {
            if (isset($usedVm[$vm->id])) { continue; }
            $ms = (array) $vm->sig;
            $info = $vmInfo($vm);
            $unit = (string) ($ms['unit'] ?? '') ?: 'เม็ด';
            $freq = (string) ($ms['freq'] ?? '') ?: 'OD';
            $q = self::qtyNumber($ms['qty'] ?? '');
            if ($info['hasMed'] && $q && in_array($unit, TbRegistration::$sigUnit, true) && array_key_exists($freq, TbRegistration::$sigFreq)) {
                $sig = ['qty' => self::fmtNum($q), 'unit' => $unit, 'freq' => $freq,
                        'route' => array_key_exists((string) ($ms['route'] ?? ''), TbRegistration::$sigRoute) ? (string) $ms['route'] : 'PO',
                        'timing' => array_key_exists((string) ($ms['timing'] ?? ''), TbRegistration::$sigTiming) ? (string) $ms['timing'] : '',
                        'period' => array_values((array) ($ms['period'] ?? [])), 'note' => (string) ($ms['note'] ?? '')];
                if (trim($info['amount']) !== '') { $sig['dispense'] = trim($info['amount']); }
                $order['others'][] = ['name' => $info['name'], 'qty' => $sig['qty'], 'sig' => $sig, 'vm' => $info];
                $fromManual['other:' . $vm->id] = $info['name'];
                continue;
            }
            $extra[] = $info;
        }

        $lock = self::visitDateLock($visit) ?? self::visitOrderLock($visit);

        return [
            'reg' => $reg,
            'order' => $order,
            'embed' => [
                'visit' => $visit,
                'registered' => $registered,
                'url' => route('tb.visitOrder', $visit->id),
                'pharm' => $pharm,
                'lock' => $lock,
                // ล็อกเพราะเป็น visit เก่าอย่างเดียว (ไม่ใช่การเงินคิดเงินแล้ว) = ปลดล็อกเองได้เพื่อลงยาย้อนหลัง (2569-10-27)
                'dateLockOnly' => $lock !== null && self::visitOrderLock($visit) === null && self::visitDateLock($visit) !== null,
                'asOf' => self::visitAsOf($visit),
                'medNames' => TbPharmacyMap::names(),
                'fromManual' => $fromManual,
                'linked' => $linked,
                // ตารางเดียว = กล่องกางอยู่ (มีบัตร หรือเคยลงยาจากกล่อง) และแก้ได้ — ตรงกับเงื่อนไขพับกล่องใน _tb_order_box
                'unified' => ! $lock && ($registered || $boxByKey->isNotEmpty()),
                'extra' => $extra,
                'boxCount' => VisitMedicine::where('visit_id', $visit->id)->get()->filter(fn ($vm) => self::isBoxRow($vm, (int) $visit->id))->count(),
            ],
        ];
    }

    /**
     * ห้ามแก้การสั่งยาจากกล่องเมื่อการเงิน/ห้องยาทำงานกับ visit นี้ไปแล้ว — คืนข้อความ หรือ null = แก้ได้
     * ราคา/จำนวนในบิลอ้างอิงแถวยาของ visit (billing_items.item_id) ถ้าเปลี่ยนแถวทีหลัง ยอดเงินกับของที่จ่ายจะไม่ตรงกัน
     */
    private static function visitOrderLock(Visit $visit, bool $forUpdate = false): ?string
    {
        $q = Billing::where('visit_id', $visit->id);
        $bill = $forUpdate ? $q->lockForUpdate()->first() : $q->first();
        if (! $bill || $bill->status === 'cancelled') { return null; }
        if ($bill->stock_deducted_at) {
            return 'ห้องยาจ่ายยา/ตัดสต๊อกของ visit นี้ไปแล้ว — แก้การสั่งยาจากหน้าตรวจไม่ได้ ติดต่อห้องยา';
        }
        if ($bill->status === 'paid' || (float) Payment::where('billing_id', $bill->id)->sum('amount') > 0) {
            return 'การเงินรับเงินของ visit นี้ไปแล้ว — แก้การสั่งยาจากหน้าตรวจไม่ได้ ติดต่อห้องการเงิน';
        }
        if (BillingItem::where('billing_id', $bill->id)->where('item_type', 'medicine')->exists()) {
            return 'การเงินคิดค่ายาของ visit นี้ไว้แล้ว — ให้การเงินแก้บิลก่อน (หรือเพิ่มยาด้วยปุ่ม Add New)';
        }
        return null;
    }

    /** คอลัมน์ source ของ visit_medicines (migration 2026_10_05_200000) — ยังไม่รันก็ใช้เครื่องหมายใน sig แทน */
    private static function vmHasSource(): bool
    {
        static $has = null;
        return $has = $has ?? Schema::hasColumn('visit_medicines', 'source');
    }

    /**
     * แถวยานี้กล่องสั่งยาวัณโรคของ visit นี้สร้างไว้ไหม
     * เครื่องหมายใน sig ต้องมีเลข visit ด้วย — ปุ่ม "ยาเดิม" คัดลอก sig ทั้งก้อนมาจาก visit ก่อน
     * ถ้าไม่เช็คเลข visit แถวที่หมอดึงมาเองจะถูกนับเป็นของกล่องแล้วโดนลบตอนกดบันทึกกล่อง
     */
    private static function isBoxRow(VisitMedicine $vm, int $visitId): bool
    {
        if (self::vmHasSource() && $vm->source === 'tb_order') { return true; }
        $s = (array) $vm->sig;
        return ($s['_src'] ?? null) === 'tb_order' && (int) ($s['_visit'] ?? 0) === $visitId;
    }

    private static function boxKey(VisitMedicine $vm): string
    {
        if (self::vmHasSource() && $vm->source_key) { return (string) $vm->source_key; }
        return (string) (((array) $vm->sig)['_key'] ?? ('#' . $vm->id));
    }

    /**
     * ผลจากกล่อง (buildOrder) → แถวยาที่จะลงรายการยาของ visit
     * จำนวนจ่าย = ครั้งละ × จำนวนครั้ง (ครั้งต่อวัน × วัน · รายสัปดาห์ปัดจำนวนครั้งขึ้น) ปัดขึ้นเป็นเม็ดเต็ม
     * ยาอื่นที่กรอก "จ่ายทั้งหมด" ไว้ใช้ตัวเลขนั้น · ยาที่สั่งเป็น มก. (ยาฉีด/ไม่รู้ความแรง) คิดจำนวนไม่ได้ ปล่อยว่างให้ห้องยากรอก
     */
    private function visitOrderLines(TbRegistration $reg, array $o, int $days, ?string $startBe = null): array
    {
        $lines = [];
        foreach ($o['doses'] as $k => $amt) {
            $k = (string) $k;
            $sig  = (array) ($o['sigs'][$k] ?? []);
            $freq = array_key_exists((string) ($sig['freq'] ?? ''), TbRegistration::$sigFreq) ? (string) $sig['freq'] : 'OD';
            $p    = TbRegistration::$sigFreq[$freq][1];
            // รายสัปดาห์นับวันนี้ + วันนัดรวมสองหัว (ดู TbRegistration::doseTimes)
            $times = $p === null ? null : TbRegistration::doseTimes($freq, $days, (string) ($sig['note'] ?? ''), $startBe);
            $isTb = in_array($k, $o['tbKeys'], true);
            $why  = null;
            $mgText = null;

            if ($isTb) {
                $kind = self::orderDrugKind($reg, $k);
                $strength = $kind['kind'] === 'strength' ? ((float) ($sig['strength'] ?? 0) ?: null) : null;
                $qty  = $kind['kind'] === 'mg' ? null : self::qtyNumber($sig['qty'] ?? '');
                $unit = 'เม็ด';
                if ($kind['kind'] === 'mg') {
                    $mgText = self::fmtNum((float) $amt) . ' มก./' . ($p !== null && $p < 1 ? 'ครั้ง' : 'วัน');
                }
                $name = $k . ($strength ? ' ' . self::fmtNum($strength) . ' mg' : '');
                $med  = TbPharmacyMap::resolve($k, $strength, $why);
                $srcKey = $k;
                $total = $qty !== null && $times !== null ? $qty * $times : null;
            } else {
                $full = (string) ($o['otherNames'][$k] ?? $k);
                $qty  = self::qtyNumber($sig['qty'] ?? '');
                $unit = (string) ($sig['unit'] ?? 'เม็ด');
                $strength = null;
                $name = $full;
                $med  = TbPharmacyMap::resolveName($full);
                if (! $med) { $why = 'ไม่ตรงกับชื่อยาในคลังยา'; }
                $srcKey = 'other:' . mb_substr($k, 0, 100);
                // หลอด/ขวด (ยาทา ยาน้ำ ยาหยอดตา) ไม่ได้ใช้หมดทีละหน่วย — ไม่ใส่จำนวนจ่าย = 1 ไม่ใช่ ครั้งละ × ครั้ง (เคยได้ 56 หลอด)
                $total = isset($sig['dispense']) && $sig['dispense'] !== ''
                    ? (float) $sig['dispense']
                    : (in_array($unit, ['หลอด', 'ขวด'], true) ? 1.0 : ($qty !== null && $times !== null ? $qty * $times : null));
            }

            $amount = $total !== null && $total > 0 ? (string) (int) ceil($total - 1e-9) : '';
            $dose = $qty !== null
                ? self::fmtNum($qty) . ' ' . $unit . ($strength ? ' (' . self::fmtNum($strength) . ' mg)' : '') . ' ' . $freq
                : (string) $mgText;

            // วิธีใช้รูปแบบเดียวกับหน้าสั่งยาคลินิกหลัก (DrugSig) — ห้องยา/ใบสั่งยาอ่านข้อความเดียวกัน
            $note = trim(($mgText ? $mgText . ' ' : '') . (string) ($sig['note'] ?? ''));
            $vsig = [
                'qty'    => $qty !== null ? (string) ($sig['qty'] ?? self::fmtNum($qty)) : '',
                'unit'   => in_array($unit, \App\Support\DrugSig::$unit, true) ? $unit : '',
                'freq'   => $freq,
                'route'  => (string) ($sig['route'] ?? 'PO') ?: 'PO',
                'timing' => (string) ($sig['timing'] ?? ''),
                'days'   => $p === null ? '' : (string) $days,
                'period' => array_values((array) ($sig['period'] ?? [])),
                'note'   => \App\Support\DrugSig::cleanNote($note),
            ];

            $lines[] = compact('srcKey', 'name', 'med', 'why', 'amount', 'dose', 'vsig', 'isTb');
        }

        return $lines;
    }

    /**
     * เขียนแถวยาลง visit — แก้เฉพาะแถวที่กล่องนี้สร้าง (จับคู่ด้วยรหัสยาในกล่อง) · แถวของกล่องที่ไม่ได้สั่งแล้ว = ลบ
     * ยาที่หมอเพิ่มเอง (Add New / ยาเดิม) ไม่แตะเลย ถ้าเป็นยาตัวเดียวกันก็ไม่ลงซ้ำ แค่เตือน
     */
    private function writeVisitMedicines(Visit $visit, array $lines): array
    {
        $vid = (int) $visit->id;
        $all = VisitMedicine::where('visit_id', $vid)->lockForUpdate()->get();
        $box = $all->filter(fn ($vm) => self::isBoxRow($vm, $vid))->keyBy(fn ($vm) => self::boxKey($vm));
        $manualRows = $all->reject(fn ($vm) => self::isBoxRow($vm, $vid))->values();
        $manual = $manualRows->pluck('medicine_id')->map(fn ($x) => (string) $x)->all();

        $warnings = [];
        $written  = [];
        $keep     = [];
        foreach ($lines as $l) {
            if (! $l['med']) {
                $warnings[] = $l['name'] . ': ' . $l['why'] . ' — ไม่ได้ลงรายการยา (ห้องยาต้องเพิ่มเอง)';
                continue;
            }
            // ยาตัวเดียวกันที่สั่งผ่าน Add New และกล่องยังไม่เคยลงแถวของยานี้ = รับแถวนั้นมาเป็นของกล่อง (2569-10-12 สั่งได้ 2 ที่)
            // กล่องเติมค่าจากแถวนั้นให้ตอนเปิดหน้าแล้ว · แถวเดิมจะได้จำนวนจ่าย/วิธีใช้ตามกล่อง ไม่เกิดแถวซ้ำ
            $adopt = null;
            if (! $box->has($l['srcKey'])) {
                $adopt = $manualRows->first(fn ($vm) => (string) $vm->medicine_id === (string) $l['med']->id);
            }
            if ($adopt) {
                $manualRows = $manualRows->reject(fn ($vm) => $vm->id === $adopt->id)->values();
                $manual = $manualRows->pluck('medicine_id')->map(fn ($x) => (string) $x)->all();
            } elseif (in_array((string) $l['med']->id, $manual, true)) {
                $warnings[] = $l['med']->name . ' มีในรายการยาอยู่แล้ว (เพิ่มเอง) — ไม่ลงซ้ำ ตรวจจำนวนในรายการยาเอง';
                continue;
            }
            if ($l['amount'] === '') {
                $warnings[] = $l['med']->name . ': คิดจำนวนจ่ายไม่ได้ — ห้องยา/การเงินต้องกรอกจำนวนเอง';
            }

            $vm = $box->get($l['srcKey']) ?: ($adopt ?: new VisitMedicine());
            $vm->fill([
                'visit_id'    => $vid,
                'medicine_id' => (string) $l['med']->id,
                'dose'        => mb_substr($l['dose'], 0, 255),
                'route'       => $l['vsig']['route'],
                'amount'      => $l['amount'],
                'sig'         => $l['vsig'] + ['_src' => 'tb_order', '_visit' => $vid, '_key' => $l['srcKey']],
            ]);
            if (self::vmHasSource()) {
                $vm->forceFill(['source' => 'tb_order', 'source_key' => $l['srcKey']]);
            }
            $vm->save();
            $keep[] = $vm->id;
            $written[] = ['id' => $vm->id, 'key' => $l['srcKey'], 'medicine' => $l['med']->name, 'amount' => $l['amount']];
        }
        foreach ($box as $vm) {
            if (! in_array($vm->id, $keep, true)) { $vm->delete(); }
        }

        // เหมือน VisitMedicineController@store: คนที่สั่งยาคือผู้สั่งยาของ visit นี้
        if ($written && Auth::check()) {
            $visit->pharmacy_by = Auth::id();
            $visit->handled_by  = Auth::id();
            $visit->save();
        }

        return compact('warnings', 'written');
    }

    /**
     * วันนัดครั้งหน้าของกล่องสั่งยา → ลงเป็นนัดจริงในตารางนัด ถ้าคนไข้ยังไม่มีนัดวันนั้น (คลินิกขอ 2569-10-29)
     * เดิมวันในกล่องใช้คิดจำนวนวัน/เม็ดอย่างเดียว พยาบาลนึกว่าลงนัดแล้ว คนไข้เลยไม่มีนัด ไม่ได้ LINE เตือน
     * คิดวันจาก "จำนวนวัน" (ผ่าน validate แล้ว) ไม่ใช้ช่องวันที่ — สองช่องนี้ผูกกันอยู่แล้วในหน้า
     * visit ย้อนหลังไม่ลงนัด (วันนัดของรอบนั้นผ่านไปแล้ว) · วันหยุด = ไม่ลง บอกให้ลงเองในกล่องวันนัด
     * คืน null = ไม่ได้ทำอะไร · ['id','date','created','msg','warn']
     */
    private static function ensureOrderAppointment(Visit $visit, int $days): ?array
    {
        if ($days < 1 || self::visitAsOf($visit) !== null) { return null; }
        $todayAd = \Carbon\Carbon::createFromFormat('Y-m-d', \App\Utillity::BE2AD(self::visitDateBe($visit)))->startOfDay();
        $dAd = $todayAd->copy()->addDays($days);
        $be  = \App\Utillity::AD2BE($dAd->format('Y-m-d'));
        $th  = \App\Utillity::db2thDate($be);
        $out = ['id' => null, 'date' => $th, 'created' => false, 'msg' => '', 'warn' => ''];

        // มีนัดวันนั้นอยู่แล้ว (ลงในกล่องวันนัดแล้ว หรือนัดจากครั้งก่อน) = ไม่ลงซ้ำ
        $same = VisitAppointment::open()->where('patient_id', $visit->patient_id)->where('appointment_date', $be)->first();
        if ($same) {
            $out['id'] = $same->id;
            $out['msg'] = 'มีนัด ' . $th . ' ในระบบแล้ว';
            return $out;
        }
        $hol = \App\Models\Holiday::nameForBE($be);
        if ($hol || $dAd->isWeekend()) {
            $out['warn'] = 'ยังไม่ได้ลงนัด — ' . $th . ' เป็น' . ($hol ?: 'วันเสาร์-อาทิตย์') . ' แก้จำนวนวัน หรือลงนัดเองในกล่อง "วันนัด"';
            return $out;
        }

        $a = VisitAppointment::create([
            'visit_id'         => $visit->id,
            'patient_id'       => $visit->patient_id,
            'appointment_date' => $be,
            'made_by'          => Auth::id(),
            'doctor_id'        => $visit->doctor_by ?: null,   // แบบเดียวกับแถวนัดใหม่ในหน้าตรวจ
        ]);
        VisitAppointment::syncMirror($visit->id);

        // นัดอื่นที่ใกล้กันไม่เกิน 14 วัน (น่าจะเป็นนัดรับยาเดิมที่เลื่อน) — ไม่ลบให้ เพราะอาจเป็นนัดคนละเรื่อง แต่บอกให้เห็น
        // นัดไกล ๆ เช่น CXR 6 เดือน ไม่ต้องเตือน
        $other = VisitAppointment::open()->where('patient_id', $visit->patient_id)
            ->where('appointment_date', '>', VisitAppointment::todayBe())->where('id', '!=', $a->id)
            ->whereBetween('appointment_date', [
                \App\Utillity::AD2BE($dAd->copy()->subDays(14)->format('Y-m-d')),
                \App\Utillity::AD2BE($dAd->copy()->addDays(14)->format('Y-m-d')),
            ])
            ->orderBy('appointment_date')->pluck('appointment_date')
            ->map(fn ($d) => \App\Utillity::db2thDate($d))->unique()->values()->all();

        $out['id'] = $a->id;
        $out['created'] = true;
        $out['msg'] = 'ลงนัด ' . $th . ' ให้แล้ว (ดูในกล่องวันนัด)';
        if ($other) {
            $out['warn'] = 'คนไข้มีนัดวันอื่นอยู่ด้วย: ' . implode(', ', $other) . ' — ถ้าเป็นการเลื่อนนัด ให้ลบนัดเดิมในกล่อง "วันนัด"';
        }
        return $out;
    }

    /** POST จากกล่องสั่งยาในหน้าตรวจ (fetch) — ตอบ JSON เสมอ หน้าตรวจไม่ต้องโหลดใหม่ */
    public function saveVisitOrder(Request $request, $visitId)
    {
        $visit = Visit::findOrFail($visitId);
        $fail = fn ($msg, $code = 422) => response()->json(['ok' => false, 'message' => $msg], $code);

        if ((int) $visit->clinic !== 2) { return $fail('กล่องนี้ใช้กับ visit ของคลินิกวัณโรคเท่านั้น'); }
        // visit เก่า: ส่ง backfill=1 มา = พยาบาลกดปลดล็อกยืนยันแล้วว่าจะลงยาย้อนหลัง (เช่น visit วันเริ่มยา 13/03/68 ที่มีในระบบมาก่อน)
        if (($dl = self::visitDateLock($visit)) && ! $request->boolean('backfill')) { return $fail($dl); }
        if ($lock = self::visitOrderLock($visit)) { return $fail($lock, 409); }

        $reg = self::activeRegistrationFor($visit->patient_id, $visit);
        if (! $reg) {
            $reg = self::unregisteredReg($visit);
            // ไม่มีบัตร = ไม่มีแถวรอบ — ใส่ป้ายแทนไว้ให้ผ่านขั้นตรวจรอบของ buildOrder (ไม่ได้บันทึกที่ไหน)
            $request->merge(['label' => '-']);
        }

        $o = $this->buildOrder($request, $reg, self::visitAsOf($visit));
        if (is_string($o)) { return $fail($o); }

        $lines = $this->visitOrderLines($reg, $o, (int) $request->input('days'), self::visitDateBe($visit));

        try {
            [$row, $res] = DB::transaction(function () use ($reg, $o, $request, $visit, $lines) {
                // เช็คซ้ำแบบล็อกแถว — ระหว่างหมอกรอก การเงินอาจกดคิดเงินไปแล้ว
                if ($lock = self::visitOrderLock($visit, true)) { throw new \RuntimeException($lock); }
                $row = $reg->exists ? $this->persistOrder($reg, $o, $request) : null;
                return [$row, $this->writeVisitMedicines($visit, $lines)];
            });
        } catch (\RuntimeException $e) {
            return $fail($e->getMessage(), 409);
        }

        $appt = self::ensureOrderAppointment($visit, (int) $request->input('days'));

        $msg = $row
            ? 'บันทึกการสั่งยา ' . $row->labelForHumans() . ' (' . (int) $request->input('days') . ' วัน) ลง' . $reg->cardName() . ' และรายการยาของ visit แล้ว'
              . ($o['changed'] ? ' · ' . (! empty($o['restart']) ? 'Off สูตรเดิม เริ่มนับรอบใหม่ สูตร ' : 'เปลี่ยนสูตรเป็น ') . $o['regText'] . ' (สูตรเดิมเก็บไว้ในประวัติ)' : '')
              . (! empty($o['pastSeg']) ? ' · ลงเป็นรอบของช่วงสูตรเก่า สูตรที่ใช้อยู่ตอนนี้ไม่เปลี่ยน' : '')
              . (! empty($o['pastChange']) ? ' · ลงประวัติสูตรยาย้อนหลัง: ' . $o['regText'] . ' เริ่ม ' . \App\Utillity::db2thDate($o['today']) : '')
            : 'ลงรายการยาของ visit แล้ว (' . (int) $request->input('days') . ' วัน) — คนไข้ยังไม่มีบัตร TB 01 จึงไม่ได้บันทึกลงบัตร';
        if ($appt && $appt['msg'] !== '') { $msg .= ' · ' . $appt['msg']; }

        return response()->json([
            'ok'       => true,
            'message'  => $msg,
            'appointment' => $appt,
            'warnings' => array_merge($res['warnings'], $appt && $appt['warn'] ? [$appt['warn']] : []),
            'written'  => $res['written'],
            'table'    => view('visit-medicine.table', ['visitMedicine' => $visit->visitMedicine(), 'editable' => 1])->render(),
            'print'    => $row ? [
                'labels' => route('tb.labels', ['id' => $reg->id, 'row' => $row->id]),
                'sheet'  => route('tb.medSheet', $reg->id),
            ] : null,
        ]);
    }
}
