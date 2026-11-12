<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * ช่วงการใช้สูตรยาหนึ่งช่วงของบัตรหนึ่งใบ
 *
 * เปลี่ยนสูตรกลางคอร์สต้องเก็บของเดิมไว้ (คลินิกขอ 2569-09-24)
 * เช่น เดือน 0-2 ใช้ 2HRZE -> เดือน 3 เปลี่ยนเป็น 4HR : เก็บเป็น 2 แถว ไม่ใช่เขียนทับ
 *
 * ยาที่ใช้ในช่วงนั้น (drugs) เป็นตัวกำหนดว่าตารางขนาดยารายเดือนจะมีช่องยาอะไรบ้าง
 */
class TbRegimenChange extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['drugs' => 'array'];

    public function registration()
    {
        return $this->belongsTo(TbRegistration::class, 'tb_registration_id');
    }

    /** ช่วงสูตรเปลี่ยน = ช่วงนับรอบอาจเปลี่ยน จัด sort ของรอบสั่งยาใหม่ (TbRegistration::resortDoses) */
    protected static function booted(): void
    {
        $fix = function (self $r) { optional(TbRegistration::find($r->tb_registration_id))->resortDoses(); };
        static::saved($fix);
        static::deleted($fix);
    }

    /** ช่วงสูตรที่ใช้อยู่ ณ วันที่หนึ่ง (พ.ศ. Y-m-d) — visit ย้อนหลังใช้หาว่าวันนั้นกินสูตรอะไร */
    public static function coveringFor(int $registrationId, string $dateBe): ?self
    {
        return self::where('tb_registration_id', $registrationId)->whereNotNull('start_date')
            ->where('start_date', '<=', $dateBe)
            ->orderByDesc('start_date')->orderByDesc('id')->first();
    }

    /** ช่วงที่ยังใช้อยู่ = แถวล่าสุดที่ยังไม่มีวันสิ้นสุด */
    public static function currentFor(int $registrationId): ?self
    {
        return self::where('tb_registration_id', $registrationId)
            // วันสิ้นสุดที่ยังมาไม่ถึง = ยังใช้อยู่ — พยาบาลลงวันจบระยะไว้ล่วงหน้า (เช่น 4FDC ครบ 2 เดือน)
            // เดิมนับแค่แถวที่ไม่มีวันสิ้นสุด บัตรเลยไม่มี "สูตรที่ใช้อยู่" แล้วหันไปใช้สูตรมาตรฐานที่มีทั้ง 4FDC และ 2FDC (2569-10-02)
            // วันที่เป็น พ.ศ. Y-m-d เทียบแบบข้อความได้ · แถวเก่าที่หลุดเป็น ค.ศ. จะถือว่าจบแล้ว
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', VisitAppointment::todayBe()))
            ->orderByDesc('start_month')->orderByDesc('id')->first();
    }

    /**
     * วันที่ของช่วงสูตรเป็น d/m/พ.ศ. — ใช้ทั้งแสดงผลและเติมค่าในช่องแก้ไข
     * แถวที่ถูกบันทึกเป็น ค.ศ. (ปฏิทินขึ้นภาษาอังกฤษ เจอ 2569-10-02) ต้องโชว์เป็น พ.ศ. ให้ถูก
     * และเติมค่า พ.ศ. ลงช่องแก้ไข เพื่อให้กดบันทึกแล้วแถวนั้นถูกแก้เป็น พ.ศ. ไปเลย
     */
    public function thDate(string $col): ?string
    {
        $v = (string) $this->{$col};
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) { return null; }
        $y = (int) $m[1];
        if ($y < 2500) { $y += 543; }
        return $m[3] . '/' . $m[2] . '/' . $y;
    }

    public function auditLabel(): string
    {
        return 'สูตรยา ' . $this->regimen . ($this->start_month !== null ? ' (เริ่มเดือนที่ ' . $this->start_month . ')' : '');
    }
}
