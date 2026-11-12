<?php

namespace App\Services\TbMonitor;

use App\Support\TbMonitorDefinitions;
use DateTimeImmutable;

/**
 * ตัวคำนวณตารางติดตามการรักษา — PHP ล้วน ไม่แตะฐานข้อมูล ไม่แตะ Laravel
 * (ทดสอบด้วยข้อมูลสมมติได้โดยไม่ต้องเขียนอะไรลงฐาน — สำคัญเพราะระบบนี้ใช้กับคนไข้จริง)
 *
 * วันที่ทุกตัวในคลาสนี้เป็น ค.ศ. 'Y-m-d' — ตัวที่ดึงข้อมูล (TbMonitor) แปลง พ.ศ. ให้ก่อนส่งเข้ามา
 *
 * ขั้นตอน
 *   1) วันที่ควรตรวจของแต่ละจุด = วันเริ่มยา + ระยะ (สัปดาห์ = 7 วัน, เดือน = เดือนปฏิทิน, หลังจบ = treat_months + n เดือน)
 *   2) ผลตรวจหนึ่งครั้งลง "จุดที่ใกล้ที่สุด" ภายในช่วงที่ยอมรับ (TbMonitorDefinitions::WINDOWS) — ครั้งเดียวลงได้จุดเดียว
 *      จุดหนึ่งมีผลหลายครั้ง = เก็บครั้งที่ใกล้วันที่ควรตรวจที่สุด
 *   3) ช่องมีเงื่อนไข (AFB บวก ฯลฯ) ตัดสินจากผลที่ลงจุดแล้วในข้อ 2
 *   4) สถานะ: ตรวจแล้ว / ถึงกำหนด (วันนี้อยู่ในช่วง) / เลยกำหนด / ยังไม่ถึง / พิจารณา / มีเงื่อนไข
 *
 * $events  = [checkKey => [ ['date' => AD|null, 'match' => AD|null, 'text' => str, 'pos' => bool|null,
 *                            'pending' => bool, 'src' => str], ... ]]
 *            'match' = วันที่ใช้จับคู่ (ตารางเสมหะใช้ป้ายเดือนที่พยาบาลกรอก ไม่ใช่วันที่เก็บ — ดู TbMonitor)
 * $manual  = [checkKey => [pointKey => ['date' => AD|null, 'note' => str, 'id' => int]]]
 * $ctx     = ['female' => bool|null, 'hiv_pos' => bool|null, 'baseline_dst' => bool,
 *             'risk' => ['liver' => [เหตุผล...], 'kidney' => [...]], 'active' => bool]
 */
