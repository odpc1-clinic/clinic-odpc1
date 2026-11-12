<?php

namespace App;

use DateTime;

class Utillity
{
    /** ชื่อเดือนไทย ใช้ร่วมกันทั้งระบบ — index 1-12 ตรงเลขเดือน (index 0 ว่าง) */
    public const TH_MONTH_SHORT = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
                                   'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    public const TH_MONTH_FULL  = ['', 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                                   'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

    public static function th2dbDate($str)
    {
        if ($str) {
            $date = DateTime::createFromFormat('d/m/Y', $str);
            return  $date->format('Y-m-d');
        }
    }

    public static function db2thDate($str)
    {
        if ($str) {
            $date = DateTime::createFromFormat('Y-m-d', $str);
            return  $date->format('d/m/Y');
        }
    }

    public static function BE2AD($str)
    {
        if ($str) {
            $date = explode('-',$str);
            $date[0] = $date[0] - 543;
            return   implode('-',$date);
        }
    }

    public static function AD2BE($str)
    {
        if ($str) {
            $date = explode('-',$str);
            $date[0] = $date[0] + 543;
            return   implode('-',$date);
        }
    }

    public static function age2BirthBE($age)
    {
        if ($age) {
            return  date("Y") - $age + 543;;
        }
    }

}
