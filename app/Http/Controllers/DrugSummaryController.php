<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use App\Models\VisitMedicine;
use App\Support\DrugSig;

/**
 * ใบสรุปรายการยากลับบ้าน หรือส่งต่อสถานพยาบาลใกล้บ้าน (คลินิกวัณโรคขอ 2569-10-06)
 *
 * ต้นแบบคือใบของ รพ.นครพิงค์ ที่คนไข้ถือมา — คนไข้ TB ต้องกินยาต่อเนื่องหลายเดือน
 * ถ้าไปรักษาที่อื่น/ย้ายที่อยู่ ใบนี้บอกโรงพยาบาลปลายทางได้ว่ากินยาอะไร ขนาดเท่าไร
 * ข้อมูลมาจากรายการยาของ visit (visit_medicines) ชุดเดียวกับที่การเงิน/ห้องยาใช้
 * คำอธิบายยาเป็นค่าตั้งต้นตามชื่อยา แก้บนหน้าจอได้ก่อนพิมพ์ (เภสัชกร/พยาบาลตรวจทานเอง)
 */
class DrugSummaryController extends Controller
{
    /** คำอธิบายตั้งต้นตามคำในชื่อยา (ตัวเล็ก) — คำแรกที่เจอชนะ */
    private const ADVICE = [
        'fdc'          => 'รับประทานตามแพทย์สั่งจนครบ ห้ามหยุดยาเอง · ปัสสาวะ น้ำตา เหงื่ออาจมีสีส้มแดงจากยา ไม่เป็นอันตราย · ถ้าตัวเหลือง ตาเหลือง ผื่นคัน หรือคลื่นไส้มาก ให้รีบพบแพทย์',
        'forecox'      => 'รับประทานตามแพทย์สั่งจนครบ ห้ามหยุดยาเอง · ปัสสาวะ น้ำตา เหงื่ออาจมีสีส้มแดงจากยา ไม่เป็นอันตราย · ถ้าตัวเหลือง ตาเหลือง ผื่นคัน หรือคลื่นไส้มาก ให้รีบพบแพทย์',
        'rifampicin'   => 'รับประทานตอนท้องว่าง ห้ามหยุดยาเอง · ปัสสาวะ น้ำตา เหงื่ออาจมีสีส้มแดง ไม่เป็นอันตราย',
        'isoniazid'    => 'รับประทานต่อเนื่องจนครบ · ถ้าชาปลายมือปลายเท้า ตัวเหลือง ตาเหลือง ให้แจ้งแพทย์',
        'pyrazinamide' => 'รับประทานต่อเนื่องจนครบ · ถ้าปวดข้อ ตัวเหลือง ตาเหลือง ให้แจ้งแพทย์',
        'ethambutol'   => 'รับประทานต่อเนื่องจนครบ · ถ้าตามัว มองสีผิดปกติ ให้หยุดยาและพบแพทย์ทันที',
        'b6'           => 'ป้องกันอาการชาปลายมือปลายเท้าจากยาวัณโรค',
        'pyridoxine'   => 'ป้องกันอาการชาปลายมือปลายเท้าจากยาวัณโรค',
        'levofloxacin' => 'ไม่รับประทานพร้อมนม ยาลดกรด หรือธาตุเหล็ก (เว้นห่าง 2 ชั่วโมง)',
        'moxifloxacin' => 'ไม่รับประทานพร้อมนม ยาลดกรด หรือธาตุเหล็ก (เว้นห่าง 2 ชั่วโมง)',
        'paracetamol'  => 'แก้ปวด ลดไข้ · ไม่ควรรับประทานเกินวันละ 8 เม็ด · ยานี้มีผลต่อตับ',
        'ibuprofen'    => 'แก้ปวด ลดอักเสบ · รับประทานหลังอาหารทันที',
        'domperidone'  => 'ป้องกันคลื่นไส้ อาเจียน · รับประทานก่อนอาหารครึ่งชั่วโมง',
        'omeprazole'   => 'ลดกรดในกระเพาะ · รับประทานก่อนอาหาร',
        'loratadine'   => 'แก้แพ้ แก้คัน',
        'chlorphen'    => 'แก้แพ้ แก้คัน · อาจทำให้ง่วง',
    ];

    /** คำอธิบายตั้งต้นของยาตัวหนึ่ง (ใช้ทั้งใบจาก visit และใบจากบัตร TB 01) */
    public static function adviceFor(string $name): string
    {
        $lname = mb_strtolower($name);
        foreach (self::ADVICE as $k => $text) {
            if (str_contains($lname, $k)) { return $text; }
        }
        return '';
    }

    public function show($id)
    {
        $visit   = Visit::findOrFail($id);
        $patient = $visit->patient2;

        $rows = VisitMedicine::where('visit_id', $visit->id)->with('medicine')->orderBy('id')->get()
            ->map(function ($vm) {
                $name = $vm->medicine->name ?? ('ยา #' . $vm->medicine_id);
                $unit = $vm->medicine->unit ?? '';
                $sig  = is_array($vm->sig) ? $vm->sig : null;
                $how  = $sig ? DrugSig::text($sig) : '';
                if ($how === '') { $how = trim(($vm->dose ?? '') . ' ' . ($vm->route ?? '')); }
                $amt = trim((string) $vm->amount);
                // จำนวนเป็นตัวเลขล้วน = เติมหน่วยของยา (เดิมบางแถวพิมพ์ "15 tabs" มาเองแล้ว)
                if ($amt !== '' && is_numeric($amt)) { $amt = rtrim(rtrim(number_format((float) $amt, 2, '.', ''), '0'), '.') . ' ' . $unit; }
                return ['name' => $name, 'amount' => $amt, 'how' => $how, 'advice' => self::adviceFor($name)];
            });

        $thaiMonths = \App\Utillity::TH_MONTH_FULL;
        $vd = (string) $visit->getRawOriginal('date');
        $visitDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $vd, $m)
            ? ((int) $m[3]) . ' ' . $thaiMonths[(int) $m[2]] . ' ' . ((int) $m[1] < 2500 ? (int) $m[1] + 543 : (int) $m[1])
            : $vd;

        return view('visit.drug_summary', [
            'visit'     => $visit,
            'patient'   => $patient,
            // คำนำหน้าภาษาอังกฤษ (Miss/Mr.) ต้องเว้นวรรคก่อนชื่อ · ภาษาไทย (นาย/นางสาว) เขียนติดกันตามแบบราชการ
            'name'      => trim(preg_replace('/([A-Za-z.])$/', '$1 ', \App\Models\Patient::prefixLabel($patient) ?? '') . ($patient->name ?? '') . ' ' . ($patient->surname ?? '')),
            'age'       => $patient ? $patient->age() : '',
            'allergy'   => trim((string) ($patient->drug_allergy ?? '')) ?: 'ไม่มีข้อมูล',
            'visitDate' => $visitDate,
            'rows'      => $rows,
            'clinic'    => \App\Models\Visit::$ClinicOption[(int) $visit->clinic] ?? '',
        ]);
    }
}
