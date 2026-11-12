<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class LabSub extends Model
{
    use Auditable;

    // ป้ายอ่านง่ายสำหรับ log: ชื่อรายการตรวจ
    public function auditLabel(): string
    {
        return self::$methodOption[$this->method] ?? ('LabSub #' . $this->getKey());
    }

    // ผูก log เข้ากับเคส: lab_sub -> lab -> (patient, visit)
    public function auditContext(): array
    {
        $lab = Lab::find($this->lab_id);
        return ['patient_id' => $lab->patient_id ?? null, 'visit_id' => $lab->visit_id ?? null];
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'lab_subs';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
    'lab_id', 
    'method', 
    'method_other', 
    'specimen_from', 
    'specimen_from_other', 
    'result',
    'remark', // <--- เพิ่มตรงนี้ครับ
    'ast',    // ผลความไวต่อยาของ GC (2569-09-30) ดู App\Support\GcAst
    'tested_by', 'tested_date',   // ผู้ทดสอบรายรายการ (2569-09-30)
];

    protected $casts = ['ast' => 'array'];

    /** รายการ Culture ที่ผลขึ้นเชื้อ ต้องลงผลความไวต่อยา (GC AST) */
    public function needsAst(): bool
    {
        return (int) $this->method === 4 && in_array(trim((string) $this->result), \App\Support\GcAst::RESULTS, true);
    }

    public static $methodOption=[""=>'',1=>'# Gram Stain', 2=>'Wet Preparation', 3=>'KOH', 4=>'# Culture'
    , 5=>'PCR for CT/NG', 6=>'# Anti-HIV', 7=>'# TP syphilis', 8=>'# RPR', 11=>'HBs Ag', 14=>'HBs Ab',12=>'Anti-HCV', 13 =>'Pap Smear', 15=>'DTX', 16=>'EKG (QTcF)', 10=>'Other'];

    /**
     * ตรวจข้างเตียง (point-of-care) — พยาบาลเจาะเองแล้วลงผลในแผง "Lab ในคลินิก" ทันที ไม่ส่งห้อง Lab (2569-10-22)
     * คลินิก TB: DTX เดิมใส่ไว้ในสัญญาณชีพ แต่พยาบาลมองว่าเป็นแล็บ สั่งแล้วหาที่ลงผลไม่เจอ
     * ผลเห็นทันทีไม่ต้องรอห้อง Lab อนุมัติ (ห้อง Lab ไม่ได้ตรวจเอง) · [หน่วย, ต่ำสุด, สูงสุด]
     */
    public static $pocMethods = [15 => ['mg/dL', 10, 900], 16 => ['ms', 200, 800]];

    public static function isPoc($method): bool
    {
        return isset(self::$pocMethods[(int) $method]);
    }

    /**
     * ผลตรวจข้างเตียงที่ต้องสะกิด — [ข้อความ, danger|warning] หรือ null
     * เกณฑ์เดียวกับการ์ดสัญญาณชีพ (visit/form/vitals) และ popup EKG ในบัตร TB 01
     * EKG: ยาวัณโรคดื้อยา (Bdq, Cfz, Lfx/Mfx, Dlm) ยืด QT — เกิน 500 ms แพทย์ต้องดูก่อนให้ยาต่อ
     */
    /**
     * QTcF ที่พยาบาลกรอกในสัญญาณชีพ → ผลของ EKG ที่สั่งไว้ใน visit เดียวกัน (2569-10-22)
     * EKG สั่งจากหน้าต่างสั่งแล็บ (คิดเงิน) แต่ลงผลที่ช่อง QTcF — ผูกให้ใบ Lab ไม่ค้าง "รอผล"
     * ไม่ได้สั่ง EKG = ไม่สร้างรายการให้ (ไม่คิดเงินเอง)
     */
    public static function syncQtcfFromVisit(Visit $visit): void
    {
        $lab = Lab::where('visit_id', $visit->id)->first();
        if (! $lab) { return; }
        $sub = self::where('lab_id', $lab->id)->where('method', 16)->orderBy('id')->first();
        if (! $sub) { return; }
        $q = $visit->qtcf ? (string) (int) $visit->qtcf : null;
        if ((string) $sub->result === (string) $q) { return; }
        $sub->fill(['result' => $q, 'tested_by' => $q ? \Illuminate\Support\Facades\Auth::id() : null,
                    'tested_date' => $q ? \App\Models\VisitAppointment::todayBe() : null])->save();
    }

    public static function pocFlag($method, $value): ?array
    {
        if (! is_numeric(trim((string) $value))) { return null; }
        $v = (float) $value;
        switch ((int) $method) {
            case 15:
                if ($v < 70)   { return ['ต่ำ (<70)', 'danger']; }
                if ($v >= 200) { return ['สูง (≥200)', 'danger']; }
                return null;
            case 16:
                if ($v > 500)  { return ['เกิน 500 ms — แจ้งแพทย์ก่อนให้ยาต่อ', 'danger']; }
                if ($v >= 450) { return ['ยาว (≥450 ms)', 'warning']; }
                return null;
        }
        return null;
    }

    /**
     * รายการที่เลิกสั่งจากแล็บในแล้ว ย้ายไปสั่งที่ "Lab นอก" แทน (พยาบาลขอ 2569-08-26)
     *
     *   13 Pap Smear -> "Pap smear, ThinPrep method (BGH)"
     *   14 HBs Ab    -> "Hepatitis B surface Antibody (HBsAb, Anti-HBs)"
     *
     * ห้ามลบออกจาก $methodOption เด็ดขาด — ของเก่ายังอ้างเลขนี้อยู่
     * (Pap 68 รายการ · HBs Ab 53 รายการ ณ วันที่ย้าย) ถ้าลบ หน้าประวัติจะขึ้น
     * "LabSub #123" แทนชื่อจริง อ่านไม่รู้เรื่อง
     *
     * ที่ทำคือซ่อนจาก "ตัวเลือกตอนสั่งใหม่" เท่านั้น ของเดิมยังแก้ไขและแสดงผลได้ปกติ
     */
    public static $retiredMethods = [13, 14];

    /**
     * รายการที่สั่งได้จากแผง "Lab ในคลินิก" ของคลินิก TB (บัตร TB 01 / หน้าตรวจ TB) — 2569-10-02
     * เป็นการตรวจเลือดที่ไม่ต้องเลือกตำแหน่งสิ่งส่งตรวจ + Other (พิมพ์เอง)
     * Gram / Wet / Culture / PCR ต้องเลือกตำแหน่ง ยังสั่งที่หน้าสั่ง Lab เต็มเหมือนเดิม
     * ลำดับนี้ = ลำดับที่แสดง (HIV ก่อน คลินิกขอ "Lab ในคลินิก มีตรวจ HIV ด้วย")
     */
    // EKG (16) ไม่อยู่ในแผงนี้ — แผงเป็น "ตรวจเลือด" (คลินิกขอ 2569-10-22) · สั่ง EKG ที่หน้าต่างสั่งแล็บ ลงผลที่ช่อง QTcF สัญญาณชีพ
    public static $tbQuickMethods = [6, 15, 11, 12, 7, 8];

    /**
     * ตัวเลือกสำหรับสั่งตรวจใหม่ — ตัดรายการที่ย้ายไปแล็บนอกออก
     * $keep = ค่าที่รายการนั้นใช้อยู่ ส่งเข้ามาตอนแก้ของเดิม จะได้ไม่ทำให้ค่าที่เลือกไว้หาย
     */
    public static function orderableMethods($keep = null): array
    {
        $out = self::$methodOption;
        foreach (self::$retiredMethods as $m) {
            if ((string) $m !== (string) $keep) {
                unset($out[$m]);
            }
        }
        return $out;
    }

    /** ชื่อรายการในแล็บนอกที่ใช้แทน — เอาไว้บอกพยาบาลว่าของย้ายไปไหน */
    public static function retiredHint(): string
    {
        return 'Pap Smear และ HBs Ab ย้ายไปสั่งที่ "Lab นอก" แล้ว';
    }

    // === การคิดเงินตามประเภทตรวจ (ใช้ทั้งหน้า visit/edit และ pharmacy/edit ให้ตรงกัน) ===
    // blood  = ตรวจเลือด คิดราคากลางอัตโนมัติ
    // sso    = เลือดที่เบิก สปสช ได้ (HBs Ag / Anti-HCV) -> ติ๊กเบิก = ไม่คิดเงิน
    // manual = Gram/C&S/Wet/KOH/PCR/Pap/Other -> ไม่คิดอัตโนมัติ (คลินิกคิดเอง/รวมแพ็กเกจ)
    // EKG (16) ทำที่คลินิก TB คิดราคากลาง ECG 450 อัตโนมัติเมื่อสั่ง (ราคาตั้งที่หน้า "ตั้งราคากลาง Lab") 2569-10-22
    public static $bloodAutoMethods = [6, 7, 8, 14, 16]; // Anti-HIV, TP syphilis, RPR, HBs Ab, EKG
    public static $ssoMethods       = [11, 12];       // HBs Ag, Anti-HCV

    public static function billingCategory($method): string
    {
        $m = (int) $method;
        if (in_array($m, self::$bloodAutoMethods, true)) return 'blood';
        if (in_array($m, self::$ssoMethods, true)) return 'sso';
        return 'manual';
    }

    // === เปรียบเทียบผลย้อนหลัง (ใช้ในหน้า "ผลย้อนหลัง" ที่กดจากตารางผล Lab) ===
    // RPR รายงานเป็น titer ข้อความ "1:32" -> แปลงเป็นตัวเลขเพื่อดูว่าค่าขึ้นหรือลง
    // Non-reactive ถือเป็น 0 (ดีที่สุด) เพื่อให้เรียงลำดับ/วาดกราฟต่อเนื่องกับ titer ได้
    public static function titerValue($result): ?int
    {
        $text = trim((string) $result);
        if ($text === '') return null;
        if (strcasecmp($text, 'Non-reactive') === 0) return 0;
        if (preg_match('/^1\s*:\s*(\d+)$/', $text, $m)) return (int) $m[1];
        return null;
    }

    // ผลที่เป็น titer เทียบกันได้ (ตอนนี้มีแค่ RPR) -> หน้าประวัติจะโชว์กราฟ + การเปลี่ยนแปลงกี่เท่า
    public static function isTiterMethod($method): bool
    {
        return (int) $method === 8;
    }

    public static $specimenFromOption=[""=>'',3=>'Cervix', 1=>'Urethra', 2=>'Vagina', 4=>'Anus', 5=>'Urine', 6=>'Oral swab', 7=>'Rectal swab', 8=>'Cervix + Urethra', 10=>'Other'];

    public static $specimenFromPCROption=[""=>'',1=>'Urethra', 2=>'Anus/Rectal swab', 3=>'Pharynx', 4=>'Vagina', 5=>'Urine', 6=>'Oral swab/Pharynx', 7=>'Rectal swab', 10 =>'Other'];

    public static $cultureResultOption=[""=>'',2=>'No growth', 3=>'Contaminated', 1=>'Growth', 4 =>'GC Growth', 5 =>'Fungus Growth', 6 =>'Neisseria spp.', 7 =>'Neisseria meningitidis', 8=>'Secondary Organisms' ];
    public static $wetResultOption=["-"=>'Not Found',1=>'Clue cell', 2=>'TV', 3=>'Budding yeast' ]; 
    public static $kohResultOption=["-"=>'-',1=>'Budding yeast', 2=>'Pseudohyphae', 3=>'Budding yeast with Pseudohyphae' ];
    public static $papResultOption=["-"=>'-',1=>'Negative for intraepithelial lesion or malignancy (NILM)', 2=>'Epithelial cell abnormality (see interpretation)', 3=>'Others (see interpretation)' ];  
    public static $pcrResultOption= [""=>'',2=>'Not detected',  3=>'Inconclusive' , 4=>'CT Detected', 5=>'NG Detected', 6=>'CT/NG Detected', 7=>'CT/NG Not Detected' ];
    public static $hivResultOption= [""=>'',2=>'Negative' , 3=>'Inconclusive' , 1=>'Positive'];  
    public static $tphrResultOption= [""=>'',2=>'Negative',  3=>'Inconclusive' , 1=>'Positive'];
    public static $HBsAgResultOption= [""=>'',2=>'Negative', 1=>'Positive'];
    public static $HBsAbResultOption= [""=>'',2=>'Negative', 1=>'Positive'];
    public static $AntiHCVResultOption= [""=>'',2=>'Negative' , 1=>'Positive'];  
    public static $rprResultOption= [""=>'',1=>'Non-reactive', 2=>'1:1', 3=>'1:2', 4=>'1:4', 5=>'1:8', 6=>'1:16', 7=>'1:32',
    8=>'1:64', 9=>'1:128', 10=>'1:256', 11=>'1:512', 12=>'1:1024', 13=>'1:2048'];  
    public static $gramResultOption1=['I: ','E: ','PMN: ','Other: '];
    public static $gramResultOptionCervix = ['I: ', 'E: ', 'PMN: ', 'Other: '];
    public static $gramResultOptionUrethra = ['I: ', 'E: ', 'PMN: ', 'Other: '];
    public static $gramResultOptionVagina = ['I: ', 'E: ', 'PMN: ', 'Fungus: ', 'Other: '];
    public static $gramResultOptionAnus = ['I: ', 'E: ', 'PMN: ', 'Other: '];
    public static $gramResultOptionOther = ['I: ', 'E: ', 'PMN: ', 'Other: '];

public static function getGramOptions($specimen)
{
    switch ($specimen) {
        case 'Cervix':
            return self::$gramResultOptionCervix;
        case 'Urethra':
            return self::$gramResultOptionUrethra;
        case 'Vagina':
            return self::$gramResultOptionVagina;
        case 'Anus':
            return self::$gramResultOptionAnus;
        default:
            return self::$gramResultOptionOther;
    }
}

    public function patient()
{
    return $this->visit()->patient();
    
}

    public function visit(){
        $lab = Lab::find($this->lab_id);
        $visit  = Visit::find($lab->visit_id);
        return $visit ;
    }
    public function method(){
        if($this->method == 10){
            return LabSub::$methodOption[$this->method].':'.$this->method_other;
        }else {
            return LabSub::$methodOption[$this->method];
        }
        // return $this->method
    }
    public function specimenFrom(): string
{
    $method = (int) $this->method;
    $code   = (int) $this->specimen_from;

    // 1) PCR for CT/NG (method = 5) ใช้แม็ป PCR
    if ($method === 5) {
        if ($code === 10) {
            return (LabSub::$specimenFromPCROption[$code] ?? 'Other')
                . ($this->specimen_from_other ? (':'.$this->specimen_from_other) : '');
        }
        return LabSub::$specimenFromPCROption[$code] ?? ($this->specimen_from_other ?: '-');
    }

    // 2) วิธีอื่น ใช้แม็ปปกติ
    if ($code === 10) {
        return (LabSub::$specimenFromOption[$code] ?? 'Other')
            . ($this->specimen_from_other ? (':'.$this->specimen_from_other) : '');
    }
    return LabSub::$specimenFromOption[$code] ?? ($this->specimen_from_other ?: '-');
}

    // ห้อง Lab ลงผลรายการย่อย → หน้าตรวจที่รอผลอยู่รู้ทันที
    protected static function booted()
    {
        static::saved(function (LabSub $sub) {
            if ($sub->wasChanged('result') || ($sub->wasRecentlyCreated && $sub->result !== null && $sub->result !== '')) {
                \App\Support\Realtime::signal('lab', ['lab' => (int) $sub->lab_id]);
            }
        });
    }
}
