@php
    // ห้อง Lab (หน้าที่ส่ง addResult=1 มา) = คนลงผล/แก้ผลเอง -> เห็นผลเสมอ
    // ฝั่งพยาบาล/หมอ -> เห็นผลเฉพาะรายการที่ "ผลออกแล้ว + Lab อนุมัติแล้ว"
    $__isLabRoom = !empty($addResult);
    $__firstSub  = collect($labsub)->first();
    $__parentLab = $__firstSub ? \App\Models\Lab::find($__firstSub->lab_id) : null;
    $resultVisible = $__isLabRoom || !$__parentLab || $__parentLab->isResultVisible();

    // หัวกระดาษตอน print ใช้ $patient จาก scope ของหน้าแม่ แต่บางหน้า (เช่น visit/lab) ไม่ได้ส่งมา
    // -> ดึงจาก lab เองแทน กัน "Call to a member function age() on null"
    $patient = $patient ?? ($__parentLab ? $__parentLab->patient : null);

    // นับรายการที่ผลออกแล้ว / ยังไม่ออก (ใช้แจ้งสถานะ + ให้ AJAX รู้ว่ามีผลใหม่เข้ามาไหม)
    $__ready   = collect($labsub)->filter(fn ($s) => trim((string) $s->result) !== '')->count();
    $__pending = collect($labsub)->count() - $__ready;
    // DTX / EKG พยาบาลลงเอง ไม่ผ่านห้อง Lab (LabSub::$pocMethods) — ไม่ต้องขึ้นแถบ "รอห้อง Lab อนุมัติ" ถ้าใบนี้มีแค่พวกนี้
    $__onlyPoc = collect($labsub)->isNotEmpty() && collect($labsub)->every(fn ($s) => \App\Models\LabSub::isPoc($s->method));

    // คลินิก TB: ผล Anti-HIV ปิดไว้ กดดูแล้วบันทึก log (2569-10-06) — ห้อง Lab ต้องลงผลจึงเห็นเสมอ · STI/TMC เหมือนเดิม
    $__hivMask = ! $__isLabRoom && $__parentLab && $__parentLab->visit_id
        && (int) \App\Models\Visit::whereKey($__parentLab->visit_id)->value('clinic') === 2;
    $__hivPid  = $__hivMask ? ($__parentLab->patient_id ?: \App\Models\Visit::whereKey($__parentLab->visit_id)->value('patient_id')) : null;
    $__hivMask = $__hivMask && $__hivPid;

@endphp

<div class="d-flex justify-content-end mb-2">
    {{-- เลือกภาษาใบผลตอนพิมพ์ (ห้อง Lab ขอ 2569-10-16) — English = ชื่อคนไข้/เจ้าหน้าที่ภาษาอังกฤษ + วันที่ ค.ศ. --}}
    <button id="printButton" type="button" class="btn btn-success btn-sm print-lab-btn" data-lang="th">
        <i class="fa fa-print me-1"></i> Print ผลที่เลือก (ไทย)
    </button>
    <button id="printButtonEn" type="button" class="btn btn-outline-success btn-sm print-lab-btn ms-1" data-lang="en">
        <i class="fa fa-print me-1"></i> Print (English)
    </button>
</div>

