<?php

namespace App\Http\Controllers;

use App\Models\Lab;
use App\Exports\LabExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class LabExportController extends Controller
{
    public function export(Request $request)
    {
        $requestData = $request->all();
        $keyword = $request->get('search');
        $status = $request->get('status');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // เริ่มต้น query
        $query = Lab::with('labSub2');

        // ถ้าค้นหาด้วย keyword
        if (!empty($keyword)) {
            $query->where(function ($q) use ($keyword) {
                $q->where('LN', 'LIKE', "%$keyword%")
                  ->orWhereHas('patient', function ($q) use ($keyword) {
                      $q->where('name', 'LIKE', "%$keyword%")
                        ->orWhere('surname', 'LIKE', "%$keyword%")
                        ->orWhere('code', 'LIKE', "%$keyword%");
                  });
            });
        }

        // ถ้ามีการเลือกสถานะให้กรองตามสถานะนั้น
        if ($status) {
            $query->where('status', $status);
        }

        // กรองตามวันที่
        if ($startDate && $endDate) {
            $query->whereBetween('collected_date', [$startDate, $endDate]);
        }

        // ดึงข้อมูลที่กรองแล้ว
        $labData = $query->get();

        // ส่งข้อมูลที่กรองแล้วไปยัง Excel
        return Excel::download(new LabExport($labData), 'lab_data.xlsx');
    }
}

