<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        'line/webhook', // LINE ส่ง POST ตรงเข้ามาโดยไม่มี Laravel CSRF token ตรวจสอบตัวตนด้วย X-Line-Signature แทน
    ];
}
