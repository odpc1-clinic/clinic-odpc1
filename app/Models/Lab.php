<?php

namespace App\Models;

use App\Utillity;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Lab extends Model
{
    use Auditable;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'labs';

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
    'patient_id',
    'visit_id',
    'report_by',
    'approve_by',
    'approve_date',
    'approve_at',
    'collected_date',
    'collected_at',
    'report_date',
    'report_at',
    'LN',
    'remark', // 👈 เพิ่มตรงนี้
    'status',
    // flow ห้อง Lab ใหม่ 2569-09-30: รับ Lab / ผู้ทดสอบ
    'received_by', 'received_at', 'tested_by', 'tested_date',
];




    public static $statusOption = [1 => 'ส่ง Lab', 3 => 'รอลงผล RPR', 6 => 'รอลงผล Culture', 7 => 'รอลงผล PCR', 8 => 'รอลงผล Pap Smear', 9 => 'รอลงผล HBs Ab', 4 => 'รอลงผล Culture/PCR', 5 => 'รอลงผล RPR+Culture/PCR', 2 => 'เสร็จ'];

    /**
     * วันที่เริ่มใช้กติกา "ต้องอนุมัติผลก่อน พยาบาล/หมอถึงจะเห็น"
     * Lab ที่สร้างก่อนวันนี้ถือว่าอนุมัติแล้วโดยปริยาย — กันผลเก่า 2,400+ เคสหายจากประวัติคนไข้
     */
    const APPROVAL_REQUIRED_FROM = '2026-07-20';

    /** อนุมัติผลแล้วหรือยัง (ห้อง Lab กดอนุมัติ = มี approve_by) */
    public function isApproved(): bool
    {
        return !empty($this->approve_by);
    }

    /** ผล Lab นี้ให้ฝั่งพยาบาล/หมอเห็นได้ไหม (ห้อง Lab เห็นเสมอ เช็คแยกที่หน้าจอ) */
    public function isResultVisible(): bool
    {
        if ($this->isApproved()) {
            return true;
        }
        // Lab เก่ากว่าวันเริ่มใช้กติกา -> ยังแสดงตามเดิม
        return $this->created_at
            && $this->created_at->lt(\Carbon\Carbon::parse(self::APPROVAL_REQUIRED_FROM)->startOfDay());
    }


    public function labItem(){
        return LabItem::where('lab_id',$this->id)->get();
    }
    public function labSub(){
        return LabSub::where('lab_id',$this->id)->get();
    }
public function labSub2()
{
    return $this->hasMany(LabSub::class, 'lab_id', 'id');
}
// ความสัมพันธ์ไปหาตาราง lab_subs (รายการตรวจย่อยของแล็บนั้นๆ)
    public function labSubs()
    {
        // 1 ใบสั่งแล็บ (Lab) มีรายการตรวจย่อย (LabSub) ได้หลายรายการ
        return $this->hasMany(\App\Models\LabSub::class, 'lab_id', 'id');
    }
    public function labSubConfig(){
        $labsubs= LabSub::where('lab_id',$this->id)->orderBy('method', 'ASC')->get();
        $result = [];
        foreach($labsubs as $labsub){
            if($labsub->method == 10){
                $result[$labsub->method]['key']= $labsub->method_other;
                $result[$labsub->method]['value']= $labsub->result;
            }else{
                if(!array_key_exists($labsub->method,$result)){
                    $result[$labsub->method] = [];
                }
                if($labsub->specimen_from){
                    if(!array_key_exists($labsub->specimen_from,$result[$labsub->method])){
                        
                        if($labsub->specimen_from==10){
                            $result[$labsub->method][$labsub->specimen_from] = []; 
                            $result[$labsub->method][$labsub->specimen_from]['key'] = $labsub->specimen_from_other; 
                            $result[$labsub->method][$labsub->specimen_from]['value'] = $labsub->result; 
                        }else{
                            $result[$labsub->method][$labsub->specimen_from] = $labsub->result;
                            
                        }
                    }
                }else{
                    $result[$labsub->method]= $labsub->result;
                }
            }

        }
        return $result;
    }
    public function visit()
{
    return $this->belongsTo(Visit::class);
}
    public function labBlood()
{
    return $this->hasOne(LabBlood::class, 'visit_id', 'visit_id'); 
}

    public static function format2DB($requestData){
        // แปลงเฉพาะช่องที่ส่งมา — ฟอร์มใหม่ไม่ส่ง report/approve แล้ว (ประทับจากปุ่ม)
        // ถ้าแปลงคีย์ที่ไม่มี จะได้ null ไปทับชื่อ/เวลาที่ปุ่มประทับไว้ (2569-09-30)
        foreach (['collected_date', 'report_date', 'approve_date', 'tested_date'] as $k) {
            if (array_key_exists($k, $requestData)) {
                $requestData[$k] = Utillity::th2dbDate($requestData[$k]) ;
            }
        }
        return $requestData;
    }

    public  function formatDBBack(){
        $this->collected_date = Utillity::db2thDate($this->collected_date) ;
        $this->report_date = Utillity::db2thDate($this->report_date) ;
        $this->approve_date = Utillity::db2thDate($this->approve_date) ;
        $this->tested_date = Utillity::db2thDate($this->tested_date) ;
        
        // return $requestData;
    }
    
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    public  function status(){
        if($this->status){
            return $this->status.":".self::$statusOption[$this->status];
        }
       
    }

    public static function count($status)
    {
        return self::where('status', $status)->count();
    }

    // อนุมัติผล / ถอนอนุมัติ / เปลี่ยนสถานะใบ → หน้าตรวจที่เปิดรอผลอยู่รู้ทันที (เดิมเช็คทุก 15 วิ — ยังเช็คอยู่เป็นตัวสำรอง)
    protected static function booted()
    {
        static::saved(function (Lab $lab) {
            if ($lab->wasRecentlyCreated || $lab->wasChanged(['approve_by', 'status'])) {
                \App\Support\Realtime::signal('lab', ['lab' => (int) $lab->id, 'visit' => (int) $lab->visit_id]);
            }
        });
    }
}
