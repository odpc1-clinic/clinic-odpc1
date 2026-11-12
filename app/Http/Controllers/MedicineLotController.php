<?php

namespace App\Http\Controllers;

use App\Models\Config;
use App\Models\Medicine;
use App\Models\MedicineLot;
use App\Models\MedicineLotMovement;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class MedicineLotController extends Controller
{
    // หน้าคลังยา: รายการยาพร้อมยอดคงคลังรวม + เข้าไปดู/รับ lot ได้
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $medicines = Medicine::when($keyword, function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%");
            })
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        $blockMode = Config::isEnabled('block_dispense_on_insufficient');

        return view('medicine_lot.index', compact('medicines', 'keyword', 'blockMode'));
    }

    // เปิด/ปิดโหมดบล็อกการจ่ายยาเมื่อสต๊อกไม่พอ
    public function toggleBlockMode(Request $request)
    {
        $enable = $request->boolean('enable');
        Config::setValue('block_dispense_on_insufficient', $enable ? '1' : '0');

        Alert::success($enable ? 'เปิดโหมดบล็อกแล้ว' : 'ปิดโหมดบล็อกแล้ว',
            $enable ? 'ถ้าสต๊อกไม่พอจะจ่ายยาไม่ได้' : 'จ่ายยาได้แม้สต๊อกไม่พอ (ตัดเท่าที่มี)');
        return redirect()->route('medicine-lot.index');
    }

    // รายงาน: ยาหมดอายุแล้ว / ใกล้หมดอายุ / สต็อกต่ำกว่าจุดสั่งซื้อ
    public function report(Request $request)
    {
        $days = (int) $request->get('days', 90); // ช่วงเตือน "ใกล้หมดอายุ" (วัน)

        // lot ที่ยังมีของเหลือ
        $baseLots = MedicineLot::with('medicine')->where('quantity_remaining', '>', 0);

        $expired = (clone $baseLots)->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now())
            ->orderBy('expiry_date')->get();

        $expiringSoon = (clone $baseLots)->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '>=', now())
            ->whereDate('expiry_date', '<=', now()->addDays($days))
            ->orderBy('expiry_date')->get();

        // ยาสต็อกต่ำ: คงคลังรวม <= reorder_point (reorder_point > 0)
        $lowStock = Medicine::where('reorder_point', '>', 0)
            ->get()
            ->filter(fn ($m) => $m->totalLotStock() <= $m->reorder_point)
            ->sortBy('name')
            ->values();

        return view('medicine_lot.report', compact('expired', 'expiringSoon', 'lowStock', 'days'));
    }

    // ดู lot ทั้งหมดของยา 1 ตัว (เรียงแบบ FEFO) + ฟอร์มรับเข้า
    public function show($medicineId)
    {
        $medicine = Medicine::findOrFail($medicineId);
        $lots = $medicine->lots()
            ->orderByRaw('expiry_date IS NULL, expiry_date asc')
            ->orderBy('received_date', 'asc')
            ->get();

        return view('medicine_lot.show', compact('medicine', 'lots'));
    }

    // รับยาเข้าคลังเป็น lot ใหม่
    public function store(Request $request, $medicineId)
    {
        // ปฏิทินหน้าเว็บเป็น พ.ศ. (d/m/Y) แต่ DB เก็บ ค.ศ. -> แปลงก่อน validate
        $request->merge([
            'received_date' => MedicineLot::toDbDate($request->input('received_date')),
            'expiry_date'   => MedicineLot::toDbDate($request->input('expiry_date')),
        ]);

        $data = $request->validate([
            'lot_number'    => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'cost_price'    => ['nullable', 'numeric', 'min:0'],
            'sale_price'    => ['nullable', 'numeric', 'min:0'],
            'quantity'      => ['required', 'integer', 'min:1'],
            'expiry_date'   => ['nullable', 'date'],
        ]);

        $medicine = Medicine::findOrFail($medicineId);
        $medicine->receiveLot($data);

        Alert::success('รับยาเข้าคลังแล้ว', "เพิ่ม {$data['quantity']} {$medicine->unit} เข้าคลัง (คงเหลือรวม {$medicine->totalLotStock()})");
        return redirect()->route('medicine-lot.show', $medicine->id);
    }

    // แก้ไขข้อมูล lot (เลข lot, วันที่, ราคา) — ไม่แก้จำนวนตรงนี้ (ใช้ adjust แทน เพื่อเก็บ log)
    public function update(Request $request, $lotId)
    {
        $request->merge([
            'received_date' => MedicineLot::toDbDate($request->input('received_date')),
            'expiry_date'   => MedicineLot::toDbDate($request->input('expiry_date')),
        ]);

        $data = $request->validate([
            'lot_number'    => ['nullable', 'string', 'max:255'],
            'received_date' => ['nullable', 'date'],
            'cost_price'    => ['nullable', 'numeric', 'min:0'],
            'sale_price'    => ['nullable', 'numeric', 'min:0'],
            'expiry_date'   => ['nullable', 'date'],
        ]);

        $lot = MedicineLot::findOrFail($lotId);
        $lot->update($data);

        Alert::success('แก้ไขข้อมูล lot แล้ว', '');
        return redirect()->route('medicine-lot.show', $lot->medicine_id);
    }

    // ปรับยอดคงเหลือของ lot (นับสต๊อกผิด / ของเสียหาย / หมดอายุตัดออก) — บันทึก log ส่วนต่าง
    public function adjust(Request $request, $lotId)
    {
        $data = $request->validate([
            'quantity_remaining' => ['required', 'integer', 'min:0'],
            'reason'             => ['required', 'string', 'max:255'],
        ]);

        $lot = MedicineLot::findOrFail($lotId);
        $delta = (int) $data['quantity_remaining'] - (int) $lot->quantity_remaining;

        $lot->quantity_remaining = (int) $data['quantity_remaining'];
        $lot->save();

        MedicineLotMovement::create([
            'medicine_lot_id' => $lot->id,
            'medicine_id'     => $lot->medicine_id,
            'type'            => 'adjust',
            'qty'             => $delta,
            'user_id'         => auth()->id(),
            'note'            => $data['reason'],
        ]);

        $lot->medicine->syncStockFromLots();

        Alert::success('ปรับยอดคงเหลือแล้ว', "จำนวนใหม่ {$lot->quantity_remaining} (ส่วนต่าง " . ($delta >= 0 ? "+{$delta}" : $delta) . ")");
        return redirect()->route('medicine-lot.show', $lot->medicine_id);
    }

    // ลบ lot — อนุญาตเฉพาะ lot ที่ยังไม่เคยถูกเบิกจ่าย (กันประวัติการจ่ายยาหาย)
    public function destroy($lotId)
    {
        $lot = MedicineLot::findOrFail($lotId);
        $medicineId = $lot->medicine_id;

        $hasDispense = MedicineLotMovement::where('medicine_lot_id', $lot->id)
            ->where('type', 'dispense')->exists();

        if ($hasDispense) {
            Alert::error('ลบไม่ได้', 'lot นี้เคยถูกเบิกจ่ายไปแล้ว หากต้องการตัดยอดให้ใช้ "ปรับยอด" เป็น 0 แทน');
            return redirect()->route('medicine-lot.show', $medicineId);
        }

        $medicine = $lot->medicine;
        MedicineLotMovement::where('medicine_lot_id', $lot->id)->delete();
        $lot->delete();
        if ($medicine) { $medicine->syncStockFromLots(); }

        Alert::success('ลบ lot แล้ว', '');
        return redirect()->route('medicine-lot.show', $medicineId);
    }
}
