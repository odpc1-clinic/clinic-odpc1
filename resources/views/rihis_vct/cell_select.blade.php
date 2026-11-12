@php
    // สีของช่อง: รอกรอก (เหลือง) มาก่อน ถ้าไม่ใช่ค่อยดูว่าเป็น 0 / "ไม่ได้" ไหม (ชมพู)
    $__cls = in_array($f, $pending, true)
        ? 'rv-pending'
        : ((string) $r->$f === '0' ? 'rv-zero' : '');
@endphp
<td>
    <select class="form-select form-select-sm {{ $__cls }}" data-f="{{ $f }}">
        <option value=""></option>
        @foreach($opts as $k => $v)
            <option value="{{ $k }}" @if((string) $r->$f !== '' && (string) $r->$f === (string) $k) selected @endif>{{ $k }}: {{ $v }}</option>
        @endforeach
    </select>
</td>
