<?php

namespace App\Services;

/**
 * กุญแจถอดรหัสข้อมูลที่คนไข้กรอกในฟอร์ม LINE
 *
 * ทำไม: ฟอร์มต้องวางบนเว็บสาธารณะ และข้อมูลพักที่ Google ก่อนเครื่องคลินิกมาดึง
 * มือถือคนไข้จึงเข้ารหัสด้วยกุญแจสาธารณะก่อนส่ง Google เห็นแต่ก้อนที่อ่านไม่ออก
 * กุญแจลับอยู่ที่เครื่องคลินิกเท่านั้น — ตอนย้าย server ต้องคัดลอกไฟล์นี้ไปด้วย
 * ไม่งั้นคำขอที่ค้างอยู่ใน Google จะถอดไม่ได้
 *
 * รูปแบบ: AES-256-GCM สำหรับเนื้อหา + RSA-OAEP ห่อกุญแจ AES
 * ใช้ OAEP แบบ SHA-1 เพราะ openssl_private_decrypt ของ PHP < 8.5 รองรับแค่นั้น (OAEP+SHA-1 ยังปลอดภัย)
 */
class LineLinkCrypto
{
    public static function keyPath(): string
    {
        return storage_path('app/private/line-link/private.pem');
    }

    public static function exists(): bool
    {
        return is_file(self::keyPath());
    }

    /** สร้างคู่กุญแจครั้งแรก — มีอยู่แล้วไม่ทับ */
    public static function generate(): void
    {
        if (self::exists()) {
            return;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if (! $key || ! openssl_pkey_export($key, $pem)) {
            throw new \RuntimeException('สร้างกุญแจไม่สำเร็จ: ' . openssl_error_string());
        }

        @mkdir(dirname(self::keyPath()), 0700, true);
        file_put_contents(self::keyPath(), $pem);
        @chmod(self::keyPath(), 0600);
    }

    /** กุญแจสาธารณะแบบ SPKI base64 — ฝังในหน้าฟอร์ม */
    public static function publicSpki(): string
    {
        $details = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents(self::keyPath())));
        $pem = $details['key'];

        return preg_replace('/-----[^-]+-----|\s/', '', $pem);
    }

    /**
     * @param  array{k:string,iv:string,d:string}  $box
     */
    public static function decrypt(array $box): ?array
    {
        $priv = openssl_pkey_get_private(@file_get_contents(self::keyPath()));
        $wrapped = base64_decode($box['k'] ?? '', true);
        $iv      = base64_decode($box['iv'] ?? '', true);
        $data    = base64_decode($box['d'] ?? '', true);

        if (! $priv || ! $wrapped || ! $iv || $data === false || strlen($data) < 17) {
            return null;
        }

        if (! openssl_private_decrypt($wrapped, $aesKey, $priv, OPENSSL_PKCS1_OAEP_PADDING)) {
            return null;
        }

        // WebCrypto ต่อ tag 16 ไบต์ไว้ท้ายข้อความ
        $tag = substr($data, -16);
        $cipher = substr($data, 0, -16);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);

        $json = $plain === false ? null : json_decode($plain, true);

        return is_array($json) ? $json : null;
    }
}
