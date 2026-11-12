{{-- ส่วนที่เหลือของบัตร TB 01: X-ray · น้ำหนัก/ขนาดยารายเดือน · ปฏิทินกินยา · ผลความไวต่อยา
     ทุกส่วนเป็น <form> แยกของตัวเอง วางเป็นพี่น้องกัน ไม่ซ้อนกัน (CLAUDE.md ข้อ 1)
     ตัวแปรที่ต้องส่งมา: $reg, $maxMonth, $doseRows, $dotMonth, $dotDays, $dotMonthOptions --}}

{{-- ---------- ผล X-ray ---------- --}}
<form method="POST" action="{{ route('tb.xray', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-xray">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fa fa-x-ray me-1"></i> ผลเอกซเรย์ปอด</h6>
            {{-- ปุ่มเปิดฟิล์มของคนไข้ใน PACS — ปุ่มเป็นลิงก์ ไม่ใช่ submit จึงอยู่ในฟอร์มนี้ได้ --}}
            <div class="mb-2">@include('partials.pacs_link', ['hn' => optional($reg->patient)->code])</div>
            {{-- ผลอ่านเอกซเรย์จากหน้าตรวจ OPD ที่ยังไม่อยู่ในตารางนี้ (คลินิกขอ 2569-10-12) — กด "ดึงลงตาราง" แล้วเติมแถวให้ (วันที่ · ท่า · ผล · ผู้อ่าน)
                 ยังไม่บันทึกจนกด "บันทึก" ของตารางนี้ · เทียบว่ามีแล้วด้วยวันที่ถ่าย --}}
            @php
                $__haveX = $reg->xrays->map(fn ($x) => $x->xray_date ? substr((string) $x->xray_date, 0, 10) : null)->filter()->all();
                $__opdX = [];
                if ($reg->patient_id) {
                    $__scs = \App\Models\TbScreening::where('patient_id', $reg->patient_id)->whereNotNull('visit_id')->get();
                    $__vdates = \App\Models\Visit::whereIn('id', $__scs->pluck('visit_id'))->pluck('date', 'id');
                    foreach ($__scs as $__sc) {
                        $__vd = (string) ($__vdates[$__sc->visit_id] ?? '');
                        if (! preg_match('/^\d{4}-\d{2}-\d{2}/', $__vd) || in_array(substr($__vd, 0, 10), $__haveX, true)) { continue; }
                        foreach ($__sc->cxrRows() as $__cr) {
                            $__res = (int) ($__cr['result'] ?? 0);
                            if ($__res === 4 || (! $__res && empty($__cr['view']) && trim((string) ($__cr['note'] ?? '')) === '')) { continue; }   // 4 = ไม่ได้เอกซเรย์
                            $__opdX[] = [
                                'date'   => \App\Utillity::db2thDate(substr($__vd, 0, 10)),
                                'view'   => $__cr['view'] ?? '',
                                'viewL'  => \App\Models\TbXray::$viewOption[$__cr['view'] ?? ''] ?? '',
                                'result' => trim((\App\Models\TbScreening::$cxrOption[$__res] ?? '') . (trim((string) ($__cr['note'] ?? '')) !== '' ? ' · ' . trim((string) $__cr['note']) : ''), ' ·'),
                                'doctor' => (string) ($__cr['doctor'] ?? ''),
                            ];
                        }
                    }
                }
            @endphp
            @if($__opdX)
                <div class="mb-2 p-2 rounded" style="background:#eef6ff; border:1px solid #c7dcf5;">
                    <div class="fw-bold small mb-1"><i class="fa fa-lightbulb text-primary"></i> ผลเอกซเรย์จากหน้าตรวจ OPD ที่ยังไม่อยู่ในตารางนี้</div>
                    @foreach($__opdX as $__ox)
                        <div class="d-flex flex-wrap align-items-center gap-2 small py-1 border-top">
                            <b>{{ $__ox['date'] }}</b>
                            @if($__ox['viewL'])<span class="badge bg-light text-dark border">{{ $__ox['viewL'] }}</span>@endif
                            <span>{{ $__ox['result'] ?: '—' }}</span>
                            @if($__ox['doctor'])<span class="text-muted">อ่านโดย {{ $__ox['doctor'] }}</span>@endif
                            <button type="button" class="btn btn-outline-primary btn-sm py-0 ms-auto js-xopd-pull" data-x="{{ json_encode($__ox, JSON_UNESCAPED_UNICODE) }}">ดึงลงตาราง</button>
                        </div>
                    @endforeach
                    <div class="small text-muted mt-1">ดึงแล้วตรวจดู แล้วกด "บันทึก" ของตารางนี้</div>
                </div>
            @endif
            {{-- รายชื่อแพทย์สำหรับช่องผู้อ่าน (คลินิกขอ 2569-10-12) — เลือกจากรายการหรือพิมพ์เองได้ --}}
            <datalist id="tb-xray-readers">
                @foreach(\App\Models\User::physicians() as $__doc)<option value="{{ $__doc->name }}">@endforeach
            </datalist>
            <div class="table-responsive">
                {{-- หนึ่งแถว = การถ่ายหนึ่งครั้ง/หนึ่งวัน เลือกท่าที่ถ่ายแล้วอ่านผลของครั้งนั้น
                     ท่าถ่ายตามใบส่งตรวจเอกซเรย์ของศูนย์ (คลินิกขอ 2569-09-29) --}}
                <table class="table table-sm table-bordered align-middle mb-1" id="tb-xray-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:3rem">ครั้งที่</th>
                            <th style="width:9rem">วันที่</th>
                            <th style="width:22%">ท่าที่ถ่าย</th>
                            <th>ผลการอ่าน</th>
                            <th style="width:9rem">วันที่อ่าน / ผู้อ่าน</th>
                            @if(\App\Models\TbXray::hasNote())<th style="width:14rem">หมายเหตุ</th>@endif
                            <th style="width:2.5rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $__xrows = $reg->xrays->count() ? $reg->xrays : collect([null, null]); @endphp
                    @foreach($__xrows as $i => $x)
                        <tr>
                            <td class="text-center text-muted">{{ $i + 1 }}
                                <input type="hidden" name="xray[{{ $i }}][id]" value="{{ $x->id ?? '' }}"></td>
                            <td><input type="text" name="xray[{{ $i }}][xray_date]" class="form-control form-control-sm tb-date"
                                       value="{{ $x && $x->xray_date ? \App\Utillity::db2thDate($x->xray_date) : '' }}" placeholder="วว/ดด/ปปปป">
                                {{-- ช่อง XN เอาออก (คลินิกขอ 2569-10-05) ใช้ช่องหมายเหตุท้ายแถวแทน — ไม่ส่ง xn = ไม่ทับค่าเดิม
                                     ค่า XN / X-ray No. เดิม (ถ้ามี) โชว์เป็นข้อความเล็ก ข้อมูลไม่หาย --}}
                                @if($x && (trim((string) $x->xn) !== '' || trim((string) $x->xray_no) !== ''))
                                    <div class="small text-muted mt-1" data-remove-on-clone="1">
                                        {{ collect([trim((string) $x->xn) !== '' ? 'XN เดิม: ' . $x->xn : null, trim((string) $x->xray_no) !== '' ? 'X-ray No. เดิม: ' . $x->xray_no : null])->filter()->implode(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                {{-- ท่าที่ถ่าย: โชว์เฉพาะท่าที่เลือกเป็นป้าย มี × ลบ + dropdown "เพิ่มท่า" (คลินิกขอ 2569-10-02 เดิมติ๊กครบ 8 ช่องทุกแถว รกตา)
                                     แต่ละป้ายมี hidden xray[i][views][] อยู่ข้างใน — ส่งไปรูปแบบเดิม saveXrays ไม่ต้องแก้
                                     ตัวจัดการปุ่มอยู่ใน _sections_js ผูกที่ document (แถวที่กดเพิ่มทีหลังใช้ได้ด้วย) --}}
                                @php $__views = (array) ($x->views ?? []); @endphp
                                <div class="js-xv-box">
                                    <div class="js-xv-chips d-flex flex-wrap gap-1 mb-1">
                                        @foreach(\App\Models\TbXray::$viewOption as $vk => $vLabel)
                                            @if(in_array($vk, $__views, true))
                                                <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-1 js-xv-chip" data-remove-on-clone="1" data-view="{{ $vk }}"
                                                      style="font-weight:500; font-size:.8rem;">
                                                    {{ $vLabel }}
                                                    <input type="hidden" name="xray[{{ $i }}][views][]" value="{{ $vk }}">
                                                    <button type="button" class="btn-close js-xv-remove" style="font-size:.55rem;" title="เอาท่านี้ออก" aria-label="เอาท่านี้ออก"></button>
                                                </span>
                                            @endif
                                        @endforeach
                                    </div>
                                    {{-- ไม่มี name = ไม่ถูกส่งไปกับฟอร์ม ใช้เลือกอย่างเดียว --}}
                                    <select class="form-select form-select-sm js-xv-add" style="max-width:14rem;" data-no-highlight="1">
                                        <option value="">+ เพิ่มท่า</option>
                                        @foreach(\App\Models\TbXray::$viewOption as $vk => $vLabel)
                                            <option value="{{ $vk }}" {{ in_array($vk, $__views, true) ? 'hidden disabled' : '' }}>{{ $vLabel }}</option>
                                        @endforeach
                                        <option value="__other">อื่น ๆ ระบุ…</option>
                                    </select>
                                    @php $__ov = trim((string) ($x->other_view ?? '')); @endphp
                                    <input type="text" name="xray[{{ $i }}][other_view]" class="form-control form-control-sm mt-1 js-xv-other {{ $__ov === '' ? 'd-none' : '' }}" data-hide-on-clone="1"
                                           value="{{ $__ov }}" placeholder="ท่าอื่น ๆ ระบุ">
                                </div>
                                {{-- ช่องติ๊ก "นำ CD เข้า PACS" / "เขียน CD ไปรักษาต่อ" เอาออกแล้ว (คลินิกขอ 2569-10-02 ใช้หมายเหตุแทน)
                                     แถวที่เคยติ๊กไว้ยังโชว์เป็นข้อความ ข้อมูลไม่หาย --}}
                                @if($x && ($x->cd_to_pacs || $x->cd_write))
                                    <div class="small text-muted mt-1" data-remove-on-clone="1">
                                        <i class="fa fa-compact-disc me-1"></i>{{ collect([$x->cd_to_pacs ? 'CD เข้า PACS แล้ว' : null, $x->cd_write ? 'เขียน CD ไปรักษาต่อแล้ว' : null])->filter()->implode(' · ') }}
                                    </div>
                                @endif
                            </td>
                            <td><textarea name="xray[{{ $i }}][result]" rows="3" class="form-control form-control-sm"
                                          placeholder="เช่น infiltration RUL, no cavity">{{ $x->result ?? '' }}</textarea></td>
                            <td>
                                <input type="text" name="xray[{{ $i }}][read_date]" class="form-control form-control-sm tb-date mb-1"
                                       value="{{ $x && $x->read_date ? \App\Utillity::db2thDate($x->read_date) : '' }}" placeholder="วันที่อ่าน">
                                <input type="text" name="xray[{{ $i }}][reader]" class="form-control form-control-sm" list="tb-xray-readers" autocomplete="off"
                                       value="{{ $x->reader ?? '' }}" placeholder="ผู้อ่าน (เลือก/พิมพ์)">
                            </td>
                            @if(\App\Models\TbXray::hasNote())
                                <td><textarea name="xray[{{ $i }}][note]" rows="3" class="form-control form-control-sm"
                                              placeholder="เช่น นำ CD เข้า PACS แล้ว · ถ่ายจาก รพ.อื่น">{{ $x->note ?? '' }}</textarea></td>
                            @endif
                            <td class="text-center">
                                <button type="button" class="btn btn-outline-primary btn-sm js-tb-insert-row" title="แทรกแถวต่อจากแถวนี้">
                                    <i class="fa fa-plus"></i>
                                </button>
                                {{-- พิมพ์ใบส่งตรวจของครั้งนี้ (ปุ่มใต้ตารางพิมพ์ได้แค่ครั้งล่าสุด — คลินิกขอ 2569-10-21)
                                     แถวที่ยังไม่บันทึกไม่มี id จึงไม่มีปุ่ม · ถอดออกเมื่อโคลนเป็นแถวใหม่ --}}
                                @if($x && $x->id)
                                    <a href="{{ route('tb.xrayRequest', ['id' => $reg->id, 'row' => $x->id]) }}" target="_blank"
                                       class="btn btn-outline-secondary btn-sm mt-1" data-remove-on-clone="1" title="พิมพ์ใบส่งตรวจ X-ray ของครั้งนี้">
                                        <i class="fa fa-print"></i>
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm js-tb-add-row" data-table="tb-xray-table">
                <i class="fa fa-plus"></i> เพิ่มช่อง
            </button>
            <a href="{{ route('tb.xrayRequest', $reg->id) }}" target="_blank" class="btn btn-outline-secondary btn-sm ms-1">
                <i class="fa fa-print"></i> พิมพ์ใบส่งตรวจ X-ray (ครั้งล่าสุด)
            </a>
            <script>
            // ดึงผลอ่านจาก OPD ลงตาราง X-ray — หน้าบัตรโหลดเต็ม สคริปต์รันได้ · ผูกที่ document
            (function () {
                if (window.__xopdInit) return;
                window.__xopdInit = true;
                function emptyRow(tbody) {
                    return Array.prototype.find.call(tbody.querySelectorAll('tr'), function (tr) {
                        var d = tr.querySelector('input[name$="[xray_date]"]'), r = tr.querySelector('textarea[name$="[result]"]');
                        return d && !d.value.trim() && (!r || !r.value.trim());
                    });
                }
                function set(el, v) { if (el && v !== undefined && v !== null) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true })); } }
                document.addEventListener('click', function (e) {
                    var b = e.target.closest && e.target.closest('.js-xopd-pull');
                    if (!b) return;
                    var x = JSON.parse(b.dataset.x), tbody = document.querySelector('#tb-xray-table tbody');
                    var tr = emptyRow(tbody);
                    if (!tr) { document.querySelector('.js-tb-add-row[data-table="tb-xray-table"]').click(); tr = emptyRow(tbody); }
                    if (!tr) { alert('เพิ่มแถวไม่ได้ — กด "เพิ่มช่อง" แล้วลองใหม่'); return; }
                    set(tr.querySelector('input[name$="[xray_date]"]'), x.date);
                    set(tr.querySelector('textarea[name$="[result]"]'), x.result);
                    set(tr.querySelector('input[name$="[read_date]"]'), x.date);
                    if (x.doctor) set(tr.querySelector('input[name$="[reader]"]'), x.doctor);
                    if (x.view) { var sel = tr.querySelector('.js-xv-add'); if (sel && !tr.querySelector('.js-xv-chip[data-view="' + x.view + '"]')) { set(sel, x.view); } }
                    b.disabled = true; b.textContent = '✓ ดึงแล้ว (ยังไม่บันทึก)';
                    tr.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            })();
            </script>
            <div class="small text-muted mt-1">ล้างแถวจนว่าง = ลบครั้งนั้นออก · หนึ่งแถวคือการถ่ายหนึ่งครั้ง/หนึ่งวัน</div>

        </div>
        <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกผล X-ray</button></div>
    </div>
</form>

{{-- ---------- แท็บยา (จัดใหม่ 2569-10-02) ----------
     ① กล่อง "สั่งยาครั้งนี้" (tb-order) — ที่เดียวที่สั่งยา: น้ำหนัก → วันนัด → สูตร → ยา/วิธีใช้ → บันทึกครั้งเดียว
     ② "ใบสรุปการรักษา" (tb-dose) — ตารางรอบสั่งยาเดิม อ่านอย่างเดียว กดดินสอแก้ทีละแถว
     ③ "ประวัติสูตรยา" (tb-regimen) — พับไว้ท้ายแท็บ
     กล่อง "วิธีใช้ยา" และตัวคำนวณ "เติมลงตาราง" รวมเข้ากล่องสั่งยาแล้ว ไม่มีแยกอีก --}}
@include('tb._order')

{{-- ---------- ใบสรุปการรักษา (รอบสั่งยาทุกรอบ) ---------- --}}
<form method="POST" action="{{ route('tb.dose', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-dose">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fa fa-table-list me-1"></i> ใบสรุปการรักษา (น้ำหนัก / ขนาดยาแต่ละรอบ)</h6>
            <div class="small text-muted mb-2">
                หนึ่งแถว = การสั่งยาหนึ่งครั้ง · สั่งยารอบใหม่ที่กล่อง "สั่งยาครั้งนี้" ด้านบน ·
                แก้ย้อนหลังกด <i class="fa fa-pencil"></i> ท้ายแถว · คอลัมน์ยามาจากยาทุกตัวที่เคยสั่งในบัตรนี้ (เปลี่ยนสูตรแล้วคอลัมน์เดิมไม่หาย)
            </div>
            {{-- คอลัมน์ยา = ยาทุกตัวที่เคยสั่ง ∪ สูตรที่ใช้อยู่ ($summaryKeys) — ความกว้างเป็นเปอร์เซ็นต์ แบ่งตามจำนวนยา --}}
            @php
                $__cols  = count($summaryKeys) ?: 1;
                $__drugW = max(5, (int) floor(45 / $__cols));
                $__hasRows = collect($doseRows)->contains(fn ($r) => $r['row']);
            @endphp
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-1" id="tb-dose-table" style="table-layout:fixed; width:100%;">
                    <thead class="table-light">
                        <tr>
                            <th style="width:7%">รอบ</th>
                            <th style="width:7%">น้ำหนัก (กก.)</th>
                            <th style="width:10%">วันที่สั่งยา</th>
                            <th style="width:8%">จ่ายกี่วัน</th>
                            @foreach($summaryKeys as $key)
                                <th style="width:{{ $__drugW }}%" title="{{ $drugNames[$key] ?? $key }}">
                                    {{ $key }}
                                    @if(! empty($doseUnits[$key]))<span class="fw-normal small text-muted">{{ $doseUnits[$key] }}</span>@endif
                                </th>
                            @endforeach
                            <th>หมายเหตุ</th>
                            <th style="width:3%"></th>
                        </tr>
                    </thead>
                    <tbody>
                    @php
                        // หลายช่วงนับรอบ (เช่น 2HRZE แล้วเปลี่ยนเป็น BPaL / Off แล้วเริ่มใหม่) — ขึ้นหัวช่วงคั่น เลขรอบซ้ำกันได้ข้ามช่วง (2569-10-26)
                        $__segs = $reg->doseSegments();
                        $__segShown = -1;
                    @endphp
                    @foreach($doseRows as $i => $dr)
                        @if(count($__segs) > 1 && ($dr['seg'] ?? 0) !== $__segShown)
                            @php $__segShown = $dr['seg'] ?? 0; $__sg = $__segs[$__segShown] ?? null; @endphp
                            <tr class="js-dose-seg">
                                <td colspan="{{ 6 + count($summaryKeys) }}" class="small fw-bold" style="background:#fff7ed; color:#9a3412;">
                                    <i class="fa fa-layer-group me-1"></i> ช่วงที่ {{ $__segShown + 1 }} · {{ implode(' → ', array_unique($__sg['regimens'] ?? [$__sg['regimen'] ?? ''])) }}
                                    <span class="fw-normal text-muted">({{ \App\Utillity::db2thDate($__sg['start'] ?? null) }}{{ ! empty($__sg['end']) ? ' – ' . \App\Utillity::db2thDate($__sg['end']) : ' – ปัจจุบัน' }})</span>
                                </td>
                            </tr>
                        @endif
                        {{-- แถวว่างทั้งหมดพับไว้ — รอบใหม่สร้างจากกล่องสั่งยา · เปิดเพิ่มย้อนหลังได้จากปุ่มใต้ตาราง --}}
                        @include('tb._dose_row', ['i' => $i, 'dr' => ['visible' => (bool) $dr['row']] + $dr, 'drugKeys' => $summaryKeys])
                    @endforeach
                    </tbody>
                </table>
                {{-- แถวนอกรอบที่กดเพิ่ม — JS แทน __I__ ด้วยเลขแถวใหม่ · เปิดแก้ได้ทันที --}}
                <template id="tb-dose-row-tpl">
                    @include('tb._dose_row', ['i' => '__I__', 'editable' => true, 'drugKeys' => $summaryKeys,
                                              'dr' => ['label' => '', 'month_no' => 0, 'row' => null, 'standard' => false,
                                                       'visible' => true, 'text' => '', 'pos' => 0]])
                </template>
            </div>
            @unless($__hasRows)
                <div class="small text-muted mb-2">ยังไม่มีการสั่งยาในบัตรนี้ — เริ่มที่กล่อง "สั่งยาครั้งนี้" ด้านบน</div>
            @endunless

            @php $__folded = collect($doseRows)->filter(fn ($r) => ! $r['row']); @endphp
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <div class="input-group input-group-sm" style="width:auto;">
                    <span class="input-group-text"><i class="fa fa-plus me-1"></i> ลงรอบย้อนหลัง</span>
                    <select class="form-select" id="tb-dose-add-pick" style="max-width:13rem;">
                        <option value="">— เลือก —</option>
                        @foreach($__folded as $dr)
                            <option value="{{ $dr['label'] }}">{{ $dr['text'] }}</option>
                        @endforeach
                        <option value="__extra">นอกรอบ (ตั้งชื่อเอง)…</option>
                    </select>
                    <input type="text" class="form-control d-none" id="tb-dose-add-label" maxlength="30"
                           placeholder="เช่น วันที่ 2 / นัดปรับยา" style="max-width:12rem;">
                    <select class="form-select d-none" id="tb-dose-add-month" style="max-width:8rem;" title="แถวนี้อยู่ในเดือนที่ (ใช้กับสติกเกอร์)">
                        @for($m = 0; $m <= $maxMonth; $m++)
                            <option value="{{ $m }}" {{ $m === min($maxMonth, $reg->monthsOnTreatment()) ? 'selected' : '' }}>ในเดือนที่ {{ $m }}</option>
                        @endfor
                    </select>
                    <button type="button" class="btn btn-outline-primary" id="tb-dose-add-btn">เพิ่ม</button>
                </div>
                <span class="small text-muted">ใช้ลงรอบที่สั่งไปก่อนมีระบบ · ล้างทุกช่องในแถวแล้วบันทึก = ลบแถวนั้น</span>
            </div>

            <div class="small text-muted">
                ขนาดยาเป็นตัวเลขตามหน่วยที่หัวคอลัมน์ (เม็ด/วัน หรือ มก./วัน) · ยาอื่น (Para ฯลฯ) มีหน่วยอยู่ในช่อง
            </div>
        </div>
        {{-- ปุ่มบันทึกโผล่เฉพาะตอนมีแถวที่กำลังแก้ — แถวที่ไม่ได้แก้ไม่ถูกส่งไป (ช่อง disabled) จึงไม่ทับของคนอื่น --}}
        <div class="card-footer bg-white d-none" id="tb-dose-save">
            <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกการแก้ไขในใบสรุป</button>
            <button type="button" class="btn btn-outline-secondary ms-1" id="tb-dose-cancel">ยกเลิก</button>
        </div>
    </div>
</form>

{{-- ---------- ประวัติสูตรยา (พับไว้ท้ายแท็บ) ----------
     ไม่ใช่ฟอร์มแล้ว (2569-10-02) — ขึ้นสูตรใหม่ทำผ่านกล่องสั่งยา · ปุ่มแก้/ลบสร้างฟอร์มต่อ body (ห้ามฟอร์มซ้อน) --}}
<div class="mb-3" id="tb-regimen">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-0 d-flex align-items-center">
                <span><i class="fa fa-clock-rotate-left me-1"></i> ประวัติสูตรยา</span>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="collapse" data-bs-target="#tb-regimen-body"
                        aria-expanded="false" aria-controls="tb-regimen-body">
                    <i class="fa fa-chevron-down me-1"></i> แสดง/ซ่อน ({{ $regimenChanges->count() }} ช่วง)
                </button>
            </h6>
            <div class="collapse" id="tb-regimen-body">
                <div class="pt-2">
            @if($regimenChanges->count())
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width:12%">เริ่มเดือนที่</th>
                                <th style="width:14%">วันที่เริ่ม</th>
                                <th style="width:24%">สูตรยา</th>
                                <th style="width:24%">ยาที่ใช้</th>
                                <th>เหตุผลที่เปลี่ยน</th>
                                <th style="width:8%"></th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($regimenChanges as $rc)
                            <tr class="{{ $rc->end_date ? 'text-muted' : '' }} js-regimen-view" data-rc="{{ $rc->id }}">
                                <td class="text-center">{{ $rc->start_month ?? '—' }}</td>
                                <td>{{ $rc->thDate('start_date') ?? '—' }}</td>
                                <td>
                                    <b>{{ $rc->regimen }}</b>
                                    @if($rc->end_date)
                                        <span class="badge bg-secondary">ใช้ถึง {{ $rc->thDate('end_date') }}</span>
                                    @else
                                        <span class="badge bg-success">ใช้อยู่</span>
                                    @endif
                                </td>
                                <td class="small">{{ $rc->drugs ? implode(' · ', $rc->drugs) : '—' }}</td>
                                <td class="small">{{ $rc->reason }}</td>
                                <td class="text-center text-nowrap">
                                    <button type="button" class="btn btn-sm btn-link p-0 me-2 js-regimen-edit"
                                            data-rc="{{ $rc->id }}" title="แก้ไขช่วงนี้"><i class="fa fa-pencil"></i></button>
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0 js-regimen-delete"
                                            data-url="{{ route('tb.regimen.delete', ['id' => $reg->id, 'changeId' => $rc->id]) }}"
                                            title="ลบช่วงนี้">&times;</button>
                                </td>
                            </tr>
                            {{-- แถวแก้ไข: ช่องไม่มี name — ปุ่มบันทึกส่งผ่านฟอร์มที่ JS สร้างต่อ body --}}
                            <tr class="d-none js-regimen-editrow" data-rc="{{ $rc->id }}"
                                data-url="{{ route('tb.regimen.update', ['id' => $reg->id, 'changeId' => $rc->id]) }}"
                                style="background:#fff7ed;">
                                <td colspan="6">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">เริ่มเดือนที่</label>
                                            <input type="number" min="0" max="31" class="form-control form-control-sm"
                                                   data-f="start_month" value="{{ $rc->start_month }}" data-no-highlight>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">วันที่เริ่ม</label>
                                            <input type="text" class="form-control form-control-sm tb-date" data-f="start_date"
                                                   value="{{ $rc->thDate('start_date') }}" placeholder="วว/ดด/ปปปป" data-no-highlight>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small text-muted mb-1">ใช้ถึงวันที่ <span class="fw-normal">(ว่าง = ใช้อยู่)</span></label>
                                            <input type="text" class="form-control form-control-sm tb-date" data-f="end_date"
                                                   value="{{ $rc->thDate('end_date') }}" placeholder="วว/ดด/ปปปป" data-no-highlight>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-1">สูตรยา</label>
                                            <input type="text" class="form-control form-control-sm" list="tb-regimen-full"
                                                   data-f="regimen" value="{{ $rc->regimen }}" maxlength="255" data-no-highlight>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small text-muted mb-1">เหตุผลที่เปลี่ยน</label>
                                            <input type="text" class="form-control form-control-sm" data-f="reason"
                                                   value="{{ $rc->reason }}" maxlength="255" data-no-highlight>
                                        </div>
                                        <div class="col-md-8">
                                            <label class="form-label small text-muted mb-1">ยาที่ใช้ (ชื่อย่อ คั่นด้วยจุลภาค เช่น H, R, Z, E)</label>
                                            <input type="text" class="form-control form-control-sm" data-f="drugs"
                                                   value="{{ $rc->drugs ? implode(', ', $rc->drugs) : '' }}" data-no-highlight>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <button type="button" class="btn btn-sm btn-primary js-regimen-save"><i class="fa fa-save"></i> บันทึกการแก้ไข</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-regimen-cancel">ยกเลิก</button>
                                        </div>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        แก้เฉพาะช่วงนี้ ช่วงอื่นไม่ถูกปิด/เปิดให้อัตโนมัติ · ปีที่เป็น ค.ศ. จะถูกแปลงเป็น พ.ศ. ตอนบันทึก
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <datalist id="tb-regimen-full">
                    @foreach($regimenOptions as $ro)
                        <option value="{{ $ro->regimen }}">{{ $ro->name ?: $ro->category }}</option>
                    @endforeach
                </datalist>
            @else
                <div class="alert alert-light border py-2 px-3 small mb-0">
                    ยังไม่มีประวัติสูตรยา · ตอนนี้ใช้สูตร <b>{{ $reg->regimen ?: 'ยังไม่ระบุ' }}</b> จากช่องสูตรยาในข้อมูลบัตร
                    — เลือกสูตรในกล่อง "สั่งยาครั้งนี้" แล้วบันทึก ระบบจะเริ่มประวัติให้
                </div>
            @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// ใบสรุปการรักษา: อ่านอย่างเดียว กดดินสอแก้ทีละแถว (2569-10-02)
// เปิดแก้ = ปลด disabled ทุกช่องของแถวนั้นพร้อมกัน — ห้ามปลดแค่บางช่อง (ส่ง id ไปโดยไม่มีค่า = saveDoses ลบแถวนั้น)
(function () {
    var table = document.getElementById('tb-dose-table');
    var save  = document.getElementById('tb-dose-save');
    if (!table || !save) return;
    function editing() { return table.querySelectorAll('tr.js-dose-editing').length > 0; }
    window.tbDoseEnableRow = function (tr) {
        tr.classList.add('js-dose-editing');
        tr.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = false; });
        tr.querySelectorAll('.js-dose-rw').forEach(function (i) { i.classList.remove('d-none'); });
        tr.querySelectorAll('.js-dose-ro').forEach(function (s) { s.classList.add('d-none'); });
        var p = tr.querySelector('.js-dose-edit'); if (p) p.classList.add('d-none');
        tr.style.background = '#fffbeb';
        save.classList.toggle('d-none', !editing());
    };
    table.addEventListener('click', function (e) {
        var b = e.target.closest('.js-dose-edit');
        if (!b) return;
        window.tbDoseEnableRow(b.closest('tr'));
        var f = b.closest('tr').querySelector('.js-dose-rw'); if (f) f.focus();
    });
    document.getElementById('tb-dose-cancel').addEventListener('click', function () { location.reload(); });
})();
</script>


