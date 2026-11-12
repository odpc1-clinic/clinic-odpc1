<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ราคากลางตามประกาศ สคร.1 */
class StandardPrice extends Model
{
    protected $table = 'standard_prices';

    protected $fillable = [
        'category', 'name', 'unit', 'price', 'price_foreign',
        'source', 'effective_date', 'active', 'sort_order',
    ];

    protected $casts = ['active' => 'boolean', 'price' => 'decimal:2', 'price_foreign' => 'decimal:2'];

    /** หมวดที่ใช้ในประกาศ */
    public static $categories = [
        'vaccine'   => 'วัคซีน',
        'medicine'  => 'ยาและเวชภัณฑ์',
        'lab'       => 'ค่าตรวจทางห้องปฏิบัติการ',
        'radiology' => 'รังสีวิทยา',
        'service'   => 'ค่าบริการตรวจรักษา',
        'occmed'    => 'ตรวจโรคจากการประกอบอาชีพ',
        // ราคาอ้างอิงจาก อย. = ราคา "จัดซื้อ" ไม่ใช่ราคาที่เก็บจากคนไข้
        // แยกหมวดไว้ และไม่ถูกใช้ในการจับคู่ราคายาอัตโนมัติ (ตัวจับคู่ดูเฉพาะ medicine/vaccine)
        'fda_ref'   => 'ราคาอ้างอิง อย. (ราคาจัดซื้อ)',
        'moph_ref'  => 'ราคาอ้างอิง สธ. (บัญชีค่าบริการ ม.ค. 2569)',
    ];

    /** หมวดที่เป็นราคาอ้างอิง ไม่ใช่ราคาเก็บเงิน */
    /**
     * หมวดที่เป็น "บัญชีอ้างอิง" ไม่ใช่รายการที่คลินิกให้บริการเอง
     * หน้า /standard-price จะซ่อนไว้จนกว่าจะเลือกหมวดนั้นเอง
     * ไม่งั้นรายการที่คลินิกใช้จริง (~150 รายการ) จะจมอยู่ในหลักพัน
     */
    public const REFERENCE_CATEGORIES = ['fda_ref', 'moph_ref'];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * ตารางชื่อพ้อง: ชื่อยาในระบบ => ชื่อรายการในประกาศ
     * ใช้กับคู่ที่เป็นยาตัวเดียวกันแน่ๆ แต่สะกดต่างกัน (บางคู่ประกาศพิมพ์ผิดเอง
     * เช่น Acycloclovia = Acyclovir, Ceftriazone = Ceftriaxone)
     *
     * ตั้งใจทำเป็น "ตารางที่เขียนไว้ชัดๆ" แทนการเดาด้วยความคล้ายของตัวอักษร
     * เพราะการเดาให้ผลอันตราย เช่น Clarithromycin ไปเข้ากับ Azithromycin
     * หรือ B6 ไปเข้ากับวัคซีนไข้กาฬหลังแอ่น 3,080 บาท
     *
     * คู่ที่ขนาดยาไม่ตรงกันจะยังถูกจัดเป็น "ต้องตรวจก่อน" ให้เองโดยอัตโนมัติ
     */
    public static $aliases = [
        'Amoxycillin(500mg)'      => 'Amoxycilline 500 mg',
        'Dextrometrophan(15mg)'   => 'Dextromethrophan 15 mg',
        'Hyoscin(10mg)'           => 'Hyoscine 10 mg',
        'icloxacillin(250mg)'     => 'Dicloxacillin 250 mg',
        'Acyclovir(400mg)'        => 'Acycloclovia 400 mg',
        'Acyclovir cream'         => 'Acycloclovia cream 5 gm',
        'adrenaline'              => 'Adrenaline injection 1 mg',
        'Dexamethasone'           => 'Dexamethasone injection 10 mg',
        'Benzathine Penicillin G' => 'Benzathine Penicillin 1.2 mu',
        'Ceftriaxone'             => 'Ceftriazone 1 gm Inj',
        'Clotrimazole(100mg)'     => 'Clotrimazole vagina tablet',
        'calminative'             => 'Mixture Carminative 180 ml',
        'TA cream 0.02%'          => 'Triamcinolone 0.02% cream',
        'TA cream 0.1%'           => 'Triamcinolone 0.1% cream',
        'Vit B6'                  => 'Vitamin B6',
        'B6'                      => 'Vitamin B6',

        // วัคซีน — ในระบบมีชื่อเรียกหลายแบบของตัวเดียวกัน (ยี่ห้อ/ภาษา/ลำดับคำ)
        'engerix-b vaccine'       => 'วัคซีนป้องกันไวรัสตับอักเสบบี (Hepatitis B vaccine) Engerix B',
        'Hepatitis B vaccine'     => 'วัคซีนป้องกันไวรัสตับอักเสบบี (Hepatitis B vaccine) Engerix B',
        'vaccine Hepatitis B'     => 'วัคซีนป้องกันไวรัสตับอักเสบบี (Hepatitis B vaccine) Engerix B',
        'Twinrix(HepA+B)'         => 'วัคซีนป้องกันไวรัสตับอักเสบ A และ B (Twinrix)',
    ];

