<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จรับเงิน - {{ $visit->patient2->name ?? '' }}</title>

    {{-- โหลดฟอนต์ Sarabun จาก Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        @media print {
            .no-print { display: none; }
            @page { margin: 0.5cm; }
        }
        /* เปลี่ยนมาใช้ Sarabun และปรับขนาดตัวอักษรให้อ่านง่าย */
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 16px;
            line-height: 1.4;
            color: #000;
        }
        .receipt-box { width: 100%; max-width: 800px; margin: auto; border: 1px solid #eee; padding: 20px; position: relative; }
        .header { text-align: center; margin-bottom: 20px; }

        /* เน้นตัวหนาให้ชัดเจนขึ้นเมื่อเป็นฟอนต์ Sarabun */
        h2 { font-weight: 700; }
        strong { font-weight: 600; }

        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items-table th { border-bottom: 2px solid #333; padding: 8px; text-align: left; font-weight: 600; }
        .items-table td { border-bottom: 1px dashed #ddd; padding: 8px; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .btn-print { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-family: 'Sarabun', sans-serif; font-size: 16px; }

        /* ตราประทับ "สำเนา" ทับกระดาษ เอียง 45 องศา แสดงเฉพาะพิมพ์ครั้งที่ 2 ขึ้นไป */
        .copy-watermark {
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 90px;
            font-weight: 700;
            color: rgba(120, 120, 120, 0.35);
            white-space: nowrap;
            pointer-events: none;
            z-index: 10;
            user-select: none;
        }

        /* โมดัลถามเหตุผลก่อนพิมพ์ซ้ำ */
        .reason-modal-backdrop {
            display: none;
            position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            align-items: center; justify-content: center; z-index: 100;
        }
        .reason-modal-box {
            background: #fff; border-radius: 8px; padding: 20px; width: 100%; max-width: 420px;
            font-family: 'Sarabun', sans-serif;
        }
        .reason-modal-box textarea { width: 100%; box-sizing: border-box; padding: 8px; font-family: inherit; }
        .reason-modal-box .btn-cancel { background: #6c757d; color: #fff; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; }
        .reason-modal-box .btn-confirm { background: #dc3545; color: #fff; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin: 20px;">
        <button class="btn-print" id="printBtn">คลิกเพื่อพิมพ์ใบเสร็จ</button>
        <a href="{{ url('/finance') }}" style="margin-left: 10px;">กลับหน้ารายการคิว</a>
        @if($printCount > 0)
            <div style="color:#dc3545; margin-top:8px; font-size: 14px;">เคยพิมพ์ใบเสร็จนี้ไปแล้ว {{ $printCount }} ครั้ง — การพิมพ์รอบนี้ต้องระบุเหตุผลและจะมีตราประทับ "สำเนา"</div>
        @endif
    </div>

    <div class="reason-modal-backdrop no-print" id="reasonModalBackdrop">
        <div class="reason-modal-box">
            <h5 style="margin-top:0;">เหตุผลที่ต้องพิมพ์ใบเสร็จซ้ำ</h5>
            <p style="font-size: 13px; color:#666;">บันทึกไว้เพื่อการตรวจสอบ (เช่น เครื่องพิมพ์ขัดข้อง, พิมพ์ผิด, คนไข้ทำใบเสร็จหาย)</p>
            <textarea id="reasonInput" rows="3" placeholder="ระบุเหตุผล..."></textarea>
            <div style="text-align:right; margin-top:12px; display:flex; gap:8px; justify-content:flex-end;">
                <button class="btn-cancel" id="reasonCancelBtn" type="button">ยกเลิก</button>
                <button class="btn-confirm" id="reasonConfirmBtn" type="button">ยืนยันพิมพ์สำเนา</button>
            </div>
        </div>
    </div>

    <div class="receipt-box">
        @if($printCount > 0)
            <div class="copy-watermark">สำเนา / COPY</div>
        @endif
        <div class="header" style="border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px;">
                <img src="{{ asset('images/logo-กรม-2566 png.png') }}" style="height: 60px; width: auto;">
                <div style="text-align: center;">
                    <h3 style="margin: 0; font-size: 17px; font-weight: bold;">ศูนย์บริการเวชศาสตร์ป้องกัน</h3>
                    <div style="font-size: 13px;">Preventive Medicine Service Center</div>
                    <h4 style="margin: 4px 0 0 0; font-size: 15px; font-weight: bold;">สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่</h4>
                    <div style="font-size: 11px;">The Office of Disease Prevention and Control 1 Chiang Mai</div>
                    <div style="font-size: 12px; margin-top: 4px;">143 ถนนศรีดอนไชย ตำบลช้างคลาน อำเภอเมืองเชียงใหม่ จังหวัดเชียงใหม่ 50100 โทรศัพท์ 0-5314-0774-6</div>
                    <div style="font-size: 12px;">รหัสสถานบริการ: {{ \App\Models\Config::where('name', 'facility_code')->value('value') ?? '-' }}</div>
                </div>
                <img src="{{ asset('images/สำนักงานป้องกันควบคุมโรคที่ 1.png') }}" style="height: 60px; width: auto;">
            </div>
            <h2 style="margin: 10px 0 0 0;">ใบเสร็จรับเงิน / Receipt</h2>
        </div>

        <table class="info-table">
            <tr>
                <td width="30%"><strong>เลขที่บิล:</strong> {{ $visit->billings->bill_no ?? '-' }}</td>
                <td width="35%"><strong>HN:</strong> {{ $visit->patient2->code ?? '-' }}</td>
                <td width="35%" class="text-right"><strong>วันที่:</strong> {{ date('d/M/Y H:i') }}</td>
            </tr>
            <tr>
                <td colspan="3"><strong>ชื่อ-นามสกุล:</strong> {{ $visit->patient2->name ?? '' }} {{ $visit->patient2->surname ?? '' }}</td>
            </tr>
        </table>

        <table class="items-table">
            <thead>
                <tr>
                    <th>รายการ</th>
                    <th class="text-right" width="100">จำนวน</th>
                    <th class="text-right" width="120">ราคา/หน่วย</th>
                    <th class="text-right" width="120">รวมเงิน</th>
                </tr>
            </thead>
            <tbody>
                @php $total = 0; @endphp

                {{-- ========================================== --}}
                {{-- 1. หมวดยา (Medicines)                      --}}
                {{-- ========================================== --}}
                @if($visit->visitMedicines && $visit->visitMedicines->count() > 0)
                    <tr style="background-color: #f9f9f9;">
                        <td colspan="4" style="border-top: 1px solid #333; border-bottom: 1px solid #ccc;">
                            <strong>รายการยา (Medicines)</strong>
                        </td>
                    </tr>
                    @foreach($visit->visitMedicines as $vm)
                        @php
                            $price = 0;
                            if (isset($visit->billings) && isset($visit->billings->billingItems)) {
                                // ปรับให้ค้นหาทั้งจาก ID ตารางยา และ ID ตารางคิวจ่ายยา
                                $billingItem = $visit->billings->billingItems->first(function($item) use ($vm) {
                                    $isMedicine = strtolower($item->item_type) === 'medicine';
                                    $isMatchId = ($item->item_id == $vm->medicine_id || $item->item_id == $vm->id);
                                    return $isMedicine && $isMatchId;
                                });

                                if ($billingItem) {
                                    $price = $billingItem->unit_price;
                                }
                            }

                            // กรณีFallback: ถ้ายังไม่มีบิล
                            if (!$price || $price == 0) {
                                $price = $vm->price ?? ($vm->medicine->price ?? 0);
                            }

                            $subtotal = (float)$vm->amount * (float)$price;
                            $total += $subtotal;
                        @endphp
                        <tr>
                            <td>- {{ $vm->medicine->name ?? 'ไม่ทราบชื่อยา' }}</td>
                            <td class="text-right">{{ number_format((float)$vm->amount, 0) }}</td>
                            <td class="text-right">{{ number_format((float)$price, 2) }}</td>
                            <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                        </tr>
                    @endforeach
                @endif

                {{-- ========================================== --}}
                {{-- 2. หมวดตรวจทางห้องปฏิบัติการ (Labs)            --}}
                {{-- ========================================== --}}
                @php
                    $hasLabInBill = $visit->billings && $visit->billings->billingItems && $visit->billings->billingItems->where('item_type', 'lab')->count() > 0;

                    // เช็คก่อนว่ามีรายการ Lab จริงๆ ไหม จะได้แสดงหัวข้อถูก
                    $showLabHeader = false;
                    if ($hasLabInBill) {
                        $showLabHeader = true;
                    } elseif (isset($visit->labs)) {
                        foreach($visit->labs as $labHeader) {
                            if(isset($labHeader->labSubs) && count($labHeader->labSubs) > 0) {
                                $showLabHeader = true; break;
                            }
                        }
                    }
                @endphp

                @if($showLabHeader)
                    <tr style="background-color: #f9f9f9;">
                        <td colspan="4" style="border-top: 1px solid #333; border-bottom: 1px solid #ccc;">
                            <strong>รายการตรวจทางห้องปฏิบัติการ (Labs)</strong>
                        </td>
                    </tr>

                    @if($hasLabInBill)
                        {{-- ดึงจากบิลที่บันทึกแล้ว --}}
                        @foreach($visit->billings->billingItems->where('item_type', 'lab') as $bItem)
                            @php
                                $realLabSub = \App\Models\LabSub::find($bItem->item_id);
                                $methodName = $realLabSub ? $realLabSub->method() : 'ค่าตรวจ Lab';
                                $specimen = $realLabSub ? $realLabSub->specimenFrom() : '-';

                                $subtotal = (float)$bItem->qty * (float)$bItem->unit_price;
                                $total += $subtotal;
                            @endphp
                            <tr>
                                <td>
                                    - {{ $methodName }}
                                    @if($specimen !== '-' && $specimen !== '')
                                        <span>({{ $specimen }})</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($bItem->qty, 0) }}</td>
                                <td class="text-right">{{ number_format($bItem->unit_price, 2) }}</td>
                                <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    @else
                        {{-- ดึงจากใบสั่ง Lab กรณีที่ยังไม่ได้บันทึกบิล --}}
                        @foreach($visit->labs as $labHeader)
                            @if(isset($labHeader->labSubs) && count($labHeader->labSubs) > 0)
                                @foreach($labHeader->labSubs as $sub)
                                    @php
                                        $realLabSub = \App\Models\LabSub::find($sub->id);
                                        $methodName = $realLabSub ? $realLabSub->method() : 'รายการ Lab';
                                        $specimen = $realLabSub ? $realLabSub->specimenFrom() : '-';

                                        $labPrice = $sub->price ?? 0;
                                        $total += $labPrice;
                                    @endphp
                                    <tr>
                                        <td>
                                            - {{ $methodName }}
                                            @if($specimen !== '-' && $specimen !== '')
                                                <span>({{ $specimen }})</span>
                                            @endif
                                        </td>
                                        <td class="text-right">1</td>
                                        <td class="text-right">{{ number_format((float)$labPrice, 2) }}</td>
                                        <td class="text-right">{{ number_format((float)$labPrice, 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        @endforeach
                    @endif
                @endif

                {{-- ========================================== --}}
                {{-- 3. หมวดค่าบริการและส่วนลด                       --}}
                {{-- ========================================== --}}
                @if($visit->billings)
                    @if($visit->billings->total_service > 0)
                        @php $total += $visit->billings->total_service; @endphp
                        <tr>
                            <td colspan="4" style="border-top: 1px dashed #ccc;"></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-right"><strong>ค่าบริการอื่นๆ:</strong></td>
                            <td class="text-right">{{ number_format($visit->billings->total_service, 2) }}</td>
                        </tr>
                    @endif

                    @if($visit->billings->discount > 0)
                        @php $total -= $visit->billings->discount; @endphp
                        <tr>
                            <td colspan="3" class="text-right"><strong>ส่วนลด:</strong></td>
                            <td class="text-right text-danger">-{{ number_format($visit->billings->discount, 2) }}</td>
                        </tr>
                    @endif
                @endif
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="text-right"><strong>รวมค่าใช้จ่ายทั้งหมด:</strong></td>
                    <td class="text-right"><strong>{{ number_format($total, 2) }} บาท</strong></td>
                </tr>
                {{-- ดึงยอดชำระแล้วจากฐานข้อมูล --}}
                @php
                    $paidAmount = $visit->billings->paid_amount ?? 0;
                    $balance = $total - $paidAmount;
                    if($balance < 0) $balance = 0;
                @endphp
                <tr>
                    <td colspan="3" class="text-right" style="color: #28a745;"><strong>ยอดชำระแล้ว (รวมรอบนี้):</strong></td>
                    <td class="text-right" style="color: #28a745;"><strong>{{ number_format($paidAmount, 2) }} บาท</strong></td>
                </tr>
                <tr>
                    <td colspan="3" class="text-right text-danger"><strong>ยอดคงค้างชำระ:</strong></td>
                    <td class="text-right text-danger" style="border-bottom: 3px double #333;">
                        <strong>{{ number_format($balance, 2) }} บาท</strong>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div class="footer">
            <div>ผู้รับเงิน: ...........................................</div>
            <div>วันที่: ...........................................</div>
        </div>
    </div>

    <script>
        var printCount = {{ (int) $printCount }};
        var logPrintUrl = '{{ route('finance.logPrint', $visit->id) }}';
        var csrfToken = '{{ csrf_token() }}';

        function submitPrintLog(reason) {
            fetch(logPrintUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ reason: reason })
            }).then(function(res) {
                if (!res.ok) {
                    return res.json().then(function(data) {
                        throw new Error(data.message || 'บันทึกประวัติการพิมพ์ไม่สำเร็จ');
                    });
                }
                window.print();
            }).catch(function(err) {
                alert(err.message);
            });
        }

        document.getElementById('printBtn').addEventListener('click', function () {
            if (printCount > 0) {
                document.getElementById('reasonModalBackdrop').style.display = 'flex';
            } else {
                submitPrintLog(null);
            }
        });

        document.getElementById('reasonCancelBtn').addEventListener('click', function () {
            document.getElementById('reasonModalBackdrop').style.display = 'none';
        });

        document.getElementById('reasonConfirmBtn').addEventListener('click', function () {
            var reason = document.getElementById('reasonInput').value.trim();
            if (!reason) {
                alert('กรุณาระบุเหตุผลที่ต้องพิมพ์ซ้ำ');
                return;
            }
            document.getElementById('reasonModalBackdrop').style.display = 'none';
            submitPrintLog(reason);
        });
    </script>
</body>

</html>
