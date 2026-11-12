<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Billing;
use App\Models\Payment;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * ตรรกะคิดเงิน/รับเงินของ visit — ใช้ร่วมกันระหว่างห้องยากับหน้าการเงิน
 *
 * แยกออกมาเป็นส่วนกลางเมื่อ 2569-09-11 ตอนย้ายงานเก็บเงินกลับไปที่ห้องยา
 * (กองคลังแจ้งว่าออกใบเสร็จจากคอมไม่ได้ ต้องเขียนจากเล่ม คนไข้จึงไม่ต้องเดินไปห้องการเงิน)
 * เหตุที่ไม่ปล่อยให้ก๊อปโค้ดไว้สองที่ เพราะสูตรราคา (RPR ฟรี / เบิก สปสช / แพ็กเกจ)
 * ถ้าแก้ไม่ครบทั้งสองที่จะคิดเงินคนไข้ไม่เท่ากันระหว่างสองหน้า
 */
trait HandlesVisitBilling
{
    /**
     * ข้อมูลที่หน้าคิดเงินต้องใช้ (lab / ค่าบริการ / แล็บนอก / ราคาที่เคยบันทึกไว้)
     * ไม่รวมรายการยา เพราะห้องยากับหน้าการเงินแสดงคนละแบบ (ห้องยาต้องเลือก lot ด้วย)
     */
    protected function billingViewData(Visit $visit): array
    {
        $labMasterPrices = \App\Models\LabPrice::pluck('price', 'name')->toArray();
        $labMethods      = \App\Models\LabSub::$methodOption;

        $savedPrices = [];
        $savedQtys   = [];
        $savedWaived = [];
        if ($visit->billings && $visit->billings->billingItems) {
            foreach ($visit->billings->billingItems as $item) {
                $savedPrices[$item->item_type . '_' . $item->item_id] = $item->unit_price;
                $savedQtys[$item->item_type . '_' . $item->item_id]   = $item->qty;
                $savedWaived[$item->item_type . '_' . $item->item_id] = (bool) $item->waived;
            }
        }

        // กฎ RPR ฟรี: คนไข้ "เคยวินิจฉัย Syphilis" (ดูจากประวัติทุก visit) + สั่ง TPHA (7) คู่กับ RPR (8)
        $freeRpr = $this->freeRprApplies($visit, ($visit->labs && $visit->labs->labSub2)
            ? $visit->labs->labSub2->pluck('method')->map(fn ($m) => (int) $m)->all()
            : []);

        // นโยบายราคา Lab:
        //   เลือด (auto ราคากลาง): Anti-HIV(6), TP syphilis(7), RPR(8), HBs Ab(14)
        //   เบิก สปสช ได้ (ติ๊ก=ฟรี): HBs Ag(11), Anti-HCV(12)
        //   ที่เหลือ Gram/Wet/KOH/Culture/PCR/Pap/Other = คลินิกคิดเอง/รวมแพ็ก -> เริ่มที่ 0
        $labs = collect();
        if ($visit->labs && $visit->labs->labSub2) {
            foreach ($visit->labs->labSub2 as $sub) {
                $method     = (int) $sub->method;
                $methodName = $labMethods[$sub->method] ?? 'ไม่ระบุรายการ';
                $master     = (float) ($labMasterPrices[$methodName] ?? 0);
                $category   = \App\Models\LabSub::billingCategory($method);

                $hasSaved = array_key_exists('lab_' . $sub->id, $savedPrices);
                $price = $hasSaved
                    ? (float) $savedPrices['lab_' . $sub->id]
                    : ($category === 'manual' ? 0.0 : $master);

                $isFree = false;
                if ($freeRpr && $method === 8) {
                    $price  = 0;
                    $isFree = true;
                }

                $labs->push((object) [
                    'id'           => $sub->id,
                    'name'         => $methodName,
                    'price'        => $price,
                    'total'        => $price,
                    'free'         => $isFree,
                    'category'     => $category,
                    'master_price' => $master,
                    'sso_claimed'  => ($category === 'sso' && $hasSaved && (float) $price === 0.0),
                    // อนุเคราะห์รายตัว (การเงินติ๊ก "ยกเว้น") — ราคายังโชว์ไว้อ้างอิง แต่ไม่คิดเงิน (2569-10-22)
                    'waived'       => (bool) ($savedWaived['lab_' . $sub->id] ?? false),
                ]);
            }
        }

        return [
            'labs'          => $labs,
            'serviceMaster' => \App\Models\ServicePrice::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            // ค่าตรวจแล็บนอก: ดูอย่างเดียว (พยาบาลกรอกราคาจากหน้าตรวจ)
            'externalLabs'  => \App\Models\VisitExternalLab::where('visit_id', $visit->id)->orderBy('id')->get(),
            'savedPrices'   => $savedPrices,
            'savedQtys'     => $savedQtys,
            'savedWaived'   => $savedWaived,
        ];
    }

    /** คนไข้เคยวินิจฉัยซิฟิลิส + ครั้งนี้สั่ง TPHA คู่กัน -> RPR ไม่คิดเงิน */
    protected function freeRprApplies(Visit $visit, array $methods): bool
    {
        $patientVisitIds = Visit::where('patient_id', $visit->patient_id)->pluck('id');
        $hasSyphilisDx   = \App\Models\Diagnosi::whereIn('visit_id', $patientVisitIds)->where('disease', 1)->exists();

        return $hasSyphilisDx && in_array(7, array_map('intval', $methods), true);
    }

