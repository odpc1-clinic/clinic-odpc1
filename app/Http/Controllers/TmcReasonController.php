<?php

namespace App\Http\Controllers;

use App\Models\TmcReason;
use Illuminate\Http\Request;

/** เพิ่มตัวเลือก "สาเหตุการมาที่คลินิก" TMC เข้า master list (กันเพิ่มซ้ำ) */
class TmcReasonController extends Controller
{
    public function store(Request $request)
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return response()->json(['ok' => false, 'msg' => 'กรุณากรอกชื่อสาเหตุ'], 422);
        }
        if (mb_strlen($name) > 191) {
            $name = mb_substr($name, 0, 191);
        }

        // firstOrCreate + unique(name) = กันซ้ำ (แก้ปัญหาที่ Medivel เพิ่มซ้ำได้)
        $reason = TmcReason::firstOrCreate(['name' => $name]);

        return response()->json([
            'ok'      => true,
            'id'      => $reason->id,
            'name'    => $reason->name,
            'created' => $reason->wasRecentlyCreated,   // false = มีอยู่แล้ว
        ]);
    }
}
