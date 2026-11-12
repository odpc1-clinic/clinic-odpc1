<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \URL::forceScheme('https');

        Paginator::useBootstrap();

        // Pulse อ่าน CPU/แรมบน Windows ด้วย wmic ซึ่ง Windows 11 เอาออกแล้ว → ใช้ PowerShell (CIM) แทน
        if (PHP_OS_FAMILY === 'Windows' && class_exists(\Laravel\Pulse\Recorders\Servers::class)) {
            $ps = fn ($cmd) => trim((string) shell_exec('powershell -NoProfile -NonInteractive -Command "' . $cmd . '"'));
            \Laravel\Pulse\Recorders\Servers::detectCpuUsing(fn () => (int) round((float) $ps(
                '(Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average')));
            \Laravel\Pulse\Recorders\Servers::detectMemoryUsing(function () use ($ps) {
                [$total, $free] = array_map('intval', preg_split('/\s+/', $ps(
                    '$o = Get-CimInstance Win32_OperatingSystem; Write-Output $o.TotalVisibleMemorySize $o.FreePhysicalMemory')) + [0, 0]);
                return ['total' => intdiv($total, 1024), 'used' => intdiv($total - $free, 1024)];   // KB → MB
            });
        }

        View::composer('lab-sub.show', function ($view) {
        // ตรวจสอบว่ามี Visit หรือไม่
        $visit = request()->route('visit') ? Visit::find(request()->route('visit')) : null;

        // ดึงข้อมูลผู้ป่วย
        $patient = $visit ? $visit->patient : Patient::find(request('patient'));

        // ส่งข้อมูลไปยัง View
        $view->with(compact('visit', 'patient'));
    });
        
    }
}
