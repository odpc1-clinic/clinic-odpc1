<?php

namespace App\Console\Commands;

use App\Support\R506\R506Exporter;
use Illuminate\Console\Command;

/**
 * ส่งออกผู้ป่วย STI เป็นไฟล์ DBF รูปแบบรายงาน 506 (layout H05YY)
 * เพื่อ import เข้าโปรแกรม FoxPro 506 (กันคีย์ซ้ำ)
 *
 *   php artisan r506:export --from=2569-07-01 --to=2569-07-31            # เฉพาะเคสที่พยาบาลติ๊ก+ยังไม่ส่ง
 *   php artisan r506:export --from=... --to=... --all                    # ทุกเคสที่เข้าเกณฑ์ (ไว้ทดสอบ/ย้อนหลัง)
 *
 * วันที่ที่ใส่เป็น พ.ศ. (ตรงกับที่เก็บใน visits.date)
 * งานหลักให้ใช้หน้าเว็บ /r506 (ติ๊กเลือก + เตือนที่อยู่ + กันส่งซ้ำ)
 */
class R506ExportCommand extends Command
{
    protected $signature = 'r506:export
        {--from= : วันที่เริ่ม (พ.ศ. YYYY-MM-DD)}
        {--to= : วันที่สิ้นสุด (พ.ศ. YYYY-MM-DD)}
        {--all : ส่งออกทุกเคสที่เข้าเกณฑ์ (ไม่สนใจการติ๊ก/สถานะส่งแล้ว; E0 เริ่ม 1 ต่อปี)}
        {--facility=50010502 : รหัสสถานพยาบาล (HSERV/OFFICEID)}
        {--metropol=1 : ค่า METROPOL คงที่}
        {--result=3 : ค่า RESULT คงที่}
        {--data-dir= : โฟลเดอร์ตาราง lookup ของ FoxPro (default C:\\r506\\databackup)}
        {--out= : ไฟล์ปลายทาง}';

    protected $description = 'ส่งออกผู้ป่วย STI เป็นไฟล์ DBF รายงาน 506 สำหรับ import เข้าโปรแกรม FoxPro';

    public function handle(): int
    {
        $from = $this->option('from');
        $to   = $this->option('to');
        if (!$from || !$to) {
            $this->error('ต้องระบุ --from และ --to (พ.ศ. YYYY-MM-DD)');
            return self::FAILURE;
        }

        $dataDir = $this->option('data-dir') ?: R506Exporter::defaultDataDir();
        if (!is_dir($dataDir)) {
            $this->error("ไม่พบโฟลเดอร์ตาราง lookup: $dataDir");
            return self::FAILURE;
        }

        $exporter = new R506Exporter(
            $dataDir,
            trim($this->option('facility')),
            trim($this->option('metropol')),
            trim($this->option('result'))
        );

        $query = R506Exporter::baseQuery()
            ->whereBetween('v.date', [$from, $to])
            ->orderBy('v.date')->orderBy('v.id');

        if (!$this->option('all')) {
            $query->where('d.report_506', 1)->whereNull('d.report_506_sent_at');
        }

        $rows = $query->get();
        if ($rows->isEmpty()) {
            $this->warn('ไม่พบเคสที่ต้องส่ง' . ($this->option('all') ? '' : ' (ลองใช้ --all เพื่อดูทุกเคสที่เข้าเกณฑ์)'));
            return self::SUCCESS;
        }

        // --all = ดัมป์ทดสอบ เริ่ม E0 ที่ 1 ต่อปี ; ปกติ = นับต่อจากที่ส่งไปแล้วในปีนั้น
        $built = $exporter->buildRecords($rows, !$this->option('all'));
        if (!$built['records']) {
            $this->warn('ไม่มี record ให้เขียน');
            return self::SUCCESS;
        }

        $out = $this->option('out');
        if (!$out) {
            $dir = storage_path('app/r506');
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $out = $dir . DIRECTORY_SEPARATOR . R506Exporter::fileName((int) $built['year'], (int) $built['seqFirst'], (int) $built['seqLast']);
        }

        $exporter->write($out, $built['records']);

        $this->newLine();
        $this->info('เขียนไฟล์เสร็จ: ' . $out);
        $this->table(['รายการ', 'จำนวน'], [
            ['เคสที่ส่งออก', count($built['records'])],
            ['จับคู่ ADDRCODE ได้', $built['matched']],
            ['จับคู่ ADDRCODE ไม่ได้ (เว้นว่าง)', $built['noAddr']],
            ['ช่วง E0 (ปี ' . $built['year'] . ')', $built['seqFirst'] . ' - ' . $built['seqLast']],
        ]);
        $this->warn('โปรดทดสอบ import เข้าสำเนาสำรองของโปรแกรม FoxPro ก่อนใช้จริง');
        if ($this->option('all')) {
            $this->line('โหมด --all: ไม่ได้ mark ว่าส่งแล้ว (ใช้หน้าเว็บ /r506 เพื่อกันส่งซ้ำ)');
        }

        return self::SUCCESS;
    }
}
