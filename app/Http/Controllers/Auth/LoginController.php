<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    // ผิด 5 ครั้ง (บัญชีเดียวกัน + เครื่องเดียวกัน) → ล็อก 5 นาที · เดิม 5 ครั้ง/1 นาที เดารหัสได้ ~7,000 ครั้งต่อวัน (2569-10-20)
    protected $maxAttempts = 5;
    protected $decayMinutes = 5;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        // กันไล่เดาหลายบัญชีจากเครื่องเดียว (ตัวล็อกข้างบนนับแยกบัญชี) — เผื่อเครือข่าย <CLINIC_LAN> ที่หลายเครื่องออก IP เดียวกัน
        $this->middleware('throttle:login-ip')->only('login');
    }
}
