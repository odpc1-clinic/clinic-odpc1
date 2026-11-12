{{-- เนื้อหา popup "รหัสผูก LINE" — เป็น "นิพจน์ JS" (สตริงต่อกับ data.code / data.expires_at) ใช้ใน html: ของ Swal.fire
     ใช้ร่วม 3 หน้า (ทะเบียนคนไข้ · หน้าตรวจ · หน้าห้อง) เดิมก็อปข้อความไว้ 3 ที่ แก้ทีต้องไล่ครบ
     มีฟอร์มใน LINE (LIFF) = QR เดียวจบ: สแกนแล้ว LINE ถามเพิ่มเพื่อนเอง + เปิดหน้ากรอกรหัส (2569-10-22)
     เขียนเป็นขั้นตอนให้พยาบาลอ่านบอกคนไข้ได้ทีละข้อ (คลินิกขอ 2569-10-22) — ข้อความบนปุ่มตรงกับที่ LINE/ฟอร์มแสดงจริง
     ตั้งแต่ 2569-10-31 ใช้ "พิมพ์รหัสในแชท" เป็นหลัก — ฟอร์มปิดไว้ (LINE_LINK_FORM_ENABLED) เพราะแทบไม่มีคนใช้
     และคนไข้ที่เป็นเพื่อนอยู่แล้วต้องข้ามขั้นสแกน · คำตอบช้าได้ไม่เกิน 1 นาที (ผ่านที่พัก Google) --}}
@if(config('services.line.link.form_enabled') && config('services.line.link.liff_id'))
'<div style="text-align:left; font-size:1rem; line-height:1.55;">' +
  '<div class="mb-1"><b>ให้คนไข้ทำตามนี้</b></div>' +
  '<ol style="padding-left:1.3rem; margin:0;">' +
    '<li>เปิด<b>กล้องมือถือ</b> หรือในแอป LINE กด <b>เพิ่มเพื่อน → สแกนคิวอาร์</b> แล้วสแกน QR นี้' +
      '<img src="{{ asset(config('services.line.link.liff_qr')) }}" alt="QR ผูก LINE" style="max-width:190px; margin:6px auto; display:block;"></li>' +
    '<li>LINE ถามสิทธิ์ → กด <b>อนุญาต</b> · ถ้าขึ้นให้เพิ่มเพื่อน → กด <b>เพิ่มเพื่อน</b></li>' +
    '<li>หน้า "ผูกบัญชี LINE" เปิดขึ้น → กรอกรหัสนี้ แล้วกด <b>ผูกบัญชี</b>' +
      '<div style="font-size:2.5rem; font-weight:bold; letter-spacing:6px; text-align:center; margin:2px 0;">' + data.code + '</div></li>' +
    '<li>รอข้อความ <b>"✅ ผูกบัญชีสำเร็จ"</b> ในแชท LINE (ไม่เกิน 1 นาที) — เสร็จแล้ว</li>' +
  '</ol>' +
  '<div class="text-muted small mt-2" style="text-align:center;">รหัสใช้ได้ถึง ' + data.expires_at + ' น. (10 นาที) · หมดเวลา/ขึ้นว่ารหัสไม่ถูกต้อง = กดปุ่ม "รหัสผูก LINE" ใหม่</div>' +
'</div>'
@else
'<div style="text-align:left; font-size:1rem; line-height:1.55;">' +
  '<div class="mb-1"><b>ให้คนไข้ทำตามนี้</b></div>' +
  '<ol style="padding-left:1.3rem; margin:0;">' +
    '<li>สแกน QR นี้เพื่อ<b>เพิ่มเพื่อน</b> LINE คลินิก <b>{{ config('services.line.oa_id') }}</b>' +
      '<div class="text-muted small">เป็นเพื่อนอยู่แล้ว = ข้ามข้อนี้ เปิดแชท LINE คลินิกได้เลย</div>' +
      '<img src="{{ asset(config('services.line.oa_qr')) }}" alt="QR เพิ่มเพื่อน LINE คลินิก" style="max-width:190px; margin:6px auto; display:block;"></li>' +
    '<li>พิมพ์<b>รหัสนี้</b>ส่งเข้าไปในแชท' +
      '<div style="font-size:2.5rem; font-weight:bold; letter-spacing:6px; text-align:center; margin:2px 0;">' + data.code + '</div></li>' +
    '<li>รอข้อความ <b>"✅ ผูกบัญชีสำเร็จ"</b> ในแชท (ไม่เกิน 1 นาที) — เสร็จแล้ว</li>' +
  '</ol>' +
  '<div class="text-muted small mt-2" style="text-align:center;">รหัสใช้ได้ถึง ' + data.expires_at + ' น. (10 นาที) · หมดเวลา/ขึ้นว่ารหัสไม่ถูกต้อง = กดปุ่ม "รหัสผูก LINE" ใหม่</div>' +
'</div>'
@endif