class TbMonitorEngine
{
    public static function build(array $def, ?string $startAd, string $todayAd, array $events, array $manual = [], array $ctx = []): array
    {
        $start = self::date($startAd);
        $today = self::date($todayAd) ?? new DateTimeImmutable('today');
        $treatMonths = (int) ($def['treat_months'] ?? 6);

        // ---------- 1) จุดเวลา ----------
        $points = [];
        foreach ($def['points'] as $p) {
            [$key, $label, $unit, $n] = $p;
            $flag = $p[4] ?? null;
            $exp = $start ? self::expected($start, $unit, (int) $n, $treatMonths) : null;
            [$lo, $hi] = TbMonitorDefinitions::WINDOWS[$unit] ?? [14, 14];
            $points[$key] = [
                'key' => $key, 'label' => $label, 'unit' => $unit, 'n' => (int) $n, 'flag' => $flag,
                'expected' => $exp ? $exp->format('Y-m-d') : null,
                'from' => $exp ? $exp->modify("-{$lo} days")->format('Y-m-d') : null,
                'to'   => $exp ? $exp->modify("+{$hi} days")->format('Y-m-d') : null,
                'lo' => $lo, 'hi' => $hi,
            ];
        }

        // จุดปัจจุบัน = จุดที่วันนี้อยู่ในช่วง (ใกล้ที่สุด) — ช่องขยายระยะไม่นับ (ระบบไม่รู้ว่าขยายจริงไหม)
        $current = null; $best = null;
        if ($start) {
            foreach ($points as $k => $p) {
                if ($p['flag'] === 'ext') { continue; }
                $t = $today->format('Y-m-d');
                if ($t < $p['from'] || $t > $p['to']) { continue; }
                $d = abs(self::days($today, self::date($p['expected'])));
                if ($best === null || $d < $best) { $best = $d; $current = $k; }
            }
        }

        // ---------- 2) ลงผลตรวจเข้าจุด ----------
        $matched = [];   // [check][point] => event
        foreach ($def['checks'] as $c) {
            $ck = $c['key'];
            // ทุกจุดรับผลได้ (ตรวจนอกแผน เช่น AFB ด.1 ก็โชว์ว่าตรวจแล้ว) ยกเว้นช่องขยายระยะ/ติ๊กมือ
            // ไม่งั้นผลเดือนที่ 5 จะถูกแย่งไปลงช่อง "ขยาย 1" ที่อยู่วันเดียวกัน
            $eligible = [];
            foreach ($points as $pk => $p) {
                $spec = $c['points'][$pk] ?? null;
                if ($p['flag'] === 'ext') { continue; }
                if (is_array($spec) && ($spec['cond']['if'] ?? null) === 'manual') { continue; }
                $eligible[] = $pk;
            }
            $matched[$ck] = [];
            if (! $start) {
                // ยังไม่มีวันเริ่มยา: ผลที่มีวันที่ก่อน/วันนี้ ถือเป็นผลแรกรับ (จุดแรก) อย่างเดียว
                $first = array_key_first($points);
                if ($first && in_array($first, $eligible, true)) {
                    foreach ($events[$ck] ?? [] as $ev) {
                        if (! empty($ev['match']) || ! empty($ev['date'])) { $matched[$ck][$first] = $ev; }
                    }
                }
                continue;
            }
            $dist = [];
            foreach ($events[$ck] ?? [] as $ev) {
                $md = self::date($ev['match'] ?? null) ?? self::date($ev['date'] ?? null);
                if (! $md) { continue; }
                $hit = null; $hitD = null;
                foreach ($eligible as $pk) {
                    $p = $points[$pk];
                    $diff = self::days(self::date($p['expected']), $md);   // + = หลังวันที่ควรตรวจ
                    if ($diff < -$p['lo'] || $diff > $p['hi']) { continue; }
                    if ($hitD === null || abs($diff) < $hitD) { $hitD = abs($diff); $hit = $pk; }
                }
                if ($hit === null) { continue; }
                // จุดเดียวกันมีหลายครั้ง: เอาครั้งที่มีผลก่อน แล้วค่อยครั้งที่ใกล้วันที่ควรตรวจที่สุด
                $prev = $matched[$ck][$hit] ?? null;
                $better = ! $prev
                    || (! empty($prev['pending']) && empty($ev['pending']))
                    || (empty($prev['pending']) === empty($ev['pending']) && $hitD < $dist[$hit]);
                if ($better) { $matched[$ck][$hit] = $ev; $dist[$hit] = $hitD; }
            }
        }

        // ---------- 3) + 4) สถานะแต่ละช่อง ----------
        $rows = [];
        $due = []; $overdue = [];
        $t = $today->format('Y-m-d');
        foreach ($def['checks'] as $c) {
            $ck = $c['key'];
            $cells = [];
            foreach ($points as $pk => $p) {
                $spec = $c['points'][$pk] ?? null;
                $ev   = $matched[$ck][$pk] ?? null;
                $man  = $manual[$ck][$pk] ?? null;

                $need = 'na'; $why = null;   // req | optional | cond | na
                if ($spec === 'req') {
                    $need = 'req';
                } elseif ($spec === 'consider') {
                    $need = 'optional'; $why = 'พิจารณาตรวจ';
                } elseif (is_string($spec) && str_starts_with($spec, 'advice:')) {
                    $risk = $ctx['risk'][substr($spec, 7)] ?? [];
                    if ($risk) { $need = 'req'; $why = 'มีปัจจัยเสี่ยง: ' . implode(', ', $risk); }
                    else { $need = 'optional'; $why = 'ตรวจถ้ามีปัจจัยเสี่ยง'; }
                } elseif (is_array($spec)) {
                    $r = self::cond($spec['cond'], $pk, $matched, $ctx);
                    $why = $spec['text'] ?? null;
                    $need = $r === true ? 'req' : ($r === false ? 'na' : 'cond');
                }

                $cell = ['need' => $need, 'why' => $why, 'expected' => $p['expected'], 'event' => null, 'manual' => null];
                if ($ev) {
                    $cell['status'] = 'done';
                    $cell['event'] = $ev;
                } elseif ($man) {
                    $cell['status'] = 'done';
                    $cell['manual'] = $man;
                } elseif ($need === 'req') {
                    if (! $start) { $cell['status'] = 'upcoming'; }
                    elseif ($t < $p['from']) { $cell['status'] = 'upcoming'; }
                    elseif ($t <= $p['to']) { $cell['status'] = 'due'; }
                    else { $cell['status'] = 'overdue'; }
                } else {
                    $cell['status'] = $need;   // optional | cond | na
                }
                // ช่องที่ไม่ได้อยู่ในแผนแต่มีผล (เช่นตรวจ AFB ด.1 หรือ ด.3 ทั้งที่ ด.2 ลบ) — แสดงว่าตรวจแล้ว ไม่ถือว่าผิด
                if ($cell['status'] === 'done' && $need === 'na') { $cell['need'] = 'extra'; }

                $cells[$pk] = $cell;

                if (($c['remind'] ?? true) && ($ctx['active'] ?? true)) {
                    $item = ['check' => $ck, 'label' => $c['label'], 'point' => $pk, 'pointLabel' => $p['label'],
                             'preset' => $c['preset'] ?? [], 'why' => $why, 'expected' => $p['expected']];
                    if ($cell['status'] === 'due') { $due[] = $item; }
                    if ($cell['status'] === 'overdue') { $overdue[] = $item; }
                }
            }
            $rows[] = ['key' => $ck, 'label' => $c['label'], 'sub' => $c['sub'] ?? null,
                       'manualOnly' => empty($c['sources']), 'cells' => $cells];
        }

        return [
            'schedule' => $def['key'],
            'title'    => $def['title'],
            'notes'    => $def['notes'] ?? [],
            'start'    => $start ? $start->format('Y-m-d') : null,
            'today'    => $t,
            'points'   => $points,
            'current'  => $current,
            'rows'     => $rows,
            'due'      => $due,
            'overdue'  => $overdue,
        ];
    }