{{-- ---------- ปฏิทินกินยา ---------- --}}
{{-- ล็อกไว้ตั้งต้น ต้องกดปุ่ม "แก้ไขปฏิทิน" ก่อนถึงจะกดช่องวันได้ (คลินิกขอ 2569-09-29)
     หมอเปิดบัตรแล้วเลื่อน/คลิกผ่าน ๆ แล้วไปโดนช่องวัน สถานะวันนั้นเปลี่ยนโดยไม่รู้ตัว
     ที่ลงประจำวันคือหน้ากระดาน DOT อยู่แล้ว (เลือกวันย้อนหลังได้) ตรงนี้เก็บไว้สำหรับงานเป็นก้อน
     เช่น เติมทั้งเดือน หรือลงย้อนหลังก่อนวันเริ่มยาของเคส refer ซึ่งหน้ากระดานไม่มี
     ล็อกด้วย CSS (pointer-events) + ซ่อนปุ่มบันทึก ไม่ได้ disable ช่อง เพราะช่องที่ถูก disable
     จะไม่ถูกส่งไปกับฟอร์ม ถ้าปลดล็อกแล้วกดบันทึกค่าจะหายทั้งเดือน --}}
<style>
    /* มุมพับสีส้ม = วันนั้นมีหมายเหตุจากหน้า DOT เอาเมาส์ชี้ดู */
    /* วันที่ผ่านไปแล้วไม่มีใครลง — กากบาทแดงเส้นประ แยกจาก ✗ "วันเริ่มกินยา" (ปุ่มสีน้ำเงินทึบ) */
    .tb-dot-missing { background:#fff5f5 !important; color:#dc3545 !important; border:1px dashed #dc3545 !important; }
    /* รอเช็ค (กินยาหลัง 16:00) · คนไข้กินยาเอง (วันหยุด) — กติกาเดียวกับหน้า DOT (2569-10-01) */
    .tb-dot-pending { background:#f9fafb !important; color:#6b7280 !important; border:1px dashed #9ca3af !important; }
    .tb-dot-self    { background:#f0f9ff !important; color:#0369a1 !important; border:1px dashed #38bdf8 !important; }
    .tb-dot-note-flag { position:absolute; top:0; right:0; width:0; height:0;
                        border-top:.55rem solid #f59e0b; border-left:.55rem solid transparent; }
    /* ล็อกด้วย JS (ไม่กดวนสัญลักษณ์) แทน pointer-events:none — แบบเดิมเมาส์ชี้แล้วหมายเหตุ DOT ไม่ขึ้น (2569-09-30) */
    #tb-dot[data-locked="1"] .js-dot-cell,
    #tb-dot[data-locked="1"] .js-dot-locked { cursor: default; opacity: .75; }
    #tb-dot[data-locked="1"] .js-dot-cell:hover { filter: none; }
    #tb-dot[data-locked="1"] .tb-dot-edit-only { display: none !important; }
    #tb-dot:not([data-locked="1"]) .tb-dot-lock-note { display: none !important; }
</style>
<form method="POST" action="{{ route('tb.dot', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-dot" data-locked="1">
    @csrf
    <input type="hidden" name="month" value="{{ $dotMonth }}">
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <h6 class="fw-bold mb-0"><i class="fa fa-calendar-check me-1"></i> ปฏิทินกินยา
                    <span class="badge bg-secondary ms-1 tb-dot-lock-note"><i class="fa fa-lock"></i> ดูอย่างเดียว</span>
                </h6>
                <div class="d-flex align-items-center gap-2 tb-dot-lock-note">
                    <a href="{{ route('tb.dotBoard') }}" class="btn btn-sm btn-outline-success">
                        <i class="fa fa-clipboard-check me-1"></i> ลงกินยาประจำวันที่หน้า DOT
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="tb-dot-unlock-edit">
                        <i class="fa fa-lock-open me-1"></i> แก้ไขปฏิทิน
                    </button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted mb-0">เดือน</label>
                    {{-- เปลี่ยนเดือนด้วยลิงก์ ไม่ใช่ submit เพราะอยู่ในฟอร์มบันทึก --}}
                    <select class="form-select form-select-sm" style="width:12rem;" id="tb-dot-month"
                            data-base="{{ route('tb.show', $reg->id) }}">
                        @foreach($dotMonthOptions as $value => $label)
                            <option value="{{ $value }}" {{ $value === $dotMonth ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                {{-- ระยะ DOT มาจากการสั่งยา (ช่วงสูตร/รอบสั่งยาของแต่ละวัน) แสดงอย่างเดียว (คลินิกขอ 2569-10-02)
                     เดิมเป็นช่องเลือกที่แก้ได้แม้ปฏิทินล็อกอยู่ · ตอนบันทึกเซิร์ฟเวอร์คิดระยะของแต่ละวันเอง --}}
                <div>
                    @php
                        $__phShort = [1 => 'ระยะเข้มข้น', 2 => 'ระยะต่อเนื่อง'];
                        $__segs = $dotPhase['segments'] ?? [];
                        $__fromOrder = collect($__segs)->every(fn ($s) => $s[3] === 'order');
                    @endphp
                    <span class="badge text-dark border" style="background:#fff7ed; font-size:.9rem; font-weight:600;" id="tb-dot-phase">
                        ระยะ:
                        @foreach($__segs as $__s)
                            @if(count($__segs) > 1)<span class="fw-normal">{{ $__s[0] }}–{{ $__s[1] }}</span>@endif
                            {{ $__phShort[$__s[2]] ?? $__s[2] }}@if(! $loop->last) · @endif
                        @endforeach
                        <span class="fw-normal text-muted">({{ $__fromOrder ? 'ตามสูตรยาที่สั่ง' : (collect($__segs)->contains(fn ($s) => $s[3] === 'order') ? 'ตามสูตรยาที่สั่ง / เดือนที่รักษา' : 'ตามเดือนที่รักษา — ยังไม่มีการสั่งยาที่บอกระยะ') }})</span>
                    </span>
                </div>
                <script>
                (function () {
                    var f = document.getElementById('tb-dot');
                    var b = document.getElementById('tb-dot-unlock-edit');
                    if (f && b) { b.addEventListener('click', function () { f.setAttribute('data-locked', '0'); }); }
                })();
                </script>
                <div class="small text-muted">
                    กดที่ช่องวันเพื่อวนสัญลักษณ์:
                    <span class="badge bg-success">✓ กินยา</span>
                    <span class="badge bg-info text-dark">⌂ กินยาเอง (วันหยุด)</span>
                    <span class="badge bg-primary">✗ เริ่มยา</span>
                    <span class="badge bg-secondary">– จ่ายยา</span>
                    <span class="badge bg-danger">0 ไม่กิน</span>
                    <span class="badge bg-light text-dark border">ว่าง</span>
                    <span class="badge" style="background:#fff5f5; color:#dc3545; border:1px dashed #dc3545;">✗ ไม่ได้บันทึก</span>
                </div>
            </div>

            @php
                // วันที่ยังไม่ถึงวันเริ่มยา กดไม่ได้ กันพยาบาลติ๊กพลาดย้อนหลัง (คลินิกขอ 2569-09-24)
                // และเดือนที่มีไม่ถึง 31 วัน ไม่ต้องขึ้นช่องวันที่ไม่มีจริง
                $__tbStart = $reg->treatment_start_date ?: $reg->register_date;
                [$__tbY, $__tbM] = array_pad(explode('-', $dotMonth), 2, '01');
                $__tbDays = (int) date('t', mktime(0, 0, 0, (int) $__tbM, 1, (int) $__tbY - 543));
            @endphp
            <div class="d-flex flex-wrap gap-1" id="tb-dot-grid">
                @for($day = 1; $day <= $__tbDays; $day++)
                    @php
                        $date = sprintf('%s-%02d', $dotMonth, $day);
                        $mark = $dotDays->get($date)->mark ?? '';
                        $__dnote = trim((string) ($dotDays->get($date)->note ?? ''));
                        // ล็อกเฉพาะวันก่อนเริ่มยาที่ยังไม่มีข้อมูล — ถ้าเคยติ๊กไว้แล้วยังแก้ได้ ไม่ซ่อนของเดิม
                        $lockDay = $__tbStart && $date < $__tbStart && $mark === '';
                        // วันที่ผ่านไปแล้วแต่ไม่มีใครลง DOT = กากบาทแดง "ไม่ได้บันทึก" (พยาบาลขอ 2569-09-30)
                        // แสดงอย่างเดียว ไม่เก็บลงฐาน — กดแก้เป็น ✓ ได้ถ้าคนไข้กินยาจริงแต่เจ้าหน้าที่ลืมลง
                        // นับเฉพาะตั้งแต่วันแรกที่ลง DOT ในระบบ, วันที่ยัง DOT อยู่ และ (รายสัปดาห์) ตรงวันนัดกินยา
                        // กติกาเดียวกับหน้า DOT (2569-10-01): เลย 16:00 ของวันทำการถัดไป = ไม่ได้บันทึก · กินยาหลัง 16:00 = รอเช็ค
                        // เสาร์-อาทิตย์/วันหยุด = คนไข้กินยาเอง (ไม่ขึ้นกากบาท พยาบาลมาตรวจสอบแล้วแก้ย้อนหลัง)
                        $__state   = (! $lockDay && $mark === '') ? $reg->dotUnrecordedState($date, $dotSince ?? null) : null;
                        $__missing = $__state === 'missed';
                        $__stTitle = ['missed' => 'ไม่ได้บันทึก DOT — กดเพื่อแก้เป็น ✓ กินยา',
                                      'pending' => 'รอเช็ค (กินยาหลัง 16:00) ' . \App\Models\TbRegistration::dotCheckDeadlineText($date),
                                      'self' => 'วันหยุด — คนไข้กินยาเอง พยาบาลตรวจสอบแล้วแก้ย้อนหลังได้'][$__state] ?? '';
                    @endphp
                    <div style="width:3rem;">
                        <div class="text-center small text-muted">{{ $day }}</div>
                        <input type="hidden" name="mark[{{ $day }}]" value="{{ $mark }}" class="js-dot-input">
                        {{-- ค่าตอนเปิดหน้า — เซิร์ฟเวอร์แตะเฉพาะวันที่กดเปลี่ยน ไม่ทับที่หน้า DOT ลงไว้ระหว่างเปิดค้าง --}}
                        <input type="hidden" name="orig[{{ $day }}]" value="{{ $mark }}">
                        <button type="button" class="btn btn-sm w-100 {{ $lockDay ? 'js-dot-locked' : 'js-dot-cell' }}"
                                data-day="{{ $day }}" {{ $lockDay ? 'disabled' : '' }} @if($__missing) data-missing="1" @endif data-state="{{ $__state }}"
                                title="{{ trim(($lockDay ? 'ก่อนวันเริ่มยา ' . \App\Utillity::db2thDate($__tbStart) : '') . $__stTitle . ($__dnote !== '' ? "\nหมายเหตุ: " . $__dnote : '')) }}"
                                style="position:relative; height:2.2rem; font-size:1.1rem;{{ $lockDay ? ' background:#f1f3f5; border-color:#e9ecef;' : '' }}"><span class="js-dot-char">{{ \App\Models\TbDotDay::$markChar[$mark] ?? '' }}</span>@if($__dnote !== '')<span class="tb-dot-note-flag" aria-hidden="true"></span>@endif</button>
                    </div>
                @endfor
            </div>
            {{-- หมายเหตุจากหน้า DOT ของเดือนนี้ — เขียนเป็นรายการด้วย เพราะแท็บเล็ต/จอสัมผัสไม่มี hover --}}
            @php
                $__dotNotes = $dotDays->filter(fn ($r) => trim((string) $r->note) !== ''
                                                       && substr((string) $r->dot_date, 0, 7) === $dotMonth)
                                       ->sortKeys();
            @endphp
            @if($__dotNotes->isNotEmpty())
                <div class="mt-2 p-2 rounded small" style="background:#fff7e6; border:1px solid #f5d9a8;">
                    <div class="fw-bold mb-1" style="color:#b45309;"><i class="fa fa-note-sticky me-1"></i> หมายเหตุจากหน้า DOT</div>
                    @foreach($__dotNotes as $__nd)
                        <div><b>{{ (int) substr((string) $__nd->dot_date, 8, 2) }}</b> — {{ $__nd->note }}</div>
                    @endforeach
                </div>
            @endif
            @if($__tbStart)
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2 small text-muted">
                    <span>ช่องสีเทา = ก่อนวันเริ่มยา {{ \App\Utillity::db2thDate($__tbStart) }}</span>
                    {{-- เคส refer มา คนไข้กินยามาก่อนวันที่เราขึ้นทะเบียน ต้องลงย้อนหลังได้ --}}
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="tb-dot-unlock">
                        <label class="form-check-label" for="tb-dot-unlock">ลงย้อนหลังก่อนวันเริ่มยา (เคส refer / กินยามาก่อน)</label>
                    </div>
                </div>
            @endif
        </div>
        <div class="card-footer bg-white d-flex gap-2 tb-dot-edit-only">
            <button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกปฏิทินเดือนนี้</button>
            <button type="button" class="btn btn-outline-secondary" id="tb-dot-fill">เติม ✓ ทั้งเดือน</button>
            <button type="button" class="btn btn-outline-danger" id="tb-dot-clear">ล้างทั้งเดือน</button>
        </div>
    </div>
</form>

@include('tb._dot_history')

{{-- การติดตามผู้ป่วย — ย้ายมาจากหน้าตรวจ (คลินิกขอ 2569-10-01) --}}
@include('tb._followup')

{{-- ผลความไวต่อยา (DST) ย้ายไปอยู่ใต้ "ผลตรวจเสมหะรายเดือน" ใน tb/show (คลินิกขอ 2569-09-29)
     อ่านผลเสมหะแล้วดูผลดื้อยาต่อได้เลย ไม่ต้องเลื่อนผ่านสูตรยา/ขนาดยา/ปฏิทินลงไปหา
     เนื้อหาอยู่ที่ tb/_dst.blade.php --}}

@include('tb._sections_js')
