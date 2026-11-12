<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'license_number', // <--- เพิ่มบรรทัดนี้ค่ะ
        'name_en',        // ชื่อภาษาอังกฤษ — ใบผล Lab ภาษาอังกฤษ (2569-10-16)
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    public static function getName($id){
        if($id){
            $user = User::find($id);
            if($user){
                return $user->name;
            }
            
        }
        
    }

    /** รายชื่อแพทย์ (role Doctor) สำหรับช่องเลือกแพทย์ — ใบนัด · แถวนัด TB ใช้ชุดเดียวกัน */
    public static function doctors()
    {
        return static::whereHas('roles', fn ($q) => $q->where('name', 'Doctor'))->orderBy('name')->get(['id', 'name']);
    }

    /**
     * แพทย์ที่ลงนามใบรับรองแพทย์ได้ — คนที่ชื่อขึ้นต้นด้วย นายแพทย์ / แพทย์หญิง (คลินิกกำหนด 2569-10-06)
     * role Doctor ในระบบมีพยาบาลปนอยู่ (ให้สิทธิ์หน้าหมอ) ใบรับรองจึงคัดจากคำนำหน้าชื่อแทน
     */
    public static function physicians()
    {
        return static::doctors()->filter(fn ($u) => preg_match('/^\s*(นายแพทย์|แพทย์หญิง|นพ\.|พญ\.|พ\.ญ\.)/u', (string) $u->name))->values();
    }
}
