<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Concerns\HandlesVisitBilling;
use App\Models\Visit;
use App\Models\Billing;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * ห้องการเงิน: คิดเงิน + รับชำระ + พิมพ์ใบเสร็จ + ตัดสินใจปลายทาง (ฉีดยา/กลับบ้าน)
 * แยกออกมาจาก PharmacyController เมื่อ 2569-09-03 — ยาถูกจ่าย/ตัดสต๊อกจากห้องยา (สถานะ 5) เรียบร้อยแล้วก่อนมาถึงที่นี่ (สถานะ 9)
 * ที่นี่จึงแก้ได้แค่ "ราคา" ของยา ไม่แก้ "จำนวน/lot" (ห้องยาเป็นคนกำหนดของจริงที่จ่ายไปแล้ว)
 */
class FinanceController extends Controller
{
    use HandlesVisitBilling;

    /**
     * สถานะที่ถือว่า "รับเงินเสร็จ ผ่านห้องการเงินไปแล้ว"
     *   5 = ห้องยา (ปลายทางปกติ — จ่ายเงินแล้วไปรับยาต่อ)
     *   3 = ห้องเจาะเลือด/ฉีดยา
     *   7 = ห้องตรวจ 7 (TMC) — วัคซีนฉีดในห้อง 7 เอง จ่ายเงินแล้วจึงส่งกลับไปฉีด
     *   6 = กลับบ้าน (เคสไม่มียาให้รับ)
     */
    public const DONE_STATUSES = [5, 3, 6, 7];

