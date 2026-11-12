
<?php
if(!isset($editable)){
    $editable = 0;
}


?>
@php
    // ราคายาให้พยาบาลเห็นทันทีที่หมอสั่ง — คนไข้ชอบถามว่ากี่บาท แล้วพยาบาลตอบไม่ได้ (พี่ Dearkung แจ้ง 2569-08-05)
    // ใช้ราคาต่อหน่วยจากคลังยา x จำนวน = สูตรเดียวกับที่ห้องยาใช้ตั้งต้นในบิล
    // (ห้องยายังปรับราคารายตัวได้ ยอดจริงจึงยึดที่ห้องยาเสมอ)
    // ดึงยาทั้งหมดทีเดียว ไม่ query ในลูป (ของเดิม Medicine::find() ทุกแถว)
    $__meds    = \App\Models\Medicine::whereIn('id', collect($visitMedicine)->pluck('medicine_id')->filter()->unique())
                    ->get()->keyBy('id');
    $__total   = 0;
    $__noPrice = 0;
@endphp
<div class="table-responsive">
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>ชื่อยา</th>
                <th>dose </th>
                <th>route </th>
                <th>จำนวน</th>
                <th class="text-end">ราคา/หน่วย</th>
                <th class="text-end">รวม</th>
                @if($editable)
                <th>Actions</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($visitMedicine as $item)
            @php
                $__m      = $__meds[$item->medicine_id] ?? null;
                $__unit   = $__m ? (float) $__m->price : 0;
                $__sum    = $__unit * (float) ($item->amount ?: 0);
                $__total += $__sum;
                if ($__m && $__unit <= 0) { $__noPrice++; }
            @endphp
            <tr>
                <td>{{ $loop->iteration }}</td>

                <td>
                    {{ $__m->name ?? '' }}
                    @if(! empty($item->lot_number))<span class="badge bg-light text-dark border ms-1" title="Lot">Lot {{ $item->lot_number }}</span>@endif
                    {{-- แถวที่กล่องสั่งยาวัณโรคลงให้ (2569-10-05) — กดบันทึกกล่องซ้ำแถวนี้จะถูกแก้ตาม · ป้ายบอกพยาบาลว่ามาจากไหน --}}
                    @php $__vs = (array) $item->sig; @endphp
                    @if(($item->source ?? null) === 'tb_order' || (($__vs['_src'] ?? null) === 'tb_order' && (int) ($__vs['_visit'] ?? 0) === (int) $item->visit_id))
                        <span class="badge" style="background:#b45309;color:#fff;" title="ลงจากกล่องสั่งยาวัณโรคครั้งนี้ — แก้ที่กล่องแล้วกดบันทึกซ้ำ">กล่องยาวัณโรค</span>
                    @endif
                    @if($item->sigText() !== '')
                        <div class="small" style="color:#0f766e;"><i class="fa fa-prescription-bottle-medical"></i> {{ $item->sigText() }}</div>
                    @endif
                </td>
                <td>{{ $item->dose }}</td>
                <td>{{ $item->route }}</td>
                <td>{{ $item->amount }}{{ $__m && $__m->unit ? ' ' . $__m->unit : '' }}</td>
                <td class="text-end">
                    @if($__unit > 0)
                        {{ number_format($__unit, 2) }}
                    @else
                        <span class="badge bg-secondary" title="ยังไม่ได้ตั้งราคายาตัวนี้ในคลังยา">ยังไม่ตั้งราคา</span>
                    @endif
                </td>
                <td class="text-end fw-semibold">{{ $__unit > 0 ? number_format($__sum, 2) : '-' }}</td>
                @if($editable)
                <td>
                    <!-- <a href="{{ url('/backend/visit-medicine/' . $item->id) }}" title="View VisitMedicine"><button class="btn btn-info btn-sm"><i class="fa fa-eye" aria-hidden="true"></i> View</button></a> -->
                    <a href="{{ route('visit-medicine.edit',['visit_medicine'=>$item->id,'page'=>request('page', 1)]) }}" title="Edit VisitMedicine" class="btn btn-primary btn-sm"><i class="fa fa-pencil" aria-hidden="true"></i></a>

                    {{-- ห้ามใช้ <form> ที่นี่ (อยู่ในฟอร์มหลักของหน้าตรวจ) ดูเหตุผลใน diagnosis/table.blade.php --}}
                    <button type="button" class="btn btn-danger btn-sm js-row-delete" title="Delete VisitMedicine"
                            data-action="{{ route('visit-medicine.destroy',['visit_medicine'=>$item->id,'page'=>request('page', 1)]) }}"><i class="fa fa-trash" aria-hidden="true"></i></button>
                </td>
                @endif
            </tr>
            @endforeach
        </tbody>
        @if(count($visitMedicine))
        <tfoot>
            <tr class="table-light">
                <td colspan="6" class="text-end fw-bold">รวมค่ายา (ประมาณการ)</td>
                <td class="text-end fw-bold fs-5 text-success">{{ number_format($__total, 2) }}</td>
                @if($editable)<td></td>@endif
            </tr>
        </tfoot>
        @endif
    </table>

</div>

@if(count($visitMedicine))
    <div class="alert alert-info py-2 px-3 mb-0">
        <i class="fas fa-circle-info me-1"></i>
        <b>ตอบคนไข้ได้เลยว่าค่ายาประมาณ {{ number_format($__total, 2) }} บาท</b>
        — คิดจากราคาในคลังยา ยอดที่เก็บจริงยึดตามห้องยา/การเงิน และยังไม่รวมค่าตรวจ/ค่าแล็บ
        @if($__noPrice)
            <div class="mt-1 text-danger">
                <i class="fas fa-triangle-exclamation me-1"></i>
                มียา <b>{{ $__noPrice }}</b> รายการที่ยังไม่ได้ตั้งราคาในคลังยา ยอดนี้จึงยังไม่ครบ
            </div>
        @endif
    </div>
@endif
