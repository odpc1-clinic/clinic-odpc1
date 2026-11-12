{{-- แถบเตือน "เดือนนี้ต้องตรวจ" บนหัวบัตร TB 01 (2569-10-05)
     ถูกสลับด้วย outerHTML หลังบันทึกในแท็บติดตาม — ⚠️ ห้ามมี <script>
     ตัวแปร: $reg --}}
@php
    $__m = \App\Services\TbMonitor\TbMonitor::safe($reg);
    $__r = $__m ? \App\Services\TbMonitor\TbMonitor::reminderItems($__m['grid']) : null;
    $__name = function ($i, $cur) { return $i['label'] . ($i['point'] !== $cur ? ' (' . $i['pointLabel'] . ')' : ''); };
@endphp
<div id="tb-monitor-header" class="small mt-1">
    @if($__m && $reg->isActive())
        @php
            $__cur  = $__m['grid']['current'] ?? null;
            $__due  = array_map(fn ($i) => $__name($i, $__cur), $__r['due']);
            $__late = array_map(fn ($i) => $__name($i, null), $__r['late']);
            $__adv  = array_filter([$__m['vision']['advice'] ? 'ตรวจสายตา' : null, $__m['depression']['advice'] ? 'ประเมินซึมเศร้า' : null]);
        @endphp
        <a href="#tb-monitor" class="d-inline-block text-white text-decoration-none"
           style="background:rgba(0,0,0,.18); border-radius:.4rem; padding:.2rem .6rem;" title="เปิดตารางติดตามการรักษา">
            <i class="fa fa-bell me-1"></i>
            @if($__due)
                <b>{{ $__r['pointLabel'] ? 'ช่วงนี้ (' . $__r['pointLabel'] . ')' : 'ช่วงนี้' }} ต้องตรวจ:</b> {{ implode(', ', $__due) }}
            @elseif(! $__late)
                ช่วงนี้ไม่มีรายการตรวจที่ถึงกำหนด
            @endif
            @if($__late)
                {{-- หัวบัตรเหลือที่น้อย — โชว์ 3 รายการแรก ที่เหลือดูในกล่องสั่งตรวจ/ตาราง --}}
                <span class="ms-1"><b>{{ $__due ? '· ' : '' }}ค้าง:</b> {{ implode(', ', array_slice($__late, 0, 3)) }}@if(count($__late) > 3) และอีก {{ count($__late) - 3 }} รายการ@endif</span>
            @endif
            @if($__r['oldLate'])
                <span class="ms-1 opacity-75">· ค้างเก่า {{ $__r['oldLate'] }} รายการ</span>
            @endif
            @if($__adv)
                <span class="ms-1"><b>· ควร:</b> {{ implode(', ', $__adv) }}</span>
            @endif
        </a>
    @endif
</div>
