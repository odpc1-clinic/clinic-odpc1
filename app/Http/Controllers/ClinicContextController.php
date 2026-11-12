<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Illuminate\Http\Request;

/**
 * สลับคลินิกที่กำลังทำงานอยู่ (STI / TB / TMC)
 * เก็บใน session เพราะเจ้าหน้าที่คนเดียวอาจต้องสลับไปมาระหว่างวัน
 * ไม่ผูกกับ user เพื่อไม่ต้องให้แอดมินมาตั้งค่าให้ทุกคน
 */
class ClinicContextController extends Controller
{
    public function switch(Request $request, $clinic)
    {
        $clinic = (int) $clinic;

        if (array_key_exists($clinic, Visit::$ClinicOption)) {
            session(['active_clinic' => $clinic]);
        }

        // กลับหน้าเดิม แต่ตัด query ที่เป็นเลขหน้าออก เพราะจำนวนรายการเปลี่ยนไปแล้ว
        $back = url()->previous();
        $back = preg_replace('/([?&])(page|hpage|vpage)=\d+/', '$1', $back);

        return redirect($back ?: route('home'));
    }
}
