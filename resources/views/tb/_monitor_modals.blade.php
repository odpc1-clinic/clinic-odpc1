{{-- popup ของแท็บ "ติดตาม" ในบัตร TB 01 (2569-10-05)
     วางท้ายหน้า หลังปิด container — อยู่นอกฟอร์มอื่นทั้งหมด จึงใช้ <form> ได้ (ไม่ซ้อน)
     ส่งด้วย fetch ใน tb/_monitor_js (ไม่โหลดหน้าใหม่ — ฟอร์มอื่นในบัตรอาจกรอกค้าง) · ตัวแปร: $reg --}}
@php $__DS = \App\Services\TbMonitor\DepressionScale::class; @endphp

{{-- ---------- ช่องในตารางติดตาม: รายละเอียด + ติ๊กมือ ---------- --}}
<div class="modal fade" id="tbMonitorTickModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content js-tbm-form" data-url="{{ route('tb.monitor.check', $reg->id) }}" autocomplete="off">
            <div class="modal-header py-2">
                <h5 class="modal-title"><span class="js-tbm-t-check"></span> · <span class="js-tbm-t-point"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="check"><input type="hidden" name="point">
                <div class="small mb-2">
                    <div>ควรตรวจราว: <b class="js-tbm-t-expected"></b> · สถานะ: <b class="js-tbm-t-status"></b></div>
                    <div class="text-muted js-tbm-t-why"></div>
                </div>
                <div class="alert alert-success py-2 small js-tbm-t-detail d-none"></div>
                <div class="js-tbm-t-form">
                    @if(\App\Models\TbMonitorCheck::ready())
                        <div class="small text-muted mb-2">ติ๊กเองเมื่อตรวจแล้วแต่ระบบไม่มีที่เก็บผล (เช่น consent) หรือไปตรวจที่อื่น</div>
                        {{-- EKG: ช่องตัวเลข QTcF แยก (คลินิกขอ 2569-10-22 แทนการพิมพ์ในหมายเหตุ) — กรอกที่หน้าตรวจ (สัญญาณชีพ) ก็ขึ้นช่องนี้เองเหมือนกัน --}}
                        <div class="js-tbm-qtcf d-none mb-2">
                            <label class="form-label small mb-0 fw-semibold">QTcF (ms)</label>
                            <input type="number" name="qtcf" class="form-control form-control-sm text-center fw-semibold" min="200" max="800" step="1" style="max-width:9rem;">
                            <div class="small js-tbm-qtcf-flag"></div>
                        </div>
                        <div class="row g-2">
                            <div class="col-5">
                                <label class="form-label small mb-0">วันที่ตรวจ</label>
                                <input type="text" name="done_date" class="form-control form-control-sm tb-date" placeholder="วว/ดด/ปปปป">
                            </div>
                            <div class="col-7">
                                <label class="form-label small mb-0">หมายเหตุ / ผล</label>
                                <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="เช่น QTcF 430 ms · ตรวจที่ รพ.นครพิงค์">
                            </div>
                        </div>
                    @else
                        <div class="alert alert-light border py-2 small mb-0"><i class="fa fa-hourglass-half me-1"></i>ติ๊กมือ <b>รอเปิดใช้</b> — ผู้ดูแลระบบยังไม่ได้สร้างตาราง</div>
                    @endif
                </div>
                <div class="alert alert-danger py-2 small mt-2 mb-0 d-none js-tbm-err"></div>
            </div>
            <div class="modal-footer py-2">
                @if(\App\Models\TbMonitorCheck::ready())
                    <button type="button" class="btn btn-outline-danger btn-sm me-auto d-none js-tbm-untick"
                            data-url="{{ route('tb.monitor.uncheck', $reg->id) }}"><i class="fa fa-times me-1"></i> เอาติ๊กออก</button>
                    <button type="submit" class="btn btn-primary btn-sm js-tbm-t-save"><i class="fa fa-check me-1"></i> ติ๊กว่าตรวจแล้ว</button>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- กำหนดตารางติดตามเอง (สูตร Individualized) 2569-10-22 ----------
     ตารางติ๊กสร้างด้วย JS (tb/_monitor_js) จาก data-* — เปลี่ยนจำนวนเดือนแล้วคอลัมน์เปลี่ยนตาม --}}
