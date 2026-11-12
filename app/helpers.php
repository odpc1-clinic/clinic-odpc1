<?php

if (!function_exists('DateThaiShortMonth')) {
    function DateThaiShortMonth($month)
    {
        $thMonths = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
        return $thMonths[$month - 1] ?? '';
    }
}
