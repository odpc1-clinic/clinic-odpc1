{{--
    แถวหนึ่งของ "ใบสรุปการรักษา" (ตารางรอบสั่งยา TB 01 — หนึ่งแถว = การสั่งยาหนึ่งครั้ง) ใช้ทั้งแถวจริงและ <template> แถวนอกรอบ
    ตัวแปร: $i = เลขแถวในฟอร์ม · $dr = ['label', 'month_no', 'row' (TbMonthlyDose|null), 'standard', 'visible', 'text', 'pos']
    ต้องมี $reg, $drugKeys จากหน้าที่ include · $editable = เปิดแก้ได้ทันที (แถวนอกรอบที่เพิ่งกดเพิ่ม)

    อ่านอย่างเดียวเป็นค่าตั้งต้น (คลินิกขอ 2569-10-02 — สั่งยาทำที่กล่อง "สั่งยาครั้งนี้" แล้ว ตารางนี้ไว้ดู/แก้ย้อนหลัง)
    ช่องทุกช่องของแถวที่ยังไม่กดดินสอเป็น disabled = ไม่ถูกส่งไปกับฟอร์ม
    สำคัญ: saveDoses ลบแถวที่ส่งมาแต่ว่างทั้งแถว ถ้าส่ง id ไปโดยไม่มีค่าอื่น แถวนั้นจะหาย — ห้ามปลด disabled แค่บางช่อง
--}}
@php
    $row  = $dr['row'];
    $vals = (array) ($row->doses ?? []);
    $__ed = ! empty($editable);
    $__dis = $__ed ? '' : 'disabled';
    $__in = $__ed ? '' : ' d-none';
@endphp
<tr class="js-dose-row {{ $dr['visible'] ? '' : 'd-none js-dose-folded' }} {{ $__ed ? 'js-dose-editing' : '' }}"
    data-label="{{ $dr['label'] }}" data-text="{{ $dr['text'] }}" data-pos="{{ $dr['pos'] }}" data-seg="{{ $dr['seg'] ?? 0 }}">
    <td class="text-center">
        <input type="hidden" name="dose[{{ $i }}][id]" value="{{ $row->id ?? '' }}" {{ $__dis }}>
        <input type="hidden" name="dose[{{ $i }}][month_no]" value="{{ $dr['month_no'] }}" class="js-dose-month" {{ $__dis }}>
        @if($dr['standard'])
            <input type="hidden" name="dose[{{ $i }}][label]" value="{{ $dr['label'] }}" {{ $__dis }}>
            <div class="fw-bold">{{ preg_match('/^\d+$/', $dr['label']) ? $dr['label'] : $dr['text'] }}</div>
        @else
            {{-- แถวนอกรอบ: แก้ชื่อได้ · บอกว่าอยู่ในเดือนไหน --}}
            <span class="fw-bold js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $dr['label'] }}</span>
            <input type="text" name="dose[{{ $i }}][label]" value="{{ $dr['label'] }}" maxlength="30" {{ $__dis }}
                   class="form-control form-control-sm text-center fw-bold js-dose-label js-dose-rw{{ $__in }}" style="padding:.15rem .25rem;" placeholder="นอกรอบ">
            <div class="small text-muted js-dose-month-text">เดือน {{ $dr['month_no'] }}</div>
        @endif
    </td>
    <td>
        <span class="js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $row && $row->weight ? (float) $row->weight : '' }}</span>
        <input type="number" step="0.01" name="dose[{{ $i }}][weight]" class="form-control form-control-sm js-dose-rw{{ $__in }}" value="{{ $row->weight ?? '' }}" {{ $__dis }}>
    </td>
    {{-- วันที่สั่ง/จ่ายยาของรอบนั้น — คลินิกขอให้เห็นคู่กับขนาดยา (2569-09-28) --}}
    <td>
        @php
            // แถวเก่าบางแถวหลุดเป็น ค.ศ. — แสดงเป็น พ.ศ. ให้ถูก (ช่องแก้ไขก็เติม พ.ศ. ไว้ กดบันทึกแล้วแถวนั้นถูกแก้ไปด้วย)
            $__dd = $row && $row->dispensed_date ? \App\Utillity::db2thDate(\App\Models\TbRegistration::beDate($row->dispensed_date) ?? $row->dispensed_date) : '';
        @endphp
        <span class="js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $__dd }}</span>
        <input type="text" name="dose[{{ $i }}][dispensed_date]" class="form-control form-control-sm tb-date js-dose-rw{{ $__in }}"
               autocomplete="off" placeholder="วว/ดด/ปปปป" value="{{ $__dd }}" {{ $__dis }}>
    </td>
    {{-- จำนวนวันที่จ่าย ใช้คิดจำนวนเม็ดบนสติ๊กเกอร์ยา --}}
    <td>
        <span class="js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $row->dispensed_days ?? '' }}</span>
        <input type="number" min="1" max="120" name="dose[{{ $i }}][dispensed_days]" {{ $__dis }}
               class="form-control form-control-sm js-dose-rw{{ $__in }}" placeholder="{{ $dr['label'] === '2wk' ? '14' : '28' }}"
               value="{{ $row->dispensed_days ?? '' }}">
        @if($row)
            <a class="small text-nowrap" target="_blank" href="{{ route('tb.labels', ['id' => $reg->id, 'row' => $row->id]) }}">
                <i class="fa fa-tag"></i> สติ๊กเกอร์
            </a>
        @endif
    </td>
    @foreach($drugKeys as $key)
        <td>
            <span class="js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $vals[$key] ?? '' }}</span>
            <input type="text" name="dose[{{ $i }}][doses][{{ $key }}]" class="form-control form-control-sm js-dose-rw{{ $__in }}" {{ $__dis }}
                   value="{{ $vals[$key] ?? '' }}" data-drug="{{ $key }}" data-row="{{ $i }}">
        </td>
    @endforeach
    <td>
        <span class="small js-dose-ro{{ $__ed ? ' d-none' : '' }}">{{ $row->note ?? '' }}</span>
        <input type="text" name="dose[{{ $i }}][note]" class="form-control form-control-sm js-dose-rw{{ $__in }}" value="{{ $row->note ?? '' }}" {{ $__dis }}>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-sm btn-link p-0 js-dose-edit{{ $__ed ? ' d-none' : '' }}" title="แก้ไขแถวนี้"><i class="fa fa-pencil"></i></button>
    </td>
</tr>
