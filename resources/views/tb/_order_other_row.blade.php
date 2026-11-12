{{-- แถว "ยาอื่น" ในกล่องสั่งยาครั้งนี้ (Para, Brufen ... ที่ไม่ใช่ยาวัณโรค) — ใช้ทั้งแถวจริงและ <template> (n = __N__)
     ตัวแปร: $n, $ot = ['name', 'qty', 'sig' => [...]] หรือค่าที่ส่งกลับมาจากฟอร์ม (old) ที่วิธีใช้อยู่ระดับเดียวกัน --}}
@php
    $__p  = 'other[' . $n . ']';
    $__sg = (array) ($ot['sig'] ?? $ot);
@endphp
<tr class="js-ord-other">
    <td>
        {{-- หน้าตรวจ ($__embed จาก tb/_order): เลือกชื่อจากคลังยา ชื่อยาวได้ (จับคู่คลังยาด้วยชื่อเต็ม) · บัตร TB 01 คงจำกัด 20 ตัวอักษรตามคีย์ในตารางขนาดยา --}}
        <input type="text" name="{{ $__p }}[name]" maxlength="{{ ! empty($__embed) ? 100 : 20 }}" class="form-control form-control-sm fw-bold js-ord-other-name" value="{{ $ot['name'] ?? '' }}"
               placeholder="{{ ! empty($__embed) ? 'เลือก/พิมพ์ชื่อยาในคลังยา' : 'ชื่อยา เช่น Para 500' }}" @if(! empty($__embed)) list="tb-ord-medlist" @endif>
        <span class="badge bg-light text-dark border mt-1" title="ไม่ใช่ยาวัณโรค — สติกเกอร์ไม่พิมพ์ข้อความยาวัณโรค">ยาอื่น</span>
        @if(! empty($__embed))<div class="small js-ord-pharm"></div>@endif
    </td>
    <td>
        <div class="input-group input-group-sm">
            <input type="text" inputmode="decimal" name="{{ $__p }}[qty]" class="form-control js-ord-qty" style="max-width:4rem;"
                   value="{{ $ot['qty'] ?? ($__sg['qty'] ?? '') }}" placeholder="1">
            <select name="{{ $__p }}[unit]" class="form-select">
                @foreach(\App\Models\TbRegistration::$sigUnit as $__u)
                    <option value="{{ $__u }}" {{ ($__sg['unit'] ?? 'เม็ด') === $__u ? 'selected' : '' }}>{{ $__u }}</option>
                @endforeach
            </select>
        </div>
    </td>
    <td>
        <select name="{{ $__p }}[freq]" class="form-select form-select-sm js-ord-freq">
            @foreach(\App\Models\TbRegistration::$sigFreq as $__c => [$__lbl, $__x])
                <option value="{{ $__c }}" {{ ($__sg['freq'] ?? 'OD') === $__c ? 'selected' : '' }}>{{ $__c }} · {{ $__lbl }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="{{ $__p }}[route]" class="form-select form-select-sm">
            @foreach(\App\Models\TbRegistration::$sigRoute as $__c => $__lbl)
                <option value="{{ $__c }}" {{ ($__sg['route'] ?? 'PO') === $__c ? 'selected' : '' }}>{{ $__c }} · {{ $__lbl }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <select name="{{ $__p }}[timing]" class="form-select form-select-sm">
            <option value="">— ไม่ระบุ —</option>
            @foreach(\App\Models\TbRegistration::$sigTiming as $__c => $__lbl)
                <option value="{{ $__c }}" {{ ($__sg['timing'] ?? '') === $__c ? 'selected' : '' }}>{{ $__lbl }}</option>
            @endforeach
        </select>
    </td>
    <td>
        <div class="d-flex flex-column gap-1">
            @include('partials.drug_sig_period', ['prefix' => $__p, 'sig' => $__sg])
        </div>
    </td>
    <td>
        {{-- ยาอื่นจ่ายเป็นจำนวน (เช่น Para 10 เม็ด) ไม่ผูกกับจำนวนวัน — ว่างไว้ = คิดจากครั้งละ × ความถี่ × วัน --}}
        <div class="input-group input-group-sm">
            <input type="text" inputmode="decimal" name="{{ $__p }}[dispense]" class="form-control js-ord-dispense" style="max-width:4.5rem;"
                   value="{{ $__sg['dispense'] ?? '' }}">
            <button type="button" class="btn btn-outline-danger js-ord-other-del" title="เอายาตัวนี้ออก">&times;</button>
        </div>
        <div class="small text-muted js-ord-total"></div>
    </td>
    @if(! empty($__unified))<td>@include('tb._order_vm_cell', ['vm' => $ot['vm'] ?? null, 'editLink' => false])</td>@endif
</tr>
