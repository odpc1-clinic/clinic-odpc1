<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;

/**
 * เกณฑ์รหัสผ่านตามหนังสือ สธ 0418.17/719 ลว. 27 ส.ค. 2569
 * (อ้างถึง สธ 0434.02/247 กองดิจิทัลเพื่อการควบคุมโรค ตามที่ สกมช. แจ้งเตือน)
 *
 *   1. ยาวไม่น้อยกว่า 12 ตัวอักษร
 *   2. ผสมทั้งตัวพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ
 *   3. ต้องไม่ซ้ำกับรหัสเดิม
 *   4. ไม่ใช้ข้อมูลที่คาดเดาได้ง่าย
 *
 * เขียนไว้ที่เดียวแล้วเรียกใช้ทุกทางที่ตั้งรหัสได้ (แอดมินตั้งให้ · ผู้ใช้เปลี่ยนเอง ·
 * หน้าแก้ไขผู้ใช้เดิม) ไม่งั้นจะมีทางใดทางหนึ่งหลุดเกณฑ์แล้วไม่มีใครรู้
 * — ของเดิมหน้าแก้ไขผู้ใช้ไม่ตรวจอะไรเลย พิมพ์ 1234 ก็ผ่าน
 */
class StrongPassword implements Rule
{
    /** แฮชรหัสเดิมของผู้ใช้ ไว้เทียบว่าไม่ได้ตั้งซ้ำของเก่า */
    protected ?string $currentHash;

    /** ข้อมูลที่เดาง่ายของผู้ใช้คนนั้น เช่น ชื่อ อีเมล — ห้ามเอามาเป็นรหัส */
    protected array $personal;

    protected string $why = '';

    public function __construct(?string $currentHash = null, array $personal = [])
    {
        $this->currentHash = $currentHash;

        // แตกเป็นคำย่อย ๆ ก่อน — เทียบทั้งสตริงไม่พอ
        // "somchai@clinic.com" ต้องจับ "somchai" ให้ได้ ไม่งั้น Somchai2569!! ผ่านฉลุย
        $bits = [];
        foreach ($personal as $item) {
            $parts = preg_split('/[^A-Za-z0-9\x{0E00}-\x{0E7F}]+/u', (string) $item);
            foreach ($parts as $w) {
                $w = trim($w);
                if (mb_strlen($w) >= 4) { $bits[] = $w; }
            }
        }
        $this->personal = array_values(array_unique($bits));
    }

    public function passes($attribute, $value): bool
    {
        $v = (string) $value;

        if (mb_strlen($v) < 12) {
            $this->why = 'รหัสผ่านต้องยาวอย่างน้อย 12 ตัวอักษร (ตอนนี้ ' . mb_strlen($v) . ' ตัว)';
            return false;
        }

        $missing = [];
        if (!preg_match('/[A-Z]/', $v))                  { $missing[] = 'ตัวพิมพ์ใหญ่ (A-Z)'; }
        if (!preg_match('/[a-z]/', $v))                  { $missing[] = 'ตัวพิมพ์เล็ก (a-z)'; }
        if (!preg_match('/[0-9]/', $v))                  { $missing[] = 'ตัวเลข (0-9)'; }
        if (!preg_match('/[^A-Za-z0-9]/u', $v))          { $missing[] = 'อักขระพิเศษ (เช่น ! @ # $)'; }
        if ($missing) {
            $this->why = 'ยังขาด: ' . implode(' · ', $missing);
            return false;
        }

        // ห้ามซ้ำรหัสเดิม — ข้อ 1 ของหนังสือระบุไว้ชัด
        if ($this->currentHash && Hash::check($v, $this->currentHash)) {
            $this->why = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสเดิม';
            return false;
        }

        // เดาง่ายจากข้อมูลตัวเอง เช่น เอาชื่อหรืออีเมลมาตั้ง
        $low = mb_strtolower($v);
        foreach ($this->personal as $p) {
            $p = mb_strtolower($p);
            if (mb_strpos($low, $p) !== false) {
                $this->why = 'ห้ามใช้ชื่อหรืออีเมลของตัวเองเป็นส่วนหนึ่งของรหัสผ่าน';
                return false;
            }
        }

        // รหัสยอดฮิตที่ถูกเดาเป็นอันดับต้น ๆ — ยาวพอแต่ไม่ปลอดภัย
        foreach (['password', '123456', 'qwerty', 'admin', 'clinic', 'hospital', '111111'] as $bad) {
            if (mb_strpos($low, $bad) !== false) {
                $this->why = 'ห้ามมีคำที่เดาง่ายอยู่ในรหัสผ่าน (เช่น password, admin, 123456)';
                return false;
            }
        }

        return true;
    }

    public function message(): string
    {
        return $this->why ?: 'รหัสผ่านไม่เป็นไปตามเกณฑ์';
    }

    /** ข้อความอธิบายเกณฑ์ ใช้แสดงใต้ช่องกรอกให้ผู้ใช้อ่านก่อนพิมพ์ */
    public static function hint(): string
    {
        return 'อย่างน้อย 12 ตัวอักษร · มีตัวพิมพ์ใหญ่ พิมพ์เล็ก ตัวเลข และอักขระพิเศษ · ไม่ซ้ำรหัสเดิม';
    }
}
