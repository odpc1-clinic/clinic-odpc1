<?php

namespace App\Http\Controllers;

use App\Models\Medicine;
use App\Models\StandardPrice;
use Illuminate\Http\Request;

/**
 * ระบบตั้งราคากลาง — ตามประกาศอัตราค่าบริการสาธารณสุขของ สคร.1
 * แยกจากราคาที่ใช้จริงหน้างาน (medicines.price) โดยตั้งใจ
 * เพราะประกาศเป็นอัตราอ้างอิงที่ออกใหม่เป็นปีๆ ส่วนหน้างานปรับได้ตามจริง
 */
class StandardPriceController extends Controller
{
    public function index(Request $request)
    {
        $category = $request->get('category');
        $keyword  = $request->get('search');

        $query = StandardPrice::query()->orderBy('category')->orderBy('sort_order');

        if ($category) {
            $query->where('category', $category);
        } else {
            // ราคาอ้างอิง อย. มี ~2,500 รายการ ถ้าปนมาจะกลบราคากลางจริงของ สคร.1
            // ต้องเลือกหมวดนั้นเองถึงจะเห็น
            $query->whereNotIn('category', StandardPrice::REFERENCE_CATEGORIES);
        }

        if ($keyword)  { $query->where('name', 'LIKE', "%{$keyword}%"); }

        $prices = $query->paginate(50)->appends($request->query());

        return view('standard-price.index', compact('prices', 'category', 'keyword'));
    }

    /** บันทึกราคาหลายรายการพร้อมกัน (แก้ inline ในตาราง) */
    public function update(Request $request)
    {
        foreach ((array) $request->input('rows', []) as $id => $fields) {
            $row = StandardPrice::find($id);
            if (!$row) { continue; }

            $row->price         = ($fields['price'] ?? '') !== '' ? (float) $fields['price'] : 0;
            $row->price_foreign = ($fields['price_foreign'] ?? '') !== '' ? (float) $fields['price_foreign'] : null;
            $row->active        = !empty($fields['active']);
            $row->save();
        }

        flash('บันทึกราคากลางแล้ว', 'success');

        return back();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category'      => ['required', 'string'],
            'name'          => ['required', 'string', 'max:255'],
            'unit'          => ['nullable', 'string', 'max:50'],
            'price'         => ['required', 'numeric', 'min:0'],
            'price_foreign' => ['nullable', 'numeric', 'min:0'],
        ]);

        StandardPrice::create($data + [
            'source'     => 'เพิ่มเองในระบบ',
            'active'     => true,
            'sort_order' => 999,
        ]);

        flash('เพิ่มรายการราคากลางแล้ว', 'success');

        return back();
    }

    /**
     * จับคู่ราคากลาง -> ราคายาในระบบ
     * ตั้งใจให้ "เสนอแล้วให้คนยืนยัน" ไม่เขียนทับเอง เพราะชื่อยาในระบบกับในประกาศ
     * ขนาดไม่ตรงกันได้ (เช่น Metronidazole 200mg ในระบบ แต่ประกาศมีแค่ 400mg)
     */
    public function matchMedicines()
    {
        $std = StandardPrice::active()->whereIn('category', ['medicine', 'vaccine'])->get();

        $rows = [];
        foreach (Medicine::orderBy('name')->get() as $m) {
            [$match, $forceReview] = StandardPrice::findForMedicine($m->name, $std);

            // ราคาอ้างอิงของ อย. (ราคาจัดซื้อ) ไว้ช่วยตัดสินใจกับยาที่ไม่มีในประกาศ สคร.1
            $base = trim(preg_replace('/\(.*?\)|\d+.*/u', '', $m->name));
            $fda  = mb_strlen($base) >= 4
                ? StandardPrice::where('category', 'fda_ref')
                    ->where('name', 'LIKE', '%' . $base . '%')
                    ->orderBy('price')->limit(3)->get()
                : collect();

            // ขนาดยาตรงกันไหม ถ้าไม่ตรง (หรือเป็นคู่ที่กำหนดให้ตรวจเสมอ) = ห้ามติ๊กให้อัตโนมัติ
            $doseSame = $match && !$forceReview
                && StandardPrice::digitsOf($m->name) === StandardPrice::digitsOf($match->name);

            $rows[] = (object) [
                'medicine'  => $m,
                'match'     => $match,
                'doseSame'  => $doseSame,
                'isAlias'   => $match && isset(StandardPrice::$aliases[$m->name]) || isset(StandardPrice::$aliasesNeedReview[$m->name]),
                'hasPrice'  => (float) $m->price > 0,
                'fda'       => $fda,
            ];
        }

        return view('standard-price.match', ['rows' => collect($rows)]);
    }

    /** ลงราคาจริงตามที่ผู้ใช้ติ๊กยืนยัน */
    public function applyMedicines(Request $request)
    {
        $applied = 0;

        foreach ((array) $request->input('apply', []) as $medicineId => $price) {
            if ($price === '' || !is_numeric($price)) { continue; }

            $med = Medicine::find($medicineId);
            if (!$med) { continue; }

            $med->price = (float) $price;
            $med->save();
            $applied++;
        }

        flash("ลงราคายาแล้ว {$applied} รายการ", $applied ? 'success' : 'warning');

        return redirect()->route('standard-price.match');
    }

    /** ดึงตัวเลขขนาดยาออกมาเทียบ เช่น "Metronidazole (400)" -> "400" */
    private function doseOf(?string $name): string
    {
        preg_match_all('/\d+(?:\.\d+)?/', (string) $name, $m);
        return implode('/', $m[0] ?? []);
    }
}
