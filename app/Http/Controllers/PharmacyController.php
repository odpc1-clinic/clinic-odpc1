<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Concerns\HandlesVisitBilling;
use App\Models\Visit;
use App\Models\Billing;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * ห้องยา: จ่ายยา + ตัดสต๊อก + คิดเงิน + รับเงิน — เป็นด่านสุดท้ายก่อนคนไข้กลับบ้าน
 *
 * ประวัติการเปลี่ยน (อ่านก่อนแก้ลำดับห้องอีกครั้ง):
 *   ก่อน 2569-09-03  status 5 = "ห้องยา/การเงิน" รวมกันในหน้าเดียว
 *   2569-09-03       แยกเป็นห้องยา (5) กับห้องการเงิน (9) เพราะเจ้าหน้าที่ห้องยาบอกว่าดูงานแล้วงง
 *   2569-09-08       สลับลำดับเป็น การเงิน -> ห้องยา ตามที่คลินิกใช้จริง (จ่ายเงินก่อนรับยา)
 *   2569-09-11       กองคลังแจ้งว่าออกใบเสร็จจากคอมไม่ได้ ต้องเขียนจากเล่มเท่านั้น
 *                    คนไข้จึงไม่ต้องเดินไปห้องการเงิน -> ย้ายงานเก็บเงินกลับมาที่ห้องยา
 *                    หน้าห้องการเงิน (FinanceController) ยังอยู่ ไว้ให้การเงินเปิดดู/แก้บิลย้อนหลัง
 *                    แต่ไม่อยู่ในเส้นทางเดินของคนไข้แล้ว
 *
 * สูตรคิดเงินใช้ร่วมกับหน้าการเงินผ่าน HandlesVisitBilling จะได้ไม่คิดเงินคนไข้ไม่เท่ากันสองหน้า
 */
class PharmacyController extends Controller
{
    use HandlesVisitBilling;

    // หน้าแสดงคิวผู้ป่วยรอรับยา (สถานะ 5 = ห้องยา)
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $filterDate = $request->get('filter_date', now()->format('Y-m-d'));
        $perPage = 25;

        // ตั้งใจ "ไม่กรองตามคลินิก" เหมือนห้อง Lab — ห้องยาเป็นหน่วยบริการกลาง รับคนไข้จากทุกคลินิก
        $query = Visit::with(['patient2', 'visitMedicines.medicine'])->where('status', \App\Enums\VisitStatus::Pharmacy->value);

        if (!empty($filterDate)) {
            $query->whereDate('created_at', $filterDate);
        }

        if (!empty($keyword)) {
            $query->whereHas('patient2', function ($q) use ($keyword) {
                $q->where('code', 'LIKE', "%$keyword%")
                  ->orWhere('name', 'LIKE', "%$keyword%")
                  ->orWhere('surname', 'LIKE', "%$keyword%")
                  ->orWhere('id_card_number', 'LIKE', "%$keyword%");
            });
        }

        // queue เป็น varchar ถ้า orderBy ตรงๆ จะเรียงแบบตัวอักษร (1,10,11,...,2,20 ผิดลำดับ) ต้อง cast เป็นตัวเลขก่อน
        $visits = $query->orderByRaw('CAST(queue AS UNSIGNED) asc')->paginate($perPage);

