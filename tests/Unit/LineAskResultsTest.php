<?php

namespace Tests\Unit;

use App\Services\LineMessagingService;
use Tests\TestCase;

/**
 * ประโยค "สอบถามผลตรวจได้ทาง LINE นี้" ที่ต่อท้ายข้อความหาคนไข้
 *
 * ต้องขึ้นเฉพาะบัญชีที่มีเจ้าหน้าที่คอยตอบเท่านั้น (LINE_STAFFED=true)
 * บัญชีแจ้งเตือนล้วนถ้าไปบอกว่า "ถามได้ที่นี่" คนไข้จะนั่งรอคำตอบที่ไม่มีวันมา
 * ซึ่งแย่กว่าไม่บอกอะไรเลย — เงื่อนไขนี้พังแล้วไม่มีอะไรฟ้อง จึงต้องมีเทสต์กันไว้
 *
 * และคำต้องเจาะจงว่า "กรณีที่นัดฟังผลทาง LINE" เสมอ ห้ามเขียนกว้างจนกลายเป็น
 * ชวนให้ถามผลอะไรก็ได้ เพราะ **ผลตรวจ HIV คนไข้ต้องมาฟังเองที่คลินิก**
 * ห้ามมีชื่อโรคโผล่ในข้อความด้วย ข้อความเด้งขึ้นหน้าจอให้คนรอบตัวคนไข้เห็นได้
 */
class LineAskResultsTest extends TestCase
{
    public function test_shows_when_staff_are_watching_the_account()
    {
        config(['services.line.staffed' => true]);

        $this->assertStringContainsString('กรณีที่นัดฟังผลทาง LINE', LineMessagingService::askResultsLine());
        $this->assertStringContainsString('was to receive results via LINE', LineMessagingService::askResultsLineEn());
    }

    public function test_stays_silent_on_an_unattended_account()
    {
        config(['services.line.staffed' => false]);

        $this->assertSame('', LineMessagingService::askResultsLine());
        $this->assertSame('', LineMessagingService::askResultsLineEn());
    }

    /** @dataProvider diseaseWords */
    public function test_never_names_a_disease($word)
    {
        config(['services.line.staffed' => true]);

        foreach ([LineMessagingService::askResultsLine(),
                  LineMessagingService::askResultsLineEn(),
                  LineMessagingService::askResultsLine('พรุ่งนี้'),
                  LineMessagingService::askResultsLineEn('tomorrow')] as $text) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text);
        }
    }

    public static function diseaseWords(): array
    {
        return [['HIV'], ['เอชไอวี'], ['ซิฟิลิส'], ['syphilis'], ['เอดส์'], ['STI']];
    }

    public function test_scopes_the_invitation_to_results_appointments_only()
    {
        config(['services.line.staffed' => true]);

        // ต้องมีคำที่จำกัดขอบเขต ไม่ใช่ชวนให้ทุกคนถามผลทาง LINE
        $this->assertStringContainsString('กรณี', LineMessagingService::askResultsLine());
        $this->assertStringContainsString('If your appointment', LineMessagingService::askResultsLineEn());
    }

    public function test_names_the_appointment_day_when_it_is_known()
    {
        config(['services.line.staffed' => true]);

        $this->assertStringContainsString(
            'สามารถสอบถามได้ในวันพรุ่งนี้ค่ะ',
            LineMessagingService::askResultsLine('พรุ่งนี้')
        );
        $this->assertStringContainsString(
            'you may ask us tomorrow.',
            LineMessagingService::askResultsLineEn('tomorrow')
        );
    }

    /**
     * เตือนซ้ำเช้าวันนัดส่ง 'วันนี้' เข้ามา ห้ามกลายเป็น "ในวันวันนี้"
     * และห้ามกลายเป็น "พรุ่งนี้" ซึ่งจะเลื่อนคนไข้ออกไปอีกวันทั้งที่นัดคือวันนี้
     */
    public function test_says_today_not_tomorrow_on_the_appointment_morning()
    {
        config(['services.line.staffed' => true]);

        $th = LineMessagingService::askResultsLine('วันนี้');

        $this->assertStringContainsString('สามารถสอบถามได้ในวันนี้ค่ะ', $th);
        $this->assertStringNotContainsString('วันวันนี้', $th);
        $this->assertStringNotContainsString('พรุ่งนี้', $th);
    }

    /**
     * นัดที่ข้ามวันหยุดยาวไม่ได้อยู่ "พรุ่งนี้" — ต้องบอกวันจริง
     * ถ้าฮาร์ดโค้ดคำว่าพรุ่งนี้ไว้ จะกลายเป็นชวนให้ถามในวันที่คลินิกปิด
     */
    public function test_uses_the_real_day_when_the_appointment_is_not_tomorrow()
    {
        config(['services.line.staffed' => true]);

        $th = LineMessagingService::askResultsLine('วันจันทร์ ที่ 05/01/2570');

        $this->assertStringContainsString('05/01/2570', $th);
        $this->assertStringNotContainsString('พรุ่งนี้', $th);
        // ตัวเลขห้ามชนคำว่า ค่ะ
        $this->assertStringNotContainsString('2570ค่ะ', $th);
    }

    /**
     * ตอนคนไข้เพิ่งกดติดตามหรือเพิ่งผูกบัญชี ยังไม่รู้ว่ามีนัดวันไหน
     * ห้ามหลุดคำว่าพรุ่งนี้/วันนี้ออกไป เพราะไม่มีอะไรมารองรับ
     */
    public function test_stays_vague_when_there_is_no_appointment_day()
    {
        config(['services.line.staffed' => true]);

        $th = LineMessagingService::askResultsLine();

        $this->assertStringContainsString('กรณีที่นัดฟังผลทาง LINE', $th);
        $this->assertStringNotContainsString('พรุ่งนี้', $th);
        $this->assertStringNotContainsString('วันนี้', $th);
    }

    public function test_stays_silent_on_an_unattended_account_even_with_a_day()
    {
        config(['services.line.staffed' => false]);

        $this->assertSame('', LineMessagingService::askResultsLine('พรุ่งนี้'));
        $this->assertSame('', LineMessagingService::askResultsLineEn('tomorrow'));
    }

    public function test_starts_with_a_blank_line_so_it_never_runs_into_the_previous_sentence()
    {
        config(['services.line.staffed' => true]);

        foreach ([LineMessagingService::askResultsLine(),
                  LineMessagingService::askResultsLineEn(),
                  LineMessagingService::askResultsLine('พรุ่งนี้'),
                  LineMessagingService::askResultsLineEn('tomorrow')] as $text) {
            $this->assertStringStartsWith("\n\n", $text);
        }
    }
}