    // หน้าแสดงคิวผู้ป่วยห้องการเงิน (รวมทั้งที่รอคิดเงิน/รับเงิน และที่จ่ายเงินเสร็จแล้ว ไว้หน้าเดียว)
    public function index(Request $request)
    {
        $keyword = $request->get('search');
        $filterDate = $request->get('filter_date', now()->format('Y-m-d'));
        $perPage = 25;

        $buildQuery = function () use ($request, $filterDate, $keyword) {
            // ตั้งใจ "ไม่กรองตามคลินิก" เหมือนห้อง Lab — ห้องการเงินเป็นหน่วยบริการกลาง
            $query = Visit::with(['patient2', 'visitMedicines.medicine', 'billings']);

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

            return $query;
        };

        // กลุ่มที่ 1: รอคิดเงิน/รับเงิน (สถานะ 9) เรียงตามคิว ขึ้นก่อนเสมอ
        // queue เป็น varchar ถ้า orderBy ตรงๆ จะเรียงแบบตัวอักษร (1,10,11,...,2,20 ผิดลำดับ) ต้อง cast เป็นตัวเลขก่อน
        $waiting = $buildQuery()->where('status', \App\Enums\VisitStatus::Finance->value)->orderByRaw('CAST(queue AS UNSIGNED) asc')->get();

        // กลุ่มที่ 2: รับเงินเสร็จแล้ว เรียงจากล่าสุด อยู่ด้านล่าง
        $done = $buildQuery()->whereIn('status', self::DONE_STATUSES)->orderBy('updated_at', 'desc')->get();

        $merged = $waiting->concat($done);

        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage();
        $visits = new \Illuminate\Pagination\LengthAwarePaginator(
            $merged->forPage($currentPage, $perPage)->values(),
            $merged->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        return view('finance.index', compact('visits', 'filterDate'));
    }

    public function edit($id)
    {
        $visit = Visit::with(['patient2', 'visitMedicines.medicine', 'labs.labSub2', 'billings.billingItems'])->findOrFail($id);

        // ใช้สูตรคิดเงินชุดเดียวกับห้องยา (ดู HandlesVisitBilling)
        $billingData = $this->billingViewData($visit);
        $savedPrices = $billingData['savedPrices'];
        $savedQtys   = $billingData['savedQtys'];

        // ยา: หน้าการเงินแก้ได้ทั้งจำนวนที่คิดเงินและราคา แต่ไม่ยุ่งกับ lot/สต๊อก (เป็นงานห้องยา)
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
                    'unit' => $item->medicine->unit ?? '',
                ];
            });
        }

        return view('finance.edit', array_merge($billingData, compact('visit', 'medicines')));
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $visit = Visit::findOrFail($id);

            // คิดเงิน + รับเงิน ใช้สูตรชุดเดียวกับห้องยา (ดู HandlesVisitBilling)
            $billing = $this->saveBillingFrom($request, $visit);

            // อัปเดตสถานะตามปุ่มที่กด (บันทึกเฉยๆ=9 อยู่ห้องการเงินต่อ, ส่งไปรับยา=5, ฉีดยา=3, กลับบ้าน=6)
            $nextStatus = (int) $request->input('next_status', \App\Enums\VisitStatus::Finance->value);
            $visit->status = $nextStatus;
            $visit->save();

            $visit->syncCentralQueue();

            // เคสไม่มียาให้รับแล้วกดกลับบ้านจากห้องการเงินเลย -> ส่งยืนยันนัดทาง LINE (กันส่งซ้ำใน sendOnDischarge)
            if ($nextStatus === \App\Enums\VisitStatus::Home->value) {
                app(\App\Services\AppointmentLineNotifier::class)->sendOnDischargeLater($visit->fresh());
            }

            if ($nextStatus === \App\Enums\VisitStatus::Pharmacy->value) {
                $msg = 'รับเงินแล้ว ส่งคนไข้ไปรับยาที่ห้องยาเรียบร้อย';
                $redirectUrl = '/finance';
            } elseif ($nextStatus === \App\Enums\VisitStatus::Injection->value) {
                $msg = 'รับเงินแล้ว ส่งคนไข้ไปห้องฉีดยาเรียบร้อย';
                $redirectUrl = '/finance/history';
            } elseif ($nextStatus === \App\Enums\VisitStatus::Home->value) {
                $msg = 'รับเงินแล้ว คนไข้กลับบ้านเรียบร้อย';
                $redirectUrl = '/finance/history';
            } else {
                $msg = 'บันทึกข้อมูลร่างเรียบร้อยแล้ว (ยังไม่ตัดคิว)';
                $redirectUrl = '/finance';
            }

            DB::commit();

            return redirect($redirectUrl)->with('flash_message', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            Alert::error('เกิดข้อผิดพลาด', $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    public function history(Request $request)
    {
        $keyword = $request->get('search');
        $filterDate = $request->get('filter_date', now()->format('Y-m-d'));
        $perPage = 25;

        // เอาเฉพาะที่ผ่านห้องการเงินไปแล้ว (ดู self::DONE_STATUSES — รวมห้อง 7 ของ TMC ด้วย)
        $query = Visit::with(['patient2', 'visitMedicines.medicine', 'billings'])
            ->whereIn('status', self::DONE_STATUSES);

        if (!empty($filterDate)) {
            $query->whereDate('created_at', $filterDate);
        }

        if (!empty($keyword)) {
            $query->whereHas('patient2', function ($q) use ($keyword) {
                $q->where('code', 'LIKE', "%$keyword%")
                  ->orWhere('name', 'LIKE', "%$keyword%")
                  ->orWhere('surname', 'LIKE', "%$keyword%");
            });
        }

        $visits = $query->orderBy('updated_at', 'desc')->paginate($perPage);

        return view('finance.history', compact('visits', 'filterDate'));
    }

    public function print($id)
    {
        $visit = Visit::with([
            'patient2',
            'billings.billingItems',
            'visitMedicines.medicine',
        ])->findOrFail($id);

        $labMethods = [
            "" => '', 1 => '# Gram Stain', 2 => 'Wet Preparation', 3 => 'KOH', 4 => '# Culture',
            5 => 'PCR for CT/NG', 6 => '# Anti-HIV', 7 => '# TP syphilis', 8 => '# RPR',
            11 => 'HBs Ag', 12 => 'Anti-HCV', 13 => 'Pap Smear', 10 => 'Other',
        ];

        $printCount = $visit->billings ? $visit->billings->printLogs()->count() : 0;

        return view('finance.print', compact('visit', 'labMethods', 'printCount'));
    }

    // บันทึกการพิมพ์ใบเสร็จ (เรียกก่อน window.print() ทุกครั้ง เพื่อเก็บประวัติไว้ตรวจสอบ)
    public function logPrint(Request $request, $id)
    {
        $visit = Visit::with('billings')->findOrFail($id);

        if (!$visit->billings) {
            return response()->json(['message' => 'ไม่พบบิลของ visit นี้'], 404);
        }

        $printCount = $visit->billings->printLogs()->count();

        if ($printCount > 0 && !$request->filled('reason')) {
            return response()->json(['message' => 'กรุณาระบุเหตุผลที่ต้องพิมพ์ซ้ำ'], 422);
        }

        \App\Models\BillPrintLog::create([
            'billing_id' => $visit->billings->id,
            'printed_by' => auth()->id(),
            'reason'     => $printCount > 0 ? $request->input('reason') : null,
        ]);

        return response()->json(['print_count' => $printCount + 1]);
    }

    public function updateStatus(Request $request, $id)
    {
        $visit = Visit::findOrFail($id);
        $visit->status = $request->status;
        $visit->save();

        // กด "กลับบ้าน" (status 6) -> ปิดคิวกลางให้จบงานด้วย (status=completed, is_calling=0)
        $visit->syncCentralQueue();

        // เคสไม่มียาให้รับแล้วกดกลับบ้านจากห้องการเงินเลย -> ส่งยืนยันนัดทาง LINE
        if (\App\Enums\VisitStatus::Home->is($request->status)) {
            app(\App\Services\AppointmentLineNotifier::class)->sendOnDischargeLater($visit->fresh());
        }

        $msg = match ((int) $request->status) {
            5 => 'ส่งคนไข้ไปรับยาที่ห้องยาแล้ว',
            6 => 'คนไข้กลับบ้านเรียบร้อยแล้ว',
            default => 'ส่งคนไข้ไปห้องฉีดยาแล้ว',
        };

        return redirect()->back()->with('flash_message', $msg);
    }

    /**
     * เรียกคิวห้องการเงิน (ห้อง 11)
     */
    public function callQueue(Request $request, $id)
    {
        $room = 11; // ห้องการเงิน
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

        Alert::success('เรียกคิว ' . $queue->queue_number . ' (ห้องการเงิน) เรียบร้อยแล้ว');
        return redirect()->back();
    }

    public function storePayment(Request $request, $billing_id)
    {
        Payment::create([
            'billing_id'     => $billing_id,
            'amount'         => $request->amount_paid,
            'payment_date'   => $request->payment_date,
            'payment_method' => $request->payment_method,
            'received_by'    => auth()->user()->id,
            'remark'         => $request->remark,
        ]);

        return redirect()->back()->with('success', 'บันทึกการชำระเงินเรียบร้อยแล้ว');
    }
}
