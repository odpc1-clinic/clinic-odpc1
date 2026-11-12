@extends('layouts.app')

@section('content')
<div class="container-fluid">
    @include('sweetalert::alert')
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <div class="row mb-3">
        <div class="col-md-12">
            <a href="{{ url('/pharmacy') }}" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> กลับหน้ารายการคิว</a>
        </div>
    </div>

    <form action="{{ url('/pharmacy/'.$visit->id.'/update') }}" method="POST">
        @csrf
        <div class="row">
            <div class="col-md-8">
                <div class="card mb-4 shadow-sm">
                    <div class="card-body bg-light">
                        <div class="row">
                            <div class="col-md-4">
                                <strong>HN (Code):</strong> {{ $visit->patient2->code ?? '-' }}
                            </div>
                            <div class="col-md-5">
                                <strong>ชื่อ-สกุล:</strong> {{ \App\Models\Patient::prefixLabel($visit->patient2) ?? '' }}{{ $visit->patient2->name ?? '' }} {{ $visit->patient2->surname ?? '' }}
                            </div>
                            <div class="col-md-3">
                                <span style="background-color: #ff0000 !important; color: #ffffff !important; padding: 5px 10px; border-radius: 10px; font-weight: bold;">
                                    คิวที่: {{ $visit->queue ?? '-' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <h6 class="text-primary fw-bold mb-2"><i class="fa fa-pills"></i> จ่ายยา · คิดเงิน · รับชำระ</h6>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0" style="color:#ffffff !important;"><i class="fa fa-pills"></i> รายการยาที่แพทย์สั่ง</h5>
                    </div>
                    <div class="card-body p-0">
                        @if($stockAlreadyDeducted)
                        <div class="alert alert-secondary mb-0 rounded-0"><i class="fa fa-check-circle"></i> บิลนี้หักสต๊อกยาไปแล้ว การแก้ไขจำนวนรอบนี้จะไม่หักสต๊อกซ้ำ</div>
                        @endif
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>รายการ</th>
                                    <th class="text-center" width="11%">วิธีใช้ (ที่แพทย์สั่ง)</th>
                                    <th class="text-center" width="9%">คงเหลือ</th>
                                    <th class="text-center" width="20%">Lot ที่เบิก</th>
                                    <th class="text-center" width="11%">จำนวนที่จ่ายจริง</th>
                                    <th class="text-center" width="13%">ราคา/หน่วย</th>
                                    <th class="text-center" width="13%">รวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($medicines as $med)
                                <tr>
                                    <td class="align-middle">{{ $med->name }}</td>
                                    <td class="text-center align-middle">{{ $med->amount }}</td>
                                    <td class="text-center align-middle {{ $med->stock <= 0 ? 'text-danger fw-bold' : '' }}">
                                        {{ $med->stock }} {{ $med->unit }}
                                    </td>
                                    <td>
                                        {{-- เลือก lot เอง (ว่างไว้ = อัตโนมัติ FEFO เบิก lot ใกล้หมดอายุก่อน) --}}
                                        <select class="form-select form-select-sm" name="medicines[{{ $med->id }}][lot_id]">
                                            <option value="">อัตโนมัติ (FEFO)</option>
                                            @foreach($med->lots as $lot)
                                                <option value="{{ $lot->id }}">
                                                    {{ $lot->lot_number ?? ('lot#'.$lot->id) }} · เหลือ {{ $lot->quantity_remaining }}@if($lot->expiry_date) · หมดอายุ {{ $lot->expiry_date->format('d/m/y') }}@endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control text-right med-qty calc-input" name="medicines[{{ $med->id }}][qty]" value="{{ $med->qty }}" step="1" min="0">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control text-right med-price calc-input" name="medicines[{{ $med->id }}][price]" value="{{ $med->price }}" step="0.01">
                                    </td>
                                    <td>
                                        <input type="text" class="form-control text-right med-total" value="{{ number_format($med->total, 2) }}" readonly>
                                    </td>
                                </tr>
                                @empty
                                <tr><td colspan="7" class="text-center text-muted">ไม่มีรายการยา</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header" style="background-color: #17a2b8 !important; border-bottom: 2px solid #138496;">
                        <h5 class="card-title mb-0" style="color:#ffffff !important;"><i class="fa fa-flask"></i> รายการตรวจทางห้องปฏิบัติการ (Labs)</h5>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>รายการ</th>
                                    <th class="text-center" width="20%">ราคา</th>
                                </tr>
                            </thead>
                            <tbody>
    @forelse($labs as $lab)
    <tr>
        <td class="align-middle">
            @php
                // ดึง Model LabSub ตัวจริงขึ้นมา เพื่อให้เรียกใช้ method() และ specimenFrom() ได้ 100%
                $realLabSub = \App\Models\LabSub::find($lab->id);
            @endphp

            @if($realLabSub)
                <strong>{{ $realLabSub->method() }}</strong>
                <span class="text-muted small">
                    ({{ $realLabSub->specimenFrom() === '-' ? 'ไม่ระบุแหล่งที่มา' : $realLabSub->specimenFrom() }})
                </span>
            @else
                {{ $lab->name ?? 'รายการ Lab' }}
            @endif
            @if(!empty($lab->free))
                <span class="badge bg-success ms-1" title="RPR ฟรี: วินิจฉัย Syphilis + สั่ง TPHA คู่กัน"><i class="fas fa-gift"></i> ฟรี (สั่งคู่ TPHA)</span>
            @elseif(($lab->category ?? '') === 'manual')
                <span class="badge bg-warning text-dark ms-1" title="Gram/C&S/Pap/PCR ไม่คิดอัตโนมัติ — คลินิกคิดเอง หรือรวมในแพ็กเกจ"><i class="fas fa-user-nurse"></i> คลินิกคิดเอง/แพ็ก</span>
            @endif
        </td>
        <td>
            <input type="number" id="lab-{{ $lab->id }}"
                   class="form-control text-right lab-price calc-input" data-waived="{{ !empty($lab->waived) ? 1 : 0 }}"
                   @if(!empty($lab->waived)) style="text-decoration:line-through; opacity:.5;" @endif
                   name="labs[{{ $lab->id }}][price]"
                   value="{{ $lab->price ?? 0 }}"
                   step="0.01"
                   {{ (!empty($lab->free) || !empty($lab->sso_claimed)) ? 'readonly' : '' }}>
            @if(($lab->category ?? '') === 'sso')
                <div class="form-check mt-1">
                    <input type="checkbox" class="form-check-input sso-claim" id="sso-{{ $lab->id }}"
                           data-target="lab-{{ $lab->id }}" data-master="{{ $lab->master_price ?? 0 }}"
                           {{ !empty($lab->sso_claimed) ? 'checked' : '' }}>
                    <label class="form-check-label small text-success" for="sso-{{ $lab->id }}">
                        <i class="fas fa-hand-holding-medical"></i> เบิก สปสช (ไม่คิดเงิน)
                    </label>
                </div>
            @endif
        </td>
    </tr>
    @empty
    <tr><td colspan="2" class="text-center text-muted">ไม่มีรายการตรวจ Lab</td></tr>
    @endforelse
</tbody>
        </table>
                    </div>
                </div>

                {{-- Lab ส่งตรวจภายนอก: ดูอย่างเดียว ราคาถูกกรอกจากหน้าตรวจโดยพยาบาล
                     รวมอยู่ในยอด "รวมค่า Lab" ด้านขวาแล้ว --}}
                @isset($externalLabs)
                    @if($externalLabs->count())
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2"
                                 style="background-color:#0dcaf0 !important; border-bottom:2px solid #0aa2c0;">
                                <h5 class="card-title mb-0" style="color:#053f4a !important;">
                                    <i class="fa fa-truck-medical"></i> Lab ส่งตรวจภายนอก
                                </h5>
                                <span class="small" style="color:#053f4a;">พยาบาลกรอกราคาจากหน้าตรวจ · แก้ที่นี่ไม่ได้</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>รายการ</th>
                                            <th class="text-center" width="20%">ราคา</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($externalLabs as $ex)
                                        <tr>
                                            <td class="align-middle">
                                                <strong>{{ $ex->name }}</strong>
                                                @if($ex->code)<span class="badge bg-light text-muted border ms-1">{{ $ex->code }}</span>@endif
                                                @unless($ex->hasResult())
                                                    <span class="badge bg-secondary ms-1">รอผล</span>
                                                @endunless
                                            </td>
                                            <td class="text-right align-middle">
                                                @if((float) $ex->price > 0)
                                                    <span class="ext-lab-price" data-price="{{ (float) $ex->price }}">
                                                        {{ number_format((float) $ex->price, 2) }}
                                                    </span>
                                                @else
                                                    <span class="text-muted small ext-lab-price" data-price="0">ยังไม่ได้ใส่ราคา</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endisset

                {{-- ค่าบริการ (Services) — ติ๊กเลือกจากรายการมาตรฐาน --}}
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2" style="background-color:#6f42c1 !important; border-bottom:2px solid #59359a;">
                        <h5 class="card-title mb-0" style="color:#fff !important;"><i class="fa fa-hand-holding-medical"></i> ค่าบริการ (Services)</h5>
                        <div class="d-flex align-items-center gap-3">
                            <span class="text-white small"><i class="fas fa-hand-holding-heart"></i> ติ๊ก "ยกเว้น" รายตัว = อนุเคราะห์ (แพ็กเกจยกเว้นไม่ได้)</span>
                            @hasanyrole('Pharmacy|Admin')
                            <a href="{{ route('service-price.index') }}" target="_blank" class="btn btn-light btn-sm" title="จัดการรายการค่าบริการ"><i class="fas fa-gear"></i> จัดการ</a>
                            @endhasanyrole
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th width="8%" class="text-center">เลือก</th>
                                    <th>รายการ</th>
                                    <th class="text-center" width="13%">จำนวน</th>
                                    <th class="text-center" width="16%">ราคา/หน่วย</th>
                                    <th class="text-center" width="10%">ยกเว้น</th>
                                    <th class="text-center" width="16%">รวม</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($serviceMaster as $svc)
                                @php
                                    $checked = isset($savedQtys['service_' . $svc->id]);
                                    $svcWaived = !empty($savedWaived['service_' . $svc->id]);
                                @endphp
                                <tr>
                                    <td class="text-center align-middle">
                                        <input type="checkbox" class="form-check-input svc-use" name="services[{{ $svc->id }}][use]" value="1" {{ $checked ? 'checked' : '' }}>
                                        <input type="hidden" name="services[{{ $svc->id }}][name]" value="{{ $svc->name }}">
                                    </td>
                                    <td class="align-middle">
                                        {{ $svc->name }}
                                        @if($svc->is_package)<span class="badge bg-info-subtle text-info-emphasis ms-1">แพ็กเกจ</span>@endif
                                    </td>
                                    <td>
                                        <input type="number" class="form-control text-right svc-qty calc-input" name="services[{{ $svc->id }}][qty]" value="{{ $savedQtys['service_' . $svc->id] ?? 1 }}" min="1" step="1">
                                    </td>
                                    <td>
                                        <input type="number" class="form-control text-right svc-price calc-input" name="services[{{ $svc->id }}][price]" value="{{ $savedPrices['service_' . $svc->id] ?? $svc->price }}" step="0.01" min="0">
                                    </td>
                                    <td class="text-center align-middle">
                                        @if($svc->is_package)
                                            <span class="text-muted small" title="แพ็กเกจเหมาจ่าย ยกเว้นไม่ได้">คิดเสมอ</span>
                                        @else
                                            <input type="hidden" name="services[{{ $svc->id }}][waive]" value="0">
                                            <input type="checkbox" class="form-check-input svc-waive" style="transform:scale(1.3);" name="services[{{ $svc->id }}][waive]" value="1" title="ขอความอนุเคราะห์ (ไม่คิดรายการนี้)" {{ $svcWaived ? 'checked' : '' }}>
                                        @endif
                                    </td>
                                    <td>
                                        <input type="text" class="form-control text-right svc-total" value="0.00" readonly>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">ยังไม่มีรายการค่าบริการ (เพิ่มได้ที่หน้าจัดการค่าบริการ)</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-header bg-dark text-white">
                        <h5 class="card-title mb-0" style="color:#ffffff !important;"><i class="fa fa-calculator"></i> สรุปค่าใช้จ่าย</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label">รวมค่ายา</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right" id="summary_med" name="total_medicine" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label">รวมค่า Lab</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right" id="summary_lab" name="total_lab" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label">รวมค่าบริการ</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right bg-light" id="summary_service" name="total_service" value="0.00" readonly>
                            </div>
                        </div>
                        <hr>
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label text-danger">ส่วนลด</label>
                            <div class="col-sm-7">
                                <input type="number" class="form-control text-right calc-input text-danger" id="summary_discount" name="discount" value="{{ $visit->billings->discount ?? '0.00' }}" step="0.01">
                            </div>
                        </div>
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label font-weight-bold" style="font-size: 1.2rem;">ยอดสุทธิทั้งหมด</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right font-weight-bold bg-light" id="summary_net" name="net_total" value="0.00" style="font-size: 1.2rem; border: 2px solid #333;" readonly>
                            </div>
                        </div>

                        {{-- ข้อมูลการจ่ายเงิน (ดึงยอดเดิมที่เคยจ่ายมาแล้ว) --}}
                        <div class="form-group row mb-2">
                            <label class="col-sm-5 col-form-label text-success"><i class="fa fa-history"></i> จ่ายแล้ว (สะสม)</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right text-success bg-white" id="summary_paid_before" value="{{ $visit->billings->paid_amount ?? '0.00' }}" readonly>
                            </div>
                        </div>

                        {{-- ช่องให้แคชเชียร์กรอกเงินที่รับมาในรอบนี้ --}}
                        <div class="form-group row mb-3">
                            <label class="col-sm-5 col-form-label font-weight-bold text-primary" style="font-size: 1.1rem;">รับเงินรอบนี้</label>
                            <div class="col-sm-7">
                                <input type="number" class="form-control text-right font-weight-bold payment-input" name="amount_paid" id="amount_paid" value="0" step="0.01" style="font-size: 1.3rem; border: 2px solid #007bff; background-color: #e9f2ff;" placeholder="ใส่จำนวนเงิน">
                            </div>
                        </div>

                        <hr>
                        {{-- จุดแสดงผล ค้างชำระ หรือ เงินทอน (เปลี่ยนสีอัตโนมัติ) --}}
                        <div class="form-group row mb-4 p-2 rounded" id="balance_box" style="background-color: #f8f9fa;">
                            <label class="col-sm-5 col-form-label font-weight-bold" id="balance_label" style="font-size: 1.2rem;">ยอดค้างชำระ</label>
                            <div class="col-sm-7">
                                <input type="text" class="form-control text-right font-weight-bold" id="summary_balance" value="0.00" style="font-size: 1.4rem;" readonly>
                            </div>
                        </div>

                        <div class="form-group mb-3">
                            <label>วิธีชำระเงิน (รอบนี้)</label>
                            <select class="form-control" name="payment_method">
                                <option value="เงินสด">เงินสด</option>
                                <option value="โอนเงิน">โอนเงิน</option>
                                <option value="บัตรเครดิต/เดบิต">บัตรเครดิต/เดบิต</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>หมายเหตุ</label>
                            <textarea class="form-control" name="remark" rows="2" placeholder="เช่น จ่ายส่วนที่ค้าง, ขอส่วนลด">{{ $visit->billings->remark ?? '' }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4" style="border: 2px solid #198754;">
            <div class="card-header bg-success text-white">
                <h6 class="card-title mb-0"><i class="fa fa-hand-holding-medical"></i> ยืนยันจ่ายยา &amp; รับเงิน</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    กดปุ่มด้านล่างเมื่อส่งมอบยาและรับเงินเรียบร้อยแล้ว
                    <span class="text-danger">(ใบเสร็จให้เขียนจากเล่ม — ออกจากระบบไม่ได้ตามที่กองคลังแจ้ง)</span>
                    <br><span class="small">ระบบจะหักสต๊อกยาตอนกดจบรายการ · แก้จำนวนที่จ่ายจริงได้ถ้าของขาด</span>
                </p>
                <div class="d-flex justify-content-end gap-2 flex-wrap">
                    <button type="submit" name="next_status" value="5" class="btn btn-info text-white">
                        <i class="fa-solid fa-save me-1"></i> บันทึกข้อมูล (ยังไม่ตัดคิว)
                    </button>

                    <button type="submit" name="next_status" value="3" class="btn btn-warning text-dark">
                        <i class="fa-solid fa-syringe me-1"></i> จ่ายยาแล้ว → ส่งห้องฉีดยา
                    </button>

                    <button type="submit" name="next_status" value="6" class="btn btn-success btn-lg">
                        <i class="fa-solid fa-home me-1"></i> จ่ายยา+รับเงินแล้ว → กลับบ้าน
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        function calculateTotal() {
            let totalMed = 0; let totalLab = 0;
            $('.med-price').each(function() {
                let price = parseFloat($(this).val()) || 0;
                let qty = parseFloat($(this).closest('tr').find('.med-qty').val()) || 0;
                let rowTotal = price * qty;
                $(this).closest('tr').find('.med-total').val(rowTotal.toFixed(2));
                totalMed += rowTotal;
            });
            // การเงินอนุเคราะห์ไว้ (data-waived) = ไม่รวมยอด — ห้องยาเปลี่ยนไม่ได้ (ไม่มีช่องยกเว้นในหน้านี้)
            $('.lab-price').each(function() { if ($(this).data('waived') == 1) return; totalLab += (parseFloat($(this).val()) || 0); });
            // ค่าแล็บนอกรวมอยู่ในยอด Lab ด้วย (แก้ที่นี่ไม่ได้ จึงอ่านจาก data-price)
            $('.ext-lab-price').each(function() { totalLab += (parseFloat($(this).data('price')) || 0); });

            // ค่าบริการ: คิดเฉพาะรายการที่ติ๊กเลือก (qty x price)
            let totalService = 0;
            $('.svc-use').each(function() {
                let $row = $(this).closest('tr');
                let rowTotal = 0;
                let waived = $row.find('.svc-waive').is(':checked'); // ยกเว้นรายตัว (อนุเคราะห์)
                if ($(this).is(':checked') && !waived) {
                    let price = parseFloat($row.find('.svc-price').val()) || 0;
                    let qty = parseFloat($row.find('.svc-qty').val()) || 0;
                    rowTotal = price * qty;
                }
                $row.find('.svc-total').val(rowTotal.toFixed(2));
                totalService += rowTotal;
            });

            let discount = parseFloat($('#summary_discount').val()) || 0;
            let netTotal = (totalMed + totalLab + totalService) - discount;
            if(netTotal < 0) netTotal = 0;

            $('#summary_med').val(totalMed.toFixed(2));
            $('#summary_lab').val(totalLab.toFixed(2));
            $('#summary_service').val(totalService.toFixed(2));
            $('#summary_net').val(netTotal.toFixed(2));

            calculatePayment(netTotal);
        }

        // ฟังก์ชันคำนวณเงินทอน หรือ ค้างชำระ
        function calculatePayment(netTotal) {
            let paidBefore = parseFloat($('#summary_paid_before').val()) || 0;
            let amountPaidNow = parseFloat($('#amount_paid').val()) || 0;

            // ยอดที่ยังขาดอยู่ (สุทธิ - ที่เคยจ่ายแล้ว)
            let remaining = netTotal - paidBefore;
            if(remaining < 0) remaining = 0;

            // ค้างชำระ หรือ ทอน
            let balance = remaining - amountPaidNow;
            let balanceBox = $('#balance_box');
            let balanceLabel = $('#balance_label');
            let summaryBalance = $('#summary_balance');

            if (balance > 0) {
                // จ่ายไม่ครบ = ยอดค้างชำระ (สีแดง)
                balanceBox.css('background-color', '#ffeeba');
                balanceLabel.text('ยอดค้างชำระ').css('color', '#dc3545');
                summaryBalance.val(balance.toFixed(2)).css({'color': '#dc3545', 'border-color': '#dc3545'});
            } else if (balance < 0) {
                // จ่ายเกิน = เงินทอน (สีน้ำเงิน)
                balanceBox.css('background-color', '#d4edda');
                balanceLabel.text('เงินทอน').css('color', '#28a745');
                summaryBalance.val(Math.abs(balance).toFixed(2)).css({'color': '#28a745', 'border-color': '#28a745'});
            } else {
                // จ่ายพอดี
                balanceBox.css('background-color', '#f8f9fa');
                balanceLabel.text('ค้างชำระ / ทอน').css('color', '#333');
                summaryBalance.val('0.00').css({'color': '#333', 'border-color': '#ccc'});
            }
        }

        calculateTotal();
        $('.calc-input').on('input', function() { calculateTotal(); });
        $('.payment-input').on('input', function() { calculateTotal(); });
        $('.svc-use').on('change', function() { calculateTotal(); });
        // ยกเว้นรายตัว (อนุเคราะห์) -> คิดยอดใหม่ + หรี่แถวนั้นให้เห็นชัด
        $('.svc-waive').on('change', function() {
            var $row = $(this).closest('tr');
            var on = $(this).is(':checked');
            $row.find('.svc-price, .svc-qty, .svc-total').css({'text-decoration': on ? 'line-through' : '', 'opacity': on ? 0.5 : 1});
            calculateTotal();
        }).trigger('change');

        // เบิก สปสช (HBs Ag / Anti-HCV): ติ๊ก = ไม่คิดเงิน (0), เอาออก = คืนราคากลาง
        $('.sso-claim').on('change', function() {
            var input = document.getElementById(this.dataset.target);
            if (!input) return;
            if (this.checked) { input.value = 0; input.readOnly = true; }
            else { input.value = this.dataset.master; input.readOnly = false; }
            calculateTotal();
        });
    });
</script>

@endsection