@unless($__isLabRoom)
    @if(!$resultVisible && ! $__onlyPoc)
        <div class="alert alert-warning d-flex align-items-center gap-2 py-2 mb-2" id="lab-approval-wait">
            <i class="fas fa-hourglass-half"></i>
            <div><b>ผลตรวจรอห้อง Lab อนุมัติ</b> — ยังไม่แสดงผลเพื่อป้องกันการอ่านค่าที่ยังไม่ยืนยัน (ระบบจะแจ้งทันทีที่อนุมัติ)</div>
        </div>
    @elseif($__pending > 0)
        <div class="alert alert-info d-flex align-items-center gap-2 py-2 mb-2" id="lab-approval-wait">
            <i class="fas fa-flask-vial"></i>
            <div>ผลออกแล้ว <b>{{ $__ready }}</b> รายการ · <b>รออีก {{ $__pending }}</b> รายการ — ระบบจะแจ้งเมื่อมีผลใหม่</div>
        </div>
    @endif

    @if($__parentLab && (!$resultVisible || $__pending > 0))
    <script>
    (function () {
        // เช็คทุก 15 วิ — ทั้งกรณี "รออนุมัติ" และ "รอผลที่เหลือ"
        // ถ้าสถานะเปลี่ยน (อนุมัติแล้ว / มีผลใหม่เข้ามา) จะแจ้งให้กดดู
        // ตั้งใจไม่รีเฟรชเอง เพราะหน้าตรวจอาจมีข้อมูลที่ยังไม่ได้ Save
        var url  = "{{ route('lab.approveStatus', $__parentLab->id) }}";
        var box  = document.getElementById('lab-approval-wait');
        var seen = { visible: {{ $resultVisible ? 'true' : 'false' }}, ready: {{ $__ready }} };
        if (!box) return;
        // Reverb: ห้อง Lab ลงผล/อนุมัติใบนี้ → เช็คทันที ไม่ต้องรอรอบ 15 วิ (รอบยังอยู่เป็นตัวสำรอง)
        var labId = {{ (int) $__parentLab->id }}, rtWait = null;
        if (window.ClinicRT) {
            ClinicRT.on('lab', function (d) {
                if (d.lab !== labId || !timer) return;
                clearTimeout(rtWait);
                rtWait = setTimeout(check, 500);
            });
        }
        var timer = setInterval(check, 15000);
        function check() {
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (!d) return;
                    var changed = (d.visible && !seen.visible) || (d.ready > seen.ready);
                    if (changed) {
                        clearInterval(timer); timer = null;
                        box.className = 'alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 mb-2';
                        box.innerHTML = '<div><b><i class="fas fa-circle-check"></i> มีผลตรวจพร้อมแสดงแล้ว</b> — กดเพื่อดูผลล่าสุด</div>'
                            + '<button type="button" class="btn btn-success btn-sm" onclick="location.reload()">'
                            + '<i class="fas fa-eye"></i> แสดงผล</button>';
                    }
                })
                .catch(function () {});
        }
    })();
    </script>
    @endif
@endunless
<style>
    @media print { .print-lab-btn { display: none !important; } }
