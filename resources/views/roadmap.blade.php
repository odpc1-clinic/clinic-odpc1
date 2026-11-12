<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>เส้นทางระบบคลินิก</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Thai:wght@400;500;600;700&family=Sarabun:wght@400;500;600&family=IBM+Plex+Mono:wght@500;600&display=swap">
<style>
  :root {
    --paper:      #f6f5f1;
    --surface:    #ffffff;
    --line:       #dfe3e0;
    --ink:        #16211f;
    --ink-2:      #56675f;
    --ink-3:      #8a9a93;
    --accent:     #0e6b5b;
    --accent-2:   #dceae5;
    --amber:      #8c5c12;
    --amber-2:    #f5e9d4;
    --rose:       #9d3346;
    --rose-2:     #f6e0e3;
    --radius:     10px;
  }
  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --paper:#0e1614; --surface:#16211f; --line:#2b3a36;
      --ink:#e9efec; --ink-2:#9bafa8; --ink-3:#6d827b;
      --accent:#58c4ab; --accent-2:#1a3a33;
      --amber:#d8a55c; --amber-2:#382c17;
      --rose:#e08a98; --rose-2:#3a1f25;
    }
  }
  :root[data-theme="dark"] {
    --paper:#0e1614; --surface:#16211f; --line:#2b3a36;
    --ink:#e9efec; --ink-2:#9bafa8; --ink-3:#6d827b;
    --accent:#58c4ab; --accent-2:#1a3a33;
    --amber:#d8a55c; --amber-2:#382c17;
    --rose:#e08a98; --rose-2:#3a1f25;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0; background: var(--paper); color: var(--ink);
    font-family: "Sarabun", system-ui, sans-serif;
    font-size: 16px; line-height: 1.65; -webkit-font-smoothing: antialiased;
  }
  .wrap { max-width: 1000px; margin: 0 auto; padding: 44px 22px 90px; }
  h1, h2, h3, .num, .eyebrow, .tag, .yr, .mono {
    font-family: "IBM Plex Sans Thai", "Sarabun", system-ui, sans-serif;
  }
  .mono, .num, .yr, .bar-n, .log-date {
    font-family: "IBM Plex Mono", ui-monospace, monospace;
    font-variant-numeric: tabular-nums;
  }
  .eyebrow {
    font-size: .74rem; font-weight: 600; letter-spacing: .13em;
    text-transform: uppercase; color: var(--accent);
  }
  h1 {
    font-size: clamp(1.9rem, 4.6vw, 2.7rem); font-weight: 700;
    line-height: 1.2; letter-spacing: -.01em; margin: 8px 0 10px; text-wrap: balance;
  }
  .lede { color: var(--ink-2); max-width: 64ch; font-size: 1.03rem; margin: 0; }

  .stats {
    display: grid; gap: 1px; margin: 34px 0 0;
    grid-template-columns: repeat(auto-fit, minmax(148px, 1fr));
    background: var(--line); border: 1px solid var(--line);
    border-radius: var(--radius); overflow: hidden;
  }
  .stat { background: var(--surface); padding: 16px 18px; }
  .stat .num { font-size: 1.75rem; font-weight: 600; line-height: 1.1; color: var(--accent); }
  .stat .lbl { font-size: .84rem; color: var(--ink-2); margin-top: 3px; }

  section { margin-top: 50px; }
  h2 { font-size: 1.22rem; font-weight: 600; margin: 0 0 4px; }
  .why { color: var(--ink-2); margin: 0 0 20px; max-width: 66ch; font-size: .97rem; }

  /* ---------- แถบปริมาณงานรายปี ---------- */
  .years { display: grid; gap: 10px; }
  .yrow {
    display: grid; grid-template-columns: 74px 1fr auto; gap: 14px; align-items: center;
  }
  .yr { font-size: .95rem; font-weight: 600; color: var(--ink); }
  .yr small { display: block; font-size: .7rem; color: var(--ink-3); font-weight: 500; }
  .bar-track { background: var(--line); border-radius: 4px; height: 22px; overflow: hidden; }
  .bar-fill { height: 100%; background: var(--accent); border-radius: 4px; }
  .bar-fill.partial { background: repeating-linear-gradient(135deg, var(--accent) 0 7px, var(--accent-2) 7px 14px); }
  .bar-n { font-size: .85rem; color: var(--ink-2); min-width: 96px; text-align: right; }

  /* ---------- ไทม์ไลน์เส้นตั้ง ---------- */
  .tl { position: relative; padding-left: 30px; }
  .tl::before {
    content: ""; position: absolute; left: 7px; top: 6px; bottom: 6px;
    width: 2px; background: var(--line);
  }
  .ev { position: relative; margin-bottom: 26px; }
  .ev::before {
    content: ""; position: absolute; left: -30px; top: 7px;
    width: 16px; height: 16px; border-radius: 50%;
    background: var(--surface); border: 3px solid var(--accent);
  }
  .ev.minor::before { border-color: var(--ink-3); width: 12px; height: 12px; left: -28px; top: 9px; }
  .ev-when {
    font-family: "IBM Plex Mono", monospace; font-size: .78rem; font-weight: 600;
    color: var(--accent); letter-spacing: .02em; font-variant-numeric: tabular-nums;
  }
  .ev.minor .ev-when { color: var(--ink-3); }
  .ev-title { font-family: "IBM Plex Sans Thai", sans-serif; font-weight: 600; font-size: 1.02rem; margin: 1px 0 5px; }
  .ev-body { color: var(--ink-2); font-size: .95rem; max-width: 68ch; margin: 0; }
  .ev-list { list-style: none; margin: 9px 0 0; padding: 0; display: grid; gap: 6px; }
  .ev-list li {
    background: var(--surface); border: 1px solid var(--line); border-radius: 8px;
    padding: 9px 13px; font-size: .92rem; display: grid;
    grid-template-columns: auto 1fr; gap: 10px; align-items: start;
  }
  .tag {
    font-size: .66rem; font-weight: 600; letter-spacing: .05em;
    padding: 2px 7px; border-radius: 5px; white-space: nowrap; margin-top: 3px;
    font-family: "IBM Plex Sans Thai", sans-serif;
  }
  .tag.new { color: var(--accent); background: var(--accent-2); }
  .tag.fix { color: var(--amber);  background: var(--amber-2); }
  .tag.chg { color: var(--ink-2);  background: var(--line); }

  .phase {
    margin: 34px 0 22px; padding: 14px 18px;
    background: var(--accent-2); border-radius: var(--radius);
    border-left: 4px solid var(--accent);
  }
  .phase h3 { margin: 0 0 3px; font-size: 1.05rem; font-weight: 700; color: var(--ink); }
  .phase p { margin: 0; font-size: .93rem; color: var(--ink-2); }

  .callout {
    border-radius: var(--radius); padding: 16px 18px; margin: 22px 0 0;
    border: 1px solid var(--line); background: var(--surface);
  }
  .callout.warn { border-color: var(--rose); background: var(--rose-2); }
  .callout h3 { margin: 0 0 6px; font-size: 1rem; font-weight: 600; }
  .callout.warn h3 { color: var(--rose); }
  .callout p { margin: 0; font-size: .95rem; color: var(--ink-2); }
  .callout.warn p { color: var(--ink); }

  @media (max-width: 560px) {
    .wrap { padding: 30px 16px 64px; }
    .yrow { grid-template-columns: 60px 1fr; row-gap: 4px; }
    .bar-n { grid-column: 2; text-align: left; min-width: 0; }
    .tl { padding-left: 24px; }
    .ev::before { left: -24px; }
    .ev.minor::before { left: -22px; }
    .ev-list li { grid-template-columns: 1fr; gap: 5px; }
    .tag { justify-self: start; margin-top: 0; }
  }

  footer {
    margin-top: 50px; padding-top: 18px; border-top: 1px solid var(--line);
    color: var(--ink-3); font-size: .84rem;
  }
