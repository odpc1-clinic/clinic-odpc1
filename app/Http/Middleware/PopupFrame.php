<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * หน้าเพิ่มยา / วินิจฉัย / Lab ฯลฯ ที่เปิดเป็น popup (iframe) จากหน้าตรวจ — 2569-10-07
 *
 * หน้าพวกนั้นบันทึกเสร็จแล้ว redirect กลับ visit.edit เสมอ ถ้าปล่อยไป iframe จะโหลดหน้าตรวจทั้งหน้าซ้อนข้างใน (หนัก ~700KB)
 * หน้าตรวจจึงแอบใส่ _popup=1 ไปกับทุกฟอร์มใน iframe (visit/_draft_popup_js) → ตรงนี้เปลี่ยน redirect นั้นเป็นหน้าเล็ก ๆ "เสร็จแล้ว"
 * ให้หน้าตรวจปิด popup แล้วโหลดตัวเองใหม่ (ข้อมูลที่พิมพ์ค้างไว้เติมกลับจากที่จำไว้)
 *
 * ถ้าหน้า "เสร็จแล้ว" ไปโผล่นอก iframe (เช่น เปิดแท็บเอง) มันจะพาไปหน้าตรวจต่อเอง ไม่ค้าง
 */
class PopupFrame
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->input('_popup') === '1' && $response instanceof RedirectResponse) {
            $target = $response->getTargetUrl();
            $path = (string) parse_url($target, PHP_URL_PATH);
            if (preg_match('#/visit/\d+/edit$#', $path)) {
                return response()->view('partials.popup_done', ['target' => $target]);
            }
        }

        return $response;
    }
}
