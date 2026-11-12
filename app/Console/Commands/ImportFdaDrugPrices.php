<?php

namespace App\Console\Commands;

use App\Models\StandardPrice;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ดึง "ราคากลางยา" ของ อย. (บัญชีท้ายประกาศคณะกรรมการพัฒนาระบบยาแห่งชาติ) เข้าระบบ
 * ใช้เป็น "ราคาอ้างอิงสำหรับจัดซื้อ" เท่านั้น ไม่ใช่ราคาที่เก็บจากคนไข้
 * (ราคาเก็บคนไข้ต้องอิงประกาศอัตราค่าบริการของ สคร.1 ซึ่งสูงกว่าราคาจัดซื้อ 2-4 เท่า)
 *
 * ใช้: php artisan fda:import-prices            (โหลดจากเว็บ อย.)
 *      php artisan fda:import-prices --file=... (ใช้ไฟล์ที่โหลดไว้แล้ว)
 */
class ImportFdaDrugPrices extends Command
{
    protected $signature = 'fda:import-prices {--file= : ไฟล์ .xlsx ในเครื่อง (ถ้าไม่ระบุจะโหลดจากเว็บ อย.)}';

    protected $description = 'นำเข้าราคากลางยาของ อย. เป็นราคาอ้างอิง (หมวด fda_ref)';

    /** ไฟล์บัญชีแนบท้ายประกาศ (รวม VAT) บนเว็บ อย. */
    private const SOURCE_URL = 'https://ndi.fda.moph.go.th/uploads/ประกาศราคากลางลงราชกิจจาฯ 23 ม.ค. 67.xlsx';

    public function handle()
    {
        $file = $this->option('file');

        if (!$file) {
            $file = storage_path('app/fda-drug-prices.xlsx');
            $this->info('กำลังโหลดไฟล์จากเว็บ อย. ...');

            $url = preg_replace_callback('#[^\x20-\x7E]+#u', fn ($m) => rawurlencode($m[0]), self::SOURCE_URL);
            $url = str_replace(' ', '%20', $url);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 120,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);
            $data = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200 || strlen((string) $data) < 5000) {
                $this->error("โหลดไฟล์ไม่สำเร็จ (HTTP {$code}) — ลองโหลดเองแล้วใช้ --file=");
                return 1;
            }

            file_put_contents($file, $data);
            $this->info('โหลดแล้ว ' . number_format(strlen($data)) . ' bytes');
        }

        if (!file_exists($file)) {
            $this->error("ไม่พบไฟล์: {$file}");
            return 1;
        }

        $rows = IOFactory::load($file)->getSheet(0)->toArray(null, true, false, false);

        $imported = 0;
        $skipped  = 0;

        foreach ($rows as $r) {
            $seq   = trim((string) ($r[0] ?? ''));
            $name  = trim((string) ($r[1] ?? ''));
            $form  = trim((string) ($r[2] ?? ''));
            $unit  = trim((string) ($r[3] ?? ''));
            $price = trim((string) ($r[4] ?? ''));

            // ข้ามหัวตาราง/ชื่อกลุ่มยา (ไม่มีลำดับที่เป็นตัวเลข หรือไม่มีราคา)
            if (!is_numeric($seq) || $name === '' || !is_numeric(str_replace(',', '', $price))) {
                $skipped++;
                continue;
            }

            $fullName = trim($name . ($form !== '' ? ' ' . $form : ''));

            StandardPrice::updateOrCreate(
                ['category' => 'fda_ref', 'name' => $fullName],
                [
                    'unit'           => $unit ?: null,
                    'price'          => (float) str_replace(',', '', $price),
                    'price_foreign'  => null,
                    'source'         => 'ราคากลางยา อย. (บัญชีท้ายประกาศคณะกรรมการพัฒนาระบบยาแห่งชาติ) — ราคาจัดซื้อ รวม VAT',
                    'effective_date' => null,
                    'active'         => true,
                    'sort_order'     => (int) $seq,
                ]
            );
            $imported++;
        }

        $this->info("นำเข้าแล้ว {$imported} รายการ (ข้ามหัวข้อ/กลุ่ม {$skipped} แถว)");
        $this->warn('ย้ำ: ราคานี้เป็นราคาอ้างอิงสำหรับจัดซื้อ ห้ามใช้เป็นราคาเก็บคนไข้โดยตรง');

        return 0;
    }
}
