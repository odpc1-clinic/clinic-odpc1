{{--
    ตัวเลือกรหัสโรค ICD-10 — เลือกได้หลายรหัส

    ใช้:  @include('partials.icd10_picker', ['name' => 'icd10[]', 'selected' => $codes])

    ตัวแปร:
      name        ชื่อ input ที่จะส่งไปกับฟอร์ม (ต้องลงท้าย [] เพราะส่งหลายค่า)
      selected    อาเรย์รหัสที่เลือกไว้แล้ว ตัวแรกคือรหัสหลัก
      prefix      (ไม่บังคับ) กรองให้เหลือเฉพาะรหัสที่ขึ้นต้นด้วยอะไร
      placeholder (ไม่บังคับ)

    ไฟล์นี้มีแต่ "หน้าตา" ไม่มีสคริปต์ เพราะแถววินิจฉัยถูกโคลนจาก <template> ด้วย JS
    ซึ่งสคริปต์ที่ติดอยู่ข้างในจะไม่ทำงาน (CLAUDE.md ข้อ 2)
    ตัวทำงานอยู่ที่ partials/icd10_picker_js.blade.php ซึ่งถูกเรียกจาก layout ครั้งเดียว
    แล้วผูก event ที่ document — แถวที่เพิ่งโคลนมาจึงใช้ได้ทันทีโดยไม่ต้องสั่งอะไรเพิ่ม
--}}
@php
    \App\Models\Icd10Code::$pickerUsed = true;   // ให้ layout โหลดตัวทำงานเฉพาะหน้าที่มีตัวเลือก
    $__icdName   = $name ?? 'icd10[]';
    $__icdSel    = array_values(array_filter(array_map(
        fn ($c) => strtoupper(trim((string) $c)),
        (array) ($selected ?? [])
    ), fn ($c) => $c !== ''));
@endphp
<div class="icd10-picker"
     data-name="{{ $__icdName }}"
     data-prefix="{{ $prefix ?? '' }}"
     data-selected="{{ json_encode($__icdSel, JSON_UNESCAPED_UNICODE) }}">

    {{-- ป้ายรหัส + input ซ่อน วาดจากฝั่งเซิร์ฟเวอร์ ไม่ปล่อยให้ JS สร้างตอนโหลด
         เพราะหน้าตรวจจะ "จำค่าทุกช่อง" ตอนเปิดหน้าเพื่อใช้กติกา _touched (CLAUDE.md ข้อ 6)
         ตัวจำค่าทำงานก่อนสคริปต์เรา ถ้าช่องซ่อนยังไม่เกิด ระบบจะเห็นว่าช่องนี้ถูกแก้ทุกครั้ง
         แล้วไปเขียนทับของห้องอื่นที่เปิดฟอร์มค้างไว้ · และถ้า JS พัง ค่าเดิมก็ยังส่งได้ครบ --}}
    <div class="icd10-chips">
        {{-- ส่งค่าว่างไปเสมอหนึ่งตัว ให้ฝั่งเซิร์ฟเวอร์รู้ว่าฟอร์มนี้มีช่อง ICD อยู่
             ไม่งั้นพอผู้ใช้เอารหัสออกหมด คีย์จะหายไปเลย แล้วแยกไม่ออกระหว่าง
             "ลบออกหมด" กับ "ฟอร์มนี้ไม่มีช่องนี้" ของเก่าจะค้างอยู่ตลอด --}}
        <input type="hidden" name="{{ $__icdName }}" value="">
        @foreach ($__icdSel as $__i => $__code)
            @php $__info = \App\Models\Icd10Code::info($__code); @endphp
            <span class="icd10-chip {{ $__i === 0 ? 'is-primary' : (optional($__info)->is_secondary ? 'is-secondary' : '') }}"
                  title="{{ optional($__info)->description_th ?: optional($__info)->description_en }}">
                <span>{!! $__i === 0 ? '★ ' : '' !!}<b>{{ $__code }}</b>
                    {{ optional($__info)->description_th ?: optional($__info)->description_en }}</span>
                <span class="x" data-code="{{ $__code }}" title="เอารหัสนี้ออก">×</span>
                <input type="hidden" name="{{ $__icdName }}" value="{{ $__code }}">
            </span>
        @endforeach
    </div>

    <div class="icd10-box">
        <input type="text" class="form-control form-control-sm icd10-input"
               placeholder="{{ $placeholder ?? 'พิมพ์รหัสหรือชื่อโรค เช่น A15 · วัณโรคปอด · lung' }}"
               autocomplete="off">
        <div class="icd10-menu"></div>
    </div>
</div>