</style>
</head>
<body>
<div class="wrap">

  <header>
    <div class="eyebrow">ศูนย์บริการเวชศาสตร์ป้องกัน · สคร.1</div>
    <h1>เส้นทางระบบคลินิก</h1>
    <p class="lede">
      ตั้งแต่วันแรกที่เปิดใช้ 30 พฤษภาคม 2565 จนถึงวันนี้
      รวม {{ $runningText }} — จากระบบบันทึกผู้ป่วยพื้นฐาน มาเป็นระบบที่ครอบคลุม
      ทั้งงานตรวจ ห้องยา ห้องการเงิน คลังยา รายงานส่งส่วนกลาง และการสื่อสารกับผู้รับบริการ
    </p>

    <div class="stats">
      <div class="stat"><div class="num">{{ number_format($totalPatients) }}</div><div class="lbl">ผู้รับบริการในระบบ</div></div>
      <div class="stat"><div class="num">{{ number_format($totalVisits) }}</div><div class="lbl">ครั้งที่มารับบริการ</div></div>
      <div class="stat"><div class="num">{{ $runningText }}</div><div class="lbl">ใช้งานต่อเนื่อง</div></div>
      <div class="stat"><div class="num">{{ $changeCount }}</div><div class="lbl">สิ่งที่พัฒนาปีนี้</div></div>
      <div class="stat"><div class="num">{{ $clinics }}</div><div class="lbl">คลินิกในระบบเดียว</div></div>
    </div>
  </header>

  <section>
    <h2>ปริมาณงานที่ระบบรองรับในแต่ละปี</h2>
    <p class="why">จำนวนครั้งที่มารับบริการ นับจากเวลาที่บันทึกเข้าระบบจริง</p>
    <div class="years">
      @foreach($years as $y)
      <div class="yrow">
        <div class="yr">{{ $y['year'] }}@if($loop->first)<small>เริ่มใช้ พ.ค.</small>@elseif($y['partial'])<small>ถึงวันนี้</small>@endif</div>
        <div class="bar-track"><div class="bar-fill {{ $y['partial'] ? 'partial' : '' }}" style="width:{{ $y['width'] }}%"></div></div>
        <div class="bar-n">{{ number_format($y['count']) }} ครั้ง</div>
      </div>
      @endforeach
    </div>
  </section>

  <section>
    <h2>เส้นเวลาการพัฒนา</h2>
    <p class="why">
      ระบบเดินมาเงียบ ๆ สามปีแรก แล้วเข้าสู่ช่วงพัฒนาเข้มข้นตั้งแต่กลางปี 2569
      ซึ่งเป็นช่วงที่รายการส่วนใหญ่ในเอกสารนี้เกิดขึ้น — จากการเพิ่มระบบใหม่
      มาเป็นการแก้ให้ตรงกับหน้างาน และล่าสุดคือการจัดโครงสร้างห้องให้ตรงกับลำดับงานจริง
    </p>

    <div class="tl">

      <div class="ev">
        <div class="ev-when">30 พ.ค. 2565</div>
        <div class="ev-title">วันแรกที่เปิดใช้งาน</div>
        <p class="ev-body">เริ่มบันทึกผู้ป่วยรายแรกเข้าระบบ ใช้งานเป็นระบบบันทึกการมารับบริการพื้นฐาน</p>
      </div>

      <div class="ev minor">
        <div class="ev-when">2566 – 2568</div>
        <div class="ev-title">ช่วงใช้งานต่อเนื่อง</div>
        <p class="ev-body">
          ระบบรองรับงานประจำวันปีละราว 2,600–3,200 ครั้ง รวมสามปีนี้ <strong>8,574 ครั้ง</strong>
          — เป็นช่วงสะสมข้อมูลและเรียนรู้ว่าอะไรที่หน้างานยังขาด
        </p>
      </div>

      <div class="ev minor">
        <div class="ev-when">24 เม.ย. – 7 พ.ค. 2569</div>
        <div class="ev-title">เริ่มปรับของเดิม</div>
        <ul class="ev-list">
          <li><span class="tag chg">ปรับปรุง</span><div>ปรับหน้าเลือกชุดรายการตรวจเลือด</div></li>
          <li><span class="tag new">ใหม่</span><div>เลขที่ใบประกอบวิชาชีพ สำหรับแสดงบนใบรับรองแพทย์</div></li>
        </ul>
      </div>

      <div class="phase">
        <h3>มิถุนายน – กรกฎาคม 2569 · วางรากฐานงานเงินและงานทะเบียน</h3>
        <p>ช่วงที่เพิ่มระบบใหญ่หลายตัวพร้อมกัน จากเดิมที่หลายอย่างยังทำนอกระบบ</p>
      </div>

      <div class="ev">
        <div class="ev-when">18 มิ.ย. – 3 ก.ค. 2569</div>
        <div class="ev-title">ห้องยา การเงิน และคลังยา</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>ระบบบิล — เลขที่บิล พิมพ์ใบเสร็จ บันทึกจำนวนครั้งที่พิมพ์</div></li>
          <li><span class="tag new">ใหม่</span><div>รับชำระเป็นรอบได้ รองรับกรณีจ่ายบางส่วนแล้วมาจ่ายเพิ่มทีหลัง</div></li>
          <li><span class="tag new">ใหม่</span><div>เริ่มเก็บจำนวนคงเหลือของยาในระบบ</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>เชื่อม visit เข้ากับระบบเรียกคิวกลาง กดกลับบ้านแล้วคิวปิดเอง</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">6 – 10 ก.ค. 2569</div>
        <div class="ev-title">เครื่องมือหน้างานและการตรวจสอบย้อนหลัง</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>โน้ตติดคนไข้ — เขียนเตือนกันเองระหว่างเจ้าหน้าที่ เด้งให้คนที่ยังไม่ได้อ่าน</div></li>
          <li><span class="tag new">ใหม่</span><div>ออกใบรับรองแพทย์จากในระบบ · บันทึกเวลาที่คนไข้เข้าห้องตรวจ</div></li>
          <li><span class="tag new">ใหม่</span><div>ระบบล็อตยา รับเข้า–ตัดออกตามวันหมดอายุ</div></li>
          <li><span class="tag new">ใหม่</span><div>ประวัติการแก้ไขข้อมูล — ใครแก้อะไร เมื่อไหร่</div></li>
          <li><span class="tag new">ใหม่</span><div>ตั้งราคาค่าตรวจแล็บและค่าบริการเองได้ ไม่ต้องแก้โปรแกรม</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">7 ก.ค. 2569</div>
        <div class="ev-title">เริ่มระบบ LINE</div>
        <p class="ev-body">
          ผู้รับบริการผูกบัญชีด้วยรหัส 6 หลักจากปุ่ม “LINE นัด” ในหน้าตรวจ
          — ไม่ใช้ HN โดยตรงเพื่อป้องกันการสวมสิทธิ์ พร้อมหน้าตรวจสอบประวัติข้อความที่ส่งออก
        </p>
      </div>

      <div class="ev">
        <div class="ev-when">13 – 22 ก.ค. 2569</div>
        <div class="ev-title">งานพิมพ์ ราคากลาง และแล็บภายนอก</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>พิมพ์บัตรคิวจากเครื่องพิมพ์สลิปความร้อน · กำหนดเครื่องพิมพ์ล่วงหน้า</div></li>
          <li><span class="tag new">ใหม่</span><div>ราคากลางตามอัตรา สคร.1 พร้อมหน้าจับคู่กับรายการในระบบ</div></li>
          <li><span class="tag new">ใหม่</span><div>Lab ส่งตรวจภายนอก (N Health) แยกจาก Lab ในบ้าน</div></li>
          <li><span class="tag new">ใหม่</span><div>ดูผลแล็บย้อนหลังรายรายการเป็นกราฟ เช่น RPR ค่าลงถึงไหนแล้ว</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>สลับคลินิก STI / TB / TMC ได้จากแถบเมนูด้านบน</div></li>
          <li><span class="tag fix">แก้ไข</span><div>คนไข้ต่างชาติที่ไม่มีเลขบัตรประชาชน บันทึกได้แล้ว</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">20 ก.ค. 2569</div>
        <div class="ev-title">ผลแล็บที่ยังไม่อนุมัติ ไม่แสดงให้พยาบาลและแพทย์เห็น</div>
        <p class="ev-body">จุดเริ่มของชุดงานด้านความถูกต้องและความเป็นส่วนตัวของข้อมูล</p>
      </div>

      <div class="ev">
        <div class="ev-when">23 – 27 ก.ค. 2569</div>
        <div class="ev-title">รายงานส่งส่วนกลาง และคลินิกนักเดินทาง TMC</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>รายงาน 506 ส่งออกเป็นไฟล์ .dbf นำเข้าโปรแกรม 506 ได้ทันที</div></li>
          <li><span class="tag new">ใหม่</span><div>ทะเบียนคู่สัมผัส 5 โรคหลัก รายเดือน แก้ในตารางบันทึกอัตโนมัติ</div></li>
          <li><span class="tag new">ใหม่</span><div>ประวัติ TMC ครบชุด — การเดินทาง ประเมินความเสี่ยง การวางแผน คำแนะนำ</div></li>
          <li><span class="tag new">ใหม่</span><div>สมุดเล่มเหลือง · ใบรับรองการฉีดวัคซีนภาษาอังกฤษ</div></li>
          <li><span class="tag new">ใหม่</span><div>วางแผนนัดฉีดวัคซีนล่วงหน้าได้ถึง 5 นัด ระบุวัคซีนและเข็มที่</div></li>
        </ul>
      </div>

      <div class="phase">
        <h3>สิงหาคม 2569 · ปรับจากการใช้งานจริง</h3>
        <p>ช่วงที่เปลี่ยนจาก “เพิ่มของใหม่” มาเป็น “แก้สิ่งที่หน้างานสะดุด” เกือบทั้งหมดมาจากที่พยาบาลแจ้ง</p>
      </div>

      <div class="ev">
        <div class="ev-when">3 – 5 ส.ค. 2569</div>
        <div class="ev-title">ทะเบียน RIHIS-VCT และการแจ้งเตือนในระบบ</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>ทะเบียนบริการปรึกษาและตรวจเอชไอวี + ส่งออก Excel ตามฟอร์มส่วนกลาง</div></li>
          <li><span class="tag new">ใหม่</span><div>ปุ่มดึงข้อมูลทั้งปีงบเข้าทะเบียนอัตโนมัติ</div></li>
          <li><span class="tag new">ใหม่</span><div>LINE แจ้งยืนยันนัดอัตโนมัติเมื่อกด “กลับบ้าน”</div></li>
          <li><span class="tag new">ใหม่</span><div>แสดงราคายาและยอดรวมทันทีที่หมอสั่ง ตอบคนไข้ได้เลยว่ากี่บาท</div></li>
          <li><span class="tag new">ใหม่</span><div>หน้า “มีอะไรใหม่” ให้เจ้าหน้าที่เปิดดูเองได้ว่าระบบเปลี่ยนอะไร</div></li>
          <li><span class="tag fix">แก้ไข</span><div>หน้าจอตอบสนองเร็วขึ้นหลังปรับดัชนีฐานข้อมูล</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">11 – 14 ส.ค. 2569</div>
        <div class="ev-title">ใบนัดหมาย และการแจ้งเตือนที่ไม่รบกวน</div>
        <ul class="ev-list">
          <li><span class="tag chg">ปรับปรุง</span><div>เอาเลขบัตรประชาชน / Passport ออกจากใบนัด ไม่พิมพ์ลงกระดาษอีก</div></li>
          <li><span class="tag new">ใหม่</span><div>เพิ่มเบอร์คลินิก 081-8830372 พร้อมวงเล็บว่าติดต่อในวันและเวลาราชการ</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>ย้ายป็อปอัพ “ผลแล็บออกแล้ว” เป็นป้ายเขียวค้างไว้ในหน้า Room</div></li>
          <li><span class="tag fix">แก้ไข</span><div>บัตรคิวจัดกลางกระดาษ · วันนัดแสดงเป็น “21 สิงหาคม 2569”</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">18 – 19 ส.ค. 2569</div>
        <div class="ev-title">แยกสิ่งที่เจ้าหน้าที่คุยกันเอง ออกจากสิ่งที่คนไข้เห็น</div>
        <p class="ev-body">
          เดิมมีช่องเดียว พยาบาลใช้ส่งเวรปนกับเหตุผลนัด แต่ช่องนั้นถูกพิมพ์ลงใบนัดและส่งเข้า LINE
          ให้คนไข้เห็น — ซึ่งเสี่ยงมากกับคลินิกลักษณะนี้
        </p>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>ช่อง “บันทึกภายใน / ส่งเวร” 🔒 แยกจาก “เหตุผลนัด” 👁️ ทาสีคนละสี</div></li>
          <li><span class="tag fix">แก้ไข</span><div>รูปถ่ายคนไข้คมขึ้นมาก — เดิมสั่งกล้องมาที่ 640×480 ทั้งที่ถ่ายได้ 1920×1080</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">20 ส.ค. 2569</div>
        <div class="ev-title">ระบบวันหยุดราชการ</div>
        <p class="ev-body">
          เดิมระบบรู้จักแค่เสาร์–อาทิตย์ วันหยุดนักขัตฤกษ์ถูกมองเป็นวันทำการทั้งหมด
        </p>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>หน้าจัดการวันหยุด ใส่ของปี 2569–2570 ไว้ครบ 44 วัน พยาบาลแก้เองได้</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>ปฏิทินวันนัดล็อกวันหยุด ขึ้นสีแดงขีดฆ่า กดเลือกไม่ได้</div></li>
          <li><span class="tag fix">แก้ไข</span><div>เลิกส่ง LINE เตือนให้มาวันที่คลินิกปิด และแก้นัดหลังหยุดยาวที่เดิมไม่ได้รับแจ้งเลย</div></li>
          <li><span class="tag new">ใหม่</span><div>ถึงพฤศจิกายน ถ้ายังไม่ใส่วันหยุดปีถัดไป จะมีแถบเตือนขึ้นทุกหน้า</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">21 – 24 ส.ค. 2569</div>
        <div class="ev-title">แก้ตามที่พยาบาลแจ้ง</div>
        <ul class="ev-list">
          <li><span class="tag fix">แก้ไข</span><div>บอท LINE เคยแทรก “รหัสไม่ถูกต้อง” กลางบทสนทนา เมื่อคนไข้พิมพ์ประโยคที่มีตัวเลขปนครบ 6 ตัว เช่น “อายุ 53 ปี เกิดปี 2515”</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>ทะเบียนคู่สัมผัส — เลิกเติม “Advice” อัตโนมัติ เว้นว่างให้เจ้าหน้าที่เลือกเอง</div></li>
          <li><span class="tag new">ใหม่</span><div>ข้อความ LINE เพิ่มบรรทัด “กรณีที่นัดฟังผลทาง LINE สอบถามได้ที่นี่เลยค่ะ”</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">26 – 28 ส.ค. 2569</div>
        <div class="ev-title">งานทะเบียน ราคากลาง และแล็บส่งออก</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>นัดได้หลายวันในการมาครั้งเดียว · ปิดนัดค้างจากครั้งก่อนได้จากหน้าตรวจ</div></li>
          <li><span class="tag new">ใหม่</span><div>เจ้าหน้าที่เปลี่ยนรหัสผ่านตัวเองได้ พร้อมบังคับความยาวขั้นต่ำตามหนังสือ สธ 0418.17/719</div></li>
          <li><span class="tag new">ใหม่</span><div>เจ้าหน้าที่จัดการรายการแล็บส่งตรวจภายนอกเองได้ · เพิ่มชุดตรวจ Microbiology และ TB ของ N Health</div></li>
          <li><span class="tag new">ใหม่</span><div>นำบัญชีค่าบริการกระทรวง 4,604 รายการ เข้ามาเป็นหมวดอ้างอิง ไว้ค้นเทียบราคากลาง</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>ทะเบียน RIHIS-VCT ค้นด้วย HN หรือชื่อได้ · เอา Pap smear และ HBs Ab ออกจากใบสั่งแล็บในบ้าน</div></li>
          <li><span class="tag fix">แก้ไข</span><div>ฟอร์มที่อยู่ไม่พังแล้วเมื่อยังไม่ได้เลือกตำบล · หน้าตรวจและหน้าแล็บไม่พังเมื่อ URL ไม่มีเลขหน้า</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">31 ส.ค. 2569</div>
        <div class="ev-title">เลิกเตือนคนไข้ที่มายืนอยู่ในคลินิกแล้ว</div>
        <p class="ev-body">
          พยาบาลแจ้งว่าคนไข้ได้ข้อความ “ยืนยันนัดครั้งหน้า” มาก่อน แล้วข้อความ “อย่าลืมมาวันนี้” ตามมาทีหลัง
          ทั้งที่ตรวจเสร็จกลับบ้านไปแล้ว — ตอนนี้ถ้าคนไข้ลงทะเบียนของวันนั้นแล้ว ระบบจะไม่ส่งเตือนของวันนั้นอีก
          ผลพลอยได้คือประหยัดโควตาข้อความ LINE ที่เคยส่งทิ้งเปล่า
        </p>
      </div>

      <div class="phase">
        <h3>กันยายน 2569 · แยกห้อง และทำให้ตัวเลขรายงานเชื่อถือได้</h3>
        <p>เปลี่ยนโครงสร้างการทำงานหน้างานครั้งใหญ่ที่สุดของปี พร้อมสะสางความคลาดเคลื่อนของรายงานสถิติ</p>
      </div>

      <div class="ev">
        <div class="ev-when">8 ก.ย. 2569</div>
        <div class="ev-title">แยกห้องยา ออกจาก ห้องการเงิน</div>
        <p class="ev-body">
          เดิมสองงานนี้อยู่หน้าจอเดียวกัน เจ้าหน้าที่ห้องยาเปิดมาเจอทั้งการคิดเงิน รับชำระ และจ่ายยาปนกันจนสับสน
          ตอนนี้แยกเป็นคนละห้อง เดินตามลำดับจริงที่คลินิกใช้ — <strong>จ่ายเงินก่อน แล้วค่อยรับยา</strong>
        </p>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>ห้องการเงิน (ห้องคิว 11) — คิดเงิน รับชำระ พิมพ์ใบเสร็จ แล้วส่งคนไข้ไปรับยา</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>ห้องยา (ห้องคิว 10) — เหลือแค่จ่ายยาและตัดสต๊อก ไม่มีเรื่องเงินให้สับสนอีก</div></li>
          <li><span class="tag new">ใหม่</span><div>ห้องยาแก้จำนวนที่จ่ายจริงได้เมื่อของขาด กระทบแค่สต๊อก ไม่กระทบยอดเงินที่รับไปแล้ว</div></li>
          <li><span class="tag new">ใหม่</span><div>จอเรียกคิวเพิ่มห้องการเงินเป็นห้องที่ 11 แยกเสียงเรียกจากห้องยา</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">8 ก.ย. 2569</div>
        <div class="ev-title">แดชบอร์ดสถิติ — ยอดการ์ดกับตารางไม่ตรงกัน</div>
        <p class="ev-body">
          ตรวจพบว่าการ์ดด้านบนนับเฉพาะคลินิกที่เลือกอยู่ แต่ตารางด้านล่างหลายตัวนับรวมทุกคลินิก
          ตัวเลขจึงไม่ตรงกันมาตลอด ตอนนี้ทุกตารางบวกกันได้เท่ากับการ์ดพอดีแล้ว
        </p>
        <ul class="ev-list">
          <li><span class="tag fix">แก้ไข</span><div>ตารางกิจกรรม โรคหลัก และ Lab เคยมีคนไข้ TB และ TMC ปนเข้ามา (เกิน 68 คน · 140 รายการแล็บ)</div></li>
          <li><span class="tag fix">แก้ไข</span><div>สัดส่วนภูมิลำเนาเคยเอายอดคนละชุดมาลบกัน — แยกช่อง “ไม่ระบุที่อยู่” ออกมา 57 คนที่เคยถูกนับเป็นต่างจังหวัด</div></li>
          <li><span class="tag fix">แก้ไข</span><div>ตารางสัญชาติเคยตกหล่นคนไข้ที่ไม่ได้ระบุสัญชาติไป 4 คน</div></li>
          <li><span class="tag new">ใหม่</span><div>ลิงก์ลับสำหรับฉายในห้องประชุม เปิดดูได้โดยไม่ต้อง login · เปลี่ยนลิงก์ได้ทันทีถ้าหลุด</div></li>
          <li><span class="tag chg">ปรับปรุง</span><div>อธิบายในหน้าจอว่ายอดรายเดือนบวกกันแล้วไม่เท่ายอดรวม เพราะเป็นการนับ “คนไม่ซ้ำ” ต่อช่วงเวลา</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">8 ก.ย. 2569</div>
        <div class="ev-title">งานหน้าตรวจ และเครื่องลูกข่าย</div>
        <ul class="ev-list">
          <li><span class="tag new">ใหม่</span><div>หน้ารวมปุ่มลัดเข้าห้อง พร้อมโหลด shortcut ไปวางบนเดสก์ท็อปเครื่องลูกข่ายได้เลย</div></li>
          <li><span class="tag new">ใหม่</span><div>LMP — เลือกได้ว่าประจำเดือนมาแล้วกี่วัน (1–7 วันขึ้นไป)</div></li>
          <li><span class="tag new">ใหม่</span><div>ข้อความสำเร็จรูปสำหรับเคสฟังผล RPR ทางไลน์ ทั้งช่องเหตุผลนัดและอาการเจ็บป่วยปัจจุบัน</div></li>
        </ul>
      </div>

      <div class="ev">
        <div class="ev-when">วันนี้ · {{ $today }}</div>
        <div class="ev-title">สถานะปัจจุบัน</div>
        <p class="ev-body">
          ระบบรองรับ {{ $clinics }} คลินิกในฐานเดียว (STI · TB · TMC) ·
          ผู้รับบริการผูก LINE แล้ว <strong>{{ number_format($lineLinked) }} ราย</strong>
          ส่งข้อความไปแล้ว <strong>{{ number_format($lineMessages) }} ข้อความ</strong> ·
          วันหยุดในระบบ <strong>{{ $holidays }} วัน</strong> ·
          นัดล่วงหน้าที่ยังไม่ถึงกำหนด <strong>{{ number_format($upcoming) }} นัด</strong>
        </p>
      </div>

    </div>
  </section>

  {{-- กล่องนี้อ้างถึงนัดของคนไข้รายบุคคล จึงให้เห็นเฉพาะเจ้าหน้าที่ที่ล็อกอินแล้ว
       คนทั่วไปที่เปิดลิงก์สาธารณะจะไม่เห็นส่วนนี้ --}}
  @auth
    @if($holidayClashes->count())
    <div class="callout warn">
      <h3>เรื่องที่ต้องดำเนินการ <span class="tag chg">เจ้าหน้าที่เท่านั้น</span></h3>
      <p>
        <strong>มีนัดค้าง {{ $holidayClashes->count() }} ราย ตรงกับวันที่คลินิกปิด</strong> —
        @foreach($holidayClashes as $c)
          {{ $c->appointment_date }}{{ $c->reason ? ' (' . trim($c->reason, ' ,') . ')' : '' }}@if(!$loop->last) · @endif
        @endforeach
        · ระบบไม่ส่งแจ้งเตือนให้แล้ว แต่ผู้รับบริการยังไม่ทราบว่าต้องมาวันไหนแทน — รบกวนติดต่อเลื่อนนัด
      </p>
    </div>
    @endif

    <div class="callout">
      <h3>ดูรายละเอียดเต็มได้ในระบบ</h3>
      <p>เมนู <strong>นัดหมาย → มีอะไรใหม่</strong> — ทุกรายการมีคำอธิบายว่าเปลี่ยนอะไร ของเดิมย้ายไปไหน และข้อมูลเก่ากระทบหรือไม่</p>
    </div>
  @endauth

  <footer>
    ข้อมูล ณ {{ $todayFull }} · ตัวเลขทั้งหมดดึงสดจากฐานข้อมูลทุกครั้งที่เปิดหน้านี้ ·
    จำนวนผู้รับบริการและครั้งที่มารับบริการนับจากฐานข้อมูลจริง ·
    รายการพัฒนานับจากบันทึกการเปลี่ยนแปลงในระบบ
  </footer>

</div>
</body>
</html>
