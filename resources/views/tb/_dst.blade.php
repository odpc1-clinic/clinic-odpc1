{{-- ผลทดสอบความไวต่อยา (DST) ของบัตร TB 01
     แยกออกมาจาก tb/_sections (2569-09-29) เพื่อวางไว้ใต้ผลตรวจเสมหะรายเดือน
     ใช้ตัวแปร $reg ต้องโหลด susceptibilities มาแล้ว · มี <form> ของตัวเอง ห้ามวางในฟอร์มอื่น --}}
{{-- ---------- ผลความไวต่อยา (DST) ---------- --}}
<form method="POST" action="{{ route('tb.dst', $reg->id) }}" autocomplete="off" class="mb-3" id="tb-dst">
    @csrf
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fa fa-flask-vial me-1"></i> ผลทดสอบความไวต่อยา (DST)</h6>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-1" id="tb-dst-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width:8.5rem">วันที่ส่ง</th>
                            <th style="width:8.5rem">วันที่ได้ผล</th>
                            <th style="width:7rem">วิธี</th>
                            {{-- คลินิกเรียกเลขนี้ว่า LSN (Lab Serial No.) — คอลัมน์ในฐานข้อมูลยังชื่อ cs_no ตามเดิม (2569-09-29) --}}
                            <th style="width:7rem">LSN.</th>
                            @foreach(\App\Models\TbDrugSusceptibility::$drugs as $drug)
                                <th style="width:3.2rem" class="text-center">{{ $drug }}</th>
                            @endforeach
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $dstRows = $reg->susceptibilities; @endphp
                    @for($i = 0; $i < $dstRows->count() + 1; $i++)
                        @php $d = $dstRows->get($i); $res = (array) ($d->results ?? []); @endphp
                        <tr>
                            <td>
                                <input type="hidden" name="dst[{{ $i }}][id]" value="{{ $d->id ?? '' }}">
                                <input type="text" name="dst[{{ $i }}][sent_date]" class="form-control form-control-sm tb-date"
                                       value="{{ $d && $d->sent_date ? \App\Utillity::db2thDate($d->sent_date) : '' }}" placeholder="วว/ดด/ปปปป">
                            </td>
                            <td><input type="text" name="dst[{{ $i }}][result_date]" class="form-control form-control-sm tb-date"
                                       value="{{ $d && $d->result_date ? \App\Utillity::db2thDate($d->result_date) : '' }}" placeholder="วว/ดด/ปปปป"></td>
                            <td>
                                <select name="dst[{{ $i }}][method]" class="form-select form-select-sm">
                                    <option value="">—</option>
                                    @foreach(\App\Models\TbDrugSusceptibility::$methodOption as $k => $label)
                                        <option value="{{ $k }}" {{ (int) ($d->method ?? 0) === $k ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                    {{-- แถวเก่าที่บันทึกไว้ตอนยังเป็น "Molecular" รวม ๆ — ยังเลือกค้างไว้ได้ ไม่ให้ข้อมูลหาย --}}
                                    @foreach(\App\Models\TbDrugSusceptibility::$methodLegacy as $k => $label)
                                        @if((int) ($d->method ?? 0) === $k)
                                            <option value="{{ $k }}" selected>{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </td>
                            <td><input type="text" name="dst[{{ $i }}][cs_no]" class="form-control form-control-sm" value="{{ $d->cs_no ?? '' }}" placeholder="LSN"></td>
                            @foreach(\App\Models\TbDrugSusceptibility::$drugs as $drug)
                                <td class="p-1">
                                    <select name="dst[{{ $i }}][results][{{ $drug }}]" class="form-select form-select-sm p-1" style="font-size:.8rem;">
                                        <option value="">—</option>
                                        @foreach(\App\Models\TbDrugSusceptibility::$resultOption as $rk => $rl)
                                            <option value="{{ $rk }}" {{ ($res[$drug] ?? '') === $rk ? 'selected' : '' }}>{{ $rk }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            @endforeach
                            <td><input type="text" name="dst[{{ $i }}][note]" class="form-control form-control-sm" value="{{ $d->note ?? '' }}"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn btn-outline-primary btn-sm js-tb-add-row" data-table="tb-dst-table">
                <i class="fa fa-plus"></i> เพิ่มช่อง
            </button>
            <div class="small text-muted mt-1">S = ไว · R = ดื้อ · I = ก้ำกึ่ง · ยาที่ไม่มีในตาราง (เช่น BDQ, DLM, LZD) ให้เขียนในหมายเหตุ</div>
        </div>
        <div class="card-footer bg-white"><button class="btn btn-primary"><i class="fa fa-save"></i> บันทึกผลความไวต่อยา</button></div>
    </div>
</form>
