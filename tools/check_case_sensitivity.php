<?php
/**
 * ตรวจชื่อไฟล์ตัวพิมพ์เล็ก/ใหญ่ ก่อนย้ายขึ้น server Linux (2569-10-20)
 *
 * Windows ไม่สนตัวพิมพ์ — view('Lab.edit') เปิดไฟล์ lab/edit.blade.php ได้ แต่บน Linux จะหาไม่เจอ (หน้า 500)
 * ไล่หา: ชื่อ view ใน view()/@include/@extends/@each/@component · ไฟล์ใน asset()/url()/src/href แบบตายตัว
 *         · require/include ของไฟล์ PHP · แล้วเทียบกับชื่อไฟล์จริงแบบตรงตัวพิมพ์
 * รัน: php tools/check_case_sensitivity.php   (ไม่แก้อะไร แค่รายงาน)
 */
$root = dirname(__DIR__);
$issues = [];

/** path นี้มีจริงแบบตรงตัวพิมพ์ทุกชั้นไหม (null = ไม่มีเลยแม้ไม่สนตัวพิมพ์) */
function exactExists(string $base, string $rel): ?bool
{
    static $cache = [];
    $parts = array_values(array_filter(explode('/', str_replace('\\', '/', $rel)), fn ($p) => $p !== '' && $p !== '.'));
    $dir = $base;
    $exact = true;
    foreach ($parts as $p) {
        if ($p === '..') { $dir = dirname($dir); continue; }
        if (! isset($cache[$dir])) { $cache[$dir] = is_dir($dir) ? scandir($dir) : []; }
        if (in_array($p, $cache[$dir], true)) { $dir .= '/' . $p; continue; }
        $ci = null;
        foreach ($cache[$dir] as $e) { if (strcasecmp($e, $p) === 0) { $ci = $e; break; } }
        if ($ci === null) { return null; }
        $exact = false;
        $dir .= '/' . $ci;
    }
    return $exact;
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $path = str_replace('\\', '/', $f->getPathname());
    if (! preg_match('/\.php$/', $path)) { continue; }
    if (preg_match('#/(vendor|node_modules|storage|\.git|bootstrap/cache|sti)/#', $path)) { continue; }
    $rel = substr($path, strlen($root) + 1);
    $src = file_get_contents($path);

    // 1) ชื่อ view
    if (preg_match_all("/(?:view|View::make|@include(?:If|When|Unless|First)?|@extends|@each|@component|->view|markdown)\s*\(\s*['\"]([A-Za-z0-9_.\-\/:]+)['\"]/", $src, $m)) {
        foreach ($m[1] as $v) {
            if (str_contains($v, '::')) { continue; }   // view ของแพ็กเกจ
            $file = 'resources/views/' . str_replace('.', '/', $v);
            $r1 = exactExists($root, $file . '.blade.php');
            $r2 = $r1 === null ? exactExists($root, $file . '.php') : $r1;
            if ($r2 === false) { $issues[] = "VIEW    {$rel}: '{$v}' (ตัวพิมพ์ไม่ตรงกับชื่อไฟล์)"; }
        }
    }
    // 2) ไฟล์ใน public ที่อ้างแบบตายตัว
    if (preg_match_all("/(?:asset|url|secure_asset|mix)\(\s*['\"]\/?([^'\"\$\{]+?\.(?:js|css|png|jpe?g|gif|svg|ico|mp3|wav|woff2?|ttf|pdf|json))['\"]/i", $src, $m)) {
        foreach ($m[1] as $a) {
            if (exactExists($root . '/public', $a) === false) { $issues[] = "ASSET   {$rel}: '{$a}' (ตัวพิมพ์ไม่ตรง)"; }
        }
    }
    if (preg_match_all("/(?:src|href)=[\"']\/([A-Za-z0-9_\-\/.]+\.(?:js|css|png|jpe?g|gif|svg|ico|mp3|woff2?))[\"']/i", $src, $m)) {
        foreach ($m[1] as $a) {
            if (exactExists($root . '/public', $a) === false) { $issues[] = "ASSET   {$rel}: '/{$a}' (ตัวพิมพ์ไม่ตรง)"; }
        }
    }
    // 3) require/include แบบ path ตายตัวเทียบกับไฟล์นั้น
    if (preg_match_all("/(?:require|include)(?:_once)?\s*\(?\s*(?:__DIR__\s*\.\s*)?['\"]([^'\"\$]+\.php)['\"]/", $src, $m)) {
        foreach ($m[1] as $inc) {
            if (preg_match('#^[A-Za-z]:#', $inc)) { $issues[] = "WINPATH {$rel}: '{$inc}'"; continue; }
            if (exactExists(dirname($path), ltrim($inc, '/')) === false) { $issues[] = "INCLUDE {$rel}: '{$inc}' (ตัวพิมพ์ไม่ตรง)"; }
        }
    }
}

// 4) ไฟล์ asset ที่ JS ฝั่งคิวเรียก (ชื่อไฟล์เสียง) — ตรวจแค่ว่ามีไฟล์ชื่อซ้ำต่างแค่ตัวพิมพ์ไหม (Linux จะเห็นเป็น 2 ไฟล์)
$seen = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/public', FilesystemIterator::SKIP_DOTS)) as $f) {
    $p = strtolower(str_replace('\\', '/', $f->getPathname()));
    if (str_contains($p, '/vendor/')) { continue; }
    if (isset($seen[$p])) { $issues[] = "DUP     {$p}"; }
    $seen[$p] = true;
}

echo $issues ? implode("\n", array_unique($issues)) . "\n" : '';
echo 'พบ ' . count(array_unique($issues)) . " จุด\n";
