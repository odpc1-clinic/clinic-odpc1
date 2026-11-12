<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * This is used by Laravel authentication to redirect users after login.
     *
     * @var string
     */
    public const HOME = '/';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            // /up ระบบยังทำงานไหม (clinic:health-watch / เครื่องมือของ IT) — อยู่นอกกลุ่ม web: ไม่เปิด session
            // ไม่สร้างไฟล์ session ใหม่ทุกนาที · ไม่ต้องล็อกอิน ตอบแค่ ok/warn/fail
            Route::get('/up', [\App\Http\Controllers\HealthController::class, 'up'])->name('health.up');

            Route::middleware('web')
                ->group(base_path('routes/web.php'));

            Route::prefix('api')
                ->middleware('api')
                ->group(base_path('routes/api.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        // หน้าเข้าระบบ: ไม่เกิน 30 ครั้ง/นาที ต่อ IP (นับทุกบัญชีรวมกัน) — ดู LoginController
        RateLimiter::for('login-ip', fn (Request $request) => Limit::perMinute(30)->by('login|' . $request->ip()));

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60);
        });
    }
}
