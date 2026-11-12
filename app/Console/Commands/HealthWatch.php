<?php

namespace App\Console\Commands;

use App\Support\HealthAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * เฝ้าระบบทุกนาที แจ้ง LINE ผู้ดูแลเมื่อล่ม / กลับมาแล้ว (2569-10-20)
 *
 * เรียก /up ผ่านเว็บจริง (เหมือนเครื่องพยาบาลเปิด) จึงจับได้ทั้ง Apache ดับ, PHP พัง, MySQL ดับ
 * ไม่ใช้ฐานข้อมูลเลย — สถานะเก็บใน storage/app/health-watch.json
 * พลาด 2 ครั้งติดกัน (≈2 นาที) ถึงแจ้ง กันแจ้งมั่วตอนเครื่องกระตุก/รีสตาร์ต Apache
 *
 * ข้อจำกัด: รันบนเครื่องเดียวกับระบบ — ถ้าเครื่องดับทั้งเครื่องจะไม่มีใครแจ้ง
 * หลังย้ายขึ้น server สำนักงาน ให้เครื่องพัฒนานี้รันคำสั่งนี้ชี้ไป server (--url หรือ HEALTH_WATCH_URL) จะเฝ้าข้ามเครื่องได้
 */
class HealthWatch extends Command
{
    protected $signature = 'clinic:health-watch {--url= : URL ของ /up ที่จะเฝ้า (ค่าเริ่มต้น HEALTH_WATCH_URL หรือ APP_URL/up)}';
    protected $description = 'เช็ค /up แล้วแจ้ง LINE ผู้ดูแลเมื่อระบบล่มหรือกลับมาใช้งานได้';

    private const FAILS_BEFORE_ALERT = 2;

    /*
     * ช่องเชื่อมต่อขาออกของ Windows (ephemeral port) เคยหมดจนเว็บล่ม — 21, 26, 27, 28 ต.ค. 69 (Tcpip 4231)
     * Windows ไม่บอกว่าโปรแกรมไหนกิน จึงจดเอง: นับทุกนาที ถ้าใช้เกินเกณฑ์ หรือเว็บล่ม → จดว่าโปรแกรมไหนถือกี่ช่อง
     * ลง storage/logs/port-usage.log (และแนบท้ายข้อความแจ้งล่ม) · ช่วงปกติ 49152-65535 = 16,384 ช่อง
     */
    private const PORT_WARN = 8000;

