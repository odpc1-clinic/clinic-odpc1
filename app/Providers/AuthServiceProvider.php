<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // หน้า /pulse (สุขภาพระบบ) — เฉพาะ Admin เหมือน /dev-dashboard
        \Illuminate\Support\Facades\Gate::define('viewPulse', fn ($user) => $user->hasRole('Admin'));
    }
}
