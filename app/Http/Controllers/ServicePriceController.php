<?php

namespace App\Http\Controllers;

use App\Models\ServicePrice;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ServicePriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Pharmacy|Admin');
    }

    public function index()
    {
        $services = ServicePrice::orderBy('sort_order')->orderBy('id')->get();
        return view('service_price.index', compact('services'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        $data['sort_order'] = (int) (ServicePrice::max('sort_order') + 1);
        $data['is_active'] = true;
        $data['is_package'] = (bool) $request->input('is_package', 0);
        $data['clinics'] = $this->clinicsFrom($request);
        ServicePrice::create($data);

        Alert::success('เพิ่มค่าบริการแล้ว', '');
        return redirect()->route('service-price.index');
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        $data['is_package'] = (bool) $request->input('is_package', 0);
        // ฟอร์มรุ่นเก่า (ไม่มีช่องคลินิก) = ไม่แตะค่าเดิม
        if ($request->has('clinics_on')) { $data['clinics'] = $this->clinicsFrom($request); }
        $service = ServicePrice::findOrFail($id);
        $service->update($data);

        Alert::success('บันทึกแล้ว', '');
        return redirect()->route('service-price.index');
    }

    /**
     * คลินิกที่ใช้รายการนี้ (ติ๊กได้หลายคลินิก) — ติ๊กครบทุกคลินิกหรือไม่ติ๊กเลย = ใช้ทุกคลินิก (เก็บว่าง)
     * หน้าตรวจซ่อนรายการที่ไม่ได้ใช้กับคลินิกนั้น (ServicePrice::availableFor) — TMC/TB ขอ 2569-09-30, 2569-10-01
     */
    private function clinicsFrom(Request $request): ?string
    {
        $all = array_keys(ServicePrice::CLINICS);
        $pick = array_values(array_intersect($all, array_map('intval', (array) $request->input('clinics', []))));
        return (! $pick || count($pick) === count($all)) ? null : implode(',', $pick);
    }

    // เปิด/ปิดใช้งาน (ปิดแล้วจะไม่ขึ้นในหน้าบิล แต่ประวัติเดิมยังอยู่)
    public function toggle($id)
    {
        $service = ServicePrice::findOrFail($id);
        $service->is_active = !$service->is_active;
        $service->save();

        Alert::success($service->is_active ? 'เปิดใช้งานแล้ว' : 'ปิดใช้งานแล้ว', '');
        return redirect()->route('service-price.index');
    }

    public function destroy($id)
    {
        ServicePrice::destroy($id);
        Alert::success('ลบค่าบริการแล้ว', '');
        return redirect()->route('service-price.index');
    }
}