        return view('pharmacy.index', compact('visits', 'filterDate'));
    }

    // หน้าจ่ายยา + คิดเงิน: เลือก lot / จำนวนที่จ่ายจริง / ราคา / ค่าตรวจ / รับเงิน ในหน้าเดียว
    public function edit($id)
    {
        $visit = Visit::with(['patient2', 'visitMedicines.medicine', 'labs.labSub2', 'billings.billingItems'])->findOrFail($id);

        $billing = $visit->billings;
        $stockAlreadyDeducted = $billing && $billing->stock_deducted_at;

        // ค่าตรวจ / ค่าบริการ / แล็บนอก / ราคาที่เคยบันทึกไว้ — ใช้สูตรเดียวกับหน้าการเงิน
        $billingData = $this->billingViewData($visit);
        $savedPrices = $billingData['savedPrices'];
        $savedQtys   = $billingData['savedQtys'];

        // ยา: ห้องยาเห็นทั้ง lot/สต๊อก (สำหรับจ่ายของ) และราคา (สำหรับคิดเงิน)
        $medicines = collect();
        if ($visit->visitMedicines) {
            $medicines = $visit->visitMedicines->map(function ($item) use ($savedPrices, $savedQtys) {
                $qty   = isset($savedQtys['medicine_' . $item->id]) ? (float) $savedQtys['medicine_' . $item->id] : ((float) $item->amount ?: 1);
                $price = (float) ($savedPrices['medicine_' . $item->id] ?? ($item->medicine->price ?? 0));

                return (object) [
                    'id' => $item->id,
                    'medicine_id' => $item->medicine_id,
                    'name' => $item->medicine->name ?? 'ไม่ทราบชื่อยา',
                    'amount' => $item->amount,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $qty * $price,
                    'stock' => $item->medicine ? $item->medicine->totalLotStock() : 0,
                    'unit' => $item->medicine->unit ?? '',
                    // lot ที่พร้อมเบิก (เรียง FEFO) ให้เภสัชกรเลือกเองได้ ถ้าไม่เลือก = อัตโนมัติ FEFO
                    'lots' => $item->medicine ? $item->medicine->lotsForDispensing()->get() : collect(),
                ];
            });
        }

        return view('pharmacy.edit', array_merge($billingData, compact('visit', 'medicines', 'stockAlreadyDeducted')));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $visit = Visit::findOrFail($id);

            // 0. รวมจำนวนยาที่ต้องหักสต๊อกต่อรายการยา (medicine_id => จำนวนรวม)
            //    key ของ $request->medicines คือ id ของ visit_medicine (รายการที่แพทย์สั่ง) ไม่ใช่ id ของ medicine เอง
            $deductions = [];
            $preferredLot = [];
            if ($request->medicines) {
                foreach ($request->medicines as $visitMedicineId => $data) {
                    $qty = (float) ($data['qty'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }
                    $visitMedicine = \App\Models\VisitMedicine::find($visitMedicineId);
                    if ($visitMedicine && $visitMedicine->medicine_id) {
                        $deductions[$visitMedicine->medicine_id] = ($deductions[$visitMedicine->medicine_id] ?? 0) + $qty;
                        if (!empty($data['lot_id'])) {
                            $preferredLot[$visitMedicine->medicine_id] = (int) $data['lot_id'];
                        }
                    }
                }
            }

            $billing = Billing::firstOrCreate(['visit_id' => $visit->id]);
            if (!$billing->bill_no) {
                $billing->bill_no = Billing::generateBillNo();
            }
            if (!$billing->user_id) {
                $billing->user_id = auth()->id();
            }
            $billing->save();

            // หักสต๊อกก็ต่อเมื่อบิลนี้ยังไม่เคยหักสต๊อกมาก่อน (กันหักซ้ำเวลาแก้ไขซ้ำ)
            $shouldDeductStock = !$billing->stock_deducted_at;

            // โหมดบล็อกเมื่อ stock ไม่พอ (เปิด/ปิดได้ที่หน้าคลังยา) — ค่าเริ่มต้นปิด (ช่วงเริ่มต้น stock 0 ยังจ่ายยาได้)
            if ($shouldDeductStock && !empty($deductions) && \App\Models\Config::isEnabled('block_dispense_on_insufficient')) {
                $insufficient = [];
                foreach ($deductions as $medicineId => $qtyNeeded) {
                    $medicine = \App\Models\Medicine::find($medicineId);
                    $available = $medicine ? $medicine->totalLotStock() : 0;
                    if ($available < $qtyNeeded) {
                        $insufficient[] = ($medicine->name ?? "ยา #$medicineId") . " (คงเหลือ {$available} " . ($medicine->unit ?? '') . ", ต้องการ {$qtyNeeded})";
                    }
                }
                if (!empty($insufficient)) {
                    DB::rollBack();
                    Alert::error('สต๊อกยาไม่พอ', 'จ่ายยาไม่ได้เพราะสต๊อกไม่พอ: ' . implode(', ', $insufficient));
                    return redirect()->back()->with('error', 'สต๊อกยาไม่พอ: ' . implode(', ', $insufficient));
                }
            }

            // คิดเงิน + รับเงินที่ห้องยาเลย (ตั้งแต่ 2569-09-11 คนไข้ไม่ต้องเดินไปห้องการเงินแล้ว)
            // ใช้สูตรเดียวกับหน้าการเงินผ่าน HandlesVisitBilling — ราคายา/ค่าตรวจ/ค่าบริการ/ส่วนลด/เงินที่รับ
            $billing = $this->saveBillingFrom($request, $visit);

            // หักสต๊อกยาแบบ FEFO (ตัดจาก lot ใกล้หมดอายุก่อน) ทำครั้งเดียวตอนยืนยันจ่ายยาจริง กันหักซ้ำด้วย stock_deducted_at
            $stockShort = [];
            if ($shouldDeductStock) {
                foreach ($deductions as $medicineId => $qtyNeeded) {
                    $medicine = \App\Models\Medicine::find($medicineId);
                    if (!$medicine) { continue; }
                    $result = $medicine->deductFromLots((int) ceil($qtyNeeded), [
                        'visit_id' => $visit->id,
                        'preferred_lot_id' => $preferredLot[$medicineId] ?? null,
                    ]);
                    if ($result['shortfall'] > 0) {
                        $stockShort[] = "{$medicine->name} (ขาด {$result['shortfall']} {$medicine->unit})";
                    }
                }
                $billing->stock_deducted_at = now();
                $billing->save();
            }

            // ห้องยาเป็นด่านสุดท้าย — ปลายทางมีแค่ค้างไว้ที่ห้องยา (5) กลับบ้าน (6) หรือส่งไปฉีดยาต่อ (3)
            $nextStatus = (int) $request->input('next_status', \App\Enums\VisitStatus::Pharmacy->value);
            $visit->status = $nextStatus;
            $visit->save();
            $visit->syncCentralQueue();

            DB::commit();

            // จบเคสแล้ว -> ส่งยืนยันนัดทาง LINE (เงื่อนไข/กันส่งซ้ำอยู่ใน sendOnDischarge ที่เดียว)
            if ($nextStatus === \App\Enums\VisitStatus::Home->value) {
                app(\App\Services\AppointmentLineNotifier::class)->sendOnDischargeLater($visit->fresh());
            }

            if (!empty($stockShort)) {
                Alert::warning('จ่ายยาแล้ว แต่สต๊อกในคลังไม่พอ', 'ตัดสต๊อกได้ไม่ครบ: ' . implode(', ', $stockShort) . ' (กรุณาเติมสต๊อก)');
            }

            $msg = match ($nextStatus) {
                3 => 'บันทึกแล้ว ส่งคนไข้ไปห้องฉีดยาเรียบร้อย',
                6 => 'จ่ายยาและรับเงินเรียบร้อย คนไข้กลับบ้านแล้ว',
                default => 'บันทึกข้อมูลร่างเรียบร้อยแล้ว (ยังไม่ตัดคิว)',
            };

            return redirect('/pharmacy')->with('flash_message', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('เกิดข้อผิดพลาด', $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    /**
     * ปุ่มลัดในหน้าคิว: ปิดเคสโดยไม่ต้องเปิดหน้าจ่ายยา (เช่น คนไข้ไม่มียาให้รับ)
     * ไม่ตัดสต๊อก เพราะไม่ได้จ่ายยาอะไรออกไป
     */
    public function updateStatus(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $visit->status = $request->status;
        $visit->save();

        // กด "กลับบ้าน" (status 6) -> ปิดคิวกลางให้จบงานด้วย (status=completed, is_calling=0)
        $visit->syncCentralQueue();

        // ห้องยาเป็นด่านสุดท้ายแล้ว จบเคสที่นี่ -> ส่งยืนยันนัดทาง LINE
        if (\App\Enums\VisitStatus::Home->is($request->status)) {
            app(\App\Services\AppointmentLineNotifier::class)->sendOnDischargeLater($visit->fresh());
        }

        $msg = \App\Enums\VisitStatus::Home->is($request->status) ? 'คนไข้กลับบ้านเรียบร้อยแล้ว' : 'ส่งคนไข้ไปห้องฉีดยาแล้ว';

        return redirect()->back()->with('flash_message', $msg);
    }

    /**
     * เรียกคิวห้องยา (ห้อง 10)
     * ย้ายแถวคิวเดิมของผู้ป่วยในระบบคิวกลางมาห้อง 10 แล้วเรียก (ทำงานเหมือน call_queue.php ของโปรแกรมคิวกลาง)
     * terminal + เสียง MP3 ฝั่งคิวกลางตั้งไว้ที่ห้อง 10 อยู่แล้ว
     */
    public function callQueue(Request $request, $id)
    {
        $room = 10; // ห้องยา
        $visit = Visit::findOrFail($id);

        $queue = null;
        if ($visit->queue_system_id) {
            $queue = \App\Models\QueueSystemQueue::find($visit->queue_system_id);
        }
        if (!$queue && $visit->queue) {
            $queue = \App\Models\QueueSystemQueue::whereDate('created_at', now()->toDateString())
                ->where('queue_number', $visit->queue)
                ->orderByDesc('id')
                ->first();
        }
        if (!$queue && $visit->queue) {
            $queue = new \App\Models\QueueSystemQueue();
            $queue->queue_number = $visit->queue;
            $queue->room_number  = $room;
            $queue->status       = 'waiting';
            $queue->call_count   = 0;
            $queue->is_calling    = 0;
            $queue->created_at    = now();
            $queue->save();
        }

        if (!$queue) {
            Alert::error('ยังไม่มีเลขคิว', 'ผู้ป่วยรายนี้ยังไม่มีเลขคิวในระบบคิวกลาง');
            return redirect()->back();
        }

        if ($visit->queue_system_id != $queue->id) {
            $visit->queue_system_id = $queue->id;
            $visit->save();
        }

        \App\Models\QueueSystemQueue::where('room_number', $room)
            ->where('is_calling', 1)
            ->update(['is_calling' => 0]);

        $queue->room_number = $room;
        $queue->status      = 'called';
        $queue->call_count  = $queue->call_count + 1;
        $queue->is_calling  = 1;
        $queue->save();

        Alert::success('เรียกคิว ' . $queue->queue_number . ' (ห้องยา) เรียบร้อยแล้ว');
        return redirect()->back();
    }
}
