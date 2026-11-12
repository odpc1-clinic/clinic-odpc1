{{-- ช่วงเวลาที่กิน (เช้า/กลางวัน/เย็น/ก่อนนอน ติ๊กได้หลายช่อง) + ช่องพิมพ์เพิ่มเติม — 2569-09-30
     ใช้ทั้งหน้าสั่งยาคลินิกหลักและบัตร TB 01 · ตัวแปร: $prefix, $sig
     ติ๊กช่วงเวลาแล้ว JS จะตั้ง "วันละ n ครั้ง" ให้ตามจำนวนช่องที่ติ๊ก --}}
@php $__pp = (array) (($sig ?? [])['period'] ?? []); @endphp
<span class="d-inline-flex flex-wrap align-items-center gap-2 js-sig-periods">
    @foreach(\App\Support\DrugSig::$period as $__pk => $__pl)
        <label class="form-check form-check-inline mb-0 me-0 small">
            <input class="form-check-input" type="checkbox" name="{{ $prefix }}[period][]" value="{{ $__pk }}"
                   {{ in_array($__pk, $__pp, true) ? 'checked' : '' }}>
            <span class="form-check-label">{{ $__pl }}</span>
        </label>
    @endforeach
</span>
<input type="text" name="{{ $prefix }}[note]" value="{{ ($sig ?? [])['note'] ?? '' }}" maxlength="{{ \App\Support\DrugSig::NOTE_MAX }}"
       class="form-control form-control-sm" style="min-width:10rem; max-width:16rem;" placeholder="พิมพ์เพิ่มเอง เช่น ทุก 8 ชม.">
