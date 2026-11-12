<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Clinic') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --g700:#047857; --g600:#059669; --g500:#10b981; --g400:#34d399;
            --ink:#0f172a; --muted:#64748b; --line:#e6ebf1;
        }
        *{ box-sizing:border-box; margin:0; padding:0; }
        html{ scroll-behavior:smooth; }
        body{
            font-family:'Sarabun', system-ui, -apple-system, Segoe UI, sans-serif;
            color:var(--ink); min-height:100vh; position:relative; overflow-x:hidden;
            background:
                radial-gradient(1100px 560px at 8% -12%, #d1fae5 0%, transparent 55%),
                radial-gradient(900px 480px at 100% -6%, #cffafe 0%, transparent 52%),
                radial-gradient(700px 700px at 92% 108%, #ecfccb 0%, transparent 55%),
                linear-gradient(180deg,#f9fbfd 0%, #eef3f8 100%);
            -webkit-font-smoothing:antialiased;
        }
        .wrap{ max-width:1120px; margin:0 auto; padding:22px 20px 40px; }

        /* ---- Topbar ---- */
        .topbar{ display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .brand-mini{ display:flex; align-items:center; gap:11px; font-weight:800; color:var(--g700); font-size:1.05rem; }
        .brand-mini img{ height:40px; width:auto; }
        .ghost-btn{
            display:inline-flex; align-items:center; gap:7px; text-decoration:none;
            color:var(--g700); font-weight:700; font-size:.95rem;
            padding:9px 18px; border-radius:12px; border:1.5px solid rgba(5,150,105,.28);
            background:rgba(255,255,255,.6); backdrop-filter:blur(8px); transition:.18s;
        }
        .ghost-btn:hover{ background:#fff; border-color:var(--g500); transform:translateY(-1px); }

        /* ---- Hero ---- */
        .hero{
            margin-top:22px; display:grid; grid-template-columns:1.15fr .85fr; gap:30px; align-items:center;
            background:rgba(255,255,255,.68); backdrop-filter:blur(16px);
            border:1px solid rgba(255,255,255,.7); border-radius:28px;
            box-shadow:0 26px 70px -26px rgba(4,80,60,.35); padding:46px 48px;
            animation:rise .6s cubic-bezier(.2,.7,.3,1) both;
        }
        @keyframes rise{ from{ opacity:0; transform:translateY(16px);} to{ opacity:1; transform:none; } }
        .ver-badge{
            display:inline-flex; align-items:center; gap:7px; font-weight:800; font-size:.82rem; letter-spacing:.3px;
            color:#fff; background:linear-gradient(135deg,var(--g500),var(--g700));
            padding:6px 15px; border-radius:999px; box-shadow:0 8px 18px -6px rgba(5,150,105,.6);
        }
        .ver-badge .dot{ width:8px; height:8px; border-radius:50%; background:#a7f3d0; box-shadow:0 0 0 3px rgba(167,243,208,.35); }
        .hero h1{ font-size:2.5rem; line-height:1.12; margin:16px 0 8px; letter-spacing:-.5px; }
        .hero h1 .grad{ background:linear-gradient(120deg,var(--g600),#0ea5e9); -webkit-background-clip:text; background-clip:text; color:transparent; }
        .hero .sub{ color:var(--muted); font-size:1.02rem; line-height:1.6; max-width:34rem; }
        .cta-row{ display:flex; flex-wrap:wrap; gap:14px; margin-top:26px; }
        .cta{
            display:inline-flex; align-items:center; gap:11px; text-decoration:none;
            background:linear-gradient(135deg,#10b981 0%, #059669 60%, #047857 100%);
            color:#fff; font-weight:800; font-size:1.12rem; padding:16px 34px; border-radius:16px;
            box-shadow:0 16px 34px -10px rgba(5,150,105,.65); position:relative; overflow:hidden;
            transition:transform .18s ease, box-shadow .18s ease, filter .18s ease;
        }
        .cta:hover{ transform:translateY(-3px); box-shadow:0 22px 46px -10px rgba(5,150,105,.72); filter:brightness(1.06); }
        .cta:active{ transform:translateY(-1px); }
        .cta .ico{ font-size:1.25rem; }
        .cta::after{ content:""; position:absolute; top:0; left:-120%; width:60%; height:100%;
            background:linear-gradient(100deg, transparent, rgba(255,255,255,.45), transparent); transform:skewX(-18deg); }
        .cta:hover::after{ animation:shine .9s ease; }
        @keyframes shine{ to{ left:130%; } }

        .feat-row{ display:flex; flex-wrap:wrap; gap:8px; margin-top:24px; }
        .chip{ display:inline-flex; align-items:center; gap:6px; font-size:.82rem; font-weight:600; color:var(--g700);
            background:#ecfdf5; border:1px solid #c7f0dd; padding:6px 12px; border-radius:999px; }

        /* hero art */
        .hero-art{ display:flex; justify-content:center; }
        .logo-orb{
            width:230px; height:230px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            background:radial-gradient(circle at 30% 25%, #ffffff, #ecfdf5 60%, #d1fae5);
            box-shadow:0 30px 60px -20px rgba(4,120,87,.45), inset 0 2px 10px rgba(255,255,255,.9);
            border:6px solid rgba(255,255,255,.85); position:relative;
        }
        .logo-orb::before{ content:""; position:absolute; inset:-14px; border-radius:50%;
            border:2px dashed rgba(16,185,129,.35); animation:spin 26s linear infinite; }
        @keyframes spin{ to{ transform:rotate(360deg); } }
        .logo-orb img{ width:150px; height:auto; }

        /* ---- Changelog ---- */
        .section-title{ margin:44px 4px 4px; font-size:1.4rem; display:flex; align-items:center; gap:10px; }
        .section-title small{ color:var(--muted); font-weight:500; font-size:.9rem; }
        .timeline{ margin-top:20px; position:relative; padding-left:8px; }
        .rel{
            background:rgba(255,255,255,.72); backdrop-filter:blur(10px);
            border:1px solid var(--line); border-left:5px solid var(--accent,#94a3b8);
            border-radius:16px; padding:18px 22px; margin-bottom:16px;
            box-shadow:0 10px 30px -18px rgba(15,40,30,.35); transition:.2s;
        }
        .rel:hover{ transform:translateX(3px); box-shadow:0 16px 40px -18px rgba(15,40,30,.4); }
        .rel .rhead{ display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .rel .vtag{ font-weight:800; font-size:1.08rem; color:var(--accent,#475569); }
        .rel .pill{ font-size:.68rem; font-weight:700; padding:2px 10px; border-radius:999px; }
        .rel ul{ margin:12px 0 0; padding-left:20px; color:#3f4b5b; font-size:.92rem; line-height:1.8; columns:1; }
        .rel ul b{ color:#1f2a37; }
        .rel.now{ --accent:#059669; } .rel.now .pill{ background:#d1fae5; color:#047857; }
        .rel.v15{ --accent:#0ea5e9; }
        .rel.v10{ --accent:#94a3b8; }

        footer{ text-align:center; color:var(--muted); font-size:.86rem; margin-top:34px; padding-top:18px; border-top:1px solid var(--line); }

        @media (max-width:840px){
            .hero{ grid-template-columns:1fr; padding:30px 26px; text-align:center; }
            .hero .sub{ margin:0 auto; }
            .cta-row,.feat-row{ justify-content:center; }
            .hero-art{ order:-1; }
            .logo-orb{ width:170px; height:170px; } .logo-orb img{ width:112px; }
            .hero h1{ font-size:2rem; }
        }
    </style>
</head>
<body>
    <div class="wrap">

        {{-- Topbar --}}
        <div class="topbar">
            <div class="brand-mini">
                <img src="/images/logo.png" alt="logo"> {{ config('app.name', 'Clinic 2.0') }}
            </div>
            @auth
                <a class="ghost-btn" href="{{ url('/home') }}"><i>▦</i> แดชบอร์ด</a>
            @else
                @if (Route::has('login'))
                    <a class="ghost-btn" href="{{ route('login') }}">เข้าสู่ระบบ →</a>
                @endif
            @endauth
        </div>

        {{-- Hero --}}
        <div class="hero">
            <div>
                <span class="ver-badge"><span class="dot"></span> {{ config('app.name', 'Clinic 2.0') }} · ก.ค. 2569</span>
                <h1>โปรแกรม<span class="grad">เวชระเบียน</span>ผู้ป่วย</h1>
                <p class="sub">
                    คลินิกพิเศษกามโรค · วัณโรค · เวชศาสตร์การเดินทางและท่องเที่ยว<br>
                    สำนักงานป้องกันควบคุมโรคที่ 1 เชียงใหม่ · กรมควบคุมโรค
                </p>

                <div class="cta-row">
                    @auth
                        <a class="cta" href="{{ url('/home') }}"><span class="ico">🩺</span> เข้าใช้งานระบบ</a>
                    @else
                        <a class="cta" href="{{ route('login') }}"><span class="ico">🔐</span> เข้าใช้งานระบบ</a>
                    @endauth
                </div>

                <div class="feat-row">
                    <span class="chip">🎫 บัตรคิว · ใบนัด</span>
                    <span class="chip">🧪 คิดเงิน Lab อัตโนมัติ</span>
                    <span class="chip">🔎 ค้นหา Realtime</span>
                    <span class="chip">💾 บันทึกอัตโนมัติ</span>
                </div>
            </div>

            <div class="hero-art">
                <div class="logo-orb"><img src="/images/logo.png" alt="clinic logo"></div>
            </div>
        </div>

        {{-- Changelog --}}
        <div class="section-title">🗒️ บันทึกการพัฒนา <small>— ฟีเจอร์และการแก้ไขหลักในแต่ละเวอร์ชัน</small></div>
        <div class="timeline">

            <div class="rel now">
                <div class="rhead"><span class="vtag">Clinic 2.0</span> <span class="pill">ล่าสุด · ก.ค. 2569</span></div>
                <ul>
                    <li><b>ใบนัดหมาย</b> — พิมพ์จากหน้าตรวจ (A4 ตัดครึ่ง) เลือกแพทย์ผู้นัดได้</li>
                    <li><b>คิดเงิน Lab อัตโนมัติ</b> — เลือดคิดราคาเอง · Gram/C&amp;S รวมเป็นแพ็กเกจ · HBs Ag/Anti-HCV เบิก สปสช ได้ (ตั้งได้ทั้งหน้าตรวจและการเงิน)</li>
                    <li><b>เรียกคิวห้องยา/การเงิน (ห้อง 10)</b> — เพิ่มห้องบนจอเรียกคิว + กดกลับบ้านปิดคิวอัตโนมัติ</li>
                    <li><b>โน้ตถึงห้องบัตร</b> · <b>ค้นหาแบบ Realtime</b> (ทะเบียน/Lab/Visit)</li>
                    <li><b>บันทึกอัตโนมัติ</b> ช่องอาการ/ตรวจร่างกาย/การรักษา + <b>เตือนเมื่อมีข้อมูลยังไม่ได้ Save</b></li>
                    <li>ปุ่มพิมพ์ผล Lab เด่นขึ้น · แก้ Patient Journey (ลงเวลาพบแพทย์เฉพาะหลังรับคนไข้)</li>
                </ul>
            </div>

            <div class="rel v15">
                <div class="rhead"><span class="vtag">Clinic 1.5</span></div>
                <ul>
                    <li>ตั้ง<b>ราคากลาง Lab</b> + หน้าจัดการราคา · ระบบ <b>Audit Log กลาง</b> + ปุ่ม “ดูประวัติการแก้ไข”</li>
                    <li><b>RPR ฟรีอัตโนมัติ</b> (Syphilis + สั่ง TPHA คู่) · ปุ่ม<b>ขอความอนุเคราะห์</b>ยกเว้นค่าบริการรายตัว</li>
                    <li><b>ใบรับรองแพทย์</b> (แก้ไขด่วน + ดูย้อนหลัง) · <b>บัตรคิวรายคน</b> + QR ประเมินความพึงพอใจ</li>
                    <li><b>แจ้งเตือนนัดหมายทาง LINE</b> · ปรับความสวยงามหลายหน้า · เพิ่มสถานะ Lab และตัวเลือกผลตรวจ</li>
                </ul>
            </div>

            <div class="rel v10">
                <div class="rhead"><span class="vtag">เวอร์ชันแรก</span> <span class="pill" style="background:#e2e8f0;color:#475569;">ความปลอดภัย · พื้นฐาน</span></div>
                <ul>
                    <li>ปิดช่องโหว่ <b>phpMyAdmin</b> ที่เปิดสาธารณะจากเครือข่าย</li>
                    <li>ระบบ<b>จัดการสต๊อกยา</b> (ราคา/หน่วย/จุดสั่งซื้อ) + ตัดสต๊อกตอนจ่ายยา</li>
                    <li>ระบบ<b>ใบเสร็จ/เลขที่บิล</b> + วิธีชำระเงิน · แก้ปุ่ม Back ที่พาไปผิดคนไข้</li>
                </ul>
            </div>

        </div>

        <footer>
            {{ config('app.name', 'Clinic') }} · เวชระเบียนคลินิกโรคติดต่อทางเพศสัมพันธ์ · สคร.1 เชียงใหม่
        </footer>
    </div>

    @include('sweetalert::alert')
</body>
</html>