@php
    $__TMP = \App\Services\TbMonitor\TbMonitor::class;
    $__planDef = null; $__planCur = null; $__planMonths = 6;
    if (\Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'monitor_plan')) {
        $__planCur = $__TMP::planOf($reg);
        if ($__planCur) {
            $__planMonths = (int) $__planCur['months'];
        } elseif ($__planDef = $__TMP::scheduleFor($reg)) {
            // ยังไม่เคยกำหนด — เริ่มจากตารางที่ใช้อยู่ (ช่อง "ต้องตรวจ" เท่านั้น) ให้แก้ต่อ
            $__planMonths = (int) ($__planDef['treat_months'] ?? 6);
            $__planCur = ['cells' => []];
            foreach ($__planDef['checks'] as $__c) {
                foreach ($__c['points'] as $__pk => $__rule) {
                    if ($__rule !== 'req') { continue; }
                    $__pk = $__pk === 'base' ? 'd0' : $__pk;
                    if (preg_match('/^(d0|w[1-4]|m\d+|p(6|12|18|24))$/', $__pk)) { $__planCur['cells'][$__c['key']][] = $__pk; }
                }
            }
        }
    }
    $__planCat = collect(\App\Support\TbMonitorDefinitions::checkCatalogue())->map(fn ($c) => ['key' => $c['key'], 'label' => $c['label'], 'sub' => $c['sub'] ?? ''])->values();
@endphp
<div class="modal fade" id="tbMonitorPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content js-tbm-plan-form" data-url="{{ route('tb.monitor.plan', $reg->id) }}" autocomplete="off"
              data-cat="{{ json_encode($__planCat, JSON_UNESCAPED_UNICODE) }}" data-cur="{{ json_encode($__planCur['cells'] ?? (object) [], JSON_UNESCAPED_UNICODE) }}">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="fa fa-pen-ruler me-1"></i> กำหนดตารางติดตามเอง <span class="small text-muted">สูตรที่แพทย์กำหนดยาเอง</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    <label class="small fw-semibold" for="tbm-plan-months">ระยะเวลารักษา</label>
                    <select name="months" id="tbm-plan-months" class="form-select form-select-sm w-auto js-tbm-plan-months">
                        @for($__i = 1; $__i <= 24; $__i++)<option value="{{ $__i }}" {{ $__i === $__planMonths ? 'selected' : '' }}>{{ $__i }} เดือน</option>@endfor
                    </select>
                    <span class="small text-muted">ติ๊กช่องที่ต้องตรวจ · เริ่มต้นจากตารางที่ใช้อยู่ · ผลที่ลงไว้แล้วไม่หาย ระบบจับคู่ลงช่องใหม่ให้เอง</span>
                </div>
                <div class="table-responsive js-tbm-plan-grid"></div>
                <div class="alert alert-danger py-2 small mt-2 mb-0 d-none js-tbm-err"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i> บันทึกตาราง</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- ตรวจสายตา ---------- --}}
<div class="modal fade" id="tbVisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content js-tbm-form" data-url="{{ route('tb.monitor.vision', $reg->id) }}" autocomplete="off">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="fa fa-eye me-1"></i> บันทึกผลตรวจสายตา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-5">
                        <label class="form-label small mb-0">วันที่ตรวจ</label>
                        <input type="text" name="test_date" class="form-control form-control-sm tb-date" placeholder="วว/ดด/ปปปป"
                               value="{{ \App\Utillity::db2thDate(\App\Models\VisitAppointment::todayBe()) }}">
                    </div>
                    <div class="col-12"><div class="fw-semibold small mt-1">สายตา (Visual acuity)</div></div>
                    <div class="col-6">
                        <label class="form-label small mb-0">ตาขวา (R)</label>
                        <input type="text" name="va_right" class="form-control form-control-sm" list="tb-va-list" maxlength="20" placeholder="20/20 · 6/6 · CF">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-0">ตาซ้าย (L)</label>
                        <input type="text" name="va_left" class="form-control form-control-sm" list="tb-va-list" maxlength="20" placeholder="20/20 · 6/6 · CF">
                    </div>
                    <div class="col-12"><div class="fw-semibold small mt-1">ตาบอดสี (Ishihara)</div></div>
                    <div class="col-12 d-flex gap-3">
                        @foreach(\App\Models\TbVisionTest::$colorOption as $__k => $__l)
                            <label class="form-check-label"><input type="radio" name="color_result" value="{{ $__k }}" class="form-check-input"> {{ $__l }}</label>
                        @endforeach
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-0">อ่านถูก (แผ่น)</label>
                        <input type="number" name="color_plates_correct" class="form-control form-control-sm" min="0" max="99">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-0">จากทั้งหมด (แผ่น)</label>
                        <input type="number" name="color_plates_total" class="form-control form-control-sm" min="0" max="99">
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">หมายเหตุ</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255" placeholder="เช่น ใส่แว่น · ส่งพบจักษุแพทย์">
                    </div>
                </div>
                <datalist id="tb-va-list">
                    @foreach(['20/20', '20/25', '20/30', '20/40', '20/50', '20/70', '20/100', '20/200', '6/6', '6/9', '6/12', '6/18', '6/24', '6/36', '6/60', 'CF', 'HM', 'PL', 'NPL'] as $__va)
                        <option value="{{ $__va }}">
                    @endforeach
                </datalist>
                <div class="alert alert-danger py-2 small mt-2 mb-0 d-none js-tbm-err"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i> บันทึก</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </form>
    </div>