    /** เงื่อนไขของช่อง — true / false / null (ยังไม่รู้ เช่นยังไม่มีผล AFB เดือนนั้น) */
    public static function cond(array $c, string $self, array $matched, array $ctx): ?bool
    {
        if (isset($c['all'])) {
            $unknown = false;
            foreach ($c['all'] as $sub) {
                $r = self::cond($sub, $self, $matched, $ctx);
                if ($r === false) { return false; }
                if ($r === null) { $unknown = true; }
            }
            return $unknown ? null : true;
        }
        switch ($c['if'] ?? '') {
            case 'pos':
                $at = ($c['at'] ?? 'self') === 'self' ? $self : $c['at'];
                $ev = $matched[$c['check']][$at] ?? null;
                if (! $ev || ! empty($ev['pending'])) { return null; }
                return $ev['pos'] ?? null;
            case 'no_baseline_dst':
                return empty($ctx['baseline_dst']);
            case 'female':
                return $ctx['female'] ?? null;
            case 'hiv_pos':
                return $ctx['hiv_pos'] ?? null;
            default:   // manual
                return null;
        }
    }

    /** วันที่ควรตรวจ — เดือนบวกแบบปฏิทิน ไม่ล้นเดือน (31 ม.ค. + 1 เดือน = 28/29 ก.พ.) */
    public static function expected(DateTimeImmutable $start, string $unit, int $n, int $treatMonths): DateTimeImmutable
    {
        switch ($unit) {
            case 'week':  return $start->modify('+' . (7 * $n) . ' days');
            case 'month': return self::addMonths($start, $n);
            case 'post':  return self::addMonths($start, $treatMonths + $n);
            default:      return $start;
        }
    }

