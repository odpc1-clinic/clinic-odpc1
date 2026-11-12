<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// สัญญาณรีเฟรชจอ (App\Events\ClinicSignal) — เจ้าหน้าที่ที่ล็อกอินแล้วฟังได้ทุกคน (ข้อมูลในสัญญาณเป็นแค่รหัส ไม่มีชื่อ/ผล)
Broadcast::channel('clinic', fn ($user) => (bool) $user);

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