    /**
     * บันทึกบิล + รายการย่อย + เงินที่รับรอบนี้ แล้วคิดยอดใหม่
     * คืนค่า Billing ที่อัปเดตแล้ว
     */
    protected function saveBillingFrom(Request $request, Visit $visit): Billing
    {
        $billing = Billing::firstOrCreate(['visit_id' => $visit->id]);

        if (!$billing->bill_no) { $billing->bill_no = Billing::generateBillNo(); }
        if (!$billing->user_id) { $billing->user_id = auth()->id(); }

        $billing->discount = $request->discount ?? 0;
        $billing->remark   = $request->remark;
        $billing->save();

        // อนุเคราะห์ค่า Lab ที่การเงินติ๊กไว้ — หน้าห้องยาไม่มีช่องนี้ ส่งมาไม่มี waive = คงค่าเดิม (ห้ามล้างทิ้ง)
        $prevLabWaived = \App\Models\BillingItem::where('billing_id', $billing->id)->where('item_type', 'lab')
            ->pluck('waived', 'item_id')->map(fn ($w) => (bool) $w)->all();

        // เคลียร์รายการเก่าก่อนสร้างใหม่ ยกเว้น external_lab (สร้างใหม่จาก visit_external_labs ตอน recalc)
        // และ vaccine (TMC) เพราะหน้านี้ไม่มีฟอร์มของมัน ถ้าลบทิ้งค่าวัคซีนจะหายจากบิล
        \App\Models\BillingItem::where('billing_id', $billing->id)
            ->whereNotIn('item_type', ['external_lab', 'vaccine'])
            ->delete();

        // ยา
        foreach ((array) $request->medicines as $visitMedicineId => $data) {
            $qty   = (float) ($data['qty'] ?? 1) ?: 1;
            $price = (float) ($data['price'] ?? 0);
            $this->addBillingItem($billing->id, 'medicine', $visitMedicineId, 'ค่ายา', $qty, $price);
        }

        // Lab — บังคับกฎ RPR ฟรีฝั่ง server กันการแก้ราคาในหน้าเว็บ
        $labSubMethods = \App\Models\LabSub::whereIn('id', array_keys((array) $request->labs))->pluck('method', 'id');
        $freeRpr = $this->freeRprApplies($visit, $labSubMethods->all());

        $anyWaived = false;
        foreach ((array) $request->labs as $labSubId => $data) {
            $price = (float) ($data['price'] ?? 0);
            if ($freeRpr && (int) ($labSubMethods[$labSubId] ?? 0) === 8) { $price = 0; }
            $waived = array_key_exists('waive', (array) $data) ? ! empty($data['waive']) : ($prevLabWaived[$labSubId] ?? false);
            if ($waived) { $anyWaived = true; }
            $this->addBillingItem($billing->id, 'lab', $labSubId, 'ค่าตรวจ Lab', 1, $price, $waived);
        }

        // ค่าบริการ — เฉพาะที่ติ๊กเลือก · ยกเว้นรายตัวได้เฉพาะที่ไม่ใช่แพ็กเกจ
        foreach ((array) $request->services as $serviceId => $data) {
            if (empty($data['use'])) { continue; }

            $svc       = \App\Models\ServicePrice::find($serviceId);
            $qty       = (float) ($data['qty'] ?? 1) ?: 1;
            $price     = (float) ($data['price'] ?? 0);
            $isPackage = $svc ? (bool) $svc->is_package : false;
            $waived    = !empty($data['waive']) && !$isPackage;
            if ($waived) { $anyWaived = true; }

            $item = $this->addBillingItem($billing->id, 'service', $serviceId,
                $data['name'] ?? ($svc->name ?? 'ค่าบริการ'), $qty, $price, $waived);
        }
        $billing->service_waived = $anyWaived;
        $billing->save();

        // คิดยอดใหม่จาก billing_items ทั้งหมด (ยา + lab ในบ้าน + แล็บนอก + ค่าบริการ)
        $billing->syncExternalLabItems();
        $billing->recalcTotals();

        // รับเงินรอบนี้ — ต้องทำ "หลัง" คิดยอดเสร็จ
        // ของเดิมบันทึกเงินก่อนสร้างรายการ ตอนนั้น net_total ยังเป็น 0 ระบบเลยมองว่าไม่มียอดค้าง
        // แล้วข้ามการบันทึกเงินไปเงียบ ๆ (บิลใหม่ทุกใบจะได้ "รับเงินแล้ว 0" ทั้งที่แคชเชียร์กรอกยอดไว้)
        // ดักจับการจ่ายเกิน: บันทึกแค่ยอดที่ยังค้าง ส่วนเกินคือเงินทอน ไม่ต้องบันทึก
        if ($request->has('amount_paid') && (float) $request->amount_paid > 0) {
            $paidBefore = Payment::where('billing_id', $billing->id)->sum('amount');
            $balanceDue = (float) $billing->net_total - (float) $paidBefore;

            if ($balanceDue > 0) {
                Payment::create([
                    'billing_id'     => $billing->id,
                    'amount'         => ((float) $request->amount_paid > $balanceDue) ? $balanceDue : (float) $request->amount_paid,
                    'payment_date'   => Carbon::now()->addYears(543)->format('Y-m-d'),
                    'payment_method' => $request->input('payment_method', 'เงินสด'),
                    'received_by'    => auth()->id(),
                    'remark'         => $request->remark,
                ]);
            }
        }

        return $billing->fresh();
    }

    private function addBillingItem($billingId, string $type, $itemId, string $name, float $qty, float $price, bool $waived = false)
    {
        $item = new \App\Models\BillingItem();
        $item->billing_id  = $billingId;
        $item->item_type   = $type;
        $item->item_id     = $itemId;
        $item->item_name   = $name;
        $item->qty         = $qty;
        $item->unit_price  = $price;
        $item->total_price = $waived ? 0 : $qty * $price;
        $item->waived      = $waived;
        $item->save();

        return $item;
    }
}
