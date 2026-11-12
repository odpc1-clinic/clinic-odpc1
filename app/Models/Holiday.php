<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Utillity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * วันหยุดราชการที่คลินิกปิด
 *
 * holiday_date เก็บเป็น "พ.ศ." ในคอลัมน์ DATE เหมือน visits.appointment เป๊ะๆ
 * ตั้งใจให้เทียบกันตรงๆ ได้ ('2569-12-07' === '2569-12-07') โดยไม่ต้องแปลงปีระหว่างทาง
 * เพราะแปลงพลาดครั้งเดียวแปลว่าเตือนคนไข้ผิดวัน ซึ่งแย่กว่าไม่เตือนเลย
 *
 * ห้ามส่งค่านี้เข้า Carbon::parse() ตรงๆ — จะได้ ค.ศ. 2569 (อีก 543 ปีข้างหน้า)
 * ใช้ Utillity::BE2AD() ก่อนเสมอถ้าต้องคำนวณวันในสัปดาห์หรือระยะห่างของวัน
 */
class Holiday extends Model
{
    use Auditable;

    protected $fillable = ['holiday_date', 'name', 'type', 'note', 'is_closed'];

    protected $casts = [
        'is_closed' => 'boolean',
    ];

    // ไม่ cast holiday_date เป็น date — ถ้า cast Carbon จะอ่านปี พ.ศ. เป็น ค.ศ. ทันที
    // Laravel 10 เอา $dates ออกแล้ว — ไม่มีคอลัมน์ที่ต้องแปลงเป็น Carbon อยู่แล้ว (ค่าเดิมเป็นอาร์เรย์ว่าง)

    public const TYPES = [
        'annual'       => 'วันหยุดราชการประจำปี',
        'substitution' => 'วันหยุดชดเชย',
        'special'      => 'วันหยุดพิเศษ ครม.',
        'gov'          => 'เฉพาะหน่วยงานราชการ',
    ];

    /** คีย์แคช — ล้างทุกครั้งที่มีการแก้ไข ไม่งั้นปฏิทินจะล็อกตามของเก่า */
    private const CACHE_KEY = 'holidays_closed_be';

    protected static function booted()
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * วันหยุดที่ "คลินิกปิดจริง" ทั้งหมด => [ 'พ.ศ.-ดด-วว' => 'ชื่อวันหยุด' ]
     *
     * แคชไว้เพราะหน้า visit/form เรียกทุกครั้งที่เปิดคนไข้ และรายการนี้เปลี่ยนปีละครั้ง
     */
    public static function closedMap(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(12), function () {
            return static::where('is_closed', true)
                ->orderBy('holiday_date')
                ->pluck('name', 'holiday_date')
                ->all();
        });
    }

    /** วันที่ พ.ศ. ('2569-12-07') นี้คลินิกปิดไหม */
    public static function isClosedBE(?string $beDate): bool
    {
        return $beDate ? array_key_exists($beDate, static::closedMap()) : false;
    }

    /** ชื่อวันหยุดของวันที่ พ.ศ. นี้ (null ถ้าไม่ใช่วันหยุด) */
    public static function nameForBE(?string $beDate): ?string
    {
        return $beDate ? (static::closedMap()[$beDate] ?? null) : null;
    }

    /**
     * วันนี้ (หรือวันที่ที่ส่งมาเป็น ค.ศ.) คลินิกปิดไหม — นับทั้งเสาร์-อาทิตย์และวันหยุดราชการ
     * ใช้ตัวนี้ตัดสินใจเรื่องการส่งแจ้งเตือน จะได้ไม่มีที่ไหนเช็คแค่เสาร์-อาทิตย์อีก
     */
    public static function isClinicClosed(\DateTimeInterface $adDate): bool
    {
        if (in_array((int) $adDate->format('w'), [0, 6], true)) {
            return true;                       // เสาร์-อาทิตย์
        }
        return static::isClosedBE(Utillity::AD2BE($adDate->format('Y-m-d')));
    }

    /** แปลง holiday_date เป็น ค.ศ. ('2026-12-07') สำหรับคำนวณวัน/ส่งให้ปฏิทินฝั่งหน้าเว็บ */
    public function getAdDateAttribute(): string
    {
        return Utillity::BE2AD($this->holiday_date);
    }

    /** ชื่อวันในสัปดาห์ภาษาไทย คำนวณจากวันที่จริง ไม่ได้เก็บไว้ในตาราง */
    public function getThaiDayAttribute(): string
    {
        $names = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
        return $names[(int) date('w', strtotime($this->ad_date))] ?? '';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