    /**
     * คู่ที่ "อย่าติ๊กให้อัตโนมัติ" แม้ตัวเลขขนาดจะดูตรงกัน
     * Terramycin = oxytetracycline ส่วนในประกาศเป็น tetracycline — คนละตัวยา
     */
    public static $aliasesNeedReview = [
        'Terramycin eye ointment' => 'Tetracyclin ointment',
        // ประกาศระบุยี่ห้อ Avaxim ส่วนในระบบสต๊อกเป็น Havrix — คนละยี่ห้อ ให้เภสัชยืนยันก่อน
        'havrix 1440 (hepatitis A vaccine)' => 'วัคซีนป้องกันไวรัสตับอักเสบเอ (Hepatitis A vaccine) Avaxim',
        // Atarax คือชื่อการค้าของ Hydroxyzine แต่มีทั้ง 10 mg และ 25 mg ต้องดูของที่สต๊อกจริงก่อน
        'atarax' => 'Hydroxyzine 10 mg',
    ];

    /** ตัวเลขในชื่อ ใช้เทียบขนาดยา เช่น "Metronidazole (400)" -> "400" */
    public static function digitsOf(?string $name): string
    {
        preg_match_all('/\d+(?:\.\d+)?/', (string) $name, $m);
        return implode('/', $m[0] ?? []);
    }

    /**
     * หาราคากลางที่คู่กับชื่อยาในระบบ — คืน [StandardPrice|null, ต้องตรวจก่อนไหม]
     *
     * ระวัง: normalize() ตัดตัวเลขทิ้ง ทำให้หลายรายการยุบเป็นคีย์เดียวกันได้
     * (Vitamin B6 กับ Vitamin B1-6-12, Triamcinolone 0.02% กับ 0.1%)
     * จึงต้องเลือกตัวที่ "ตัวเลขขนาดตรงกัน" ก่อน ถ้าเลือกไม่ได้ชัดให้ส่งไปให้คนตรวจ
     */
    public static function findForMedicine(string $medicineName, $pool)
    {
        $pool = collect($pool);

        // ชื่อพ้องที่ตั้งไว้ -> จับแบบชื่อตรงเป๊ะ กันชนกับรายการชื่อคล้าย
        $exactByName = fn ($name) => $pool->first(fn ($s) => $s->name === $name);

        if (isset(self::$aliases[$medicineName])) {
            return [$exactByName(self::$aliases[$medicineName]), false];
        }
        if (isset(self::$aliasesNeedReview[$medicineName])) {
            return [$exactByName(self::$aliasesNeedReview[$medicineName]), true];
        }

        // ชื่อตรงกันหลังตัดขนาด/วงเล็บ
        $key   = self::normalize($medicineName);
        $cands = $pool->filter(fn ($s) => self::normalize($s->name) === $key)->values();

        if ($cands->isEmpty()) { return [null, false]; }

        $dose  = self::digitsOf($medicineName);
        $exact = $cands->first(fn ($s) => self::digitsOf($s->name) === $dose);

        // ตรงทั้งชื่อและขนาด = ใช้ได้เลย / ไม่งั้นเสนอตัวแรกแต่บังคับให้ตรวจก่อน
        return $exact ? [$exact, false] : [$cands->first(), true];
    }

    /**
     * ชื่อแบบ "ตัดให้เทียบกันได้" — ใช้จับคู่ชื่อยาในระบบกับชื่อในประกาศ
     * ตัดขนาดยา วงเล็บ เว้นวรรค เครื่องหมาย และแปลงเป็นตัวพิมพ์เล็ก
     */
    public static function normalize(?string $name): string
    {
        $s = mb_strtolower(trim((string) $name));
        $s = preg_replace('/\(.*?\)/u', ' ', $s);                       // ตัดวงเล็บ
        $s = preg_replace('/\d+(\.\d+)?\s*(mg|gm|g|ml|mu|%)/u', ' ', $s); // ตัดขนาด
        $s = preg_replace('/[^a-z\x{0E00}-\x{0E7F}]+/u', '', $s);        // เหลือแต่ตัวอักษร
        return $s;
    }
}
