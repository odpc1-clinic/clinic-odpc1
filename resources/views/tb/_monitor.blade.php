{{-- แท็บ "ติดตาม" ของบัตร TB 01 (2569-10-05) — กล่องเปล่า + สไตล์ เนื้อในอยู่ใน partial ที่สลับในที่ได้หลังบันทึก
     #tb-monitor = ตารางติดตามการรักษา · #tb-monitor-extra = ตรวจสายตา + คัดกรองซึมเศร้า
     ไม่มีฟอร์มในกล่องเหล่านี้ (popup อยู่ท้ายหน้า tb/_monitor_modals) --}}
<style>
    #tb-monitor h6.fw-bold, #tb-monitor-extra h6.fw-bold {
        font-size: 1.3rem; color: #7c2d12; background: #fff7ed;
        border-left: 6px solid #d97706; border-radius: .4rem;
        padding: .45rem .8rem; margin-bottom: .75rem !important; display: block;
    }
    #tb-monitor h6.fw-bold > i:first-child, #tb-monitor-extra h6.fw-bold > i:first-child { color: #d97706; }

    .tb-mon-wrap { max-height: 75vh; }
    .tb-mon-table { font-size: .85rem; }
    .tb-mon-table thead th { background: #f8fafc; white-space: nowrap; font-weight: 600; position: sticky; top: 0; z-index: 2; }
    .tb-mon-table .tb-mon-first { position: sticky; left: 0; background: #fff; z-index: 1; min-width: 10rem; max-width: 14rem; }
    .tb-mon-table thead .tb-mon-first { z-index: 3; background: #f8fafc; }
    .tb-mon-table th.tb-mon-now { background: #fde68a; }
    .tb-mon-table td.tb-mon-nowcol { background: #fffbeb; }
    .tb-mon-table th.tb-mon-extcol { color: #6b7280; font-style: italic; }
    .tb-mon-date { font-size: .7rem; color: #6b7280; font-weight: 400; }

    .tb-mon-cell { display: inline-block; min-width: 2.4rem; border: 1px solid transparent; border-radius: .35rem; padding: .1rem .3rem;
                   background: transparent; font-size: .78rem; line-height: 1.25; cursor: pointer; white-space: nowrap; }
    .tb-mon-cell:hover { filter: brightness(.95); outline: 1px solid #94a3b8; }
    .tb-mon-done     { background: #dcfce7; color: #166534; border-color: #86efac; }
    .tb-mon-extra    { background: #f0fdf4; border-style: dashed; }
    .tb-mon-due      { background: #fef9c3; color: #854d0e; border-color: #facc15; font-weight: 700; }
    .tb-mon-overdue  { background: #fee2e2; color: #991b1b; border-color: #fca5a5; font-weight: 700; }
    .tb-mon-upcoming { color: #64748b; }
    .tb-mon-optional { color: #64748b; font-style: italic; border-color: #e2e8f0; }
    .tb-mon-cond     { color: #6d28d9; border-color: #ddd6fe; }
    .tb-mon-pos  { display: inline-block; background: #dc2626; color: #fff; border-radius: .25rem; padding: 0 .25rem; font-size: .68rem; font-weight: 700; }
    .tb-mon-wait { display: inline-block; background: #f59e0b; color: #fff; border-radius: .25rem; padding: 0 .25rem; font-size: .68rem; }

    /* กล่องเตือนในกล่อง "สั่งตรวจครั้งนี้" (tb/_monitor_due) */
    .tb-mon-duebox { background: #fffbeb; border: 1px solid #fcd34d; border-left: 5px solid #d97706; border-radius: .5rem; padding: .5rem .75rem; }
    .tb-mon-duebox .tb-mon-chip { display: inline-block; background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; border-radius: 1rem;
                               padding: 0 .55rem; margin: .1rem .15rem; font-size: .85rem; }
    .tb-mon-duebox .tb-mon-chip-late { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
    /* ใน legend ใช้คลาสเดียวกับช่องจริง แต่ไม่ต้องเหมือนปุ่ม */
    .tb-mon-legend .tb-mon-cell { cursor: default; }
    .tb-mon-legend .tb-mon-cell:hover { filter: none; outline: none; }
</style>

<div class="card border-0 shadow-sm mb-3" id="tb-monitor">
    <div class="card-body" id="tb-monitor-body" data-html-url="{{ route('tb.monitor.html', $reg->id) }}">
        @include('tb._monitor_grid', ['reg' => $reg])
    </div>
</div>

{{-- ตรวจสายตา + ซึมเศร้า: บัตร LTBI ไม่ใช้ (คลินิกขอ 2569-10-27) --}}
@unless($reg->isLtbi())
<div class="mb-3" id="tb-monitor-extra">
    <div id="tb-monitor-extra-body">
        @include('tb._monitor_extra', ['reg' => $reg])
    </div>
</div>
@endunless
