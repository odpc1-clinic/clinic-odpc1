<?php

namespace App\Http\Controllers;

use App\Models\Icd10Code;
use Illuminate\Http\Request;

/** ค้น ICD-10 ชุดเต็มให้ตัวเลือกในหน้าเว็บ (ชุดเต็มใหญ่เกินจะส่งไปกับหน้า 2569-10-30) */
class Icd10Controller extends Controller
{
    public function search(Request $request)
    {
        $limit = max(5, min(50, (int) $request->get('limit', 30)));

        return response()->json(
            Icd10Code::search((string) $request->get('q', ''), $limit)->map->toPicker()->values()
        );
    }
}
