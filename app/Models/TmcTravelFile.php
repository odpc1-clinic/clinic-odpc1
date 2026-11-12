<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * ไฟล์แนบแผนการเดินทาง TMC (กำหนดการ/ตั๋ว/จดหมายเชิญ/วีซ่า) — 2569-10-07
 *
 * หมอใช้ประเมินความเสี่ยงตามเส้นทางจริง (ประเทศ/เมือง/ช่วงเวลา) และบางประเทศต้องเห็นจดหมายเชิญก่อนออกใบรับรอง
 * เก็บแบบเดียวกับ TbLabFile: เข้ารหัสใน storage/app/private (ไม่ใช่ public) เปิดผ่าน route ที่ต้องล็อกอินเท่านั้น
 * ไม่ extends TbLabFile เพราะ ready() ใช้ static ตัวแปรในเมธอด — PHP 8.1+ แชร์ค่าระหว่างคลาสแม่/ลูก
 * ตารางหนึ่งพร้อม อีกตารางยังไม่ขึ้น จะตอบผิด
 */
class TmcTravelFile extends Model
{
    use Auditable;

    public const DIR = 'private/tmc_travel_files';

    /** ขนาดสูงสุดต่อไฟล์ (KB) */
    public const MAX_KB = 10240;

    /** ชนิดที่รับ ตรวจจากเนื้อไฟล์จริง — ชุดเดียวกับไฟล์ผลตรวจ TB (เบราว์เซอร์เปิด inline ได้ทุกตัว) */
    public const MIMES = TbLabFile::MIMES;

    /** ชื่อเอกสารที่ใช้บ่อย — เป็นตัวช่วยพิมพ์ (datalist) พิมพ์อย่างอื่นได้ */
    public static $titleOption = ['กำหนดการเดินทาง (Itinerary)', 'ตั๋วเครื่องบิน', 'จดหมายเชิญ', 'วีซ่า', 'หนังสือเดินทาง (Passport)', 'ใบรับรองวัคซีนเดิม'];

    protected $guarded = ['id'];

    protected static $auditExclude = ['stored_name'];

    public static function ready(): bool
    {
        static $ready = null;
        return $ready ??= Schema::hasTable('tmc_travel_files');
    }

    protected static function booted()
    {
        // ลบแถว = ลบไฟล์ทิ้งด้วย ไม่ให้เอกสารคนไข้ค้างบนดิสก์โดยไม่มีใครอ้างถึง
        static::deleted(function (self $f) {
            $f->deleteStoredFile();
        });
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

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
        return (string) $this->stored_name !== '' && Storage::disk('local')->exists($this->path());
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

    public function sizeText(): string
    {
        $s = (int) $this->size;
        if ($s >= 1048576) { return number_format($s / 1048576, 1) . ' MB'; }
        return max(1, (int) round($s / 1024)) . ' KB';
    }

    public function auditLabel(): string
    {
        return 'ไฟล์แผนการเดินทาง TMC ' . ($this->title ?: $this->original_name) . ' (visit ' . $this->visit_id . ')';
    }
}