</style>
<div class="table-responsive">
    <table class="table table-hover align-middle lab-result-table">
        <thead class="table-light">
            <tr>
                <th style="width:40px;" class="text-center"><i class="fa fa-check-square text-muted"></i></th>
                <th style="width:40px;">#</th>
                <th>Method</th>
                <th>Specimen From</th>
                <th style="min-width:220px;">Result</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>

                @foreach($labsub as $item)
            <tr>
                <td>
        <input type="checkbox" class="lab-checkbox" value="{{ $item->id }}">
    </td>
                <td>{{ $loop->iteration }}</td>              
                <td>
                    {{ $item->method() }}
                    @if($__parentLab && $__parentLab->patient_id && ! ($__hivMask && (int) $item->method === 6))
                        {{-- TB: ไม่มีปุ่มผลย้อนหลังของ HIV — popup นั้นโชว์ผลทุกครั้งโดยไม่ผ่านการกดดู --}}
                        {{-- กดดูผลเก่าของรายการนี้ เทียบว่าค่าเดินไปทางไหน (เช่น RPR ลงถึงไหนแล้ว) --}}
                        {{-- เปิด modal ด้วย data attribute เหมือน modal อื่นในหน้านี้ (app.js ไม่ได้ประกาศ window.bootstrap) --}}
                        <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline js-lab-trend"
                                data-bs-toggle="modal" data-bs-target="#labTrendModal"
                                data-url="{{ route('lab.trend', ['patient' => $__parentLab->patient_id, 'method' => $item->method, 'visit' => $__parentLab->visit_id]) }}"
                                title="ดูผลย้อนหลังของ {{ $item->method() }}">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    @endif
                </td>
                <td>{{ $item->specimenFrom() }}</td>
                {{-- <td>{{ $item->result }}</td> --}}
                <td>
                    {{-- <input type="text" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}"  value="{{ $item->result }}" class="form-control track border "> --}}

                    @php
                        // แสดงค่าจริงเมื่อ: เป็นห้อง Lab (ต้องลงผล) หรือ (Lab อนุมัติแล้ว และผลรายการนี้ออกแล้ว)
                        // ผลที่ยังว่าง -> ขึ้น "รอห้อง Lab อนุมัติผล" แทนช่องว่าง จะได้ไม่เข้าใจผิดว่าไม่มีผล
                        $__showValue = $__isLabRoom || ($resultVisible && trim((string) $item->result) !== '');
                        $__pocDef = \App\Models\LabSub::$pocMethods[(int) $item->method] ?? null;
                    @endphp
                    @if($__pocDef && ! $__isLabRoom)
                        {{-- DTX / EKG: พยาบาลลงเอง เห็นทันที (DTX = แผง Lab ในคลินิก · EKG = ปุ่ม "ตรวจหัวใจ" หรือ QTcF ในสัญญาณชีพ) --}}
                        @if(trim((string) $item->result) === '')
                            <span class="badge bg-light text-dark border py-2 px-3">รอลงผล ({{ (int) $item->method === 16 ? 'ปุ่ม ตรวจหัวใจ / QTcF ในสัญญาณชีพ' : 'แผง Lab ในคลินิก' }})</span>
                        @else
                            @php $__pf = \App\Models\LabSub::pocFlag($item->method, $item->result); @endphp
                            <b>{{ (int) $item->method === 16 ? 'QTcF ' : '' }}{{ $item->result }} {{ $__pocDef[0] }}</b>
                            @if($__pf)<span class="ms-1 fw-semibold" style="color: {{ $__pf[1] === 'danger' ? '#b91c1c' : '#b45309' }};">{{ $__pf[0] }}</span>@endif
                        @endif
                    @else
                    @if($__showValue && $__hivMask && (int) $item->method === 6)
                        @include('partials.hiv_mask', ['hmPatient' => $__hivPid, 'hmKind' => 'sub', 'hmRef' => $item->id,
                            'hmVisit' => $__parentLab->visit_id, 'hmWhere' => 'หน้าตรวจ TB · ตาราง Lab'])
                    @elseif(! $__showValue)
                        <span class="badge bg-warning text-dark py-2 px-3">
                            <i class="fas fa-hourglass-half me-1"></i> รอห้อง Lab อนุมัติผล
                        </span>
                    @else
                   @switch($item->method)
            @case(1)
            <?php 
                $case1id = $item->id; 
                $options = App\Models\LabSub::getGramOptions($item->specimenFrom());
            ?>
            <label for="result[{{$item->id}}]" class="control-label click" data-bs-toggle="modal" data-bs-target="#modal-{{$item->id}}">
                {{ 'เลือกลงผล Gram' }}
            </label>
            <textarea rows="1" id="result-{{$item->id}}" name="result[{{$item->id}}]" org="{{ $item->result }}" class="form-control track border" style="overflow:hidden; resize:true; min-height: 60px;">{{ $item->result }}</textarea>
            <!-- Modal -->
            <div class="modal fade" id="modal-{{ $item->id }}" tabindex="-1" aria-labelledby="modalLabel-{{ $item->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel-{{ $item->id }}">เลือกลงผล Gram - {{ $item->specimenFrom() }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>I</th>
                            <th>E</th>
                            <th>PMN</th>
                            @if (in_array($item->specimenFrom(), ['Vagina', 'Cervix', 'Urethra']))
                            <th>Fungus</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <!-- Column I -->
                            <td>
                                <ul>
                                    <li class="gram_item" data-value="I : Not found">I : Not found</li>
                                    <li class="gram_item" data-value="I : GNDC 1+">I : GNDC 1+</li>
                                    <li class="gram_item" data-value="I : GNDC 2+">I : GNDC 2+</li>
                                    <li class="gram_item" data-value="I : GNDC 3+">I : GNDC 3+</li>
                                    <li class="gram_item" data-value="I : GNDC 4+">I : GNDC 4+</li>
                                    <li class="gram_item" data-value="I : Other">I : Other: </li>
                                </ul>
                            </td>
                            <!-- Column E -->
                            <td>
                                <ul>
                                    <li class="gram_item" data-value="E : Not found">E : Not found</li>
                                    <li class="gram_item" data-value="E : GNDC 1+">E : GNDC 1+</li>
                                    <li class="gram_item" data-value="E : GNDC 2+">E : GNDC 2+</li>
                                    <li class="gram_item" data-value="E : GNDC 3+">E : GNDC 3+</li>
                                    <li class="gram_item" data-value="E : GNDC 4+">E : GNDC 4+</li>
                                    <li class="gram_item" data-value="I : Other">I : Other: </li>
                                </ul>
                            </td>
                            <!-- Column PMN -->
                            <td>
                                <ul>
                                    <li class="gram_item" data-value="PMN : Negative finding">PMN : Negative finding</li>
                                    <li class="gram_item" data-value="PMN : 1+">PMN : 1+</li>
                                    <li class="gram_item" data-value="PMN : 2+">PMN : 2+</li>
                                    <li class="gram_item" data-value="PMN : 3+">PMN : 3+</li>
                                    <li class="gram_item" data-value="PMN : 4+">PMN : 4+</li>
                                    <li class="gram_item" data-value="PMN : Other">PMN : Other: </li>
                                </ul>
                            </td>
                            <!-- Column Fungus -->
                            @if (in_array($item->specimenFrom(), ['Vagina', 'Cervix', 'Urethra']))
                            <td>
                                <ul>
                                    <li class="gram_item" data-value="Fungus : Not found">Fungus : Not found</li>
                                    <li class="gram_item" data-value="Fungus : Growth">Fungus : Growth</li>
                                    <li class="gram_item" data-value="Fungus : Budding yeast cell Found">Fungus : Budding yeast cell Found</li>
                                    <li class="gram_item" data-value="Fungus : Budding yeast with pseudohyphae Found">Fungus : Budding yeast with pseudohyphae Found</li>
                                    <li class="gram_item" data-value="Fungus : Pseudohyphae Found">Fungus : Pseudohyphae Found</li>
                                </ul>
                            </td>
                        @endif
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("input", function (event) {
    if (event.target.tagName.toLowerCase() !== "textarea") return;
    
    event.target.style.height = "auto"; // รีเซ็ตความสูงก่อน
    event.target.style.height = (event.target.scrollHeight) + "px"; // ปรับตามเนื้อหา
}, false);
</script>
<style>
    .gram_item {
        cursor: pointer; /* เปลี่ยนเป็นรูปนิ้วชี้ */
        color: black;    /* สีปกติ */
    }

    .gram_item:hover {
        color: red; /* เปลี่ยนเป็นสีแดงเมื่อ hover */
    }
</style>


    <script>
    $(document).off('click', '.gram_item').on('click', '.gram_item', function () {
    const text = $(this).data('value'); // ดึงค่าจาก data-value
    const modalId = $(this).closest('.modal').attr('id'); // หา ID ของ Modal
    const textareaId = modalId.replace('modal-', 'result-'); // หา ID ของ Textarea
    const textarea = $('#' + textareaId); // หา Textarea

    // อ่านค่าปัจจุบัน และเพิ่มค่าใหม่
    const currentValue = textarea.val();
    textarea.val(currentValue + text + ', ');
});


</script>


            @break

                    @case(2)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$wetResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    @break

                    @case(3)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$kohResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    @break

                    @case(4)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$cultureResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    {{-- Culture ขึ้นเชื้อ -> ผลความไวต่อยา (GC AST) — ห้อง Lab ขอ 2569-09-30
                         เป็นลิงก์ธรรมดา ไม่มีสคริปต์ (ตารางนี้บางหน้าโหลดผ่าน AJAX และอยู่ในฟอร์มลงผล) --}}
                    @if($item->needsAst() || ! empty($item->ast))
                        @php
                            $__astAlerts  = \App\Support\GcAst::alerts($item->ast);
                            // AST ลง/แก้หลัง Approve — ฝั่งพยาบาล/หมอยังไม่ให้เห็นจนกว่า Lab จะ Approve ใหม่ (รีวิว 2569-09-30)
                            $__astPending = ! empty($item->ast) && \App\Support\GcAst::pendingApproval(\App\Models\Lab::find($item->lab_id), $item->ast);
                        @endphp
                        <div class="mt-1 small">
                            @if(! empty($item->ast) && $__astPending)
                                <div class="text-warning fw-bold" style="color:#b45309 !important;"><i class="fa fa-clock"></i> AST แก้หลัง Approve — รอ Approve อีกครั้ง</div>
                            @endif
                            @if(! empty($item->ast) && (! $__astPending || $__isLabRoom))
                                <div class="{{ $__astAlerts ? 'text-danger fw-bold' : 'text-muted' }}">
                                    @if($__astAlerts)<i class="fa fa-triangle-exclamation"></i> ดื้อ {{ implode(', ', $__astAlerts) }} — @endif
                                    AST: {{ \App\Support\GcAst::summary($item->ast) }}
                                </div>
                            @endif
                            @if($__isLabRoom)
                                <a href="{{ route('lab-sub.ast', $item->id) }}" class="btn btn-sm {{ empty($item->ast) ? 'btn-warning' : 'btn-outline-secondary' }} mt-1">
                                    <i class="fa fa-pills"></i> {{ empty($item->ast) ? 'ลงผล AST (ความไวต่อยา)' : 'แก้ผล AST' }}
                                </a>
                            @elseif(! empty($item->ast) && ! $__astPending)
                                <a href="{{ route('lab-sub.ast', $item->id) }}" target="_blank" class="small">ดูผล AST เต็ม</a>
                            @endif
                        </div>
                    @endif
                    @break

                    @case(5)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$pcrResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    @break

                    @case(6) {{-- HIV --}}
    <select class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
        @foreach (App\Models\LabSub::$hivResultOption as $key => $value)
            <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                {{ $value }}
            </option>
        @endforeach
    </select>
    {{-- ✅ เพิ่มบรรทัดนี้ครับ --}}
    <input type="text" class="form-control track border mt-1 remark-input" 
           name="remark[{{$item->id}}]" 
           value="{{ $item->remark ?? '' }}" 
           placeholder="Remark (เหตุผล)">
    @break

@case(7) {{-- TPHA --}}
    <select class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
        @foreach (App\Models\LabSub::$tphrResultOption as $key => $value)
            <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                {{ $value }}
            </option>
        @endforeach
    </select>
    {{-- ✅ เพิ่มบรรทัดนี้ครับ --}}
    <input type="text" class="form-control track border mt-1 remark-input" 
           name="remark[{{$item->id}}]" 
           value="{{ $item->remark ?? '' }}" 
           placeholder="Remark (เหตุผล)">
    @break

@case(8) {{-- RPR --}}
    <select class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
        @foreach (App\Models\LabSub::$rprResultOption as $key => $value)
            <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                {{ $value }}
            </option>
        @endforeach
    </select>
    {{-- ✅ เพิ่มบรรทัดนี้ครับ --}}
    <input type="text" class="form-control track border mt-1 remark-input" 
           name="remark[{{$item->id}}]" 
           value="{{ $item->remark ?? '' }}" 
           placeholder="Remark (เหตุผล)">
    @break

                    @case(11)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$HBsAgResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    @break
                    @case(12)
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$AntiHCVResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    @break
                    @case(13)
                    @if ($item->specimen_from == 3)
                        <input type="text" class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]"
                            value="{{ $item->result }}" >
                    @else
                        <select class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                            @foreach (App\Models\LabSub::$papResultOption as $key => $value)
                            <option value="{{ $value }}" @if ($item->result == $value) selected="selected" @endif>
                            {{ $value }}
                            </option>
                            @endforeach
                        </select>
                    @endif
                    @break
                    @case(14) {{-- HBs Ab --}}
                    {{-- 1. ช่องลงผล Pos/Neg เหมือนรายการอื่นๆ --}}
                    <select class="form-control track border"  id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}">
                        @foreach (App\Models\LabSub::$HBsAbResultOption as $key => $value)
                        <option value="{{ $value }}" @if (isset($item->result) && $value == $item->result) selected="selected" @endif>
                            {{ $value }}
                        </option>
                        @endforeach
                    </select>
                    
                    {{-- 2. ช่องลงค่า (mIU/mL) ย้ายมาเก็บที่ remark แทน --}}
                    <input type="text" class="form-control track border mt-1 remark-input" 
                           name="remark[{{$item->id}}]" 
                           value="{{ $item->remark ?? '' }}" 
                           placeholder="ระบุค่า mIU/mL">
                    @break
                    @default
                    <input class="form-control track border" id="result[{{$item->id}}]" name="result[{{$item->id}}]" org="{{ $item->result }}"  value="{{ $item->result }}" type="text" >
                    @endswitch
                    @endif
                    @endif {{-- DTX / EKG (ตรวจข้างเตียง) --}}

                </td>
                @if(isset($addResult) && $addResult)
                <td>
                    <!-- <a href="{{ url('/backend/lab-sub/' . $item->id) }}" title="View LabSub"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> -->
                    <a href="{{ route('lab-sub.result',['id'=>$item->id,'page'=>request('page', 1)]) }}" title="Result LabSub" class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i> </a>

                </td>
                @else
                <td>
                    <!-- <a href="{{ url('/backend/lab-sub/' . $item->id) }}" title="View LabSub"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> -->
                    <a href="{{ route('lab-sub.edit',['lab_sub'=>$item->id,'page'=>request('page', 1)]) }}" title="Edit LabSub" class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i> </a>
                    {{-- ห้ามใช้ <form> ตรงนี้ ตารางนี้ถูก include อยู่ในฟอร์มหลักของหน้าตรวจ
                         ฟอร์มซ้อนฟอร์มผิดกติกา HTML เบราว์เซอร์จะทิ้งฟอร์มลบแล้วปุ่มไปกดบันทึกฟอร์มหลักแทน
                         (บั๊กเดียวกับปุ่มลบคู่สัมผัส แก้พร้อมกัน 2569-09-08) --}}
                    <button type="button" class="btn btn-danger btn-sm js-ls-delete"
                        data-url="{{ route('lab-sub.destroy',['lab_sub'=>$item->id,'page'=>request('page', 1)]) }}"
                        data-page="{{ request('page', 1) }}"
                        title="Delete LabSub"><i class="fa fa-trash" aria-hidden="true"></i></button>
                </td>
                @endif

            </tr>
            @endforeach
        </tbody>
    </table>
