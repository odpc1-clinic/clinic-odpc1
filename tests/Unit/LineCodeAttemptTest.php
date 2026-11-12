<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * ตรรกะ "ข้อความนี้คือการพิมพ์รหัสผูกบัญชีหรือเปล่า" ของ LineWebhookController
 *
 * ของเดิมดึงตัวเลขออกมาจากทั้งประโยคแล้วนับว่าได้ 6 หลักไหม ทำให้ประโยคธรรมดา
 * ที่มีเลขปนอยู่ 6 ตัวถูกมองเป็นรหัส บอทเลยแทรกข้อความ "รหัสไม่ถูกต้อง"
 * กลางบทสนทนาที่พยาบาลกำลังคุยกับคนไข้ (พยาบาลแจ้งเข้ามา 21 ส.ค. 2569)
 *
 * เก็บเป็นเทสต์ไว้เพราะเงื่อนไขนี้แก้ผิดแล้วไม่มีอะไรฟ้อง จะไปโผล่ที่แชทคนไข้อย่างเดียว
 */
class LineCodeAttemptTest extends TestCase
{
    /** ก็อปเงื่อนไขเดียวกับใน LineWebhookController มาไว้ตรวจ */
    private function isCodeAttempt(string $text): bool
    {
        $text = trim($text);

        return (bool) preg_match('/^[\d\s-]+$/u', $text)
            && preg_match_all('/\d/', $text) === 6;
    }

    /**
     * @dataProvider codeAttempts
     */
    public function test_messages_that_really_are_a_code($text)
    {
        $this->assertTrue($this->isCodeAttempt($text), "ควรถือว่าเป็นรหัส: {$text}");
    }

    public static function codeAttempts(): array
    {
        return [
            'เลขล้วน'          => ['123456'],
            'มีเว้นวรรคกลาง'   => ['123 456'],
            'มีขีดคั่น'        => ['123-456'],
            'มีช่องว่างหัวท้าย' => ['  123456  '],
            'เว้นทุกหลัก'      => ['1 2 3 4 5 6'],
        ];
    }

    /**
     * @dataProvider notCodeAttempts
     */
    public function test_ordinary_messages_are_left_alone($text)
    {
        $this->assertFalse($this->isCodeAttempt($text), "ไม่ควรถือว่าเป็นรหัส: {$text}");
    }

    public static function notCodeAttempts(): array
    {
        return [
            // สามอันนี้คือเคสที่ของเดิมพังจริง เลขรวมกันได้ 6 หลักพอดีแต่ปนอยู่ในประโยค
            'บอกอายุกับปีเกิด' => ['ผมอายุ 53 ปี เกิดปี 2515 ครับ'],
            'บอก HN'          => ['HN 690001 ครับ'],
            'บอกเลขแต่มีคำไทย' => ['เลข 123 456 ค่ะ'],

            'ข้อความธรรมดา'    => ['ขอถามหน่อยค่ะ'],
            'ขอเลื่อนนัด'      => ['ขอเลื่อนนัดวันที่ 25 ต.ค. 69 ค่ะ'],
            'เบอร์โทร'         => ['081-8830372'],
            'สั้นไป'           => ['12345'],
            'ยาวไป'            => ['1234567'],
            'ว่างเปล่า'        => [''],
            'อิโมจิ'           => ['ขอบคุณค่ะ 🙏'],
        ];
    }
}
