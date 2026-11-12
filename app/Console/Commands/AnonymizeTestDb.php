<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ปิดตัวตนคนไข้ในฐานทดสอบ (ระบบทดสอบ clinic-test — 2569-10-19)
 *
 * ฐานทดสอบเป็นสำเนาของจริง เพื่อให้บั๊กแบบเดียวกับของจริงยังโผล่ (วันที่ยุค ค.ศ., ข้อมูลรูปแบบแปลก ๆ)
 * แต่ห้ามมีตัวตนคนไข้: ชื่อ เลขบัตร โทร ที่อยู่ อีเมล LINE รูป ไฟล์สแกน ผล HIV
 * เก็บไว้: โครงสร้าง วันที่ รหัสโรค ยา ผลแล็บอื่น ๆ
 *
 * กันพลาด: รันได้เฉพาะเมื่อฐานที่ต่ออยู่ลงท้ายด้วย _test และ APP_ENV=staging — ไม่มีทางรันใส่ฐานจริง (clinic)
 */
class AnonymizeTestDb extends Command
{
    protected $signature = 'clinic:anonymize-test-db {--force : ไม่ถามยืนยัน}';
    protected $description = 'ปิดตัวตนคนไข้ในฐานทดสอบ (ใช้ได้เฉพาะฐาน *_test บน APP_ENV=staging)';

    public function handle(): int
    {
        $db = (string) config('database.connections.mysql.database');
        if (! str_ends_with($db, '_test') || app()->environment() !== 'staging') {
            $this->error("ไม่รัน: ฐาน '{$db}' / APP_ENV '" . app()->environment() . "' ไม่ใช่ระบบทดสอบ (ต้องเป็น *_test + staging)");
            return 1;
        }
        if (! $this->option('force') && ! $this->confirm("ปิดตัวตนคนไข้ในฐาน {$db} ?")) { return 1; }

        $hn = fn ($id) => 'T' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);

        // คนไข้: ชื่อ/สกุล/อังกฤษ เป็นรหัสตาม id · เลขบัตร/พาสปอร์ต/โทร/อีเมล/LINE/รูป ล้าง
        DB::table('patients')->orderBy('id')->chunkById(500, function ($rows) use ($hn) {
            foreach ($rows as $p) {
                DB::table('patients')->where('id', $p->id)->update([
                    'name' => 'ทดสอบ' . $p->id, 'surname' => 'คนไข้', 'name_en' => $p->name_en ? 'Test' . $p->id : null,
                    'surname_en' => $p->surname_en ? 'Patient' : null,
                    'id_card_number' => $p->id_card_number ? '0000000' . str_pad((string) $p->id, 6, '0', STR_PAD_LEFT) : null,
                    'passport' => $p->passport ? 'P' . str_pad((string) $p->id, 7, '0', STR_PAD_LEFT) : null,
                    'yellow_book_no' => null, 'tel' => $p->tel ? '0800000000' : null, 'email' => null,
                    'line_user_id' => null, 'line_link_code' => null, 'photo' => null, 'prefix_other' => null,
                    'organization' => $p->organization ? 'หน่วยงานทดสอบ' : null,
                ]);
            }
        });
        DB::table('addresses')->update(['house_no' => '1', 'address' => 'ที่อยู่ทดสอบ']);
        DB::table('contact_people')->update(['name_surname' => DB::raw("CONCAT('คู่สัมผัส', id)")]);
        DB::table('tb_household_members')->update(['name' => DB::raw("CONCAT('ผู้สัมผัส', id)")]);
        DB::table('tb_screenings')->update([
            'index_name' => DB::raw("IF(index_name IS NULL OR index_name = '', index_name, CONCAT('ผู้ป่วยต้นทาง', id))"),
            'index_id_card' => null, 'family_tree_photo' => null,
        ]);
        DB::table('visit_appointments')->update(['staff_note' => null]);

        // ทะเบียน RIHIS-VCT (กรอกเอง มีชื่อ เลขบัตร UIC วันเกิด ผลเลือด) — ตกหล่นรอบแรก ผู้ใช้แจ้ง 2569-10-20
        $has = fn ($t) => \Illuminate\Support\Facades\Schema::hasTable($t);
        if ($has('rihis_vct')) {
            $vals = DB::table('rihis_vct')->whereNotNull('blood_result')->distinct()->pluck('blood_result')->all();
            DB::table('rihis_vct')->orderBy('id')->chunkById(500, function ($rows) use ($vals) {
                foreach ($rows as $r) {
                    DB::table('rihis_vct')->where('id', $r->id)->update([
                        'name' => 'ทดสอบ' . $r->id, 'surname' => 'คนไข้', 'uic' => $r->uic ? 'UIC' . $r->id : null,
                        'id_card' => $r->id_card ? '0000000' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT) : null,
                        'birth_date' => null, 'referrer_detail' => null, 'note' => null,
                        'blood_result' => $r->blood_result !== null && $vals ? $vals[array_rand($vals)] : $r->blood_result,
                    ]);
                }
            });
        }
        // บันทึกการแก้ไข: ชื่อรายการ + ค่าก่อน/หลังแก้ (มีชื่อ ผลแล็บ) — เก็บแถวไว้ให้หน้าประวัติทดสอบได้ แต่ล้างเนื้อหา
        if ($has('activity_logs')) {
            DB::table('activity_logs')->update(['subject_label' => DB::raw("CONCAT('รายการ #', COALESCE(subject_id, id))"), 'changes' => null, 'ip' => null]);
        }
        // ข้อความ LINE ที่ส่งหาคนไข้ (มี LINE id + ข้อความนัด) และโน้ตคนไข้ (ข้อความอิสระ)
        if ($has('line_message_logs')) { DB::table('line_message_logs')->delete(); }
        if ($has('patient_notes')) { DB::table('patient_notes')->update(['body' => DB::raw("CONCAT('โน้ตทดสอบ #', id)")]); }
        if ($has('contact_sti')) { DB::table('contact_sti')->update(['note' => null]); }

        // ผล HIV (วิธี 6) สลับเป็นค่าสุ่มจากชุดเดิม — ห้ามเหลือผลจริงผูกกับคนไข้
        $hivVals = DB::table('lab_subs')->where('method', 6)->whereNotNull('result')->distinct()->pluck('result')->all();
        if ($hivVals) {
            DB::table('lab_subs')->where('method', 6)->whereNotNull('result')->orderBy('id')->chunkById(500, function ($rows) use ($hivVals) {
                foreach ($rows as $r) {
                    DB::table('lab_subs')->where('id', $r->id)->update(['result' => $hivVals[array_rand($hivVals)]]);
                }
            });
        }
        if (\Illuminate\Support\Facades\Schema::hasColumn('tb_registrations', 'hiv_result')) {
            DB::table('tb_registrations')->update(['hiv_result' => null]);
        }

        // ไฟล์สแกน/รูปไม่ได้คัดลอกมา — ล้างแถวที่ชี้ไฟล์ กันหน้าจอพยายามเปิดไฟล์ที่ไม่มี
        foreach (['tb_lab_files', 'tmc_travel_files', 'tb_dot_submissions'] as $t) {
            if (\Illuminate\Support\Facades\Schema::hasTable($t)) { DB::table($t)->delete(); }
        }

        $this->info("ปิดตัวตนเรียบร้อย: {$db}");
        $this->line('หมายเหตุ: ช่องข้อความอิสระ (อาการ บันทึกแพทย์ หมายเหตุ) อาจมีชื่อคนพิมพ์ไว้ — ไม่ได้ล้างทั้งหมด');
        return 0;
    }
}
