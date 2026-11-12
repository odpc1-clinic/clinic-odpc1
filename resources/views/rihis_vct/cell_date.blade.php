@php
    // เก็บเป็น พ.ศ. Y-m-d แต่แสดง/กรอกเป็น ว/ด/ป เหมือนช่องวันที่อื่นในระบบ
    $__v = $r->$f;
    $__show = ($__v && preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $__v, $__m))
        ? ((int) $__m[3] . '/' . (int) $__m[2] . '/' . $__m[1]) : '';
@endphp
<td>
    <input type="text" class="form-control form-control-sm date w-md {{ in_array($f, $pending, true) ? 'rv-pending' : '' }} {{ $extra ?? '' }}"
           data-f="{{ $f }}" value="{{ $__show }}" placeholder="ว/ด/ป" autocomplete="off">
</td>