    public function handle(): int
    {
        $url = $this->option('url') ?: config('services.health.watch_url');
        $file = storage_path('app/health-watch.json');
        $s = (json_decode((string) @file_get_contents($file), true) ?: []) + [
            'state' => 'up', 'fails' => 0, 'first_fail' => null, 'down_since' => null, 'warnings' => [],
        ];

        [$ok, $detail, $warnings] = $this->probe($url);
        $now = now();
        $ports = $this->portSnapshot();
        $portTxt = '';
        if ($ports && (! $ok || $ports['total'] >= self::PORT_WARN)) {
            $portTxt = $this->portText($ports);
            // จดไม่เกินนาทีละครั้งอยู่แล้ว (คำสั่งนี้รันนาทีละครั้ง)
            @file_put_contents(storage_path('logs/port-usage.log'),
                $now->toDateTimeString() . ' · ' . ($ok ? 'เว็บยังใช้ได้' : 'เว็บล่ม: ' . $detail) . "\n" . $portTxt . "\n\n", FILE_APPEND | LOCK_EX);
        }
        $s['ports'] = $ports['total'] ?? null;
        // ใกล้หมด (ยังไม่ล่ม) — แจ้งล่วงหน้าครั้งเดียวจนกว่าจะลดลง
        if ($ok && $ports && $ports['total'] >= self::PORT_WARN) {
            $warnings['ช่องเชื่อมต่อใกล้หมด'] = 'warn: ใช้ ' . number_format($ports['total']) . ' ช่อง — ' . $this->portTop($ports);
        }
        $s['last_check'] = $now->toDateTimeString();
        $s['last_result'] = $ok ? 'ok' : $detail;
        $s['url'] = $url;

        if (! $ok) {
            $s['fails']++;
            $s['first_fail'] ??= $now->toDateTimeString();
            if ($s['state'] !== 'down' && $s['fails'] >= self::FAILS_BEFORE_ALERT) {
                $s['state'] = 'down';
                $s['down_since'] = $s['first_fail'];
                HealthAlert::send("🔴 ระบบคลินิกใช้งานไม่ได้\nตั้งแต่ " . substr($s['first_fail'], 11, 5) . " น.\nสาเหตุ: {$detail}\n{$url}"
                    . ($ports ? "\nช่องเชื่อมต่อที่ใช้: " . number_format($ports['total']) . ' · ' . $this->portTop($ports) : ''));
            }
        } else {
            if ($s['state'] === 'down') {
                $min = (int) round(\Carbon\Carbon::parse($s['down_since'])->diffInMinutes($now, true));
                HealthAlert::send("🟢 ระบบคลินิกกลับมาใช้งานได้แล้ว " . $now->format('H:i') . " น.\n(ใช้ไม่ได้ประมาณ {$min} นาที)");
            }
            // จุดที่ควรดู (ดิสก์ใกล้เต็ม, Reverb ดับ ฯลฯ) — แจ้งเฉพาะรายการที่เพิ่งเกิดใหม่ ไม่แจ้งซ้ำทุกนาที
            $new = array_diff_key($warnings, $s['warnings']);
            if ($new) {
                HealthAlert::send("🟡 ระบบคลินิกยังใช้งานได้ แต่มีจุดที่ควรดู\n" . implode("\n", array_map(
                    fn ($k, $v) => "• {$k}: " . substr($v, 6), array_keys($new), $new)));
            }
            $s['warnings'] = $warnings;
            $s['state'] = 'up';
            $s['fails'] = 0;
            $s['first_fail'] = $s['down_since'] = null;
        }

        file_put_contents($file, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        $this->line(($ok ? 'ok' : "FAIL ({$s['fails']}): {$detail}") . ($warnings ? ' · warn: ' . implode(', ', array_keys($warnings)) : ''));
        return 0;
    }

    /**
     * นับการเชื่อมต่อ TCP ของทั้งเครื่อง (netstat -ano — ไม่ต้องเปิดช่องใหม่ ใช้ได้แม้ช่องหมด)
     * คืน total · ตามสถานะ · ตามโปรแกรม · TIME_WAIT ตามพอร์ตปลายทาง (TIME_WAIT ไม่มีเจ้าของ ต้องดูจากปลายทาง เช่น 3307 = MySQL)
     */
    private function portSnapshot(): ?array
    {
        if (PHP_OS_FAMILY !== 'Windows') { return null; }
        $out = @shell_exec('netstat -ano -p tcp 2>NUL');
        if (! $out) { return null; }
        $total = 0; $state = []; $pid = []; $tw = [];
        foreach (preg_split('/\r?\n/', $out) as $line) {
            if (! preg_match('/^\s*TCP\s+\S+\s+(\S+)\s+(\S+)\s+(\d+)\s*$/', $line, $m)) { continue; }
            $total++;
            $state[$m[2]] = ($state[$m[2]] ?? 0) + 1;
            if ($m[2] === 'TIME_WAIT') {
                $rp = substr(strrchr($m[1], ':'), 1);
                $tw[$rp] = ($tw[$rp] ?? 0) + 1;
            } elseif ($m[3] !== '0') {
                $pid[$m[3]] = ($pid[$m[3]] ?? 0) + 1;
            }
        }
        arsort($state); arsort($pid); arsort($tw);
        $pid = array_slice($pid, 0, 8, true);
        $names = [];
        $tl = @shell_exec('tasklist /fo csv /nh 2>NUL') ?: '';
        foreach (preg_split('/\r?\n/', $tl) as $row) {
            $c = str_getcsv($row);
            if (isset($c[1]) && isset($pid[$c[1]])) { $names[$c[1]] = $c[0]; }
        }
        $top = [];
        foreach ($pid as $p => $n) { $top[] = ['name' => $names[$p] ?? '?', 'pid' => $p, 'n' => $n]; }
        return ['total' => $total, 'states' => $state, 'top' => $top, 'timewait_to' => array_slice($tw, 0, 6, true)];
    }

    private function portTop(array $p): string
    {
        $a = array_map(fn ($t) => "{$t['name']} {$t['n']}", array_slice($p['top'], 0, 3));
        $w = array_map(fn ($port, $n) => "→{$port}:{$n}", array_keys(array_slice($p['timewait_to'], 0, 2, true)), array_slice($p['timewait_to'], 0, 2, true));
        return implode(', ', $a) . ($w ? ' · TIME_WAIT ' . implode(' ', $w) : '');
    }

    private function portText(array $p): string
    {
        $l = ['รวม ' . $p['total'] . ' · ' . implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($p['states']), $p['states']))];
        foreach ($p['top'] as $t) { $l[] = sprintf('  %-28s pid %-6s %d', $t['name'], $t['pid'], $t['n']); }
        $l[] = '  TIME_WAIT ไปพอร์ต: ' . implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($p['timewait_to']), $p['timewait_to']));
        return implode("\n", $l);
    }

    /** @return array{0: bool, 1: string, 2: array<string,string>} */
    private function probe(string $url): array
    {
        try {
            // ใบรับรองของคลินิกเป็นแบบออกเอง (self-signed) — เช็คแค่ว่าตอบไหม ไม่ได้ส่งข้อมูลลับ
            $r = Http::withoutVerifying()->timeout(15)->connectTimeout(5)->get($url);
        } catch (\Throwable $e) {
            return [false, 'เว็บไม่ตอบ (Apache ดับ หรือเครือข่ายขัดข้อง)', []];
        }
        $checks = (array) ($r->json('checks') ?? []);
        $warnings = array_filter($checks, fn ($v) => is_string($v) && str_starts_with($v, 'warn'));
        if ($r->status() === 503) {
            $bad = array_keys(array_filter($checks, fn ($v) => $v === 'fail'));
            return [false, 'ระบบตอบว่าใช้ไม่ได้: ' . (implode(', ', $bad) ?: 'ไม่ทราบจุด'), $warnings];
        }
        if (! $r->successful() || ! $r->json('status')) {
            return [false, 'หน้าเว็บ error (HTTP ' . $r->status() . ')', []];
        }
        return [true, 'ok', $warnings];
    }
}
