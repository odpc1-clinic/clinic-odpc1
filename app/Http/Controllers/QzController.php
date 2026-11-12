<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * QZ Tray: ให้เว็บสั่งพิมพ์เข้าเครื่องพิมพ์ที่ระบุได้แบบเงียบ (ไม่เด้ง dialog)
 * ต้องมีไฟล์ 2 ตัวใน storage/app/qz/ :
 *   - private-key.pem        (คีย์ส่วนตัว — ห้ามเผยแพร่)
 *   - digital-certificate.txt (ใบรับรอง public — เอาไปติดตั้งใน QZ Tray)
 * สร้างด้วย openssl (ดูคู่มือใน docs/qz-tray-setup.md)
 */
class QzController extends Controller
{
    private function keyDir(): string
    {
        return storage_path('app/qz');
    }

    // ส่งใบรับรอง public ให้ QZ (setCertificatePromise)
    public function certificate()
    {
        $path = $this->keyDir() . '/digital-certificate.txt';
        if (!is_file($path)) {
            // ยังไม่ตั้งค่า -> ให้ QZ ใช้โหมด unsigned (เด้ง Allow ทุกครั้ง) ไปก่อน
            return response('', 200)->header('Content-Type', 'text/plain');
        }
        return response(file_get_contents($path), 200)->header('Content-Type', 'text/plain');
    }

    // เซ็นข้อมูลที่ QZ ส่งมาด้วย private key (setSignaturePromise)
    public function sign(Request $request)
    {
        $toSign = (string) $request->query('request', '');
        $keyPath = $this->keyDir() . '/private-key.pem';

        if ($toSign === '' || !is_file($keyPath)) {
            return response('', 200)->header('Content-Type', 'text/plain');
        }

        $pkey = openssl_pkey_get_private(file_get_contents($keyPath));
        if (!$pkey) {
            return response('', 500);
        }

        // QZ Tray 2.1+ ใช้ SHA512 ; ถ้าเวอร์ชันเก่ากว่าให้เปลี่ยนเป็น OPENSSL_ALGO_SHA1
        openssl_sign($toSign, $signature, $pkey, OPENSSL_ALGO_SHA512);

        return response(base64_encode($signature), 200)->header('Content-Type', 'text/plain');
    }
}
