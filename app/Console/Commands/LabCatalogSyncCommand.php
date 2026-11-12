<?php

namespace App\Console\Commands;

use App\Models\LabCatalogItem;
use App\Services\LabCatalogSync;
use Illuminate\Console\Command;

/**
 * เติมแคตตาล็อกแล็บรวม (lab_catalog_items) จากแหล่งเดิม — รันหลัง migration 2026_10_02_300000
 *
 *   php artisan lab-catalog:sync            เพิ่มเฉพาะรายการที่ยังไม่มี (ของที่ผู้ดูแลแก้ไว้ไม่ถูกทับ)
 *   php artisan lab-catalog:sync --refresh  เขียนทับทุกรายการด้วยค่าตั้งต้น (ระวัง: งานที่แก้ในหน้า /lab-catalog หาย)
 */
class LabCatalogSyncCommand extends Command
{
    protected $signature = 'lab-catalog:sync {--refresh : เขียนทับรายการที่มีอยู่ด้วยค่าตั้งต้น}';

    protected $description = 'เติมแคตตาล็อกแล็บรวมจากวิธีตรวจในคลินิก / แล็บนอก / ส่งตรวจวัณโรค';

    public function handle()
    {
        if (! LabCatalogItem::ready()) {
            $this->error('ยังไม่มีตาราง lab_catalog_items — รัน migration ก่อน');
            return 1;
        }
        $r = LabCatalogSync::run((bool) $this->option('refresh'));
        $this->info("เพิ่มใหม่ {$r['created']} · เขียนทับ {$r['updated']} · คงเดิม {$r['kept']}");

        $rows = LabCatalogItem::selectRaw('category, kind, count(*) n, sum(active) a')->groupBy('category', 'kind')->get();
        $this->table(['หมวด', 'ชนิด', 'ทั้งหมด', 'เปิดใช้'], $rows->map(fn ($x) => [
            LabCatalogItem::CATEGORIES[$x->category] ?? $x->category, $x->kind, $x->n, $x->a,
        ])->all());
        return 0;
    }
}
