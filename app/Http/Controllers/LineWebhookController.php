<?php

namespace App\Http\Controllers;

use App\Services\LineMessagingService;
use App\Services\LineWebhookEvents;
use Illuminate\Http\Request;

/**
 * webhook ตรงจาก LINE (ตรวจลายเซ็นได้) — ตัวจัดการเหตุการณ์อยู่ที่ LineWebhookEvents
 * ใช้ร่วมกับทางที่พัก Apps Script (line:pull-webhook) ตั้งแต่ 2569-10-31
 */
class LineWebhookController extends Controller
{
    public function handle(Request $request, LineMessagingService $line, LineWebhookEvents $events)
    {
        $body = $request->getContent();

        if (!$line->verifySignature($body, $request->header('X-Line-Signature'))) {
            return response('invalid signature', 403);
        }

        foreach ((array) $request->input('events', []) as $event) {
            if (is_array($event)) {
                $events->handle($event);
            }
        }

        return response('ok');
    }
}
