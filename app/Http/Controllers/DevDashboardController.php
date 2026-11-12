<?php

namespace App\Http\Controllers;

use App\Console\Commands\SmokeTest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard งานพัฒนาระบบ (กลุ่ม IT ขอ 2569-10-19) — รายงานทุกงานที่พัฒนา + สถานะความปลอดภัย + ผลทดสอบ
 *
 * ดึงสดทุกครั้ง ไม่ต้องดูแลเอกสารแยก:
 *   - งานที่พัฒนา: resources/changelog.php (ไฟล์เดียวกับหน้า "มีอะไรใหม่")
 *   - migration: เทียบไฟล์ใน database/migrations กับตาราง migrations (ค้างรัน = ยังไม่ได้อัปเดตฐาน)
 *   - ช่องโหว่ไลบรารี: storage/app/security-audit.json (php artisan clinic:security-audit)
 *   - ผลทดสอบเปิดหน้า: storage/app/smoke-test.json (php artisan clinic:smoke-test)
 * ไม่มีข้อมูลคนไข้ในหน้านี้
 */
class DevDashboardController extends Controller
{
    /** กลุ่มงานจากชื่อ area ในบันทึกการเปลี่ยนแปลง */
    private static function group(string $area): string
    {
        $a = mb_strtolower($area);
        foreach ([
            'วัณโรค (TB)' => ['tb', 'วัณโรค', 'dot', 'b6', '3hp'],
            'TMC เวชศาสตร์การเดินทาง' => ['tmc', 'วัคซีน'],
            'STI' => ['sti', 'contactsti', 'r506', 'rihis'],
            'Lab' => ['lab', 'แล็บ', 'ผลตรวจ', 'hiv'],
            'ยา / ห้องยา' => ['ยา', 'pharmacy', 'คลังยา', 'lot'],
            'การเงิน' => ['การเงิน', 'บิล', 'ใบเสร็จ', 'ราคา'],
            'นัดหมาย / คิว' => ['นัด', 'คิว', 'queue', 'line'],
            'รายงาน' => ['รายงาน', 'report', 'dashboard', 'สถิติ'],
        ] as $g => $keys) {
            foreach ($keys as $k) { if (str_contains($a, $k)) { return $g; } }
        }
        return 'ส่วนกลาง / หน้าตรวจ';
    }

    public function index()
    {
        $items = [];
        foreach (ChangelogController::entries() as $b) {
            foreach ((array) ($b['items'] ?? []) as $i) {
                $items[] = [
                    'date' => $b['date'], 'month' => substr($b['date'], 0, 7),
                    'tag' => $i['tag'] ?? 'change', 'area' => $i['area'] ?? '', 'group' => self::group($i['area'] ?? ''),
                    'text' => $i['text'] ?? '', 'note' => $i['note'] ?? '', 'approx' => ! empty($b['approx']),
                ];
            }
        }
        $c = collect($items);
        $byMonth = $c->groupBy('month')->sortKeys()->map(fn ($g) => [
            'new' => $g->where('tag', 'new')->count(), 'fix' => $g->where('tag', 'fix')->count(),
            'change' => $g->where('tag', 'change')->count(), 'total' => $g->count(),
        ]);
        $byGroup = $c->groupBy('group')->map->count()->sortDesc();

        // migration: ไฟล์ที่ยังไม่ได้รันในฐานนี้
        $files = collect(glob(database_path('migrations/*.php')))->map(fn ($f) => basename($f, '.php'));
        $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : collect();
        $pending = $files->diff($ran)->values();

        $read = function ($f) {
            $p = storage_path('app/' . $f);
            return is_file($p) ? json_decode((string) file_get_contents($p), true) : null;
        };

        return view('dev_dashboard', [
            'items' => $items, 'byMonth' => $byMonth, 'byGroup' => $byGroup,
            'tags' => ['new' => $c->where('tag', 'new')->count(), 'fix' => $c->where('tag', 'fix')->count(), 'change' => $c->where('tag', 'change')->count()],
            'migrations' => ['total' => $files->count(), 'pending' => $pending],
            'audit' => $read('security-audit.json'), 'smoke' => $read('smoke-test.json'),
            'watch' => $read('health-watch.json'), 'alert' => \App\Support\HealthAlert::data(),
            'env' => app()->environment(), 'commit' => SmokeTest::commit(),
            'php' => PHP_VERSION, 'laravel' => app()->version(), 'db' => DB::getDatabaseName(),
        ]);
    }
}
