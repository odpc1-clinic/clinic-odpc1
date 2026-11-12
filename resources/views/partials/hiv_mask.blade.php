{{-- ผล HIV แบบปิดไว้ — คลินิก TB ขอ 2569-10-06 (บัตร TB 01 / หน้าตรวจ TB)
     ⚠️ ห้ามใส่ค่าผลจริงลงในไฟล์นี้ (แม้ใน data-* / title / ช่อง hidden) — ต้องอ่านจาก view-source ไม่ได้
     กด "กดดู" -> partials/hiv_mask_js ขอผลจาก POST hiv.reveal (บันทึก log ว่าใครดู) แล้วเติมทุกจุดในหน้าพร้อมกัน
     ไฟล์นี้ถูกใช้ในเนื้อหาที่โหลดผ่าน AJAX และอยู่ในฟอร์มหลักของหน้าตรวจ จึง: ห้าม <script> · ห้าม <form> · ห้าม name= · ห้าม <button>
     (ชิปใน "สั่งตรวจครั้งนี้" เป็น <button> อยู่แล้ว ปุ่มซ้อนปุ่มผิด HTML — ใช้ span role=button แทน)
     ตัวแปรขึ้นต้น hm ทั้งหมด — @include เห็นตัวแปรของหน้าแม่ ชื่อกลาง ๆ อย่าง $label เคยไปหยิบค่าจาก loop ของหน้าแม่มาโชว์
       $hmPatient · $hmKind ('reg' = ช่องผลเลือดในบัตร | 'sub' = ผล Anti-HIV หนึ่งรายการ | 'visit' = ผล HIV ของ visit นั้นทั้งหมด)
       $hmRef (id ของบัตร / lab_sub / visit) · $hmReg · $hmVisit (บริบทสำหรับ log) · $hmWhere (ชื่อหน้า)
       $hmLabel (ข้อความนำหน้า) · $hmActions (ปุ่มพิมพ์ผล/ใบ refer หลังเปิดดู · ห้ามเปิดถ้าอยู่ใน <button>/<a>) --}}
<span class="hiv-mask js-hiv-mask" data-kind="{{ $hmKind }}" data-ref="{{ $hmRef }}"
      data-url="{{ route('hiv.reveal', $hmPatient) }}"
      data-reg="{{ $hmReg ?? '' }}" data-visit="{{ $hmVisit ?? '' }}" data-where="{{ $hmWhere ?? '' }}"
      data-actions="{{ ($hmActions ?? true) ? 1 : 0 }}">
    @if(($hmLabel ?? '') !== '')<span class="hiv-mask-label">{{ $hmLabel }}</span>@endif
    <span class="hiv-mask-val">●●●●</span>
    <span role="button" tabindex="0" class="hiv-mask-btn js-hiv-reveal" title="ผลถูกปิดไว้ — กดเพื่อดู (ระบบบันทึกว่าใครเปิดดู)"><i class="fa fa-eye me-1"></i>กดดู</span>
</span>