</div>

{{-- ---------- คัดกรองซึมเศร้า 2Q -> 9Q -> 8Q ---------- --}}
<div class="modal fade" id="tbDepressionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content js-tbm-form" data-url="{{ route('tb.monitor.depression', $reg->id) }}" autocomplete="off">
            <div class="modal-header py-2">
                <h5 class="modal-title"><i class="fa fa-face-frown-open me-1"></i> คัดกรองซึมเศร้า (กรมสุขภาพจิต)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label small mb-0">วันที่ประเมิน</label>
                        <input type="text" name="screen_date" class="form-control form-control-sm tb-date" placeholder="วว/ดด/ปปปป"
                               value="{{ \App\Utillity::db2thDate(\App\Models\VisitAppointment::todayBe()) }}">
                    </div>
                </div>

                <div class="fw-bold mb-1">แบบคัดกรอง 2Q</div>
                <table class="table table-sm table-bordered small align-middle">
                    @foreach($__DS::Q2 as $__k => $__q)
                        <tr>
                            <td>{{ $__k }}. {{ $__q }}</td>
                            <td class="text-nowrap" style="width:9rem;">
                                <label class="me-2"><input type="radio" name="q2[{{ $__k }}]" value="0" class="form-check-input js-dep"> ไม่มี</label>
                                <label><input type="radio" name="q2[{{ $__k }}]" value="1" class="form-check-input js-dep"> มี</label>
                            </td>
                        </tr>
                    @endforeach
                </table>

                <div class="js-dep-9q d-none">
                    <div class="fw-bold mb-1">แบบประเมิน 9Q <span class="fw-normal small text-muted">ในช่วง 2 สัปดาห์ที่ผ่านมารวมทั้งวันนี้ ท่านมีอาการเหล่านี้บ่อยแค่ไหน</span></div>
                    <table class="table table-sm table-bordered small align-middle">
                        <thead class="table-light"><tr><th></th>@foreach($__DS::Q9_CHOICES as $__v => $__l)<th class="text-center" style="width:5.5rem;">{{ $__l }} ({{ $__v }})</th>@endforeach</tr></thead>
                        @foreach($__DS::Q9 as $__k => $__q)
                            <tr>
                                <td>{{ $__k }}. {{ $__q }}</td>
                                @foreach($__DS::Q9_CHOICES as $__v => $__l)
                                    <td class="text-center"><input type="radio" name="q9[{{ $__k }}]" value="{{ $__v }}" class="form-check-input js-dep" aria-label="{{ $__l }}"></td>
                                @endforeach
                            </tr>
                        @endforeach
                    </table>
                </div>

                <div class="js-dep-8q d-none">
                    <div class="fw-bold mb-1">แบบประเมินการฆ่าตัวตาย 8Q <span class="fw-normal small text-muted">ในช่วง 1 เดือนที่ผ่านมารวมทั้งวันนี้</span></div>
                    <table class="table table-sm table-bordered small align-middle">
                        @foreach($__DS::Q8 as $__k => [$__q, $__pt])
                            <tr class="{{ $__k === '3a' ? 'js-dep-3a d-none' : '' }}">
                                <td>{{ $__k === '3a' ? '' : $__k . '. ' }}{{ $__q }}</td>
                                <td class="text-nowrap" style="width:11rem;">
                                    @if($__k === '3a')
                                        <label class="me-2"><input type="radio" name="q8[3a]" value="0" class="form-check-input js-dep"> ได้ (0)</label>
                                        <label><input type="radio" name="q8[3a]" value="1" class="form-check-input js-dep"> ไม่ได้ ({{ $__pt }})</label>
                                    @else
                                        <label class="me-2"><input type="radio" name="q8[{{ $__k }}]" value="0" class="form-check-input js-dep"> ไม่มี (0)</label>
                                        <label><input type="radio" name="q8[{{ $__k }}]" value="1" class="form-check-input js-dep"> มี ({{ $__pt }})</label>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>

                <div class="alert alert-info py-2 small mb-2 js-dep-result">ตอบ 2Q ทั้ง 2 ข้อ</div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-check-label"><input type="checkbox" name="referred" value="1" class="form-check-input"> ส่งต่อพบแพทย์/จิตแพทย์แล้ว</label>
                    </div>
                    <div class="col-12">
                        <label class="form-label small mb-0">หมายเหตุ</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255">
                    </div>
                </div>
                <div class="alert alert-danger py-2 small mt-2 mb-0 d-none js-tbm-err"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save me-1"></i> บันทึก</button>
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ปิด</button>
            </div>
        </form>
    </div>
</div>
