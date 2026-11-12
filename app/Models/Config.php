<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'configs';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'value'];

    // อ่านค่า config แบบ boolean (เช่น เปิด/ปิดโหมดบล็อกจ่ายยาเมื่อ stock ไม่พอ)
    public static function isEnabled(string $name, bool $default = false): bool
    {
        $c = static::where('name', $name)->first();
        if (!$c) { return $default; }
        return in_array(strtolower(trim((string) $c->value)), ['1', 'true', 'on', 'yes', 'enabled'], true);
    }

    public static function setValue(string $name, $value): void
    {
        static::updateOrCreate(['name' => $name], ['value' => $value]);
    }

    // อ่านค่า config แบบข้อความ (เช่น URL แบบสอบถามความพึงพอใจ)
    public static function getValue(string $name, $default = null)
    {
        $c = static::where('name', $name)->first();
        return $c ? $c->value : $default;
    }
}
