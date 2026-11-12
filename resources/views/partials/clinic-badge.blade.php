{{-- ป้ายบอกคลินิกต้นทางของรายการ ($clinic = 1 STI / 2 TB / 3 TMC)
     ใช้ในหน้าที่เป็นหน่วยบริการกลาง (ห้อง Lab, ห้องยา/การเงิน) ซึ่งรับงานจากทุกคลินิก --}}
@php
    $__c = (int) ($clinic ?? 0);
    $__n = \App\Models\Visit::$ClinicShortName[$__c] ?? null;
@endphp
@if($__n)
    <span class="badge"
          style="background: {{ \App\Models\Visit::$ClinicColor[$__c] }}; font-size:.7rem; vertical-align:middle;"
          title="คนไข้จาก{{ \App\Models\Visit::$ClinicOption[$__c] }}">{{ $__n }}</span>
@endif
