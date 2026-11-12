<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests;

use App\Models\Medicine;
use Illuminate\Http\Request;

class MedicineController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Pharmacy|Admin');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $perPage = 500;   // ทั้งคลังราว 100 รายการ โหลดมาทั้งหมดแล้วกรองบนจอแบบ realtime (คลินิกขอ 2569-10-05)

        $query = Medicine::query();
        // คำค้นใน URL ไม่กรองฝั่งเซิร์ฟเวอร์แล้ว — ส่งยาทั้งหมดมา ให้ช่องค้นหากรองบนจอ (ลบคำค้นแล้วรายการกลับมาครบ)
        // เกินหน้าเดียว (500) ค่อยกลับไปกรองฝั่งเซิร์ฟเวอร์
        if (!empty($keyword) && Medicine::count() > $perPage) {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%$keyword%")   // แก้บั๊ก: เดิมค้นคอลัมน์ 'medicine' ที่ไม่มีจริง
                  ->orWhere('dose', 'LIKE', "%$keyword%")
                  ->orWhere('route', 'LIKE', "%$keyword%")
                  ->orWhere('amount', 'LIKE', "%$keyword%");
            });
        }

        $medicine = $query->orderBy('name')->paginate($perPage)->withQueryString();
        $totalMedicine = Medicine::count();

        return view('medicine.index', compact('medicine', 'keyword', 'totalMedicine'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('medicine.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function store(Request $request)
    {
        $request->validate([
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
        ]);

        $requestData = $request->all();

        Medicine::create($requestData);

        return redirect('medicine')->with('flash_message', 'Medicine added!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        $medicine = Medicine::findOrFail($id);

        return view('medicine.show', compact('medicine'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     *
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $medicine = Medicine::findOrFail($id);

        return view('medicine.edit', compact('medicine'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:0',
        ]);

        $requestData = $request->all();

        $medicine = Medicine::findOrFail($id);
        $medicine->update($requestData);

        return redirect('medicine')->with('flash_message', 'Medicine updated!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function destroy($id)
    {
        Medicine::destroy($id);

        return redirect('medicine')->with('flash_message', 'Medicine deleted!');
    }
}
