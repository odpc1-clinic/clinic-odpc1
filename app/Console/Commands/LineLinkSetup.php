<?php

namespace App\Console\Commands;

use App\Services\LineLinkCrypto;
use Illuminate\Console\Command;

/**
 * เตรียมฟอร์มผูก LINE (LIFF): สร้างกุญแจครั้งแรก แล้วประกอบหน้าฟอร์มพร้อมอัปโหลด
 * รันซ้ำได้ — กุญแจเดิมไม่ถูกทับ (ทับเมื่อไหร่ คำขอที่ค้างใน Google จะถอดไม่ได้)
 */
class LineLinkSetup extends Command
{
    protected $signature = 'line:link-setup';

    protected $description = 'สร้างกุญแจ + ประกอบหน้าฟอร์มผูก LINE (LIFF) สำหรับอัปโหลด';

    public function handle(): int
    {
        $liff  = config('services.line.link.liff_id');
        $relay = config('services.line.link.relay_url');

        if (! $liff || ! $relay) {
            $this->error('ตั้ง LINE_LIFF_ID และ LINE_LINK_RELAY_URL ใน .env ก่อน แล้ว php artisan config:cache');
            return 1;
        }

        $created = ! LineLinkCrypto::exists();
        LineLinkCrypto::generate();
        $this->info($created ? 'สร้างกุญแจใหม่แล้ว: ' . LineLinkCrypto::keyPath() : 'ใช้กุญแจเดิม');

        $oa = 'https://line.me/R/ti/p/' . rawurlencode((string) config('services.line.oa_id'));

        $html = strtr(file_get_contents(resource_path('line-link/liff.html')), [
            '__LIFF_ID__'    => $liff,
            '__RELAY_URL__'  => $relay,
            '__PUBLIC_KEY__' => LineLinkCrypto::publicSpki(),
            '__OA_URL__'     => $oa,
        ]);

        $out = storage_path('app/line-link-liff/index.html');
        @mkdir(dirname($out), 0755, true);
        file_put_contents($out, $html);

        $this->info("หน้าฟอร์มพร้อมอัปโหลด: {$out}");
        $this->line('  เอาไปวางบนเว็บสาธารณะ (https) แล้วตั้ง Endpoint URL ของ LIFF ให้ชี้ไปที่นั่น');
        $this->line('  ลิงก์ให้คนไข้กด: https://liff.line.me/' . $liff);

        return 0;
    }
}
