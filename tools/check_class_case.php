<?php
/**
 * อ้างชื่อคลาสผิดตัวพิมพ์ (เช่น App\Models\visit) — PHP ไม่สน แต่ตัวโหลดไฟล์บน Linux หาไฟล์ไม่เจอ (2569-10-20)
 * รัน: php tools/check_class_case.php
 */
$root = dirname(__DIR__);
$declared = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS)) as $f) {
    if (substr($f, -4) !== '.php') { continue; }
    $s = file_get_contents($f);
    if (preg_match('/^namespace\s+([^;]+);/m', $s, $ns) && preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m', $s, $c)) {
        $fq = $ns[1] . '\\' . $c[1];
        $declared[strtolower($fq)] = $fq;
    }
}
$issues = [];
$dirs = ['app', 'config', 'routes', 'resources/views', 'database', 'tests'];
foreach ($dirs as $d) {
    if (! is_dir("$root/$d")) { continue; }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$d", FilesystemIterator::SKIP_DOTS)) as $f) {
        if (substr($f, -4) !== '.php') { continue; }
        $s = file_get_contents($f);
        $rel = substr(str_replace('\\', '/', $f), strlen($root) + 1);
        if (preg_match_all('/\\\\?(App(?:\\\\\w+)+)/', $s, $m)) {
            foreach (array_unique($m[1]) as $ref) {
                $k = strtolower($ref);
                if (isset($declared[$k]) && $declared[$k] !== $ref) { $issues[] = "{$rel}: {$ref} → ต้องเป็น {$declared[$k]}"; }
            }
        }
    }
}
echo $issues ? implode("\n", $issues) . "\n" : '';
echo 'พบ ' . count($issues) . " จุด\n";