    public static function addMonths(DateTimeImmutable $d, int $n): DateTimeImmutable
    {
        $y = (int) $d->format('Y'); $m = (int) $d->format('n') + $n; $day = (int) $d->format('j');
        $y += intdiv($m - 1, 12); $m = ($m - 1) % 12 + 1;
        $last = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');
        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $y, $m, min($day, $last)));
    }

    /** $b - $a เป็นวัน */
    public static function days(DateTimeImmutable $a, DateTimeImmutable $b): int
    {
        return (int) round(($b->getTimestamp() - $a->getTimestamp()) / 86400);
    }

    public static function date(?string $v): ?DateTimeImmutable
    {
        if (! $v || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) { return null; }
        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) { return null; }
        return new DateTimeImmutable("{$m[1]}-{$m[2]}-{$m[3]}");
    }

    /**
     * ผลย้อมเสมหะเป็นบวกไหม — ช่องเป็นข้อความอิสระ (N, Neg,Neg, 1+, scanty, 3+ ...)
     * หลายค่าในช่องเดียว (คั่นด้วย , /) ค่าใดบวก = บวก · ทุกค่าลบ = ลบ · อ่านไม่ออก = null
     */
    public static function smearPositive(array $values): ?bool
    {
        $seen = false; $neg = false;
        foreach ($values as $v) {
            if (\App\Models\TbScreening::afbNotSent($v)) { continue; }   // N/A = ไม่ได้ส่ง ไม่ใช่ N (ลบ)
            foreach (preg_split('/[,\/;]+/u', (string) $v) as $part) {
                $s = trim($part);
                if ($s === '') { continue; }
                $seen = true;
                if (preg_match('/^(n|neg|negative|nf|not\s*found|ไม่พบ|-|0)\b/iu', $s) || preg_match('/^(n|neg|-|0)$/i', $s)) { $neg = true; continue; }
                if (preg_match('/[1-9]\s*\+|scanty|pos|พบเชื้อ|^\+|^[1-9]/iu', $s)) { return true; }
            }
        }
        return $seen && $neg ? false : null;
    }

    /** Molecular: detected = บวก · not detected = ลบ */
    public static function molecularPositive(?string $v): ?bool
    {
        // ตัดส่วนผลดื้อยาออกก่อน — "MTB detected · RIF resistance: Not detected" คือพบเชื้อ ไม่ใช่ลบ (2569-10-28)
        $v = preg_replace('/\bRIF\s*resistance\s*:?\s*(not\s*)?detected\b/i', '', (string) $v);
        $v = preg_replace('/\b(INH|RIF|FLQ|AG\/CP|Level)\s*:\s*[A-Za-z ]+/i', '', $v);
        if (preg_match('/not\s*detected/i', $v)) { return false; }
        if (preg_match('/detected|positive/i', $v)) { return true; }
        return null;
    }

    /** Culture: Growth = บวก · NG = ลบ · Pending = รอผล */
    public static function culturePositive(?string $v): ?bool
    {
        $v = trim((string) $v);
        if ($v === '' || preg_match('/pending/i', $v)) { return null; }
        if (preg_match('/^ng$|no\s*growth|negative/i', $v)) { return false; }
        if (preg_match('/growth|positive|mtb/i', $v)) { return true; }
        return null;
    }
}
