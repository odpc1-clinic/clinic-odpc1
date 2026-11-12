<?php

namespace App\Models;

use App\Utillity;
use DateTime;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class Patient extends Model
{
    use Auditable;

    // รหัสผูก LINE เป็นรหัสลับชั่วคราว ไม่ควรค้างอยู่ใน log (รีวิว 2569-09-30)
    // เลขบัตร/เบอร์โทรยังเก็บ เพราะ log ตอนลบมีไว้กู้ข้อมูลคืน (ดู Auditable)
    protected static $auditExclude = ['line_link_code', 'line_link_code_expires_at'];

    // visit → ใบวินิจฉัย ถูกลบตาม FK CASCADE ทั้งสาย ไม่ผ่าน hook ของโมเดล รหัส ICD-10 จึงต้องลบเอง
    protected static function booted()
    {
        static::deleting(function (Patient $p) {
            // ลบคนไข้ = visit หายตาม FK CASCADE (ไม่ผ่าน hook ของ Visit) — ประวัติวัคซีนทั้งหมดของคนไข้ต้องลบเอง ไม่งั้นค้างไร้เจ้าของ
            foreach (\App\Models\VisitVaccine::where('patient_id', $p->id)->get() as $vac) { $vac->delete(); }
            \App\Models\Icd10Code::purgeFor(\App\Models\Diagnosi::icd10Owner(), \App\Models\Diagnosi::whereIn('visit_id',
                \App\Models\Visit::where('patient_id', $p->id)->select('id'))->pluck('id')->all());
        });
    }

    // ป้ายอ่านง่ายสำหรับ log: HN + ชื่อ-นามสกุล
    public function auditLabel(): string
    {
        return trim(($this->code ?? '') . ' ' . ($this->name ?? '') . ' ' . ($this->surname ?? ''));
    }

    // ผูก log เข้ากับตัวคนไข้เอง
    public function auditContext(): array
    {
        return ['patient_id' => $this->getKey(), 'visit_id' => null];
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'patients';

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
    protected $fillable = ['code', 'prefix', 'prefix_other', 'name', 'surname', 'sex', 'first_visit', 'id_card_number', 'birth_date', 'address_id','tel'
    ,'nationality','name_en','surname_en','education','marital_status','status','occupation','other_occupation','organization' ,'congenitaldisease','drug_allergy','photo','phone_changed','line_user_id','line_linked_at','line_link_code','line_link_code_expires_at'
    ,'yellow_book_no','yellow_book_history'
    ,'health_right','health_right_hospital'
    ,'email'
    ,'passport'
    ,'sti_vaccine_history'];   // ประวัติวัคซีน STIs ข้อความอิสระ (2569-10-21) — แก้ได้ทั้งหน้าลงทะเบียนและหน้าตรวจ// แยกจากเลขบัตรประชาชน (2569-10-07) — รายงานส่งกรมฯ ของ TMC ใช้เฉพาะพาสปอร์ต

    // ลำดับในรายการ = ลำดับที่โชว์ใน dropdown (ไทยก่อน อังกฤษตาม) · เลขเดิม 1-7 ห้ามเปลี่ยน (ข้อมูลเก่าอ้างเลขนี้)
    // เพิ่ม 2569-10-14: ด.ช. ด.ญ. พระ สามเณร แม่ชี ดร. / Master Mx. Dr. / 99 = อื่นๆ พิมพ์เอง (patients.prefix_other)
    public static $prefixOption=[""=>'',1=>'นาย', 2=>'นาง', 3=>'นางสาว', 8=>'ด.ช.', 9=>'ด.ญ.', 10=>'พระ', 11=>'สามเณร', 12=>'แม่ชี', 16=>'ดร.',
        // คำนำหน้าอังกฤษพิมพ์ใหญ่ทั้งหมด ให้ตรงกับชื่ออังกฤษที่กรอกเป็นตัวใหญ่ (TMC ขอ 2569-11-10) — แสดงผลเท่านั้น รหัสเดิม
        4=>'MR.', 5=>'MRS.', 6=>'MISS', 7=>'MS.', 13=>'MASTER', 14=>'MX.', 15=>'DR.', 99=>'อื่นๆ'];

    public const PREFIX_OTHER = 99;

    /** เพศตามคำนำหน้า (เลือกคำนำหน้าแล้วเติมเพศให้) — ไม่มีในนี้ = ไม่เดา */
    public static $prefixSex = [1 => 1, 2 => 2, 3 => 2, 4 => 1, 5 => 2, 6 => 2, 7 => 2, 8 => 1, 9 => 2, 10 => 1, 11 => 1, 12 => 2, 13 => 1];

    /**
     * คำนำหน้าที่ใช้แสดง/พิมพ์ — "อื่นๆ" (99) ใช้ข้อความที่พิมพ์เอง · ไม่มีคำนำหน้า = ''
     * รับ Patient หรือ null (ที่เรียกจาก optional($patient) / $visit->patient2)
     */
    public static function prefixLabel($p): string
    {
        if (! $p) { return ''; }
        $code = $p->prefix ?? '';
        if ((int) $code === self::PREFIX_OTHER) { return trim((string) ($p->prefix_other ?? '')); }
        return (string) (self::$prefixOption[$code] ?? '');
    }
    public static $sexOption=[""=>'',1=>'ช', 2=>'ญ'];
    public static $cpsexOption=[""=>'',1=>'ชาย', 2=>'หญิง'];
    public static $educationOption=[""=>'',1=>'ต่ำกว่าประถมศึกษา', 2=>'ประถมศึกษา', 3=>'ม.ต้น', 4=>'ม.ปลาย', 8=>'ปวช', 9=>'ปวส' , 5=>'ป.ตรี', 6=>'ป.โท', 7=>'ป.เอก'];

    
    public static $maritalStatusOption=[""=>'',1=>'โสด', 2=>'หม้าย', 3=>'หย่า', 4=>'สมรส'];

    /**
     * สิทธิการรักษา — ใช้พิมพ์ลงใบนัด (คลินิกวัณโรคขอ 2569-09-29)
     *
     * เก็บเป็น "รหัสข้อความ" ไม่ใช่ตัวเลข ต่างจากตัวเลือกอื่นในโมเดลนี้โดยตั้งใจ:
     * ค่าพวกนี้ต้องไปโผล่บนกระดาษที่คนไข้ถือกลับบ้าน และต้องส่งออกรายงานให้
     * หน่วยงานอื่นอ่านรู้เรื่อง ถ้าเก็บเป็นเลขแล้ววันหนึ่งมีคนอ่านฐานตรงๆ
     * จะไม่มีทางรู้ว่า 3 แปลว่าอะไร (ปัญหาที่ status/occupation เจออยู่ตอนนี้)
     */
    public static $healthRightOption = [
        ''             => '',
        'uc'           => 'UC (บัตรทอง)',
        'sso'          => 'ประกันสังคม',
        'govt'         => 'เบิกราชการ',
        'self'         => 'ชำระเงิน / ประกันชีวิต',
        'migrant'      => 'สิทธิต่างด้าว / ประกันสุขภาพแรงงานต่างด้าว',
        'other'        => 'สิทธิอื่น ๆ',
    ];

    /** ชื่อสิทธิแบบสั้น สำหรับมุมขวาบนของสติกเกอร์ (พื้นที่แคบ ~230px) — คลินิกขอ 2569-11-03 */
    public static $healthRightShort = [
        'uc'      => 'UC',
        'sso'     => 'ปกส.',
        'govt'    => 'ขรก.',
        'self'    => 'ชำระเงิน',
        'migrant' => 'ต่างด้าว',
        'other'   => 'สิทธิอื่น',
    ];

    /**
     * สิทธิที่ "ไม่ผูกกับสถานพยาบาลหลัก" — ช่อง รพ. ปล่อยว่างได้ ไม่ต้องทวง
     * เบิกราชการใช้ที่ไหนก็ได้ ส่วนจ่ายเงินเองไม่มีต้นสังกัดอยู่แล้ว
     */
    public static $healthRightNoHospital = ['govt', 'self'];

    /** ข้อความสิทธิแบบที่คนอ่านรู้เรื่อง สำหรับใบนัด/รายงาน */
    public function healthRightText(): string
    {
        $key = (string) ($this->health_right ?? '');
        return self::$healthRightOption[$key] ?? $key;
    }

    /** สถานพยาบาลหลักที่ควรพิมพ์ — สิทธิที่ไม่ผูกต้นสังกัด (เบิกราชการ / จ่ายเอง) ไม่แสดง */
    public function healthRightHospital(): string
    {
        return in_array((string) ($this->health_right ?? ''), self::$healthRightNoHospital, true)
            ? '' : trim((string) ($this->health_right_hospital ?? ''));
    }
    public static $statusOption=[""=>'',1=>'MSM/MSW/TG', 2=>'พนักงานบริการ', 3=>'แรงงานข้ามชาติ', 4=>'ผู้ป่วยเรือนจำ', 5=>'เยาวชน', 6=>'ประชาชนทั่วไป', 7=>'ผู้ใช้สารเสพติด'];
    public static $occupationOption=[""=>'',1=>'เกษตรกรรม', 2=>'รับราชการ/รัฐวิสาหกิจ', 3=>'รับจ้าง/กรรมกร', 4=>'ค้าขาย/ธุรกิจ', 5=>'งานบ้าน', 6=>'นักเรียน/นักศึกษา',
    7=>'ทหาร/ตำรวจ', 9=>'บุคลากรสาธารณสุข', 10=>'อาชีพพิเศษ', 11=>'อื่น ๆ'
    ];
    
    /**
     * คนไข้คนนี้มีนัดค้างอยู่ไหม — สำหรับ "เตือนที่ห้องบัตร"
     * (2569-08-26: พยาบาลแจ้งว่าคนไข้นัดมา แต่หน้าห้องบัตรไม่ได้บอกว่ามีนัด)
     *
     * ต่างจาก Visit::appointmentAlert() ตรงที่ตัวนั้นต้องมี visit ของวันนี้ก่อน
     * จึงจะเตือนได้ — ใช้ที่ห้องตรวจ ส่วนห้องบัตรเปิดหน้าคนไข้ตอนที่ยังไม่มี visit
     * จึงต้องเช็คจากตัวคนไข้ตรง ๆ
     *
     * คืน ['level' => ontime|overdue|future|none, 'items' => [...]]
     *   ontime  = มีนัดวันนี้
     *   overdue = เลยวันนัดมาแล้ว และยังไม่เคยมาหลังวันนัดนั้น
     *   future  = ยังไม่ถึงวันนัด (มาก่อนนัด)
     */
    public function appointmentAlert(): array
    {
        $today = \App\Utillity::AD2BE(now()->format('Y-m-d'));

        // ดูย้อนหลังแค่ 1 ปี — นัดที่ขาดไปนานกว่านั้นไม่ใช่เรื่องของห้องบัตรแล้ว
        $floor = (((int) substr($today, 0, 4)) - 1) . substr($today, 4);

        $rows = \App\Models\VisitAppointment::open()
            ->where('patient_id', $this->id)
            ->where('appointment_date', '>=', $floor)
            ->orderBy('appointment_date')
            ->get(['appointment_date', 'reason']);

        if ($rows->isEmpty()) { return ['level' => 'none', 'items' => []]; }

        // วันที่คนไข้มารับบริการจริง ใช้ตัดสินว่านัดที่ผ่านมา "มาแล้วหรือยัง"
        $visitDates = \App\Models\Visit::where('patient_id', $this->id)
            ->pluck('date')->filter()->all();

        $items = [];
        foreach ($rows as $r) {
            $d = $r->appointment_date;

            if ($d === $today) {
                $status = 'ontime';
            } elseif ($d > $today) {
                $status = 'future';
            } else {
                // ถือว่ามาตามนัดแล้ว ก็ต่อเมื่อเคยมา "ระหว่างวันนัดกับเมื่อวาน"
                //
                // ต้องตัดวันนี้ออกจากการนับ ไม่งั้นพอห้องบัตรกดลงทะเบียนปุ๊บ
                // visit ของวันนี้จะถูกนับเป็น "มาตามนัดแล้ว" ทันที แล้วคำเตือนหายไปทั้งกล่อง
                // กลายเป็นว่าเตือนตอนยังไม่ต้องใช้ พอลงทะเบียนเสร็จซึ่งเป็นตอนที่ต้องใช้จริงกลับเงียบ
                // (พยาบาลแจ้งอาการนี้ 2569-08-26 — ตัวเตือนที่ห้องตรวจเดิมกันไว้ถูกแล้ว ตัวนี้พลาดเอง)
                $came = false;
                foreach ($visitDates as $vd) {
                    if ($vd >= $d && $vd < $today) { $came = true; break; }
                }
                if ($came) { continue; }
                $status = 'overdue';
            }

            $items[] = [
                'date'   => $d,
                'reason' => trim((string) ($r->reason ?? ''), " ,") ?: 'ไม่ระบุ',
                'status' => $status,
            ];
        }

        if (! $items) { return ['level' => 'none', 'items' => []]; }

        $statuses = array_column($items, 'status');
        $level = in_array('ontime', $statuses, true) ? 'ontime'
               : (in_array('overdue', $statuses, true) ? 'overdue' : 'future');

        return ['level' => $level, 'items' => $items];
    }

    public  function address(){
        return Address::where('patient_id',$this->id)->first();
    }
    public function address2() {
    return $this->hasOne(Address::class, 'patient_id', 'id');
}

    public  function visits(){
        return Visit::where('patient_id',$this->id)->get();
    }
    // ใน App\Models\Patient.php
public function visits2()
{
    return $this->hasMany(Visit::class, 'patient_id', 'id'); // ความสัมพันธ์กับ Visit
}



public static function calNH() {
    $yearFull = date('Y') + 543;
    
    if (date('m') > 9) {   
        $yearFull = $yearFull + 1;
    }

    $year2 = substr($yearFull, 2, 2); // ได้ค่าเช่น "68", "69"

    // ค้นหารหัสล่าสุดที่เป็น 9 หลัก
    $lastPatient = Patient::whereRaw("LENGTH(code) = 9") // เฉพาะรหัสใหม่
        ->where('code', 'like', $year2 . "%")
        ->orderBy('code', 'DESC')
        ->first();

    $lastCodeCount = 0;

    if ($lastPatient) {
        $lastCodeCount = (int) substr($lastPatient->code, 2);
    }

    // เพิ่มเลขลำดับ +1 และกำหนดให้เป็น 9 หลัก
    $result = $year2 . sprintf("%07d", $lastCodeCount + 1);

    return $result;
}

    public static function getByNH($code){
        $patient =  Patient::where('code',$code)->first();
        return $patient;
    }

    public static function format2DB($requestData){
        $requestData['birth_date'] = Utillity::th2dbDate($requestData['birth_date']) ;
        $requestData['first_visit'] = Utillity::th2dbDate($requestData['first_visit']) ;
        return $requestData;
    }

    public  function formatDBBack(){
        $this->birth_date = Utillity::db2thDate($this->birth_date) ;
        $this->first_visit = Utillity::db2thDate($this->first_visit) ;
        // return $requestData;
    }
    public  function age(){

        $today = date("Y-m-d");
        $diff = date_diff(date_create(Utillity::BE2AD($this->birth_date) ), date_create($today));
        return ($diff->y ). "y" ;
        // echo 'Your age is '.$diff->format('%y');  . $diff->m. "M- " . $diff->d. "D "

        // return $requestData;
    }

    public function calculateAge($birthDate) {
    // แปลงวันที่จาก พ.ศ. เป็น ค.ศ.
    $birthYear = $birthDate->year + 543;

    // หาความแตกต่างของปี
    $ageYears = date('Y') - $birthYear;

    // หาความแตกต่างของเดือน
    $birthMonth = $birthDate->month;
    $currentMonth = date('m');
    if ($currentMonth < $birthMonth) {
        $ageYears--;
    }

    // หาความแตกต่างของวัน
    $birthDateDay = $birthDate->day;
    $currentDay = date('d');
    if ($currentDay < $birthDateDay && $currentMonth == $birthMonth) {
        $ageYears--;
    }

    return $ageYears;
    }

    public  function lastVisitDate(){
        $visit = Visit::where('patient_id',$this->id)->orderBy('id', 'DESC')->first();
        if($visit){
            return $visit->date;
        }
    }


    /** มีคอลัมน์ "เคสเก่าจากทะเบียนกระดาษ" ของคลินิกวัณโรคแล้วหรือยัง (migration 2026_11_05_100000) */
    public static function tbPaperOldReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('patients', 'tb_paper_old');
    }

    /** มีคอลัมน์ชื่อภาษาอังกฤษแล้วหรือยัง (migration 2026_10_07_130000) */
    public static function englishNameReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('patients', 'name_en');
    }

    /** ชื่อ-สกุลภาษาอังกฤษ สำหรับเอกสารภาษาอังกฤษ — ยังไม่กรอก = ถอยไปใช้ชื่อไทยเหมือนเดิม */
    public function englishFullName(): string
    {
        $en = trim(($this->name_en ?? '') . ' ' . ($this->surname_en ?? ''));
        return $en !== '' ? $en : trim(($this->name ?? '') . ' ' . ($this->surname ?? ''));
    }

    /**
     * บรรทัดชื่ออังกฤษเสริมบนสติกเกอร์/บัตรคิว — ว่างถ้าไม่ได้กรอก หรือถ้าซ้ำกับชื่อหลัก
     * (คนต่างชาติที่ไม่มีชื่อไทย ระบบเติมชื่ออังกฤษลงช่องชื่อไทยแล้ว ไม่ต้องพิมพ์ชื่อเดียวกันสองบรรทัด)
     */
    public function englishNameLine(): string
    {
        $en = trim(($this->name_en ?? '') . ' ' . ($this->surname_en ?? ''));
        $th = trim(($this->name ?? '') . ' ' . ($this->surname ?? ''));
        return ($en === '' || strcasecmp($en, $th) === 0) ? '' : $en;
    }

    /** สัญชาติสำหรับแสดงผล — ข้อมูลเก่าปนทั้งรหัส 3 ตัว (THA/MMR) และชื่อไทย แปลงรหัสเป็นชื่อไทยให้อ่านง่าย */
    public function nationalityText(): string
    {
        $n = trim((string) $this->nationality);
        if (preg_match('/^[A-Z]{3}$/', $n)) {
            $c = \App\Models\Country::where('country_id', $n)->value('nation_name_th');
            if ($c) { return $c; }
        }
        return $n;
    }

    /** สัญชาติภาษาอังกฤษสำหรับเอกสารภาษาอังกฤษ — หาได้ทั้งจากรหัส (THA) และชื่อไทย (ไทย/พม่า) หาไม่เจอ = ค่าเดิม */
    public function nationalityEn(): string
    {
        $n = trim((string) $this->nationality);
        if ($n === '') { return ''; }
        $c = \App\Models\Country::where('country_id', $n)->orWhere('nation_name_th', $n)->value('nation_name_en');
        return $c ? ucwords(strtolower($c)) : $n;
    }
}
