<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        // \App\Http\Middleware\TrustHosts::class,
        \App\Http\Middleware\SecurityHeaders::class,   // header ความปลอดภัย (ทุกคำขอ รวมหน้า error/ปิดปรับปรุง)
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,   // Laravel 9+ มีในตัว (เดิม fruitcake/laravel-cors)
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks::class,   // defer(): งานที่ทำหลังตอบหน้าเว็บ (เช่น ส่งสัญญาณ Reverb)
        \App\Http\Middleware\ReleaseResponseEarly::class,   // มีงาน defer รออยู่ → บอกความยาวหน้า+ปิดการเชื่อมต่อ ให้เบราว์เซอร์ไม่ต้องรอ (mod_php)
        
     
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            // \App\Http\Middleware\HttpsProtocol::class,
            \RealRashid\SweetAlert\ToSweetAlert::class,
            \App\Http\Middleware\SetActingStage::class,
            \App\Http\Middleware\PopupFrame::class,
            \App\Http\Middleware\AddLogContext::class,   // ผูก ผู้ใช้/หน้า/visit id เข้ากับทุกบรรทัดใน log
            \App\Http\Middleware\BlockUsersWithoutRole::class,   // บัญชีไม่มีบทบาท = ใช้ระบบไม่ได้ (กันบัญชีที่สมัครเอง)
            \App\Http\Middleware\ForcePasswordChange::class,     // ติดธงบังคับเปลี่ยนรหัส = ตั้งรหัสใหม่ก่อนใช้งาน (2569-11-02)
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'cache.headers' => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        // spatie/laravel-permission v6 เปลี่ยนชื่อ namespace Middlewares -> Middleware (ยกรุ่น Laravel 12, 2569-10-20)
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        // เข้าได้ถ้า login อยู่ หรือถือลิงก์ลับ ?key= (ใช้ฉายรายงานในห้องประชุม)
        'report.link' => \App\Http\Middleware\ReportPublicLink::class,
    ];
}
