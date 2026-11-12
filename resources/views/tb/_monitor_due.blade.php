{{-- กล่องเตือน "ช่วงนี้ต้องตรวจ" ในกล่อง "สั่งตรวจครั้งนี้" ของบัตร TB 01 (2569-10-05)
     อยู่ใน tb/_lab_order_summary ซึ่งถูกสลับด้วย innerHTML — ⚠️ ห้ามมี <script>
     ปุ่ม "สั่งแล็บที่ถึงกำหนด" = ปุ่มเปิดตัวเลือกแคตตาล็อกตัวเดิม (partials/lab_catalog_picker) + data-preset (id รายการที่ติ๊กให้ล่วงหน้า)
     ตัวแปร: $reg · $tv (visit TB วันนี้ หรือ null) --}}
@php
    $__m = \App\Services\TbMonitor\TbMonitor::safe($reg);
    $__r = $__m ? \App\Services\TbMonitor\TbMonitor::reminderItems($__m['grid']) : null;
@endphp
@if($__m && $reg->isActive() && ($__r['due'] || $__r['late'] || $__m['vision']['advice'] || $__m['depression']['advice']))
    @php $__cur = $__m['grid']['current'] ?? null; @endphp
    <div class="tb-mon-duebox mb-3">
        <div class="d-flex flex-wrap align-items-start gap-2">
            <div class="flex-grow-1">
                <div class="fw-bold" style="color:#92400e;"><i class="fa fa-bell me-1"></i>ตามตารางติดตามการรักษา
                    <a href="#tb-monitor" class="small fw-normal ms-1" style="color:#92400e;">ดูตาราง</a></div>
                @if($__r['due'])
                    <div><b>{{ $__r['pointLabel'] ? 'ช่วงนี้ (' . $__r['pointLabel'] . ')' : 'ช่วงนี้' }} ต้องตรวจ:</b>
                        @foreach($__r['due'] as $__i)<span class="tb-mon-chip">{{ $__i['label'] }}@if($__i['point'] !== $__cur) <small>({{ $__i['pointLabel'] }})</small>@endif</span>@endforeach
                    </div>
                @endif
                @if($__r['late'])
                    <div><b style="color:#991b1b;">เกินกำหนด:</b>
                        @foreach($__r['late'] as $__i)<span class="tb-mon-chip tb-mon-chip-late">{{ $__i['label'] }} <small>({{ $__i['pointLabel'] }})</small></span>@endforeach
                    </div>
                @endif
                @if($__r['oldLate'])
                    <div class="small text-muted">ค้างเก่ากว่า 45 วันอีก {{ $__r['oldLate'] }} รายการ — ดูในตาราง</div>
                @endif
                @foreach(array_filter([$__m['vision']['advice'], $__m['depression']['advice']]) as $__a)
                    <div class="small"><i class="fa fa-circle-info me-1" style="color:#b45309;"></i>{{ $__a }}</div>
                @endforeach
            </div>
            <div class="d-flex flex-column gap-1 align-items-end">
                @if($__m['presetIds'] && \App\Models\LabCatalogItem::availableFor(2))
                    @if($tv)
                        <button type="button" class="btn btn-sm btn-warning fw-bold js-lcat-open" data-bs-toggle="modal" data-bs-target="#labCatalogModal"
                                data-picker-url="{{ route('lab-catalog.picker', $tv->id) }}"
                                data-order-url="{{ route('lab-catalog.order', $tv->id) }}"
                                data-context="tb01" data-page="{{ request('page', 1) }}" data-tb-reg="{{ $reg->id }}"
                                data-preset="{{ implode(',', $__m['presetIds']) }}">
                            <i class="fa fa-flask me-1"></i> สั่งแล็บที่ถึงกำหนด ({{ count($__m['presetIds']) }})
                        </button>
                        <span class="small text-muted">ติ๊กให้ล่วงหน้า ตรวจดูก่อนกดยืนยัน</span>
                    @else
                        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="ลงทะเบียนเปิด visit ก่อน">
                            <i class="fa fa-flask me-1"></i> สั่งแล็บที่ถึงกำหนด
                        </button>
                    @endif
                @endif
                @if($__r['needsXray'])
                    <a href="{{ route('tb.xrayRequest', $reg->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-x-ray me-1"></i> ใบส่ง X-ray
                    </a>
                @endif
            </div>
        </div>
    </div>
@endif
