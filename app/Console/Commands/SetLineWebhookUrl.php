<?php

namespace App\Console\Commands;

use App\Services\LineMessagingService;
use Illuminate\Console\Command;

class SetLineWebhookUrl extends Command
{
    protected $signature = 'line:set-webhook {url? : Base public URL, e.g. https://xxxx.trycloudflare.com}
                            {--relay : ชี้ไปที่พัก Google Apps Script (LINE_LINK_RELAY_URL) แทน — server ดึงเหตุการณ์ไปเอง}';

    protected $description = 'อัปเดต Webhook URL ของ LINE channel ให้ชี้ไปที่ /line/webhook ของ URL สาธารณะปัจจุบัน (หรือที่พัก Apps Script ด้วย --relay)';

    public function handle(LineMessagingService $line)
    {
        // --relay: server อยู่วงในของกรม LINE เข้าไม่ถึง — ให้ LINE ส่งไปพักที่ Apps Script แล้ว line:pull-webhook ดึงเอง (2569-10-31)
        if ($this->option('relay')) {
            $webhookUrl = (string) config('services.line.link.relay_url');
            if ($webhookUrl === '') {
                $this->error('ยังไม่ได้ตั้ง LINE_LINK_RELAY_URL ใน .env');
                return 1;
            }
        } elseif ($this->argument('url')) {
            $webhookUrl = rtrim($this->argument('url'), '/') . '/line/webhook';
        } else {
            $this->error('ใส่ URL หรือใช้ --relay');
            return 1;
        }

        // Quick tunnel เพิ่งสร้างใหม่ อาจต้องรอ DNS/edge routing propagate ก่อน LINE ถึงจะยอมรับ URL นี้ (ลองซ้ำแทนพัง)
        $maxAttempts = 6;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($line->setWebhookEndpoint($webhookUrl)) {
                $this->info("ตั้ง webhook URL เป็น {$webhookUrl} สำเร็จ (ครั้งที่ {$attempt})");
                return 0;
            }

            if ($attempt < $maxAttempts) {
                sleep(5);
            }
        }

        $this->error("ตั้ง webhook URL ไม่สำเร็จหลังลอง {$maxAttempts} ครั้ง ({$webhookUrl})");
        return 1;
    }
}
