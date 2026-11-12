<?php

namespace App\Http\Controllers;

use App\Models\LineMessageLog;
use Illuminate\Http\Request;

class LineMessageLogController extends Controller
{
    // หน้าดูประวัติข้อความ LINE ที่ระบบส่งออกไป (audit log)
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $filterDate = $request->get('filter_date');

        $logs = LineMessageLog::with('patient')
            ->when($keyword, function ($q) use ($keyword) {
                $q->where(function ($sub) use ($keyword) {
                    $sub->whereHas('patient', function ($p) use ($keyword) {
                        $p->where('code', 'LIKE', "%{$keyword}%")
                          ->orWhere('name', 'LIKE', "%{$keyword}%")
                          ->orWhere('surname', 'LIKE', "%{$keyword}%");
                    })->orWhere('message', 'LIKE', "%{$keyword}%");
                });
            })
            ->when($filterDate, function ($q) use ($filterDate) {
                $q->whereDate('created_at', $filterDate);
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('line.logs', compact('logs', 'keyword', 'filterDate'));
    }
}
