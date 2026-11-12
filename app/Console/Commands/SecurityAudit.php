<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * ตรวจช่องโหว่ของไลบรารีที่ระบบใช้ (composer audit) แล้วเก็บผลไว้ให้หน้า Dashboard งานพัฒนาอ่าน (กลุ่ม IT ขอ 2569-10-19)
 * ผลเก็บที่ storage/app/security-audit.json · ต้องต่ออินเทอร์เน็ต (ดึงฐานข้อมูลช่องโหว่ของ Packagist)
 * ไม่แก้อะไรในระบบ — อัปเดตไลบรารีจริงทำในระบบทดสอบก่อนเสมอ
 */
class SecurityAudit extends Command
{
    protected $signature = 'clinic:security-audit';
    protected $description = 'ตรวจช่องโหว่ไลบรารี (composer audit) แล้วเก็บผลให้หน้า Dashboard งานพัฒนา';

    public function handle(): int
    {
        $p = Process::fromShellCommandline('composer audit --locked --format=json', base_path(), null, null, 240);
        $p->run();
        $json = json_decode($p->getOutput(), true);
        if (! is_array($json)) {
            $this->error('composer audit อ่านผลไม่ได้: ' . trim($p->getErrorOutput()));
            return 1;
        }

        $packages = [];
        foreach ((array) ($json['advisories'] ?? []) as $name => $list) {
            $list = array_values(array_filter((array) $list, 'is_array'));
            if (! $list) { continue; }
            $packages[] = [
                'name'     => $name,
                'count'    => count($list),
                'severity' => array_values(array_unique(array_filter(array_map(fn ($a) => $a['severity'] ?? null, $list)))),
                'items'    => array_map(fn ($a) => [
                    'cve'      => $a['cve'] ?? null,
                    'title'    => $a['title'] ?? '',
                    'affected' => $a['affectedVersions'] ?? '',
                    'link'     => $a['link'] ?? null,
                ], $list),
            ];
        }
        usort($packages, fn ($a, $b) => $b['count'] <=> $a['count']);

        $out = [
            'run_at'   => now()->toDateTimeString(),
            'env'      => app()->environment(),
            'php'      => PHP_VERSION,
            'laravel'  => app()->version(),
            'total'    => array_sum(array_column($packages, 'count')),
            'packages' => $packages,
        ];
        file_put_contents(storage_path('app/security-audit.json'), json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->info('พบ ' . $out['total'] . ' รายการใน ' . count($packages) . ' ไลบรารี — บันทึกแล้ว');
        return 0;
    }
}
