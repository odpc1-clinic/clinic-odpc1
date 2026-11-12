<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Billing extends Model
{
    use Auditable;

    use HasFactory;

    /**
     * สร้างรายการ "ค่าตรวจแล็บนอก" ในบิลใหม่จากตาราง visit_external_labs
     * (ลบของเดิมแล้วสร้างใหม่ เหมือนแนวทางของ lab/service ที่ใช้อยู่)
     */
    public function syncExternalLabItems(): float
    {
        BillingItem::where('billing_id', $this->id)->where('item_type', 'external_lab')->delete();

        $total = 0;
        $rows = VisitExternalLab::where('visit_id', $this->visit_id)->get();

        foreach ($rows as $r) {
            $price = (float) $r->price;
            if ($price <= 0) { continue; }   // ยังไม่ได้ใส่ราคา = ยังไม่คิดเงิน

            $item = new BillingItem();
            $item->billing_id  = $this->id;
            $item->item_type   = 'external_lab';
            $item->item_id     = $r->id;
            $item->item_name   = 'Lab นอก: ' . $r->name . ($r->code ? ' (' . $r->code . ')' : '');
            $item->qty         = 1;
            $item->unit_price  = $price;
            $item->total_price = $price;
            $item->save();

            $total += $price;
        }

        return $total;
    }

    /**
     * สร้างรายการ "ค่าวัคซีน" ในบิลจากตาราง visit_vaccines (คลินิก TMC)
     *
     * นับเข็มที่คลินิกให้ในการมาครั้งนี้ (visit_id ตรง): ฉีดที่นี่ + จ่ายให้ไปทานเอง
     * จ่ายให้ไปทานเอง (อหิวาต์เข็ม 2) คิดเงินในบิลวันที่จ่าย — ราคาต่อโดส คนไข้ซื้อ 2 โดสจ่าย 2 โดส (คลินิกยืนยัน 2569-11-03)
     * ประวัติเดิมที่คนไข้ฉีดมาจากที่อื่นต้องไม่ถูกคิดเงินซ้ำ
     */
    public function syncVaccineItems(): float
    {
        BillingItem::where('billing_id', $this->id)->where('item_type', 'vaccine')->delete();

        $total = 0;
        $rows = VisitVaccine::with('medicine')
            ->where('visit_id', $this->visit_id)
            ->whereIn('source', [VisitVaccine::HERE, VisitVaccine::TAKE_HOME])
            ->get();

        foreach ($rows as $r) {
            // ราคาอ้างอิงจากแคตตาล็อกวัคซีน — ถ้ายังไม่ตั้งราคา (0) ถือว่ายังไม่คิดเงิน
            $price = (float) optional($r->medicine)->price;
            if ($price <= 0) { continue; }

            $item = new BillingItem();
            $item->billing_id  = $this->id;
            $item->item_type   = 'vaccine';
            $item->item_id     = $r->id;
            $item->item_name   = 'วัคซีน: ' . $r->name . ($r->dose_number ? ' (Dose ' . $r->dose_number . ')' : '')
                . ((int) $r->source === VisitVaccine::TAKE_HOME ? ' — จ่ายไปทานเอง' : '');
            $item->qty         = 1;
            $item->unit_price  = $price;
            $item->total_price = $price;
            $item->save();

            $total += $price;
        }

        return $total;
    }

    /**
     * คำนวณยอดรวมใหม่จาก billing_items ทั้งหมด (แหล่งความจริงเดียว)
     * ทำแบบนี้เพราะยอดถูกเขียนจากหลายหน้า (หน้าตรวจ / ห้องยา / แล็บนอก)
     * ถ้าต่างคนต่างตั้งค่า ยอดจะทับกันจนตกหล่น
     * หมายเหตุ: ค่าแล็บนอกรวมอยู่ใน total_lab เพื่อไม่ต้องแก้สูตร net_total ที่กระจายอยู่หลายที่
     */
    public function recalcTotals(): void
    {
        $sum = fn ($type) => (float) BillingItem::where('billing_id', $this->id)
            ->where('item_type', $type)->sum('total_price');

        // ค่าวัคซีนรวมไว้ใน total_medicine เพราะเป็นเวชภัณฑ์ที่เบิกจากคลังเหมือนยา
        // (และไม่ต้องแก้สูตร net_total ที่กระจายอยู่หลายที่)
        $this->total_medicine = $sum('medicine') + $sum('vaccine');
        $this->total_lab      = $sum('lab') + $sum('external_lab');
        $this->total_service  = $sum('service');

        $this->net_total = ($this->total_medicine + $this->total_lab + $this->total_service) - (float) $this->discount;
        if ($this->net_total < 0) { $this->net_total = 0; }

        $this->save();
    }

    protected $fillable = [
        'bill_no', 'visit_id', 'user_id', 'total_medicine', 'total_lab',
        'total_service', 'service_waived', 'discount', 'net_total', 'status', 'stock_deducted_at', 'remark',
    ];

    protected $casts = [
        'service_waived' => 'boolean',
    ];

    // สร้างเลขที่บิล: รหัสสถานบริการ + ปี พ.ศ. 2 หลัก + เลขรัน 7 หลัก (รีเซ็ตทุกปี) เช่น 1378369000123
    public static function generateBillNo()
    {
        $facilityCode = Config::where('name', 'facility_code')->value('value') ?? '00000';

        $yearFull = date('Y') + 543;
        if (date('m') > 9) {
            $yearFull = $yearFull + 1;
        }
        $year2 = substr($yearFull, 2, 2);

        $prefix = $facilityCode . $year2;

        $last = Billing::where('bill_no', 'like', $prefix . '%')
            ->orderBy('bill_no', 'desc')
            ->first();

        $lastSeq = 0;
        if ($last) {
            $lastSeq = (int) substr($last->bill_no, strlen($prefix));
        }

        return $prefix . sprintf('%07d', $lastSeq + 1);
    }

    public function billingItems()
{
    return $this->hasMany(BillingItem::class, 'billing_id');
}
// เพิ่มความสัมพันธ์แบบ 1 บิล มีหลายการจ่ายเงิน
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function printLogs()
    {
        return $this->hasMany(BillPrintLog::class, 'billing_id');
    }

    // ฟังก์ชันคำนวณยอดที่จ่ายมาแล้วทั้งหมด
    public function getPaidAmountAttribute()
    {
        return $this->payments()->sum('amount');
    }

    // ฟังก์ชันคำนวณยอดคงค้าง (ยอดสุทธิของบิล - ยอดที่จ่ายแล้ว)
    public function getBalanceAttribute()
    {
        $balance = $this->net_total - $this->paid_amount;
        return $balance > 0 ? $balance : 0;
    }
}
