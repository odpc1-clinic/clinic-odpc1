@php
    // $link = URL ของ visit ต้นทาง (ถ้ามี) — พี่อิ๋วขอให้กด HN/ชื่อ เข้าไปแก้ที่ visit ได้เลย
    $__link = $link ?? null;
@endphp
<td>
    @if($__link)
        <div class="input-group input-group-sm flex-nowrap">
            <input type="text" class="form-control form-control-sm {{ $w ?? '' }} {{ in_array($f, $pending, true) ? 'rv-pending' : '' }}"
                   data-f="{{ $f }}" value="{{ $r->$f }}" placeholder="{{ $ph ?? '' }}">
            <a href="{{ $__link }}" target="_blank" class="btn btn-outline-secondary"
               title="เปิด visit นี้เพื่อแก้ไข (แท็บใหม่)"><i class="fas fa-up-right-from-square"></i></a>
        </div>
    @else
        <input type="text" class="form-control form-control-sm {{ $w ?? '' }} {{ in_array($f, $pending, true) ? 'rv-pending' : '' }}"
               data-f="{{ $f }}" value="{{ $r->$f }}" placeholder="{{ $ph ?? '' }}">
    @endif
</td>
