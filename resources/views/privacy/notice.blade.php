@php
    $__all = require resource_path('privacy_notice.php');
    $n = $__all[$lang];
    // ไล่ดูทีละข้อความ (เดิมเช็คจาก json_encode ทั้งก้อน ซึ่งมี "[[" ของโครง array เองเสมอ → ขึ้นว่าร่างตลอด)
    $__draft = false;
    array_walk_recursive($n, function ($v) use (&$__draft) { if (is_string($v) && str_contains($v, '[[')) $__draft = true; });
    // [[ ... ]] = ข้อความที่ยังรอยืนยัน — ไฮไลต์ไว้ให้เห็นชัด
    $fmt = fn ($s) => preg_replace('/\[\[(.+?)\]\]/u', '<mark>$1</mark>', e($s));
@endphp
<!doctype html>
<html lang="{{ $lang }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $lang === 'en' ? 'Privacy Notice' : 'ประกาศความเป็นส่วนตัว' }} · สคร.1</title>
    <style>
        :root { --ink:#1e293b; --muted:#64748b; --line:#e2e8f0; --accent:#1e3a8a; --bg:#f8fafc; --card:#fff; }
        * { box-sizing: border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:'Sarabun','TH Sarabun New','Segoe UI',sans-serif; font-size:17px; line-height:1.65; }
        .wrap { max-width:760px; margin:0 auto; padding:16px; }
        header { background:var(--accent); color:#fff; padding:20px 16px; }
        header .wrap { padding:0 16px; }
        h1 { font-size:1.45rem; margin:0 0 4px; line-height:1.3; text-wrap:balance; }
        .sub { opacity:.85; font-size:.95rem; }
        .lang { margin-top:10px; display:flex; gap:8px; flex-wrap:wrap; }
        .lang a { color:#fff; border:1px solid rgba(255,255,255,.6); border-radius:999px; padding:2px 12px; text-decoration:none; font-size:.9rem; }
        .lang a.on { background:#fff; color:var(--accent); font-weight:700; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:16px 18px; margin-top:14px; }
        h2 { font-size:1.1rem; color:var(--accent); margin:0 0 6px; }
        p { margin:6px 0; }
        .li { margin:6px 0 6px 1.2rem; }
        .meta { color:var(--muted); font-size:.9rem; margin-top:14px; }
        .draft { background:#fef3c7; border:1px solid #f59e0b; color:#78350f; border-radius:8px; padding:8px 12px; margin-top:14px; font-size:.95rem; }
        mark { background:#fde68a; padding:0 3px; border-radius:3px; }
        @media print { header { background:#fff; color:#000; } .lang, .draft { display:none; } .card { border:0; padding:0; } }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <h1>{{ $n['title'] }}</h1>
        <div class="sub">{{ $n['subtitle'] }}</div>
        <div class="lang">
            <a href="{{ route('privacy.notice') }}" class="{{ $lang === 'th' ? 'on' : '' }}">ภาษาไทย</a>
            <a href="{{ route('privacy.notice', ['lang' => 'en']) }}" class="{{ $lang === 'en' ? 'on' : '' }}">English</a>
        </div>
    </div>
</header>
<main class="wrap">
    @if($__draft)
        <div class="draft">{{ $lang === 'en' ? 'Draft — highlighted text is awaiting confirmation by the DDC Data Protection Officer.' : 'ฉบับร่าง — ข้อความที่ไฮไลต์รอเจ้าหน้าที่คุ้มครองข้อมูลส่วนบุคคล (DPO) ของกรมยืนยัน' }}</div>
    @endif
    <div class="card">
        @foreach($n['intro'] as $__p)<p>{!! $fmt($__p) !!}</p>@endforeach
    </div>
    @foreach($n['sections'] as $__s)
        <section class="card">
            <h2>{{ $__s['h'] }}</h2>
            @foreach($__s['body'] as [$__t, $__x])
                <div class="{{ $__t === 'li' ? 'li' : '' }}">{!! $fmt($__x) !!}</div>
            @endforeach
        </section>
    @endforeach
    <div class="meta">{!! $fmt($n['issued']) !!} · {{ $lang === 'en' ? 'Version' : 'ฉบับที่' }} {{ $version }}</div>
</main>
</body>
</html>
