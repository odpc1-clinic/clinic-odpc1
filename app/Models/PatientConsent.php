<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * ความยินยอม PDPA — หนึ่งแถว = การบันทึกหนึ่งครั้ง (ประวัติ ไม่เขียนทับ) · ค่าปัจจุบัน = แถวล่าสุด
 * ช่องยินยอม: 1 ยินยอม / 0 ไม่ยินยอม / null ไม่ได้ถาม
 */
class PatientConsent extends Model
{
    protected $table = 'patient_consents';

    protected $fillable = [
        'patient_id', 'notice_version', 'notice_informed', 'line', 'photo', 'contact', 'research',
        'research_project', 'method', 'signer', 'signer_name', 'attachment', 'note', 'user_id',
    ];

    protected $casts = [
        'notice_informed' => 'boolean',
    ];

    // ฉบับประกาศความเป็นส่วนตัว — แก้ข้อความใน resources/views/privacy/notice.blade.php แล้วเลื่อนเลขนี้
    // คนไข้ที่ยืนยันฉบับเก่าจะขึ้นแถบให้แจ้งใหม่
    const NOTICE_VERSION = '1.0';

    const PURPOSES = [
        'line'     => ['label' => 'ส่ง LINE แจ้งนัด / เตือนนัด',
                       'text'  => 'ใช้บัญชี LINE ของท่านส่งข้อความแจ้งวันนัด เตือนนัด และแจ้งให้มาฟังผล โดยข้อความจะไม่ระบุชื่อโรคหรือผลตรวจ',
                       'no'    => 'จะไม่ได้รับข้อความเตือนนัดทาง LINE แต่ยังรับบริการได้ตามปกติ'],
        'photo'    => ['label' => 'เก็บรูปถ่ายจากบัตรประชาชน',
                       'text'  => 'เก็บรูปถ่ายจากบัตรประจำตัวประชาชนไว้ในเวชระเบียน เพื่อให้เจ้าหน้าที่ยืนยันตัวตนก่อนให้บริการ และป้องกันการรักษาผิดคน',
                       'no'    => 'เจ้าหน้าที่จะขอดูบัตรของท่านทุกครั้งที่มารับบริการแทน (ระบบลบรูปที่เคยเก็บไว้ให้)'],
        'contact'  => ['label' => 'ติดต่อคู่สัมผัส',
                       'text'  => 'ติดต่อคู่นอนหรือผู้สัมผัสที่ท่านให้ข้อมูลไว้ เพื่อแนะนำให้มารับการตรวจ โดยจะไม่เปิดเผยชื่อของท่านให้คู่สัมผัสทราบ',
                       'no'    => 'ศูนย์ฯ จะไม่ติดต่อคู่สัมผัสเอง ท่านสามารถรับบัตรแนะนำไปให้คู่สัมผัสด้วยตนเองได้'],
    ];

    const SIGNERS = [
        'self'     => 'เจ้าของข้อมูล (คนไข้) ตอบเอง',
        'guardian' => 'ผู้ปกครอง / ผู้แทนโดยชอบธรรม',
        'school'   => 'มีหนังสือจากโรงเรียน / สถานศึกษา',
    ];

    public static function latestFor(int $patientId): ?self
    {
        return static::where('patient_id', $patientId)->orderByDesc('id')->first();
    }

    /**
     * ทำเรื่องนี้ได้ไหม — ห้ามเฉพาะเมื่อคนไข้ "ปฏิเสธ" ไว้ชัดเจน
     * ยังไม่ได้ถาม = ทำได้ตามเดิม (คนไข้ที่ผูก LINE เองผ่านฟอร์มถือว่าประสงค์รับข้อความอยู่แล้ว)
     */
    public static function allows(int $patientId, string $purpose): bool
    {
        $c = static::latestFor($patientId);
        // ผู้เยาว์ (ต่ำกว่า 20 ปี): ต้องมีผู้ปกครองยินยอมไว้ชัดเจนเท่านั้น — ยังไม่ได้ถามก็ไม่ได้ (นโยบายกรม ข้อ 9 · 2569-10-29)
        $age = static::ageOf(Patient::find($patientId));
        if ($age !== null && $age < 20) {
            return $c && $c->signer === 'guardian' && $c->{$purpose} !== null && (int) $c->{$purpose} === 1;
        }
        return ! ($c && $c->{$purpose} !== null && (int) $c->{$purpose} === 0);
    }

    /** none = ยังไม่ได้ถาม · outdated = ประกาศฉบับเก่า · refused = ปฏิเสธบางข้อ · ok = ครบ */
    public function status(): string
    {
        if (! $this->notice_informed || $this->notice_version !== self::NOTICE_VERSION) return 'outdated';
        foreach (array_keys(self::PURPOSES) as $k) {
            if ($this->{$k} !== null && (int) $this->{$k} === 0) return 'refused';
        }
        return 'ok';
    }

    public static function statusFor(int $patientId): string
    {
        $c = static::latestFor($patientId);
        return $c ? $c->status() : 'none';
    }

    /** อายุเป็นปีเต็ม จาก birth_date ที่เก็บ พ.ศ. (ข้อมูลเก่าบางแถวเป็น ค.ศ.) — null ถ้าไม่มี/อ่านไม่ออก */
    public static function ageOf($patient): ?int
    {
        // รับได้ทั้ง Patient หรือสตริงวันเกิดรูปแบบ DB (Y-m-d พ.ศ./ค.ศ.)
        $raw = is_string($patient) ? $patient : ($patient ? (string) ($patient->getRawOriginal('birth_date') ?? '') : '');
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) return null;
        $y = (int) $m[1];
        if ($y > 2400) $y -= 543;
        if (! checkdate((int) $m[2], (int) $m[3], $y)) return null;
        return (int) \Carbon\Carbon::create($y, (int) $m[2], (int) $m[3])->diffInYears(now(), true);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
