<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Medicine extends Model
{
    use Auditable;

    // stock ถูกซิงก์อัตโนมัติจาก lot ทุกครั้งที่จ่ายยา (มี log ใน medicine_lot_movements แล้ว) จึงไม่ audit ซ้ำ
    protected static $auditExclude = ['stock'];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'medicines';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'dose', 'route', 'amount', 'price', 'stock', 'unit', 'reorder_point',
        'vaccine_intervals', 'vaccine_schedule_note', 'is_vaccine'];   // ระยะนัดวัคซีน (2569-09-30) ดู App\Services\VaccineSchedule

    public function isLowStock()
    {
        return $this->stock <= $this->reorder_point;
    }

    public function lots()
    {
        return $this->hasMany(MedicineLot::class);
    }

    public function lotMovements()
    {
        return $this->hasMany(MedicineLotMovement::class);
    }

    // จำนวนคงคลังรวมจากทุก lot
    public function totalLotStock(): int
    {
        return (int) $this->lots()->sum('quantity_remaining');
    }

    // ซิงก์ medicines.stock ให้เท่ากับผลรวมคงเหลือของทุก lot (ให้โค้ดเดิมที่อ่าน stock ยังใช้ได้)
    public function syncStockFromLots(): void
    {
        $this->stock = $this->totalLotStock();
        $this->save();
    }

    // lot ที่พร้อมเบิก เรียงแบบ FEFO (ใกล้หมดอายุก่อน; ตัวไม่มีวันหมดอายุไว้ท้าย)
    public function lotsForDispensing()
    {
        return $this->lots()
            ->where('quantity_remaining', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date asc')
            ->orderBy('received_date', 'asc')
            ->orderBy('id', 'asc');
    }

    // เบิกยาออกจากคลังแบบ FEFO (ตัดจาก lot ใกล้หมดอายุก่อน ข้ามหลาย lot ได้)
    // ไม่บล็อกถ้าของไม่พอ (ตัดเท่าที่มี) — ช่วงเริ่มต้น stock 0 ยังจ่ายยาได้ตามปกติ
    // คืน ['taken'=>ตัดได้จริง, 'shortfall'=>ขาด, 'lots'=>[['lot_id','lot_number','qty']...]]
    public function deductFromLots(int $qty, array $context = []): array
    {
        $remaining = max(0, $qty);
        $used = [];

        $lots = $this->lotsForDispensing()->lockForUpdate()->get();

        // ถ้าระบุ lot ที่ต้องการเบิกก่อน (หมอเลือกเอง) ให้เอา lot นั้นขึ้นก่อน แล้วที่เหลือค่อยไล่ FEFO ตามเดิม
        $preferredId = $context['preferred_lot_id'] ?? null;
        if ($preferredId) {
            $pref = $lots->firstWhere('id', (int) $preferredId);
            if ($pref) {
                $rest = $lots->reject(fn ($l) => (int) $l->id === (int) $preferredId);
                $lots = collect([$pref])->concat($rest);
            }
        }

        foreach ($lots as $lot) {
            if ($remaining <= 0) { break; }
            $take = (int) min($lot->quantity_remaining, $remaining);
            if ($take <= 0) { continue; }

            $lot->quantity_remaining -= $take;
            $lot->save();
            $remaining -= $take;
            $used[] = ['lot_id' => $lot->id, 'lot_number' => $lot->lot_number, 'qty' => $take];

            MedicineLotMovement::create([
                'medicine_lot_id' => $lot->id,
                'medicine_id'     => $this->id,
                'visit_id'        => $context['visit_id'] ?? null,
                'type'            => 'dispense',
                'qty'             => -$take,
                'user_id'         => auth()->id(),
                'note'            => $context['note'] ?? null,
            ]);
        }

        $this->syncStockFromLots();

        return ['taken' => $qty - $remaining, 'shortfall' => $remaining, 'lots' => $used];
    }

    // รับยาเข้าคลังเป็น lot ใหม่
    public function receiveLot(array $data): MedicineLot
    {
        $qty = (int) ($data['quantity'] ?? 0);
        $lot = $this->lots()->create([
            'lot_number'         => $data['lot_number'] ?? null,
            'received_date'      => $data['received_date'] ?? now()->toDateString(),
            'cost_price'         => $data['cost_price'] ?? 0,
            'sale_price'         => $data['sale_price'] ?? 0,
            'quantity_received'  => $qty,
            'quantity_remaining' => $qty,
            'expiry_date'        => $data['expiry_date'] ?? null,
        ]);

        MedicineLotMovement::create([
            'medicine_lot_id' => $lot->id,
            'medicine_id'     => $this->id,
            'type'            => 'receive',
            'qty'             => $qty,
            'user_id'         => auth()->id(),
            'note'            => $data['note'] ?? null,
        ]);

        $this->syncStockFromLots();

        return $lot;
    }
}