</div>
<script>
$( document ).ready(function() {
    $(".track").change(function(){
        console.log("track")
        if($(this).val()!=$(this).attr('org')){
            $(this).addClass('border-danger')
        }else{
            $(this).removeClass('border-danger')
        }
       
    })
});
</script>
<script>
    let selectedRows = "";
let rowIndex = 1; // เพิ่มตัวแปรนับลำดับ
    // ข้อความที่ต่างกันตามภาษา (คิดฝั่งเซิร์ฟเวอร์) — ดู App\Support\LabReportLang
    @php
        $__lrLab = $lab ?? null; $__lrPt = $patient ?? null; $__lrL = [];
        foreach (['th', 'en'] as $__lg) {
            $__lrL[$__lg] = [
                'name' => \App\Support\LabReportLang::patientName($__lrPt, $__lg),
                'age' => \App\Support\LabReportLang::age($__lrPt, $__lg),
                'tested' => \App\Support\LabReportLang::staffName($__lrLab->tested_by ?? null, $__lg),
                'testedDate' => \App\Support\LabReportLang::date($__lrLab->tested_date ?? null, $__lg),
                'reported' => \App\Support\LabReportLang::staffName($__lrLab->report_by ?? null, $__lg),
                'reportedDate' => \App\Support\LabReportLang::date($__lrLab->report_date ?? null, $__lg, $__lrLab->report_at ?? null),
                'approved' => \App\Support\LabReportLang::staffName($__lrLab->approve_by ?? null, $__lg),
                'approvedDate' => \App\Support\LabReportLang::date($__lrLab->approve_date ?? null, $__lg, $__lrLab->approve_at ?? null),
            ];
        }
        $__lrMissing = \App\Support\LabReportLang::missing($__lrPt, [$__lrLab->tested_by ?? null, $__lrLab->report_by ?? null, $__lrLab->approve_by ?? null]);
    @endphp
    var LAB_LANG = @json($__lrL), LAB_EN_MISSING = @json($__lrMissing);
    function __labPrint(lang) {
    var L = LAB_LANG[lang] || LAB_LANG.th;
    if (lang === 'en' && LAB_EN_MISSING.length
        && !confirm('ยังไม่มีภาษาอังกฤษ: ' + LAB_EN_MISSING.join(', ') + String.fromCharCode(10) + 'ใบจะใช้ภาษาไทยแทนในส่วนนั้น — พิมพ์ต่อไหม?')) return;
    let selectedRows = "";
    let hivLocked = false;
    document.querySelectorAll("input.lab-checkbox:checked").forEach(function (checkbox) {
    const row = checkbox.closest("tr");
    const sequence = row.children[1].textContent.trim(); 
    const method = row.children[2].textContent.trim(); 
    const specimen = row.children[3].textContent.trim(); 

    let result = "";
    const textarea = row.querySelector("textarea");
    const select = row.querySelector("select:not(.tested-input)");
    const input = row.querySelector("input[type='text']:not(.remark-input):not(.tested-input)"); // ⚠️ แก้: ไม่เอา input ที่เป็น remark / ผู้ทดสอบ
    
    // ดึงค่า Result หลัก
    const hivMask = row.querySelector(".js-hiv-mask");
    if (hivMask) {
        // ผล HIV ที่ปิดไว้ (คลินิก TB) — ไม่มีค่าในหน้า ต้องกดดูก่อนจึงพิมพ์ได้
        if (hivMask.dataset.open !== "1") { hivLocked = true; return; }
        result = hivMask.dataset.hivValue || "";
    } else if (textarea) {
        result = textarea.value.trim();
    } else if (select) {
        result = select.options[select.selectedIndex].text.trim();
    } else if (input) {
        result = input.value.trim(); 
    }

// ✅ ส่วนที่เพิ่มใหม่: ดึงค่า Remark (เหตุผล)
    let remarkText = "";
    const remarkInput = row.querySelector(".remark-input"); // หา input ที่มี class remark-input
    if (remarkInput && remarkInput.value.trim() !== "") {
        let remarkVal = remarkInput.value.trim();
        
        // เช็คว่า Method มีคำว่า HBs Ab หรือไม่
        if (method.includes("HBs Ab")) {
            // ถ้าใช่ ให้แสดงตัวเลขพร้อมหน่วย mIU/mL
            remarkText = ` <br><span style="font-size: 0.9em; color: #555;">(${remarkVal} mIU/mL)</span>`;
        } else {
            // ถ้าเป็นรายการอื่น แสดงคำว่า Note นำหน้าปกติ
            remarkText = ` <br><span style="font-size: 0.9em; color: #555;">(Note: ${remarkVal})</span>`;
        }
    }

    // ตรวจสอบคำว่า "positive"
    const isPositive = result.toLowerCase().includes("positive");
    const resultClass = isPositive ? "text-red" : "";

    // ✅ แก้ไข: เพิ่ม ${remarkText} ต่อท้าย result
    selectedRows += `
        <tr>
            <td>${rowIndex}</td>
            <td>${method}</td>
            <td>${specimen}</td>
            <td class="${resultClass}">
                ${result || "-"}${remarkText}
            </td>
        </tr>
    `;

    rowIndex++; 
});

    if (hivLocked) {
        alert('ผล HIV ถูกปิดไว้ — กด "กดดู" ที่ผล HIV ก่อน แล้วค่อยพิมพ์');
        return;
    }
    if (!selectedRows) {
        alert("กรุณาเลือกข้อมูลอย่างน้อย 1 แถวก่อนพิมพ์");
        return;
    }

    const currentDate = new Date().toLocaleDateString("th-TH", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });

    const printContent = `
        <html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Selected Rows</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css?family=Prompt:400,600&display=swap" rel="stylesheet">
<style>
body, .chartjs-render-monitor { font-family: 'Prompt', 'Nunito', sans-serif; }
</style>
    <style>
    @import url('https://fonts.googleapis.com/css2?family=Prompt:wght@400;600&display=swap');    
        .text-red {
        color: red !important;
        font-weight: bold;
    }
        @media print {
            @page {
                size: A4; /* กำหนดขนาดหน้ากระดาษเป็น A4 */
                margin: 10mm; /* กำหนดระยะขอบ */
            }

        body {
            font-family: 'Prompt', 'Nunito', sans-serif;
            line-height: 1.6;
        font-size: 14px; /* 🔽 ลดขนาดตัวอักษร */
        }

        .header-table {
            width: 100%;
            margin-bottom: 20px;
        }

        .header-table td {
            border: none !important;
        }

        .header-table img {
            max-height: 120px;
        }

        .header-title {
            font-size: 20px;
            font-weight: bold;
        }

        .header-subtitle {
            font-size: 11px;
        }

        table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }

        table, th, td {
            border: 1px solid #000;
        font-size: 14px;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }
        h3 {
        font-size: 18px;
        }

        thead {
            background-color: #f1f1f1;
        }
        .text-red {
        color: red;
        font-weight: bold;
    }
    </style>
</head>
<body class="container">
    <table class="header-table">
        <tr>
            <td class="text-center" style="width: 12%;">
                <img src="{{ asset('images/logo-กรม-2566 png.png') }}" alt="Logo">
            </td>
            <td class="text-center" style="width: 46%;">
                <div class="header-title"><h3>Laboratory Test Report</h3></div>
                <div class="header-subtitle">Medical Laboratory for Disease Control, Office of Disease Prevention and Control 1 Chiang Mai</div>
                <div class="header-subtitle">
                    143 Sri Don Chai Road, Chang Klan Subdistrict, Mueang District, Chiang Mai 50000<br>
                    Telephone: 053-140 774 to 6
                </div>
            </td>
            <td class="text-center" style="width: 12%;">
                <img src="{{ asset('images/S__21831716.png') }}" alt="Logo Right">
            </td>
        </tr>
    </table>

    <div class="row mb-4">
        <div class="col-md-6">
            <div><strong>Patient Number (HN):</strong> {{ $patient->code ?? '' }}</div>
            <div><strong>Patient Name:</strong> ${L.name}</div>
        </div>
        <div class="col-md-6">
            <div><strong>Sex:</strong>
    {{ optional($patient)->sex == '1' ? 'M' : (optional($patient)->sex == '2' ? 'F' : '') }}
</div>

            <div><strong>Age:</strong> ${L.age}</div>
        </div>
    </div>

    <h3>Result</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Method</th>
                <th>Specimen From</th>
                <th>Result</th>
            </tr>
        </thead>
        <tbody>
            ${selectedRows}
        </tbody>
    </table>
        # Tests that has been accredited complying with the ISO 15189 standard for medical laboratories.
        <br><br>
<style>
  table, th, td {
    border: none !important; /* ลบขอบของตารางทั้งหมด */
  }

  input {
    border: none !important; /* ลบขอบของ input */
    background-color: white; /* กำหนดพื้นหลังเป็นสีขาว */
  }
</style>

<table class="table table-sm table-borderless">
  <tbody>
       
    {{-- ผู้ทดสอบ (ระดับใบ Lab — ห้อง Lab ให้กลับมาอยู่ท้ายใบ 2569-09-30) --}}
    <tr>
      <th scope="row" class="align-middle"></th>
      <td class="text-center" style="font-size: 13px;">Tested By</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.tested}</td>
      </td>
      <td></td>
      <td class="text-center" style="font-size: 13px;">Tested Date</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.testedDate}</td>
      </td>
      <td></td>
    </tr>
    <tr>
      <th scope="row" class="align-middle"></th>
      <td class="text-center" style="font-size: 13px;">Reported By</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.reported}</td>
      </td>
      <td></td>
      <td class="text-center" style="font-size: 13px;">Reported Date</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.reportedDate}</td>
      </td>
      <td></td>
    </tr>
    <tr>
      <th scope="row" class="align-middle"></th>
      <td class="text-center" style="font-size: 13px;">Approved By</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.approved}</td>
      </td>
      <td></td>
      <td class="text-center" style="font-size: 13px;">Approved Date</td>
      <td class="text-center">
        <td style="font-size: 14px;">${L.approvedDate}</td>
      </td>
      <td></td>
    </tr>
  </tbody>
</table>




 
</body>
</html>
    `;

    const printWindow = window.open("", "_blank");
    printWindow.document.write(printContent);
    printWindow.document.close();
    // printWindow.print();
}
    document.getElementById("printButton").addEventListener("click", function () { __labPrint('th'); });
    document.getElementById("printButtonEn").addEventListener("click", function () { __labPrint('en'); });

