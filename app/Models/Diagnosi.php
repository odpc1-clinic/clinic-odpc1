<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Diagnosi extends Model
{
    use Auditable;
    use \App\Models\Concerns\HasIcd10Codes;

    /** ชื่อเจ้าของใน icd10_entries — ต้องตรงกับที่ controller ส่งให้ Icd10Code::syncFor() */
    protected static $icd10Owner = 'diagnosis';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'diagnoses';

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
    protected $fillable = ['visit_id', 'disease', 'other_disease', 'term_syphilis', 'disease_state', 'disease_state_other', 'egasp_info','gf', 'report_506', 'report_506_sent_at'];

    protected $casts = [
        'report_506' => 'boolean',
        'report_506_sent_at' => 'datetime',
    ];

    /** รหัสโรค 506 ของวินิจฉัยนี้ (null = ไม่ใช่โรคที่รายงาน 506) */
    public function code506(): ?string
    {
        return \App\Support\R506\R506Exporter::code506((int) $this->disease);
    }

    /** เข้าเกณฑ์รายงาน 506 ไหม */
    public function is506Reportable(): bool
    {
        return $this->code506() !== null;
    }

    public static $diseaseOption=[1=>'Syphilis', 2=>'Gonorrhea', 19=>'Chlamydia', 3=>'Non-Gonococcal urethritis', 4=>'LGV', 5=>'Chancroid', 
    6=>'Bacterial vaginosis', 7=>'Vaginal candidiasis', 8=>'Trichomoniasis', 9=>'Molluseum contagiosum', 10=>'Anogenital warts', 11=>'Other',
    12=>'Herpes simplex',
    // Mpox (2569-10-20) — เดิมพยาบาลพิมพ์ลงช่อง Other ทั้งหมด (ป่วย/สงสัย/วัคซีน ปนกัน) นับยอดไม่ได้
    // ผล PCR ยืนยันแล้ว เปลี่ยนรายการจาก 21 (สงสัย) เป็น 20 (ยืนยัน) · ห้ามเปลี่ยนเลขรหัส ข้อมูลอ้างอิงอยู่
    20=>'Mpox (ยืนยัน)', 21=>'Mpox R/O (สงสัย)', 22=>'วัคซีน Mpox เข็ม 1', 23=>'วัคซีน Mpox เข็ม 2',
    13=>'Pre-test',14=>'Post-test', 15=>'ตรวจไม่พบโรค STI',16=>'GF',17=>'Egasp 1',18=>'Egasp 2' ];

    public static $termSyphilisOption=[0=>'', 1=>'1', 2=>'2', 3=>'Early Latent', 4=>'Late Latent', 5=>'Unknown Duration'];
    // public static $diseaseStateOption=[1=>'ผู้ป่วยใหม่', 2=>'เกิดโรคซ้ำ', 3=>'อยู่ระหว่างรักษา', 4=>'มาติดตามผลการรักษา', 5=>'ส่งต่อ', 5=>'ไม่ได้รับการรักษา', 10=>'อื่่น ๆ'];

    public static $diseaseStateOption = [20 => 'ปรึกษา/ขอคำแนะนำ',1 => 'ผู้ป่วยใหม่(new case)', 2 => 'เกิดโรคซ้ำ(recurrent,reinfection)', 3 => 'การรักษาเดิมไม่ได้ผล(treatment  failure)', 4 => 'มาติดตามดูอาการ/รับการรักษาต่อเนื่อง(follow up)', 14 => 'สิ้นสุดการรักษา(completed)',  
    5 => 'มาฉีดยาpenicillin เข็มที่1', 23 => 'มาฉีดยาpenicillin เข็มที่2', 6 => 'มาฉีดยาpenicillin เข็มที่3',7 => 'มาฟังผลทางห้องปฏิบัติการ(Lab test)', 8 => 'มาฟังผลเลือด HIV', 
    24 => 'มาฟังผล RPR ก่อนรักษา',9 => 'มาฟังผลเลือด RPR หลังรักษา 3 เดือน', 10 => 'มาฟังผลเลือด RPR หลังรักษา 6 เดือน', 11 => 'มาฟังผลเลือด RPR หลังรักษา 9 เดือน', 12 => 'มาฟังผลเลือด RPR หลังรักษา 12 เดือน', 
    13 => 'มาฟังผลเลือด RPR หลังรักษา 24เดือน',
    21 => 'ตรวจเลือด HIV', 15 => 'ตรวจเลือด RPR หลังรักษา 3 เดือน', 16 => 'ตรวจเลือด RPR หลังรักษา 6 เดือน', 17 => 'ตรวจเลือด RPR หลังรักษา 9 เดือน', 18 => 'ตรวจเลือด RPR หลังรักษา 12 เดือน', 19 => 'ตรวจเลือด RPR หลังรักษา 24 เดือน', 25 => 'ตรวจเลือด RPR', 22 => 'Refer/ส่งต่อไปตรวจรักษา รพ.อื่น'

    ];

public function disease()
{
    if ($this->disease == 1) {
        return Diagnosi::$diseaseOption[$this->disease] . ":" . (Diagnosi::$termSyphilisOption[$this->term_syphilis] ?? '');
    } elseif ($this->disease == 11) {
        return Diagnosi::$diseaseOption[$this->disease] . ":" . $this->other_disease;
    } elseif (in_array($this->disease, [17, 18])) {
        return Diagnosi::$diseaseOption[$this->disease] . ":" . $this->egasp_info;
    } elseif ($this->disease == 16) { // เพิ่มเงื่อนไขสำหรับ GF
        return Diagnosi::$diseaseOption[$this->disease] . ":" . $this->gf;
    }
    return Diagnosi::$diseaseOption[$this->disease] ?? '';
}


    public  function diseaseState(){
        // TMC/TB ไม่มีสถานะของโรค (เก็บว่าง ตั้งแต่ 2569-10-01) — ห้ามหาในตารางด้วยค่าว่าง ไม่งั้นหน้า visit พัง 500
        $state = Diagnosi::$diseaseStateOption[$this->disease_state] ?? '';
        if($this->disease ==10){
            return $state.":". $this->disease_state_other;
        }
        return $state;
    }
     /** ค้นหา id ของ disease จากข้อความ (เช่น 'sy', 'Syphilis') */
    public static function findDiseaseIdsByText(?string $text): array
    {
        $text = trim((string)$text);
        if ($text === '') return [];

        $hits = [];

        // แมตช์กับรายการใน $diseaseOption
        foreach (self::$diseaseOption as $id => $label) {
            if (self::mbStriPos($label, $text) !== false) {
                $hits[] = $id;
            }
        }

        // หมายเหตุ: กรณี "Other: xxx" เราไปกรองใน whereHas ด้วย other_disease LIKE เพิ่มแล้ว
        return array_values(array_unique($hits));
    }

    /** ค้นหา id ของ disease_state จากข้อความ (เช่น 'ผู้ป่วยใหม่', 'new case') */
    public static function findDiseaseStateIdsByText(?string $text): array
    {
        $text = trim((string)$text);
        if ($text === '') return [];

        $hits = [];
        foreach (self::$diseaseStateOption as $id => $label) {
            if (self::mbStriPos($label, $text) !== false) {
                $hits[] = $id;
            }
        }
        return array_values(array_unique($hits));
    }

    /** mb_stripos ที่รองรับกรณี extension ไม่ครบ */
    protected static function mbStriPos(string $haystack, string $needle)
    {
        if ($needle === '') return 0;
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8');
        }
        return stripos($haystack, $needle);
    }

    // --- ของเดิม ---
    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }
    

}
