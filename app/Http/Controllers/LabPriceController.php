<?php

namespace App\Http\Controllers;

use App\Models\LabPrice;
use App\Models\LabSub;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class LabPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Pharmacy|Admin');
    }

    /**
     * รายการ Lab ที่ตั้งราคาได้ = ใช้ชื่อจาก LabSub::$methodOption ตรงๆ
     * เพื่อให้ชื่อตรงกับที่หน้าคิดเงิน (PharmacyController) ใช้ค้นราคา auto-fill
     * ตัด '' (ว่าง) และ 'Other' (10) ออก เพราะเป็นรายการอิสระ ไม่มีราคากลาง
     */
    private function priceableMethods(): array
    {
        $methods = LabSub::$methodOption;
        unset($methods[''], $methods[10]);
        return $methods;
    }

    public function index()
    {
        $saved = LabPrice::pluck('price', 'name')->toArray();

        $rows = [];
        foreach ($this->priceableMethods() as $code => $name) {
            $rows[] = (object) [
                'code'  => $code,
                'name'  => $name,
                'price' => isset($saved[$name]) ? (float) $saved[$name] : null,
            ];
        }

        return view('lab_price.index', ['rows' => $rows]);
    }

    /**
     * บันทึกราคาทั้งหมดในครั้งเดียว (upsert ตามชื่อ)
     * prices[] ส่งมาเป็น name => price (ว่าง = ยังไม่ตั้งราคา -> ลบ row ทิ้ง)
     */
    public function save(Request $request)
    {
        $valid = $this->priceableMethods(); // name ที่อนุญาต
        $allowed = array_flip($valid);

        foreach ((array) $request->input('prices', []) as $name => $price) {
            if (!isset($allowed[$name])) {
                continue; // กันยิงชื่อแปลกปลอมเข้ามา
            }
            $price = trim((string) $price);

            if ($price === '') {
                // เว้นว่าง = ไม่ตั้งราคากลาง -> เอา row ออก (จะกลับไปพิมพ์มือ)
                LabPrice::where('name', $name)->delete();
                continue;
            }

            LabPrice::updateOrCreate(
                ['name' => $name],
                ['price' => (float) $price]
            );
        }

        Alert::success('บันทึกราคากลาง Lab แล้ว', '');
        return redirect()->route('lab-price.index');
    }
}
