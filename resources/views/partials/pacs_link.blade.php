{{--
    ปุ่มเปิดดูฟิล์มเอกซเรย์ของคนไข้ในระบบ PACS ของ สคร.1 (INFINITT) — คลินิกขอ 2569-09-29

    ใช้:  @include('partials.pacs_link', ['hn' => $patient->code])

    สองปุ่ม ทั้งคู่คัดลอก HN ให้:
      1) เปิดฟิล์มตรง /pkg_pacs/external_interface.aspx?PID=<HN> — ผู้ใช้ชอบ แต่ปัจจุบันเปิดได้ครั้งแรกครั้งเดียว
      2) หน้าแรก PACS (แท็บ pacs_home) แล้ววาง HN ค้นเอง — login ครั้งเดียวอยู่ได้ทั้งวัน
    ผู้ดูแล PACS ยืนยัน 2569-09-30 ว่าไม่มีลิงก์อื่นให้

    ต้นเหตุที่เปิดได้ครั้งเดียว (พบ 2569-09-30): หน้าเปิดตรงสั่งเปิดโปรแกรม INFINITT Starter ในเครื่อง
    (ลิงก์ infstarter://) Chrome/Edge จะถาม "This site is trying to open INFStarter" ทุกครั้ง เพราะ PACS เป็น http
    ไม่มีช่อง "จำไว้" ให้ติ๊ก แล้วหน้า PACS ปิดตัวเองไปพร้อมกล่องถามก่อนกด Open
    ตรวจจาก log ในเครื่อง (C:\INFINITT\Smartupdate\Log, C:\INFINITT_ODPC1\VIEWER\Log\G3_Log-*.log):
    Starter -> Smartupdate -> เปิด G3PACS.exe แล้วสั่ง OPEN_STUDY [PatientID]-<HN> — ยืนยันว่า PID = HN ถูกต้อง
    ฟิล์มขึ้นในโปรแกรม G3PACS ไม่ใช่ในเบราว์เซอร์ หน้าเว็บปิดตัวเองเป็นเรื่องปกติของ INFINITT
    หลังลงค่าตั้งเครื่องแล้ว ทั้ง Edge และ Chrome เปิดฟิล์มได้ซ้ำ ๆ (log 08:56-09:00 น. 2569-09-30: 8 ใน 9 ครั้ง)
    ครั้งที่ไม่ขึ้นคือครั้งที่ต้อง login ระหว่างทาง — PACS เปิดโปรแกรมแต่ลืม HN กดปุ่มซ้ำอีกครั้งก็ขึ้น
    ทางแก้อยู่ที่เครื่องผู้ใช้: ตั้ง policy AutoLaunchProtocolsFromOrigins ให้ http://<PACS_IP> เปิด infstarter ได้เลย
    → public/cert/ตั้งค่าเปิดฟิล์ม PACS.bat (แบบเดียวกับไฟล์ติดตั้งใบรับรอง) รันด้วยสิทธิ์ admin ครั้งเดียวต่อเครื่อง

    ข้อจำกัดของแบบที่ 1:
    หน้านั้นเป็นตัวเชื่อมของ INFINITT สำหรับ "เปิดโปรแกรมดูฟิล์มแล้วปิดตัวเอง" (processClose ในหน้า)
    และออกแบบให้ระบบภายนอกส่งชื่อผู้ใช้/รหัสมาในลิงก์ทุกครั้ง เราไม่ส่งรหัส จึงโดนถาม login ทุกครั้ง
    login เสร็จหน้าต่างก็ปิดไปเอง — ผู้ใช้แจ้ง 2569-09-30 ว่าเปิดได้ครั้งแรกครั้งเดียว

    ไม่ใส่ชื่อผู้ใช้/รหัสผ่าน PACS (LID/LPW) ลงในลิงก์เด็ดขาด แม้ตัวเชื่อมต่อจะรับได้
    เพราะลิงก์ไปค้างในประวัติเบราว์เซอร์และ log ของเซิร์ฟเวอร์ ใครเปิดเครื่องก็เห็นรหัส

    ข้อสมมติ: เลขคนไข้ใน PACS = HN ของระบบนี้ ถ้าวันหนึ่งพบว่า PACS ใช้เลขอื่น
    เปลี่ยนที่ services.pacs.patient_url ได้ที่เดียว ({hn} คือที่ที่ HN จะถูกแทน)
--}}
@php
    $__pacsHome = config('services.pacs.url', 'http://<PACS_IP>/default.aspx');
    $__pacsPt   = config('services.pacs.patient_url', 'http://<PACS_IP>/pkg_pacs/external_interface.aspx?PID={hn}');
    $__pacsHn   = trim((string) ($hn ?? ''));
    $__pacsHref = $__pacsHn !== '' ? str_replace('{hn}', rawurlencode($__pacsHn), $__pacsPt) : $__pacsHome;