</script>
   {{-- ไม่โหลด Bootstrap ซ้ำ: app.js มี Bootstrap 5 อยู่แล้ว การโหลดซ้ำทำให้ dropdown/modal ทำงานสองรอบ (เปิดแล้วเด้งปิดทันที) เช่น เมนูชื่อผู้ใช้บน navbar กดไม่ออกในหน้า visit/edit --}}
   <script>
document.addEventListener('DOMContentLoaded', function () {
    const textareas = document.querySelectorAll('textarea.track');

    textareas.forEach(textarea => {
        // ฟังก์ชันปรับขนาด
        const resize = () => {
            textarea.style.height = 'auto'; // รีเซ็ตก่อน
            textarea.style.height = textarea.scrollHeight + 'px'; // ตั้งความสูงเท่ากับเนื้อหา
        };

        // เรียก resize ครั้งแรกเมื่อโหลด
        resize();

        // เรียกทุกครั้งเมื่อพิมพ์
        textarea.addEventListener('input', resize);
    });
});
</script>

{{-- ===== Popup ผลย้อนหลังรายรายการ ===== --}}
{{-- โหลดเนื้อหาด้วย AJAX ตอนกดเท่านั้น หน้าตรวจจึงไม่หนักขึ้น และไม่รบกวนข้อมูลที่ยังไม่ได้ Save --}}
<div class="modal fade" id="labTrendModal" tabindex="-1" aria-labelledby="labTrendLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="labTrendLabel">
                    <i class="fas fa-chart-line me-1"></i> ผลย้อนหลัง
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="labTrendBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <div class="mt-2">กำลังโหลด...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    // กันผูก handler ซ้ำ เผื่อ partial นี้ถูก include มากกว่า 1 ครั้งในหน้าเดียว
    if (window.__labTrendInit) return;
    window.__labTrendInit = true;

    var ctrl = null;

    // ใช้ event delegation ที่ document เพื่อให้ยังทำงานหลังตารางถูก swap ด้วย AJAX
    // การ "เปิด" modal ปล่อยให้ data-bs-toggle จัดการ ที่นี่ทำแค่โหลดเนื้อหาใส่
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-lab-trend');
        if (!btn) return;

        var body = document.getElementById('labTrendBody');
        if (!body) return;
        body.innerHTML = '<div class="text-center text-muted py-4">'
            + '<i class="fas fa-spinner fa-spin fa-2x"></i><div class="mt-2">กำลังโหลด...</div></div>';

        // ยกเลิกคำขอเก่าถ้ากดปุ่มอื่นซ้อนเข้ามา จะได้ไม่แสดงผลของรายการที่ไม่ได้กด
        if (ctrl) ctrl.abort();
        ctrl = new AbortController();

        fetch(btn.dataset.url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: ctrl.signal
        })
        .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
        .then(function (html) { body.innerHTML = html; })
        .catch(function (err) {
            if (err.name === 'AbortError') return;
            body.innerHTML = '<div class="alert alert-danger mb-0">'
                + '<i class="fas fa-triangle-exclamation me-1"></i> โหลดผลย้อนหลังไม่สำเร็จ กรุณาลองใหม่</div>';
        });
    });
})();
</script>
@if($__hivMask)
    {{-- ผล HIV ของ visit TB ปิดไว้ — ตัวจัดการ "กดดู" (กันทำงานซ้ำในตัว ถ้าหน้ามีแผง Lab ในคลินิกอยู่แล้ว) --}}
    @include('partials.hiv_mask_js')
@endif
