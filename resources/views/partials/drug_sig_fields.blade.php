{{-- วิธีใช้ยาแบบเลือก (Sig) หนึ่งรายการ — ใช้ในหน้าสั่งยาและหน้าแก้ไขยา (2569-09-29)
     ตัวแปร: $prefix = ชื่อ input เช่น "sig[3]" หรือ "sig" · $sig = ค่าเดิม (array)
     ตัวจัดการ JS อยู่ใน partials/drug_sig_js (ผูกที่ document) --}}
@php
    $__s = (array) ($sig ?? []);
    $__v = fn ($k) => (string) ($__s[$k] ?? '');
@endphp
<div class="js-rx-sig d-flex flex-wrap align-items-center gap-1">
    <span class="small text-muted me-1"><i class="fa fa-prescription-bottle-medical"></i> วิธีใช้</span>
    <select name="{{ $prefix }}[route]" class="form-select form-select-sm w-auto">
        <option value="">— วิธี —</option>
        @foreach(\App\Support\DrugSig::$route as $__c => $__l)
            <option value="{{ $__c }}" {{ $__v('route') === $__c ? 'selected' : '' }}>{{ $__l }}</option>
        @endforeach
    </select>
    <span class="small">ครั้งละ</span>
    <input type="text" inputmode="decimal" name="{{ $prefix }}[qty]" value="{{ $__v('qty') }}"
           class="form-control form-control-sm" style="width:4rem;" placeholder="1">
    <select name="{{ $prefix }}[unit]" class="form-select form-select-sm w-auto">
        <option value="">—</option>
        @foreach(\App\Support\DrugSig::$unit as $__u)
            <option value="{{ $__u }}" {{ $__v('unit') === $__u ? 'selected' : '' }}>{{ $__u }}</option>
        @endforeach
    </select>
    <select name="{{ $prefix }}[freq]" class="form-select form-select-sm w-auto">
        <option value="">— ความถี่ —</option>
        @foreach(\App\Support\DrugSig::$freq as $__c => [$__l, $__n])
            <option value="{{ $__c }}" {{ $__v('freq') === $__c ? 'selected' : '' }}>{{ $__l }}</option>
        @endforeach
    </select>
    <select name="{{ $prefix }}[timing]" class="form-select form-select-sm w-auto">
        <option value="">— เวลา —</option>
        @foreach(\App\Support\DrugSig::$timing as $__c => $__l)
            <option value="{{ $__c }}" {{ $__v('timing') === $__c ? 'selected' : '' }}>{{ $__l }}</option>
        @endforeach
    </select>
    @include('partials.drug_sig_period', ['prefix' => $prefix, 'sig' => $__s])
    <span class="small">นาน</span>
    <input type="text" inputmode="numeric" name="{{ $prefix }}[days]" value="{{ $__v('days') }}"
           class="form-control form-control-sm" style="width:3.8rem;" placeholder="7">
    <span class="small">วัน</span>
    <div class="w-100 small js-rx-sig-text" style="color:#0f766e;">{{ \App\Support\DrugSig::text(\App\Support\DrugSig::clean($__s)) }}</div>
</div>