@endphp
<div class="d-flex flex-wrap align-items-center gap-2 mt-2">
    @if($__pacsHn !== '')
        {{-- ปุ่มหลัก = เปิดฟิล์มคนไข้ตรง (ผู้ใช้ชอบแบบนี้ 2569-09-30) และคัดลอก HN ไว้ด้วยทุกครั้ง
             ถ้าเปิดตรงไม่ขึ้น (ถาม login แล้วหน้าต่างปิด) จะได้กดปุ่มที่สองแล้ววาง HN ค้นได้ทันที --}}
        <a href="{{ $__pacsHref }}" target="_blank" class="btn btn-sm btn-dark js-pacs-open" data-hn="{{ $__pacsHn }}">
            <i class="fa fa-x-ray me-1"></i> เปิดฟิล์มคนไข้ใน PACS
        </a>
        <a href="{{ $__pacsHome }}" target="pacs_home" class="btn btn-sm btn-outline-dark js-pacs-open" data-hn="{{ $__pacsHn }}">
            <i class="fa fa-magnifying-glass me-1"></i> หน้า PACS (ค้นเอง)
        </a>
        <span class="small text-muted">
            ฟิล์มเปิดใน<b>โปรแกรม INFINITT PACS</b> — หน้าเว็บ PACS จะปิดตัวเอง เป็นปกติ ·
            ถ้าต้อง login แล้วโปรแกรมขึ้นแต่ไม่มีฟิล์ม <b>กดปุ่มนี้อีกครั้ง</b> ·
            ถ้ามีกล่องถาม "open INFStarter" ให้กด <b>Open</b>
            (ถามทุกครั้ง? <a href="{{ asset('cert/' . rawurlencode('ตั้งค่าเปิดฟิล์ม PACS.bat')) }}" download>ไฟล์ตั้งค่าเครื่อง</a>
            — คลิกขวา Run as administrator ครั้งเดียวต่อเครื่อง) ·
            ทั้งสองปุ่มคัดลอก <b>HN {{ $__pacsHn }}</b> ให้ · ถ้าเปิดตรงไม่ขึ้น ใช้ปุ่มขวาแล้ววาง (Ctrl+V) ในช่องค้นหา
        </span>
    @else
        <a href="{{ $__pacsHome }}" target="pacs_home" class="btn btn-sm btn-dark">
            <i class="fa fa-x-ray me-1"></i> เปิด PACS
        </a>
    @endif
</div>

@once
<script>
// ปุ่มดูฟิล์ม: คัดลอก HN ไว้ให้ก่อน แล้วปล่อยให้ลิงก์เปิดแท็บ PACS ตามปกติ (ไม่ preventDefault)
// ผูกที่ document เพราะปุ่มนี้อาจอยู่ในส่วนที่โหลดทีหลัง
(function () {
    function copyText(t) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(t).catch(function () { legacy(t); });
        } else { legacy(t); }
    }
    // สำรองสำหรับหน้าที่ไม่ใช่ https — ใช้ textarea ชั่วคราว ไม่เด้งกล่องถาม (กล่องถามจะบล็อกการเปิดแท็บ)
    function legacy(t) {
        var ta = document.createElement('textarea');
        ta.value = t; ta.setAttribute('readonly', ''); ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('.js-pacs-open');
        if (! b) { return; }
        var hn = b.getAttribute('data-hn') || '';
        if (hn === '') { return; }
        copyText(hn);
        // เก็บป้ายเดิมครั้งแรกครั้งเดียว — กดซ้ำภายใน 2 วิ เดิมจำ "คัดลอก HN แล้ว" เป็นป้ายเดิมแล้วค้างถาวร (รีวิว 2569-09-30)
        if (! b.dataset.label) { b.dataset.label = b.innerHTML; }
        clearTimeout(b._pacsTimer);
        b.innerHTML = '<i class="fa fa-check me-1"></i> คัดลอก HN แล้ว';
        b._pacsTimer = setTimeout(function () { b.innerHTML = b.dataset.label; }, 2000);
    });
})();
</script>
@endonce
