<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * ผูกข้อมูลคำขอเข้ากับทุกบรรทัดใน log อัตโนมัติ (Laravel Context, 2569-10-20)
 *
 * เดิม error ใน log ไม่บอกว่าใครกด จากหน้าไหน visit ไหน — ต้องเดาจากเวลาแล้วไปถามพยาบาล
 * ตอนนี้ทุกบรรทัดติด: เลขคำขอ ผู้ใช้ (id) วิธี+path visit/patient id จาก URL และ IP
 * เก็บแค่ "รหัส" ไม่ใส่ชื่อคนไข้หรือค่าที่กรอก (PDPA) · เลขคำขอส่งกลับใน header X-Request-Id ให้จับคู่กับ log ได้
 */
class AddLogContext
{
    public function handle(Request $request, Closure $next)
    {
        $rid = substr(Str::uuid()->toString(), 0, 8);
        Context::add('request_id', $rid);
        Context::add('url', $request->method() . ' /' . ltrim($request->path(), '/'));
        Context::add('ip', $request->ip());
        if ($id = $request->user()?->id) { Context::add('user_id', $id); }

        // id ในเส้นทาง เช่น /visit/{id}/edit, /patient/{patient}/edit — ตั้งชื่อพารามิเตอร์ไว้ไม่เหมือนกัน จึงอ่านจาก path
        if (preg_match('#^(visit|patient|lab|tb|finance|pharmacy)/(\d+)#', $request->path(), $m)) {
            Context::add($m[1] . '_id', (int) $m[2]);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $rid);
        return $response;
    }
}
