<?php

namespace App\Console\Commands;

use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * เติม "ประเทศปลายทาง" ให้ visit TMC เก่าที่พิมพ์ไว้เป็นข้อความ (ช่องเลือกเพิ่ม 2569-11-04)
 * ค่าเริ่มต้นแค่แสดงผล ไม่เขียน — ต้องใส่ --apply · เติมเฉพาะ visit ที่ยังไม่ได้เลือกประเทศ ไม่ทับของที่พยาบาลเลือกแล้ว
 * ข้อความเดิมไม่แตะ · เดาไม่ได้ (ภูมิภาค / ทำงานบนเรือ) ปล่อยว่างให้พยาบาลเลือกเอง
 */
class TmcDestCountriesBackfill extends Command
{
    protected $signature = 'tmc:dest-countries-backfill {--apply : เขียนลงฐานจริง}';
    protected $description = 'เดาประเทศปลายทาง TMC จากข้อความเดิม (ค่าเริ่มต้น = แสดงอย่างเดียว)';

    public function handle(): int
    {
        if (!Visit::destCountriesReady()) {
            $this->error('ยังไม่มีคอลัมน์ visits.tmc_dest_countries — รัน migration ก่อน');
            return 1;
        }
        $rows = DB::table('visits')->where('clinic', 3)
            ->whereNotNull('tmc_destinations')->where('tmc_destinations', '<>', '')
            ->where(fn ($q) => $q->whereNull('tmc_dest_countries')->orWhere('tmc_dest_countries', ''))
            ->orderBy('id')->get(['id', 'date', 'tmc_destinations']);

        $fill = []; $skip = 0;
        foreach ($rows as $r) {
            $codes = Visit::cleanDestCountries(Visit::guessDestCountries($r->tmc_destinations));
            $names = implode(', ', Visit::destCountryNames($codes));
            $this->line(sprintf('%6d  %s  %-40s => %s', $r->id, $r->date, mb_strimwidth($r->tmc_destinations, 0, 40, '…'), $codes ? $names : '(เดาไม่ได้ ข้าม)'));
            if ($codes) { $fill[$r->id] = $codes; } else { $skip++; }
        }
        $this->info(sprintf('ทั้งหมด %d visit · เติมได้ %d · เดาไม่ได้ %d', $rows->count(), count($fill), $skip));

        if (!$this->option('apply')) {
            $this->comment('ยังไม่ได้เขียน — ตรวจรายการแล้วสั่งใหม่พร้อม --apply');
            return 0;
        }
        DB::transaction(function () use ($fill) {
            foreach ($fill as $id => $codes) {
                // เงื่อนไขซ้ำกันพลาด: ระหว่างรันมีคนเลือกประเทศให้ visit นั้นแล้ว ไม่ทับ
                DB::table('visits')->where('id', $id)
                    ->where(fn ($q) => $q->whereNull('tmc_dest_countries')->orWhere('tmc_dest_countries', ''))
                    ->update(['tmc_dest_countries' => $codes]);
            }
        });
        $this->info('เขียนแล้ว ' . count($fill) . ' visit');
        return 0;
    }
}
