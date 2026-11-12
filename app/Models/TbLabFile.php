<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ไฟล์ผลตรวจจากที่อื่น (สแกน/รูปถ่าย/PDF) — เริ่มที่บัตร TB 01 (2569-10-05) ขยายให้หน้าตรวจทุกคลินิก (2569-10-07)
 *
 * ชื่อตารางยังเป็น tb_lab_files ตามของเดิม · แถวที่อัปจากหน้าตรวจมี visit_id, ที่อัปจากบัตร TB มี tb_registration_id
 * ทั้งสองหน้าแสดงไฟล์ทุกแถวของคนไข้คนเดียวกัน (patient_id) — ผลแล็บเป็นของคน ไม่ใช่ของบัตร
 *
 * คนไข้ TB หลายรายเจาะเลือดที่ รพ.ใกล้บ้าน/แล็บเอกชน แล้วถือใบผลมา — คลินิกอยากเก็บไว้ในบัตร
 * แทนการถ่ายเอกสารแนบแฟ้ม · ตัวไฟล์เก็บเข้ารหัสใน storage/app/private (ไม่ใช่ public)
 * แบบเดียวกับรูปผังครอบครัว เปิดดูได้ทาง route tb.labFile.show ที่ต้องล็อกอินเท่านั้น
 */
class TbLabFile extends Model
{
    use Auditable;

    public const DIR = 'private/tb_lab_files';

    /** ขนาดสูงสุดต่อไฟล์ (KB) — ใช้ทั้ง validation และข้อความบนหน้าจอ */
    public const MAX_KB = 10240;

    /** ชนิดที่รับ ตรวจจากเนื้อไฟล์จริง (finfo) ไม่ใช่นามสกุล — HEIC ไม่รับเพราะเบราว์เซอร์ส่วนใหญ่เปิดไม่ได้ */
    public const MIMES = [
        'application/pdf' => 'pdf',
        'image/jpeg'      => 'jpg',
        'image/png'       => 'png',
        'image/webp'      => 'webp',
    ];

    protected $guarded = ['id'];

    /** ไม่ต้อง log ชื่อไฟล์สุ่มที่เก็บจริง — ไม่มีความหมายกับคนอ่าน log */
    protected static $auditExclude = ['stored_name'];

    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Schema::hasTable('tb_lab_files');
    }

    /** ผูกไฟล์กับรายการ Lab ส่งตรวจนอกได้แล้วหรือยัง (migration 2026_10_30_180000) */
    public static function externalReady(): bool
    {
        static $ready = null;
        return $ready ??= self::ready() && Schema::hasColumn('tb_lab_files', 'external_lab_id');
    }

    /** ใช้จากหน้าตรวจทุกคลินิกได้แล้วหรือยัง (migration 2026_10_07_140000 เพิ่ม visit_id) */
    public static function visitReady(): bool
    {
        static $ready = null;
        return $ready ??= self::ready() && Schema::hasColumn('tb_lab_files', 'visit_id');
    }

    protected static function booted()
    {
        // ลบแถว = ลบไฟล์ทิ้งด้วย ไม่ให้ไฟล์ข้อมูลคนไข้ค้างอยู่โดยไม่มีใครอ้างถึง
        static::deleted(function (self $f) {
            $f->deleteStoredFile();
            // ไฟล์ผลของรายการสั่งตรวจนอก -> ไม่เหลือไฟล์แล้ว รายการกลับเป็น "รอผล"
            if ($f->external_lab_id) {
                optional(VisitExternalLab::find($f->external_lab_id))->releaseFileResult();
            }
        });
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** เข้ารหัสแล้วเขียนลงดิสก์ คืนชื่อไฟล์สุ่มที่ใช้เก็บ (ยังไม่สร้างแถว) */
    public static function putEncrypted(string $bytes): string
    {
        do {
            $name = Str::random(40) . '.enc';
        } while (Storage::disk('local')->exists(self::DIR . '/' . $name));

        Storage::disk('local')->put(self::DIR . '/' . $name, Crypt::encrypt($bytes));
        return $name;
    }

    public function path(): string
    {
        return self::DIR . '/' . $this->stored_name;
    }

    public function fileExists(): bool
    {
        return $this->stored_name !== null && $this->stored_name !== ''
            && Storage::disk('local')->exists($this->path());
    }

    public function readDecrypted(): string
    {
        return Crypt::decrypt(Storage::disk('local')->get($this->path()));
    }

    public function deleteStoredFile(): void
    {
        if ($this->stored_name) {
            Storage::disk('local')->delete($this->path());
        }
    }

    public function isImage(): bool
    {
        return strpos((string) $this->mime, 'image/') === 0;
    }

    public function isPdf(): bool
    {
        return $this->mime === 'application/pdf';
    }

    public function sizeText(): string
    {
        $s = (int) $this->size;
        if ($s >= 1048576) { return number_format($s / 1048576, 1) . ' MB'; }
        return max(1, (int) round($s / 1024)) . ' KB';
    }

    /**
     * ขนาดรวมสูงสุดต่อการส่งหนึ่งครั้ง (byte) ตาม post_max_size ของ PHP
     * เกินนี้ PHP ทิ้งทั้งคำขอ (รวม CSRF token) ผู้ใช้จะเจอหน้า 419 งง ๆ — หน้าเว็บจึงเช็คก่อนส่ง
     */
    public static function postMaxBytes(): int
    {
        $v = trim((string) ini_get('post_max_size'));
        $n = (int) $v;
        switch (strtoupper(substr($v, -1))) {
            case 'G': $n *= 1024;
            // no break
            case 'M': $n *= 1024;
            // no break
            case 'K': $n *= 1024;
        }
        return $n > 0 ? $n : 8 * 1048576;
    }

    /**
     * แก้ข้อมูลกำกับไฟล์ (วันที่ตรวจ / ตรวจที่ / รายการ / หมายเหตุ) — พิมพ์ผิดตอนอัป (คลินิกขอ 2569-10-08)
     * แก้ได้ทุกไฟล์ของคนไข้ แม้อัปจาก visit ก่อน/บัตร TB (แก้ข้อความไม่เสี่ยงเท่าลบ) · คืนข้อความผิดพลาด หรือ null = สำเร็จ
     * ตัวไฟล์แก้ไม่ได้ — อัปผิดไฟล์ให้ลบแล้วอัปใหม่ · ประวัติการแก้อยู่ใน activity log (Auditable)
     */
    public function applyEdit(array $in): ?string
    {
        $date = \App\Support\ThaiDate::parse($in['test_date'] ?? null, ['allowEmpty' => true, 'notFuture' => true]);
        if (! $date['ok']) { return 'วันที่ตรวจ: ' . $date['error']; }
        $clean = function ($k, $max) use ($in) {
            $v = trim((string) ($in[$k] ?? ''));
            return $v === '' ? null : mb_substr($v, 0, $max);
        };
        $this->fill([
            'test_date' => $date['be'],
            'source'    => $clean('source', 150),
            'title'     => $clean('title', 150),
            'note'      => $clean('note', 500),
        ]);
        if ($this->isDirty()) { $this->save(); }
        return null;
    }

    public function auditLabel(): string
    {
        $where = $this->tb_registration_id ? 'บัตร TB ' . $this->tb_registration_id : 'visit ' . $this->visit_id;
        return 'ไฟล์ผลตรวจจากที่อื่น ' . ($this->title ?: $this->original_name) . ' (' . $where . ')';
    }
}
