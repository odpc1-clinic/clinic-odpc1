<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class VisitMedicine extends Model
{
    use Auditable;

    protected static function booted()
    {
        // หมอสั่งวัคซีน (TMC) แต่พยาบาลลืมติ๊ก "ต้องการฉีดวัคซีน" ในสาเหตุการมา → ติ๊กให้ (คลินิกขอ 2569-11-03)
        // พังตรงนี้ต้องไม่ทำให้การสั่งยาพัง
        static::created(function (VisitMedicine $vm) {
            try {
                if ($vm->visit_id && \App\Models\Medicine::where('id', $vm->medicine_id)->where('is_vaccine', 1)->exists()) {
                    \App\Models\Visit::ensureTmcVaccineReason($vm->visit_id);
                }
            } catch (\Throwable $e) { report($e); }
        });
    }

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'visit_medicines';

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
    protected $fillable = ['visit_id', 'medicine_id', 'lot_number', 'dose', 'route', 'amount', 'sig'];

    /** มีคอลัมน์ lot_number แล้วหรือยัง (migration 2026_10_09_100000) — ยังไม่มี = ไม่แสดง/ไม่บันทึกช่อง Lot */
    public static function lotReady(): bool
    {
        static $ready = null;
        return $ready ??= \Illuminate\Support\Facades\Schema::hasColumn('visit_medicines', 'lot_number');
    }

    /** วิธีใช้ยาแบบเลือก (2569-09-29) — รูปแบบดู App\Support\DrugSig */
    protected $casts = ['sig' => 'array'];

    /** ข้อความวิธีใช้สำหรับคนไข้/ห้องยา — ว่างถ้าหมอยังไม่ได้เลือก (ใบเก่าก่อน 2569-09-29) */
    public function sigText(): string
    {
        return \App\Support\DrugSig::text($this->sig);
    }

    public function visit(){
        return Visit::find($this->visit_id)->first();
    }
    // ในไฟล์ VisitMedicine.php
public function medicine()
{
    // เชื่อมโยงไปหาตาราง medicines (หรือ Drug) เพื่อดึงชื่อยา
    return $this->belongsTo(\App\Models\Medicine::class, 'medicine_id'); 
    // หมายเหตุ: ถ้า Model ยาของคุณชื่อ Drug ให้เปลี่ยน Medicine::class เป็น Drug::class
}

    
}
