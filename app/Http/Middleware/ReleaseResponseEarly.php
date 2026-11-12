<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ให้เบราว์เซอร์ได้หน้าเว็บครบทันที ก่อนงาน defer() จะเริ่ม (2569-10-20)
 *
 * Apache + mod_php (XAMPP) ไม่มี fastcgi_finish_request — ส่งหน้าแบบ chunked แล้วถือการเชื่อมต่อไว้จนสคริปต์จบ
 * วัดจริง: defer งาน 3 วิ → ไบต์แรกถึงใน 0.2 วิ แต่เบราว์เซอร์รอครบ 3.3 วิ (ไม่รู้ว่าหน้าจบแล้ว)
 * บอกความยาวหน้า (Content-Length) + ปิดการเชื่อมต่อ (Connection: close) เบราว์เซอร์จึงรู้ว่าได้ครบแล้ว
 * และไม่เอาการเชื่อมต่อนี้ไปส่งคำขอถัดไป (ซึ่งจะต้องรอ PHP ตัวเดิมทำงานเบื้องหลังเสร็จ)
 * ทำเฉพาะคำขอที่มีงาน defer รออยู่ — คำขออื่นเหมือนเดิมทุกอย่าง
 */
class ReleaseResponseEarly
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (app()->bound(DeferredCallbackCollection::class)
            && count(app(DeferredCallbackCollection::class)) > 0
            && ! $response instanceof StreamedResponse && ! $response instanceof BinaryFileResponse) {
            $response->headers->set('Content-Length', (string) strlen((string) $response->getContent()));
            $response->headers->set('Connection', 'close');
        }

        return $response;
    }
}
