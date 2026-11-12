<?php

namespace App\Http\Controllers;

use App\Models\ExternalLabTest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * จัดการแคตตาล็อกรายการตรวจแล็บภายนอก (N Health ฯลฯ)
 *
 * ก่อนหน้านี้แคตตาล็อกอยู่ในไฟล์ seeder อย่างเดียว การเพิ่มรายการหรือเติมราคา
 * จึงต้องแก้โค้ดแล้วรัน seeder ทุกครั้ง พยาบาลทำเองไม่ได้ (พี่กานถาม 2569-08-28)
 *
 * seeder ยังอยู่และยังใช้ได้ — เป็นชุดตั้งต้นตอนติดตั้งใหม่
 * ของที่แก้ในหน้านี้จะถูก seeder เขียนทับได้ถ้ารันซ้ำ (updateOrCreate ตาม vendor+code)
 * จึงเตือนไว้บนหน้าจอ ไม่ให้เข้าใจผิดว่าแก้แล้วหายเอง
 */
class ExternalLabTestController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:Pharmacy|Admin');
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $rows = ExternalLabTest::query()
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($w) use ($q) {
                    $w->where('code', 'LIKE', "%{$q}%")
                      ->orWhere('name', 'LIKE', "%{$q}%");
                });
            })
            ->orderBy('code')
            ->get();

        return view('external_lab_test.index', [
            'rows'    => $rows,
            'q'       => $q,
            'noPrice' => ExternalLabTest::whereNull('price')->count(),
            'total'   => ExternalLabTest::count(),
        ]);
    }

    /**
     * บันทึกทีเดียวทั้งหน้า — พยาบาลมักเติมราคาหลายรายการรวดเดียวหลังได้ใบเสนอราคา
     * ถ้าให้กดบันทึกทีละแถวจะช้ากว่ามาก
     */
    public function save(Request $request)
    {
        $rows = (array) $request->input('rows', []);

        $changed = 0;
        DB::transaction(function () use ($rows, &$changed) {
            foreach ($rows as $id => $data) {
                $t = ExternalLabTest::find((int) $id);
                if (! $t) { continue; }

                $price = trim((string) ($data['price'] ?? ''));

                $new = [
                    // ชื่อแล็บแก้ได้ — รายการที่ยังไม่รู้ว่าส่งที่ไหน (เช่น NAT2/WGS/LAMP ของวัณโรค) มาเติมทีหลัง
                    'vendor'     => trim((string) ($data['vendor'] ?? $t->vendor)) ?: $t->vendor,
                    'name'       => trim((string) ($data['name'] ?? $t->name)) ?: $t->name,
                    'specimen'   => trim((string) ($data['specimen'] ?? '')) ?: null,
                    'turnaround' => trim((string) ($data['turnaround'] ?? '')) ?: null,
                    // เว้นว่าง = ยังไม่รู้ราคา ไม่ใช่ราคา 0 — ต้องเก็บเป็น null
                    // ไม่งั้นหน้าสั่งตรวจจะโชว์ "ราคาตามใบเสนอราคา 0.00" ซึ่งผิด
                    'price'      => $price === '' ? null : (float) $price,
                    'active'     => ! empty($data['active']),
                ];

                if (array_diff_assoc(array_map('strval', $new), array_map('strval', $t->only(array_keys($new))))) {
                    $changed++;
                }
                // เพิ่งรู้ว่าส่งแล็บไหน — ใบที่สั่งไปตอนยัง "ยังไม่ระบุแล็บ" ได้ชื่อแล็บตามไปด้วย
                // (แล็บที่ระบุไว้แล้วไม่ตามแก้ ใบเก่าต้องคงแล็บที่ส่งจริงตอนนั้น)
                if ($t->vendor === 'ยังไม่ระบุแล็บ' && $new['vendor'] !== $t->vendor) {
                    \App\Models\VisitExternalLab::where('external_lab_test_id', $t->id)
                        ->where('vendor', 'ยังไม่ระบุแล็บ')->update(['vendor' => $new['vendor']]);
                }
                $t->fill($new)->save();
            }
        });

        return back()->with('success', "บันทึกแล้ว {$changed} รายการ");
    }

    /** เพิ่มรายการใหม่ที่ยังไม่มีในแคตตาล็อก */
    public function store(Request $request)
    {
        $data = $request->validate([
            'vendor' => 'required|string|max:255',
            'code'   => 'required|string|max:255',
            'name'   => 'required|string|max:255',
            'price'  => 'nullable|numeric|min:0',
        ], [
            'code.required' => 'ต้องใส่รหัสรายการตรวจ',
            'name.required' => 'ต้องใส่ชื่อรายการตรวจ',
        ]);

        $exists = ExternalLabTest::where('vendor', $data['vendor'])->where('code', $data['code'])->first();
        if ($exists) {
            return back()->with('error', "รหัส {$data['code']} มีอยู่แล้ว — {$exists->name}");
        }

        ExternalLabTest::create([
            'vendor'     => $data['vendor'],
            'code'       => $data['code'],
            'name'       => $data['name'],
            'specimen'   => trim((string) $request->input('specimen')) ?: null,
            'turnaround' => trim((string) $request->input('turnaround')) ?: null,
            'price'      => $request->input('price') === null || $request->input('price') === ''
                                ? null : (float) $request->input('price'),
            'active'     => true,
        ]);

        return back()->with('success', "เพิ่ม {$data['code']} {$data['name']} แล้ว");
    }
}
