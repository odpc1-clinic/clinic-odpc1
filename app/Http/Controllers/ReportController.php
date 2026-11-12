<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ReportController extends Controller
{
    public function dashboard(Request $request)
{
    $month = $request->input('month') ?? date('n');
    $year = $request->input('year') ?? (date('Y') + 543);

    $reportData = $this->getReportData($month, $year);
    $activityRows = $this->getMonthlyActivityReport($month, $year); // ✅ เพิ่มบรรทัดนี้

    return view('report.dashboard', compact('reportData', 'month', 'year', 'activityRows')); // ✅ เพิ่ม 'activityRows'
}

   private function getReportData($month, $year)
{

    $yearAD = $year - 543;

    // สร้าง WHERE เงื่อนไขแบบยืดหยุ่น
    $where = "YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) = $yearAD";
    if ($month) {
        $where .= " AND MONTH(DATE_SUB(v.date, INTERVAL 543 YEAR)) = $month";
    }

    // เตรียม SQL Statement
    $sql = "
        SELECT
            COUNT(DISTINCT v.patient_id) AS total_patients,
            COUNT(DISTINCT v.id) AS total_visits,

            -- จำนวน visit แยกเพศ
COUNT(DISTINCT CASE WHEN p.sex = 1 THEN v.id END) AS visit_male,
COUNT(DISTINCT CASE WHEN p.sex = 2 THEN v.id END) AS visit_female,


            -- กลุ่มเสี่ยง
            SUM(p.status = 2 AND COALESCE(p.sex, 0) <> 1) AS fsw,
            SUM(p.sex = 1 AND (p.status = 2 OR (p.status = 1 AND p.occupation = 10))) AS msw,
            SUM(p.status = 1 AND p.occupation != 10) AS msm,
            SUM(p.status = 3) AS labor,
            SUM(p.status = 5) AS young,
            SUM(p.status = 7) AS drug_user,
            SUM(p.status NOT IN (1,2,3,5,7)) AS other,

            -- อายุรวม
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) <= 14) AS age_0_14,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 15 AND 24) AS age_15_24,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 25 AND 34) AS age_25_34,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 35 AND 44) AS age_35_44,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 45 AND 54) AS age_45_54,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 55 AND 64) AS age_55_64,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 65 AND 74) AS age_65_74,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) >= 75) AS age_75_up,

            -- อายุแยกเพศ
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) <= 14 AND p.sex = 1) AS age_0_14_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) <= 14 AND p.sex = 2) AS age_0_14_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 15 AND 24 AND p.sex = 1) AS age_15_24_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 15 AND 24 AND p.sex = 2) AS age_15_24_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 25 AND 34 AND p.sex = 1) AS age_25_34_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 25 AND 34 AND p.sex = 2) AS age_25_34_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 35 AND 44 AND p.sex = 1) AS age_35_44_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 35 AND 44 AND p.sex = 2) AS age_35_44_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 45 AND 54 AND p.sex = 1) AS age_45_54_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 45 AND 54 AND p.sex = 2) AS age_45_54_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 55 AND 64 AND p.sex = 1) AS age_55_64_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 55 AND 64 AND p.sex = 2) AS age_55_64_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 65 AND 74 AND p.sex = 1) AS age_65_74_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) BETWEEN 65 AND 74 AND p.sex = 2) AS age_65_74_female,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) >= 75 AND p.sex = 1) AS age_75_up_male,
            SUM(YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) - YEAR(DATE_SUB(p.birth_date, INTERVAL 543 YEAR)) >= 75 AND p.sex = 2) AS age_75_up_female,

            -- สัญชาติ
            COUNT(DISTINCT CASE WHEN p.nationality IN ('THA', 'Thai', 'ไทย') THEN v.patient_id END) AS thai,
            COUNT(DISTINCT CASE WHEN p.nationality IN ('MMR', 'พม่า', 'Myanmar') THEN v.patient_id END) AS burmese,
            COUNT(DISTINCT CASE WHEN p.nationality NOT IN ('THA', 'Thai', 'ไทย', 'MMR', 'พม่า', 'Myanmar') THEN v.patient_id END) AS other_nationality,

            -- โรคแยกชายหญิง
            " . $this->getDiseaseBySexCounts($month, $yearAD) . ",

            -- โรคหลัก (3 โรค): ผู้ป่วยใหม่/เกิดซ้ำ
            " . implode(",\n", $this->getDiseaseSubqueries($month, $yearAD)) . ",

            -- โรคอื่นรวมทั้งหมด
            " . $this->getOtherDiseaseCounts($month, $yearAD) . ",

            -- ดึงทุกรายการโรคตามเพศ (สำหรับ yearly dashboard ใช้ร่วมกันได้)
            " . $this->getDiseaseYearlyCounts() . "

        FROM visits v
        LEFT JOIN patients p ON p.id = v.patient_id
        LEFT JOIN diagnoses d ON d.visit_id = v.id
        WHERE $where
    ";

    return DB::select($sql)[0] ?? null;
}

private function getOtherDiseaseCounts($month, $yearAD)
{
    // กำหนด diseases ให้ตรงกับชื่อ field ใน Blade
    $diseases = [
        4  => 'lgv',                    // LGV
        5  => 'chancroid',              // Chancroid
        6  => 'bacterial_vaginosis',    // Bacterial vaginosis
        7  => 'vaginal_candidiasis',    // Vaginal candidiasis
        8  => 'trichomoniasis',         // Trichomoniasis
        9  => 'molluseum_contagiosum',  // Molluseum contagiosum
        10 => 'anogenital_warts',       // Anogenital warts
        11 => 'other',                  // Other
        12 => 'herpes_simplex'          // Herpes simplex
    ];

    $queries = [];
    foreach ($diseases as $id => $alias) {
        $queries[] = "(
            SELECT COUNT(DISTINCT v.patient_id)
            FROM diagnoses d
            JOIN visits v ON v.id = d.visit_id
            WHERE d.disease = {$id}
              AND MONTH(DATE_SUB(v.date, INTERVAL 543 YEAR)) = {$month}
              AND YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) = {$yearAD}
        ) AS {$alias}";
    }

    return implode(",\n", $queries);
}

private function getDiseaseSubqueries($month, $yearAD)
{
    return [
        "(SELECT COUNT(DISTINCT v1.patient_id) FROM diagnoses d1 JOIN visits v1 ON v1.id = d1.visit_id WHERE d1.disease = 1 AND d1.disease_state = 1 AND MONTH(DATE_SUB(v1.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v1.date, INTERVAL 543 YEAR)) = $yearAD) AS syphilis_new",
        "(SELECT COUNT(DISTINCT v1.patient_id) FROM diagnoses d1 JOIN visits v1 ON v1.id = d1.visit_id WHERE d1.disease = 1 AND d1.disease_state = 2 AND MONTH(DATE_SUB(v1.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v1.date, INTERVAL 543 YEAR)) = $yearAD) AS syphilis_repeat",
        "(SELECT COUNT(DISTINCT v2.patient_id) FROM diagnoses d2 JOIN visits v2 ON v2.id = d2.visit_id WHERE d2.disease = 2 AND d2.disease_state = 1 AND MONTH(DATE_SUB(v2.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v2.date, INTERVAL 543 YEAR)) = $yearAD) AS gonorrhea_new",
        "(SELECT COUNT(DISTINCT v2.patient_id) FROM diagnoses d2 JOIN visits v2 ON v2.id = d2.visit_id WHERE d2.disease = 2 AND d2.disease_state = 2 AND MONTH(DATE_SUB(v2.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v2.date, INTERVAL 543 YEAR)) = $yearAD) AS gonorrhea_repeat",
        "(SELECT COUNT(DISTINCT v3.patient_id) FROM diagnoses d3 JOIN visits v3 ON v3.id = d3.visit_id WHERE d3.disease = 3 AND d3.disease_state = 1 AND MONTH(DATE_SUB(v3.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v3.date, INTERVAL 543 YEAR)) = $yearAD) AS chlamydia_new",
        "(SELECT COUNT(DISTINCT v3.patient_id) FROM diagnoses d3 JOIN visits v3 ON v3.id = d3.visit_id WHERE d3.disease = 3 AND d3.disease_state = 2 AND MONTH(DATE_SUB(v3.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v3.date, INTERVAL 543 YEAR)) = $yearAD) AS chlamydia_repeat",
        "(SELECT COUNT(DISTINCT v10.patient_id) FROM diagnoses d10 JOIN visits v10 ON v10.id = d10.visit_id WHERE d10.disease = 10 AND d10.disease_state = 2 AND MONTH(DATE_SUB(v10.date, INTERVAL 543 YEAR)) = $month AND YEAR(DATE_SUB(v10.date, INTERVAL 543 YEAR)) = $yearAD) AS warts_repeat"
    ];
}

private function getDiseaseBySexCounts($month, $yearAD)
    {
        $diseases = [
            1 => 'syphilis',
            2 => 'gonorrhea',
            3 => 'chlamydia',
            4 => 'lgv',
            5 => 'chancroid',
            6 => 'bacterial_vaginosis',
            7 => 'vaginal_candidiasis',
            8 => 'trichomoniasis',
            9 => 'molluscum_contagiosum',
            10 => 'anogenital_warts',
            11 => 'other',
            12 => 'herpes_simplex',
        ];

        $counts = [];
        foreach ($diseases as $id => $name) {
            $counts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$id} AND p.sex = 1 THEN v.patient_id END) AS {$name}_male";
            $counts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$id} AND p.sex = 2 THEN v.patient_id END) AS {$name}_female";
        }

        return implode(",\n", $counts);
    }


  public function yearlyDashboard(Request $request)
    {
        // 1. รับค่าวันที่จากหน้าจอ (HTML Date Input จะส่งมาเป็น ค.ศ. เช่น 2025-03-15)
        $start_input = $request->input('start_date');
        $end_input = $request->input('end_date');

        // แปลงเป็น พ.ศ. (บวก 543) เพื่อให้ตรงกับข้อมูลในฐานข้อมูล
        if ($start_input && $end_input) {
            $start_date = \Carbon\Carbon::parse($start_input)->addYears(543)->format('Y-m-d');
            $end_date = \Carbon\Carbon::parse($end_input)->addYears(543)->format('Y-m-d');
        } else {
            // ค่าเริ่มต้น: ตั้งแต่วันที่ 1 ม.ค. ของปีนี้ ถึง วันปัจจุบัน
            $start_date = (date('Y') + 543) . '-01-01';
            $end_date = (date('Y') + 543) . '-' . date('m-d');
            $start_input = date('Y') . '-01-01';
            $end_input = date('Y-m-d');
        }

        // ---- ดึงข้อมูลกิจกรรมตามช่วงวันที่ ----
        $activityRows = $this->getActivityByDateRange($start_date, $end_date);
        
        $statusOption = [
            1 => 'MSM/MSW/TG', 2 => 'พนักงานบริการ', 3 => 'แรงงานข้ามชาติ',
            4 => 'ผู้ป่วยเรือนจำ', 5 => 'เยาวชน', 6 => 'ประชาชนทั่วไป', 7 => 'ผู้ใช้สารเสพติด',
            0 => 'ไม่ระบุข้อมูล/อื่นๆ' // <--- เพิ่มบรรทัดนี้เข้าไป
        ];
        $diseaseOption = [
            1 => 'Syphilis', 2 => 'Gonorrhea', 3 => 'Non-Gonococcal urethritis',
            4 => 'LGV', 5 => 'Chancroid', 6 => 'Bacterial vaginosis',
            7 => 'Vaginal candidiasis', 8 => 'Trichomoniasis', 9 => 'Molluseum contagiosum',
            10 => 'Anogenital warts', 11 => 'Other', 12 => 'Herpes simplex', 20 => 'Mpox',
        ];
        $diseaseMainIds = [1 => 'Syphilis', 2 => 'Gonorrhea', 3 => 'Non-Gonococcal urethritis'];

        // หน้านี้เดิมยิง query ~65 ครั้ง (นับทีละช่อง) — รวมช่องที่มาจากชุดข้อมูลเดียวกันเป็น query เดียว (2569-10-20)
        // ตัวเลขต้องเท่าเดิมทุกช่อง: เทียบก่อน/หลังแก้ 15 ชุด (3 คลินิก × 5 ช่วงวัน) ตรงกันหมด
        // ผู้ป่วยใหม่/ซ้ำ 3 โรคหลัก — เดิม 12 query (โรค × เพศ × ใหม่/ซ้ำ) → นับคนไม่ซ้ำแยกกลุ่มใน query เดียว
        $newRepeat = DB::table('diagnoses')
            ->join('visits', 'visits.id', '=', 'diagnoses.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->whereIn('diagnoses.disease', array_keys($diseaseMainIds))
            ->whereBetween('visits.date', [$start_date, $end_date])
            ->groupBy('diagnoses.disease', 'patients.sex', 'diagnoses.disease_state')
            ->select('diagnoses.disease', 'patients.sex', 'diagnoses.disease_state', DB::raw('COUNT(DISTINCT patients.id) as n'))
            ->get();
        $diseaseNewRepeatRows = [];
        foreach ($diseaseMainIds as $diseaseId => $diseaseName) {
            $n = fn ($sex, $state) => (int) optional($newRepeat->first(fn ($r) =>
                $r->disease == $diseaseId && $r->sex == $sex && $r->disease_state == $state))->n;
            $diseaseNewRepeatRows[$diseaseName] = [
                'new_male' => $n(1, 1), 'new_female' => $n(2, 1),
                'repeat_male' => $n(1, 2), 'repeat_female' => $n(2, 2),
            ];
        }

        // (1) Visits
        $visitBase = DB::table('visits')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereBetween('visits.date', [$start_date, $end_date]);

        $vc = (clone $visitBase)->selectRaw('COUNT(visits.id) as total, SUM(patients.sex = 1) as male, SUM(patients.sex = 2) as female')->first();
        $totalVisits = (int) $vc->total;
        $maleVisits = (int) $vc->male;
        $femaleVisits = (int) $vc->female;

        // (2) Patients
        $patientBase = DB::table('visits')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereBetween('visits.date', [$start_date, $end_date]);

        // สัญชาติอื่น ต้องรวมคนที่ไม่ได้ระบุสัญชาติ (NULL) ด้วย ไม่งั้นหายไปจากทั้งสามช่อง
        // (SQL: NOT IN เจอค่า NULL จะไม่เป็นจริง — เจอตอนยอดสัญชาติรวมแล้วขาดจากการ์ด 4 คน 2569-09-08)
        $th = ['THA', 'Thai', 'ไทย']; $mm = ['MMR', 'Myanmar', 'พม่า'];
        $pc = (clone $patientBase)->selectRaw(
            'COUNT(DISTINCT CASE WHEN patients.nationality IN (?,?,?) THEN patients.id END) as thai,
             COUNT(DISTINCT CASE WHEN patients.nationality IN (?,?,?) THEN patients.id END) as burmese,
             COUNT(DISTINCT CASE WHEN patients.nationality IS NULL OR patients.nationality NOT IN (?,?,?,?,?,?) THEN patients.id END) as other_nat,
             COUNT(DISTINCT patients.id) as total,
             COUNT(DISTINCT CASE WHEN patients.sex = 1 THEN patients.id END) as male,
             COUNT(DISTINCT CASE WHEN patients.sex = 2 THEN patients.id END) as female',
            [...$th, ...$mm, ...$th, ...$mm])->first();
        $thai = (int) $pc->thai;
        $burmese = (int) $pc->burmese;
        $other_nationality = (int) $pc->other_nat;
        $totalPatients = (int) $pc->total;
        $malePatients = (int) $pc->male;
        $femalePatients = (int) $pc->female;

        // (3) Risk group (รายคน)
        $patientsInYear = DB::table('visits')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereBetween('visits.date', [$start_date, $end_date])
            ->select('patients.id', 'patients.sex', 'patients.status')
            // หมายเหตุ: การใช้ distinct() ตรงนี้ร่วมกับ get() จะดึงมาทุก row 
            // เราจะไปกรอง unique('id') ตอนนับ count ด้านล่างอีกครั้งเพื่อความชัวร์
            ->get();

        $riskSummary = [];
        foreach ($statusOption as $k => $groupName) {
            // นับแยกชาย-หญิง ตามปกติ
            $male = $patientsInYear->where('sex', 1)->where('status', $k)->unique('id')->count();
            $female = $patientsInYear->where('sex', 2)->where('status', $k)->unique('id')->count();
            
            // นับยอดรวมของกลุ่ม (status) นี้ทั้งหมด โดยไม่ต้องสนใจเพศ
            $totalInStatus = $patientsInYear->where('status', $k)->unique('id')->count();

            $riskSummary[$groupName] = [
                'male' => $male,
                'female' => $female,
                'total' => $totalInStatus, // ใช้ยอดรวมจริงที่ดักจับเคสไม่ระบุเพศแล้ว
            ];
        }

        // (4) Risk group (visit)
        $visitsInYear = DB::table('visits')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->whereBetween('visits.date', [$start_date, $end_date])
            ->select('visits.id', 'patients.sex', 'patients.status')
            ->get();

        $riskVisitSummary = [];
        foreach ($statusOption as $k => $groupName) {
            // นับชาย (1), หญิง (2)
            $male = $visitsInYear->where('sex', 1)->where('status', $k)->count();
            $female = $visitsInYear->where('sex', 2)->where('status', $k)->count();
            
            // ถ้าระบุ status เป็น 0 แต่เพศก็ไม่ได้ระบุด้วย (ให้ปัดเป็นชายหรือเก็บแยกก็ได้ ในที่นี้ผมปัดเศษไปรวมในชายก่อนเพื่อให้ยอดรวมครบ)
            // หรือวิธีที่ดีที่สุดคือ นับรวมไปเลยแบบไม่ต้องสนเพศ
            $totalInStatus = $visitsInYear->where('status', $k)->count();
            
            $riskVisitSummary[$groupName] = [
                'male' => $male,
                'female' => $female,
                'total' => $totalInStatus // ใช้ยอดรวมจริงของ status นี้ (รวมคนไม่ระบุเพศด้วย)
            ];
        }

        // (5) โรคหลัก (รายคน) - แยกกลุ่มเป้าหมาย
        // เดิมยิง 12 query (ทีละโรค) → ดึงคนไม่ซ้ำต่อโรคครั้งเดียว แล้วแยกโรคใน PHP
        $dxPatients = DB::table('diagnoses')
            ->join('visits', 'visits.id', '=', 'diagnoses.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->whereIn('diagnoses.disease', array_keys($diseaseOption))
            ->whereBetween('visits.date', [$start_date, $end_date])
            ->whereIn('diagnoses.disease_state', [1, 2])
            ->select('diagnoses.disease', 'patients.id', 'patients.sex', 'patients.status', 'patients.occupation')
            ->distinct()
            ->get()
            ->groupBy('disease');
        $diseaseRows = [];
        foreach ($diseaseOption as $code => $diseaseName) {
            $patients = $dxPatients->get($code, collect());

            $row = [
                'total' => 0, 'male' => 0, 'female' => 0,
                'fsw' => 0, 'msw' => 0, 'msm' => 0, 'idu' => 0, 
                'migrant' => 0, 'youth' => 0, 'other' => 0
            ];

            foreach ($patients as $p) {
                $row['total']++;
                if ($p->sex == 1) $row['male']++; elseif ($p->sex == 2) $row['female']++;

                $row[\App\Enums\PatientRiskGroup::reportBucket($p->status, $p->sex, $p->occupation)]++;
            }
            $diseaseRows[$diseaseName] = $row;
        }

        // ---------- อายุ × เพศ ----------
        $latestVisitPerPatient = DB::table('visits')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->select(DB::raw('MAX(date) as last_date'), 'patient_id')
            ->whereBetween('date', [$start_date, $end_date])
            ->groupBy('patient_id');

        $ageRows = DB::table('patients as p')
            ->joinSub($latestVisitPerPatient, 'lv', 'lv.patient_id', '=', 'p.id')
            ->select('p.id', 'p.sex', DB::raw('TIMESTAMPDIFF(YEAR, p.birth_date, lv.last_date) as age'))
            ->get();

        $ageBucketsDef = [
            '0-14' => [0, 14], '15-24' => [15, 24], '25-34' => [25, 34],
            '35-44' => [35, 44], '45-54' => [45, 54], '55-64' => [55, 64],
            '65-74' => [65, 74], '75+' => [75, 200],
        ];

        $ageBySex = [];
        foreach ($ageBucketsDef as $label => [$min, $max]) {
            $male = $ageRows->where('sex', 1)->filter(fn($r) => $r->age >= $min && $r->age <= $max)->unique('id')->count();
            $female = $ageRows->where('sex', 2)->filter(fn($r) => $r->age >= $min && $r->age <= $max)->unique('id')->count();
            $ageBySex[$label] = ['male' => $male, 'female' => $female, 'total' => $male + $female];
        }

        // ---------- สัดส่วนที่อยู่ ----------
        $lastAddrPerPatient = DB::table('addresses')
            ->select(DB::raw('MAX(id) as last_addr_id'), 'patient_id')
            ->groupBy('patient_id');

        // ต้องกรองคลินิกให้ตรงกับการ์ดด้านบน ไม่งั้นตัวหารกับตัวตั้งมาจากคนละชุด ยอดจะไม่ตรงกัน (แก้ 2569-09-08)
        $addrBase = DB::table('addresses as a')
            ->joinSub($lastAddrPerPatient, 'la', 'la.last_addr_id', '=', 'a.id')
            ->join('visits as v', 'v.patient_id', '=', 'a.patient_id')
            ->where('v.clinic', \App\Models\Visit::activeClinic())
            ->whereBetween('v.date', [$start_date, $end_date])
            ->select('a.patient_id', 'a.province', 'a.district')
            ->distinct();

        $PROV_CHIANGMAI = 72; $DIST_MUEANG = 871;
        $inCM_all = (clone $addrBase)->where('a.province', $PROV_CHIANGMAI)->count('a.patient_id');
        $inCM_mueang = (clone $addrBase)->where('a.province', $PROV_CHIANGMAI)->where('a.district', $DIST_MUEANG)->count('a.patient_id');
        $inCM_other = max(0, $inCM_all - $inCM_mueang);
        // นับ "ต่างจังหวัด" จากที่อยู่จริง ไม่ใช่เอายอดรวมมาลบ — ของเดิมเอาคนที่ไม่มีข้อมูลที่อยู่เลยมากองรวมเป็นต่างจังหวัดหมด
        $outsideCM = (clone $addrBase)->whereNotNull('a.province')->where('a.province', '!=', $PROV_CHIANGMAI)->count('a.patient_id');
        $noAddress = max(0, $totalPatients - $inCM_all - $outsideCM);

        $locationShare = [
            'เมืองเชียงใหม่' => $inCM_mueang, 'ต่างอำเภอ (จ.เชียงใหม่)' => $inCM_other,
            'ต่างจังหวัด' => $outsideCM, 'ไม่ระบุที่อยู่' => $noAddress,
        ];
        $locPercent = [];
        foreach ($locationShare as $k => $v) {
            $locPercent[$k] = $totalPatients ? round($v * 100 / $totalPatients, 1) : 0;
        }

        // ==========================================
        // คำนวณยอดการส่งตรวจ Lab
        // ==========================================
        $labTestsMap = [6 => 'Anti-HIV', 7 => 'TPHA', 8 => 'RPR', 11 => 'HBs Ag', 12 => 'Anti-HCV'];
        $labTestRows = [];
        foreach ($labTestsMap as $id => $name) {
            $labTestRows[$name] = [
                'total' => 0, 'male' => 0, 'female' => 0, 'fsw' => 0, 'msw' => 0, 'msm' => 0, 
                'idu' => 0, 'migrant' => 0, 'youth' => 0, 'other' => 0
            ];
        }

        $labsData = DB::table('lab_subs')
            ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
            ->join('visits', 'visits.id', '=', 'labs.visit_id')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.clinic', \App\Models\Visit::activeClinic())
            ->whereBetween('visits.date', [$start_date, $end_date])
            ->select('lab_subs.method', 'patients.sex', 'patients.status', 'patients.occupation')
            ->get();

        foreach ($labsData as $lab) {
            if (empty($lab->method)) continue;
            $methodData = json_decode($lab->method, true);
            if (json_last_error() !== JSON_ERROR_NONE) { $methodData = @unserialize($lab->method); }

            foreach ($labTestsMap as $id => $name) {
                $isMatch = false;
                if (is_array($methodData)) {
                    if (array_key_exists($id, $methodData) || in_array($id, $methodData)) $isMatch = true;
                } else {
                    if (preg_match('/\b' . $id . '\b/', (string)$lab->method)) $isMatch = true;
                }

                if ($isMatch) {
                    $labTestRows[$name]['total']++;
                    if ($lab->sex == 1) $labTestRows[$name]['male']++; elseif ($lab->sex == 2) $labTestRows[$name]['female']++;

                    // $lab->status ตรงนี้คือ patients.status (กลุ่มเสี่ยง) ไม่ใช่สถานะใบ Lab
                    $labTestRows[$name][\App\Enums\PatientRiskGroup::reportBucket($lab->status, $lab->sex, $lab->occupation)]++;
                }
            }
        }

        return view('report.yearly_dashboard', compact(
            'start_input', 'end_input', // ส่งไปแสดงใน Input Blade
            'totalVisits', 'maleVisits', 'femaleVisits', 'totalPatients', 'malePatients', 'femalePatients',
            'riskSummary', 'riskVisitSummary', 'diseaseRows', 'activityRows', 'thai', 'burmese', 'other_nationality', 
            'diseaseNewRepeatRows', 'ageBySex', 'locationShare', 'locPercent', 'labTestRows'
        ));
    }

// ฟังก์ชันใหม่ที่ใช้ดึงข้อมูลกิจกรรมตามช่วงวันที่
    public function getActivityByDateRange($start_date, $end_date)
    {
        $activityMap = [
            1 => 'Syphilis', 2 => 'Gonorrhea', 3 => 'Non-Gonococcal urethritis', 4 => 'LGV', 5 => 'Chancroid',
            6 => 'Bacterial vaginosis', 7 => 'Vaginal candidiasis', 8 => 'Trichomoniasis', 9 => 'Molluseum contagiosum',
            10 => 'Anogenital warts', 11 => 'Other', 12 => 'Herpes simplex', 13 => 'Pre-test', 14 => 'Post-test',
            15 => 'No STI', 16 => 'GF', 17 => 'Egasp 1', 18 => 'Egasp 2',
            20 => 'Mpox (ยืนยัน)', 21 => 'Mpox R/O (สงสัย)', 22 => 'วัคซีน Mpox เข็ม 1', 23 => 'วัคซีน Mpox เข็ม 2',
        ];

        // เดิมยิง 18 query (ทีละกิจกรรม) → ดึงคนไม่ซ้ำต่อกิจกรรมครั้งเดียว แล้วแยกใน PHP (2569-10-20 ตัวเลขเท่าเดิม)
        $byActivity = DB::table('diagnoses as d')
            ->join('visits as v', 'v.id', '=', 'd.visit_id')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where('v.clinic', \App\Models\Visit::activeClinic())
            ->whereIn('d.disease', array_keys($activityMap))
            ->whereBetween('v.date', [$start_date, $end_date])
            ->select('d.disease', 'v.patient_id', 'p.sex', 'p.status', 'p.occupation')
            ->groupBy('d.disease', 'v.patient_id', 'p.sex', 'p.status', 'p.occupation')
            ->get()
            ->groupBy('disease');

        $rows = [];
        foreach ($activityMap as $diseaseId => $activityName) {
            $patients = $byActivity->get($diseaseId, collect());

            $row = [
                'activity' => $activityName, 'male' => 0, 'female' => 0, 'total' => 0, 'fsw' => 0,
                'msw' => 0, 'msm' => 0, 'labor' => 0, 'prisoner' => 0, 'young' => 0, 'other' => 0,
            ];

            foreach ($patients as $p) {
                if ($p->sex == 1) $row['male']++; elseif ($p->sex == 2) $row['female']++;

                // พนักงานบริการชาย นับเป็น MSW (คลินิกขอ 2569-11-02)
                if ($p->status == 2 && $p->sex != 1) { $row['fsw']++; } 
                elseif ($p->sex == 1 && ($p->status == 2 || ($p->status == 1 && $p->occupation == 10))) { $row['msw']++; } 
                elseif ($p->status == 1 && $p->sex == 1 && $p->occupation != 10) { $row['msm']++; } 
                elseif ($p->status == 3) { $row['labor']++; } 
                elseif ($p->status == 4) { $row['prisoner']++; } 
                elseif ($p->status == 5) { $row['young']++; } 
                elseif (in_array($p->status, [6, 7, 0])) { $row['other']++; }
            }
            $row['total'] = $row['male'] + $row['female'];
            $rows[] = $row;
        }
        return $rows;
    }

    // public function exportLabList(Request $request)
    // {
    //     $start_input = $request->input('start_date');
    //     $end_input = $request->input('end_date');

    //     if ($start_input && $end_input) {
    //         $start_date = \Carbon\Carbon::parse($start_input)->addYears(543)->format('Y-m-d');
    //         $end_date = \Carbon\Carbon::parse($end_input)->addYears(543)->format('Y-m-d');
    //     } else {
    //         $start_date = (date('Y') + 543) . '-01-01';
    //         $end_date = (date('Y') + 543) . '-' . date('m-d');
    //     }

    //     $labTestsMap = [6 => 'Anti-HIV', 7 => 'TPHA', 8 => 'RPR', 11 => 'HBs Ag', 12 => 'Anti-HCV'];

    //     $labsData = DB::table('lab_subs')
    //         ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
    //         ->join('visits', 'visits.id', '=', 'labs.visit_id')
    //         ->join('patients', 'patients.id', '=', 'visits.patient_id')
    //         ->whereBetween('visits.date', [$start_date, $end_date])
    //         ->select(
    //             'patients.code as hn', 'patients.name', 'patients.surname', 'patients.sex', 
    //             'patients.status', 'patients.occupation', 'visits.date as visit_date', 'lab_subs.method'
    //         )
    //         ->get();

    //     $exportData = [];
    //     foreach ($labsData as $lab) {
    //         if (empty($lab->method)) continue;
    //         $methodData = json_decode($lab->method, true);
    //         if (json_last_error() !== JSON_ERROR_NONE) { $methodData = @unserialize($lab->method); }

    //         $patientLabs = [];
    //         foreach ($labTestsMap as $id => $name) {
    //             $isMatch = false;
    //             if (is_array($methodData)) {
    //                 if (array_key_exists($id, $methodData) || in_array($id, $methodData)) $isMatch = true;
    //             } else {
    //                 if (preg_match('/\b' . $id . '\b/', (string)$lab->method)) $isMatch = true;
    //             }
    //             if ($isMatch) { $patientLabs[] = $name; }
    //         }

    //         if (count($patientLabs) > 0) {
    //             $riskGroup = 'อื่นๆ';
    //             if ($lab->status == 2) { $riskGroup = 'พนักงานบริการหญิง (FSW)'; } 
    //             elseif ($lab->status == 1 && $lab->sex == 1 && $lab->occupation == 10) { $riskGroup = 'พนักงานบริการชาย (MSW)'; } 
    //             elseif ($lab->status == 1 && $lab->sex == 1 && $lab->occupation != 10) { $riskGroup = 'ชายรักชาย (MSM)'; } 
    //             elseif ($lab->status == 7) { $riskGroup = 'ผู้ใช้ยาเสพติดฉีด (IDU)'; } 
    //             elseif ($lab->status == 3) { $riskGroup = 'แรงงานข้ามชาติ'; } 
    //             elseif ($lab->status == 5) { $riskGroup = 'เยาวชน'; }

    //             $exportData[] = [
    //                 'visit_date' => \Carbon\Carbon::parse($lab->visit_date)->format('d/m/Y'),
    //                 'hn' => $lab->hn, 'name' => $lab->name . ' ' . $lab->surname,
    //                 'sex' => \App\Models\Patient::$sexOption[$lab->sex] ?? 'ไม่ระบุ',
    //                 'status' => \App\Models\Patient::$statusOption[$lab->status] ?? 'ไม่ระบุ',
    //                 'occupation' => $lab->occupation ?? '-',
    //                 'labs_taken' => implode(', ', $patientLabs), 'calculated_risk' => $riskGroup
    //             ];
    //         }
    //     }

    //     $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    //     $sheet = $spreadsheet->getActiveSheet();
    //     $headers = ['ลำดับ', 'วันที่ตรวจ', 'HN', 'ชื่อ-นามสกุล', 'เพศ (ระบบ)', 'ประเภท (ระบบ)', 'อาชีพ (ระบบ)', 'รายการตรวจ Lab', 'กลุ่มเสี่ยงที่ระบบคำนวณได้'];
    //     $sheet->fromArray($headers, NULL, 'A1');
    //     $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    //     $sheet->getStyle('A1:I1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    //     $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //     $row = 2;
    //     foreach ($exportData as $index => $patient) {
    //         $sheet->setCellValue('A' . $row, $index + 1);
    //         $sheet->setCellValue('B' . $row, $patient['visit_date']);
    //         $sheet->setCellValue('C' . $row, $patient['hn']);
    //         $sheet->setCellValue('D' . $row, $patient['name']);
    //         $sheet->setCellValue('E' . $row, $patient['sex']);
    //         $sheet->setCellValue('F' . $row, $patient['status']);
    //         $sheet->setCellValue('G' . $row, $patient['occupation']);
    //         $sheet->setCellValue('H' . $row, $patient['labs_taken']);
    //         $sheet->setCellValue('I' . $row, $patient['calculated_risk']);
    //         $row++;
    //     }

    //     foreach(range('A','I') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

    //     $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    //     $fileName = "รายชื่อตรวจ_Lab_STI_" . date('Ymd_His') . ".xlsx";
    //     header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    //     header('Content-Disposition: attachment; filename="'. urlencode($fileName) .'"');
    //     header('Cache-Control: max-age=0');
    //     $writer->save('php://output');
    //     exit;
    // }
private function getYearlyReportData($year)
{
    $yearAD = $year;

    $sql = "
        SELECT
            COUNT(DISTINCT v.patient_id) AS total_patients,
            COUNT(v.id) AS total_visits,

            COUNT(DISTINCT CASE WHEN p.sex = 1 THEN p.id END) AS male_patients,
            COUNT(DISTINCT CASE WHEN p.sex = 2 THEN p.id END) AS female_patients,

            SUM(CASE WHEN p.sex = 1 THEN 1 ELSE 0 END) AS visit_male,
            SUM(CASE WHEN p.sex = 2 THEN 1 ELSE 0 END) AS visit_female,

            SUM(CASE WHEN p.sex = 1 THEN 1 ELSE 0 END) AS male,
            SUM(CASE WHEN p.sex = 2 THEN 1 ELSE 0 END) AS female,


            COUNT(DISTINCT CASE WHEN p.nationality IN ('THA', 'Thai', 'ไทย') THEN v.patient_id END) AS thai,
            COUNT(DISTINCT CASE WHEN p.nationality IN ('MMR', 'พม่า', 'Myanmar') THEN v.patient_id END) AS burmese,
            COUNT(DISTINCT CASE WHEN p.nationality NOT IN ('THA', 'Thai', 'ไทย', 'MMR', 'พม่า', 'Myanmar') THEN v.patient_id END) AS other_nationality,

            -- อายุแยกชายหญิง
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) <= 14 AND p.sex = 1) AS age_0_14_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) <= 14 AND p.sex = 2) AS age_0_14_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 15 AND 24 AND p.sex = 1) AS age_15_24_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 15 AND 24 AND p.sex = 2) AS age_15_24_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 25 AND 34 AND p.sex = 1) AS age_25_34_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 25 AND 34 AND p.sex = 2) AS age_25_34_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 35 AND 44 AND p.sex = 1) AS age_35_44_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 35 AND 44 AND p.sex = 2) AS age_35_44_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 45 AND 54 AND p.sex = 1) AS age_45_54_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 45 AND 54 AND p.sex = 2) AS age_45_54_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 55 AND 64 AND p.sex = 1) AS age_55_64_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 55 AND 64 AND p.sex = 2) AS age_55_64_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 65 AND 74 AND p.sex = 1) AS age_65_74_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) BETWEEN 65 AND 74 AND p.sex = 2) AS age_65_74_female,

            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) >= 75 AND p.sex = 1) AS age_75_up_male,
            SUM(TIMESTAMPDIFF(YEAR, DATE_SUB(p.birth_date, INTERVAL 543 YEAR), DATE_SUB(v.date, INTERVAL 543 YEAR)) >= 75 AND p.sex = 2) AS age_75_up_female,

            " . $this->getDiseaseYearlyCounts() . "
        FROM visits v
        LEFT JOIN patients p ON p.id = v.patient_id
        LEFT JOIN diagnoses d ON d.visit_id = v.id
        WHERE YEAR(DATE_SUB(v.date, INTERVAL 543 YEAR)) = ?
    ";

    $data = DB::select($sql, [$yearAD]);
    return $data[0] ?? null;
}

private function getDiseaseYearlyCounts()
{
    $diseases = [
        1 => 'syphilis',
        2 => 'gonorrhea',
        3 => 'chlamydia',
        4 => 'lgv',
        5 => 'chancroid',
        6 => 'bacterial_vaginosis',
        7 => 'vaginal_candidiasis',
        8 => 'trichomoniasis',
        9 => 'molluscum_contagiosum',
        10 => 'anogenital_warts',
        11 => 'other',
        12 => 'herpes_simplex'
    ];

    $sqlParts = [];

    foreach ($diseases as $code => $name) {
        $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND p.sex = 1 THEN v.patient_id END) AS {$name}_male";
        $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND p.sex = 2 THEN v.patient_id END) AS {$name}_female";

        if (in_array($code, [1, 2, 3])) {
            $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND d.disease_state = 1 AND p.sex = 1 THEN v.patient_id END) AS {$name}_new_male";
            $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND d.disease_state = 1 AND p.sex = 2 THEN v.patient_id END) AS {$name}_new_female";
            $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND d.disease_state = 2 AND p.sex = 1 THEN v.patient_id END) AS {$name}_repeat_male";
            $sqlParts[] = "COUNT(DISTINCT CASE WHEN d.disease = {$code} AND d.disease_state = 2 AND p.sex = 2 THEN v.patient_id END) AS {$name}_repeat_female";
        }
    }

    return implode(",\n", $sqlParts);
}

    public function getMonthlyActivityReport($month, $year)
{
    $yearAD = $year - 543;

    // กลุ่มกิจกรรม (mapped จาก disease)
    $activityMap = [
        1 => 'Syphilis', 2 => 'Gonorrhea', 3 => 'Non-Gonococcal urethritis', 4 => 'LGV', 5 => 'Chancroid',
        6 => 'Bacterial vaginosis', 7 => 'Vaginal candidiasis', 8 => 'Trichomoniasis', 9 => 'Molluseum contagiosum',
        10 => 'Anogenital warts', 11 => 'Other', 12 => 'Herpes simplex', 13 => 'Pre-test', 14 => 'Post-test',
        15 => 'No STI', 16 => 'GF', 17 => 'Egasp 1', 18 => 'Egasp 2',
        20 => 'Mpox (ยืนยัน)', 21 => 'Mpox R/O (สงสัย)', 22 => 'วัคซีน Mpox เข็ม 1', 23 => 'วัคซีน Mpox เข็ม 2',
    ];

    $statusGroups = [
        'fsw' => [2], // พนักงานบริการที่ไม่ใช่ชาย
        'msw' => [1, 2], // MSW = พนักงานบริการชาย + อาชีพพิเศษ (กรองด้านล่าง)
        'msm' => [1], // MSM อย่างเดียว 
        'labor' => [3],
        'prisoner' => [4],
        'young' => [5],
        'other' => [6, 7, 0],
    ];

    $rows = [];

    foreach ($activityMap as $diseaseId => $activityName) {
        $row = [
            'activity' => $activityName,
            'male' => 0, 'female' => 0, 'total' => 0, 'fsw' => 0, 'msw' => 0,
            'msm' => 0, 'labor' => 0, 'prisoner' => 0, 'young' => 0, 'other' => 0,
        ];

        $query = DB::table('visits as v')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->join('diagnoses as d', 'd.visit_id', '=', 'v.id')
            ->whereYear(DB::raw("DATE_SUB(v.date, INTERVAL 543 YEAR)"), $yearAD)
            ->whereMonth(DB::raw("DATE_SUB(v.date, INTERVAL 543 YEAR)"), $month)
            ->where('d.disease', $diseaseId);

        $cloned = clone $query;

        $row['male'] = (clone $query)->where('p.sex', 1)->distinct('v.patient_id')->count('v.patient_id');
        $row['female'] = (clone $query)->where('p.sex', 2)->distinct('v.patient_id')->count('v.patient_id');
        $row['total'] = $row['male'] + $row['female'];

        foreach ($statusGroups as $group => $statusCodes) {
            $groupQuery = (clone $cloned)->whereIn('p.status', $statusCodes);
            // MSW = พนักงานบริการเพศชาย + กลุ่ม MSM/MSW/TG ที่อาชีพพิเศษ · FSW = พนักงานบริการที่ไม่ใช่ชาย (คลินิกขอ 2569-11-02)
            // เดิมผู้ชายในกลุ่มพนักงานบริการไปตกที่ FSW
            if ($group === 'fsw') {
                $groupQuery = $groupQuery->where(fn ($q) => $q->where('p.sex', '<>', 1)->orWhereNull('p.sex'));
            } elseif ($group === 'msw') {
                $groupQuery = (clone $cloned)->where('p.sex', 1)->where(fn ($q) => $q->where('p.status', 2)
                    ->orWhere(fn ($q2) => $q2->where('p.status', 1)->where('p.occupation', 10)));
            } elseif ($group === 'msm') {
                $groupQuery = $groupQuery->where('p.sex', 1)->where('p.occupation', '!=', 10);
            }
            $row[$group] = $groupQuery->distinct('v.patient_id')->count('v.patient_id');
        }

        $rows[] = $row;
    }

    return $rows;
}
public function getYearlyActivityReport($year)
{
    $yearAD = $year - 543;
    $activityRows = $this->getMonthlyActivityReport(1, $year);
    $monthlyData = [];
    for ($m = 1; $m <= 12; $m++) {
        $monthlyData[] = $this->getMonthlyActivityReport($m, $year);
    }
    return view('report.yearly_dashboard', compact('activityRows', 'monthlyData'));
}
    public function getActivityByRiskGroupForYear($year)
{
    $yearAD = $year - 543;

    $activityMap = [
        1 => 'Syphilis', 2 => 'Gonorrhea', 3 => 'Non-Gonococcal urethritis', 4 => 'LGV', 5 => 'Chancroid',
        6 => 'Bacterial vaginosis', 7 => 'Vaginal candidiasis', 8 => 'Trichomoniasis', 9 => 'Molluseum contagiosum',
        10 => 'Anogenital warts', 11 => 'Other', 12 => 'Herpes simplex', 13 => 'Pre-test', 14 => 'Post-test',
        15 => 'No STI', 16 => 'GF', 17 => 'Egasp 1', 18 => 'Egasp 2',
        20 => 'Mpox (ยืนยัน)', 21 => 'Mpox R/O (สงสัย)', 22 => 'วัคซีน Mpox เข็ม 1', 23 => 'วัคซีน Mpox เข็ม 2',
    ];

    $rows = [];

    foreach ($activityMap as $diseaseId => $activityName) {
        $patients = DB::table('diagnoses as d')
            ->join('visits as v', 'v.id', '=', 'd.visit_id')
            ->join('patients as p', 'p.id', '=', 'v.patient_id')
            ->where('d.disease', $diseaseId)
            ->whereIn('d.disease_state', [1, 2])
            ->whereYear(DB::raw("DATE_SUB(v.date, INTERVAL 543 YEAR)"), $yearAD)
            ->select('v.patient_id', 'p.sex', 'p.status', 'p.occupation')
            ->groupBy('v.patient_id', 'p.sex', 'p.status', 'p.occupation')
            ->get();

        $row = [
            'activity' => $activityName, 'male' => 0, 'female' => 0, 'total' => 0, 'fsw' => 0,
            'msw' => 0, 'msm' => 0, 'labor' => 0, 'prisoner' => 0, 'young' => 0, 'other' => 0,
        ];

        foreach ($patients as $p) {
            if ($p->sex == 1) $row['male']++;
            elseif ($p->sex == 2) $row['female']++;

            // พนักงานบริการชาย นับเป็น MSW (คลินิกขอ 2569-11-02)
            if ($p->status == 2 && $p->sex != 1) { $row['fsw']++; } 
            elseif ($p->sex == 1 && ($p->status == 2 || ($p->status == 1 && $p->occupation == 10))) { $row['msw']++; } 
            elseif ($p->status == 1 && $p->sex == 1 && $p->occupation != 10) { $row['msm']++; } 
            elseif ($p->status == 3) { $row['labor']++; } 
            elseif ($p->status == 4) { $row['prisoner']++; } 
            elseif ($p->status == 5) { $row['young']++; } 
            elseif (in_array($p->status, [6, 7, 0])) { $row['other']++; }
        }

        $row['total'] = $row['male'] + $row['female'];
        $rows[] = $row;
    }

    return $rows;
}

public function exportLabList(Request $request)
{
    $year = $request->get('year', date('Y') + 543);
    $month = $request->get('month');

    // กำหนด Lab ทั้ง 5 ตัว
    $labTestsMap = [
        6  => 'Anti-HIV',
        7  => 'TPHA',
        8  => 'RPR',
        11 => 'HBs Ag',
        12 => 'Anti-HCV'
    ];

    // 1. ดึงข้อมูล
    $labsData = DB::table('lab_subs')
        ->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
        ->join('visits', 'visits.id', '=', 'labs.visit_id')
        ->join('patients', 'patients.id', '=', 'visits.patient_id')
        ->whereYear('visits.date', $year) 
        ->when($month, function ($query, $month) {
            return $query->whereMonth('visits.date', $month);
        })
        ->select(
            'patients.code as hn', 
            'patients.name', 
            'patients.surname', 
            'patients.sex', 
            'patients.status', 
            'patients.occupation',
            'visits.date as visit_date',
            'lab_subs.method'
        )
        ->get();

    $exportData = [];

    // 2. คัดกรองและประมวลผล
    foreach ($labsData as $lab) {
        if (empty($lab->method)) continue;

        $methodData = json_decode($lab->method, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $methodData = @unserialize($lab->method);
        }

        $patientLabs = []; // เก็บว่าคนนี้ตรวจอะไรบ้าง
        
        foreach ($labTestsMap as $id => $name) {
            $isMatch = false;
            if (is_array($methodData)) {
                if (array_key_exists($id, $methodData) || in_array($id, $methodData)) $isMatch = true;
            } else {
                if (preg_match('/\b' . $id . '\b/', (string)$lab->method)) $isMatch = true;
            }
            
            if ($isMatch) {
                $patientLabs[] = $name;
            }
        }

        // ถ้าระบุว่ามีการตรวจอย่างน้อย 1 ใน 5 Lab นี้ ถึงจะเอาเข้า Excel
        if (count($patientLabs) > 0) {
            // คำนวณกลุ่มเสี่ยงให้ดู
            // $lab->status ตรงนี้คือ patients.status (กลุ่มเสี่ยง) ไม่ใช่สถานะใบ Lab
            $riskGroup = \App\Enums\PatientRiskGroup::BUCKET_LABELS[\App\Enums\PatientRiskGroup::reportBucket($lab->status, $lab->sex, $lab->occupation)];

            $exportData[] = [
                'visit_date' => \Carbon\Carbon::parse($lab->visit_date)->format('d/m/Y'),
                'hn' => $lab->hn,
                'name' => $lab->name . ' ' . $lab->surname,
                'sex' => \App\Models\Patient::$sexOption[$lab->sex] ?? 'ไม่ระบุ',
                'status' => \App\Models\Patient::$statusOption[$lab->status] ?? 'ไม่ระบุ',
                'occupation' => $lab->occupation ?? '-',
                'labs_taken' => implode(', ', $patientLabs), // รวมชื่อ Lab ไว้ในคอลัมน์เดียว
                'calculated_risk' => $riskGroup
            ];
        }
    }

    // 3. สร้างไฟล์ Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // หัวตาราง
    $headers = ['ลำดับ', 'วันที่ตรวจ', 'HN', 'ชื่อ-นามสกุล', 'เพศ (ระบบ)', 'ประเภท (ระบบ)', 'อาชีพ (ระบบ)', 'รายการตรวจ Lab', 'กลุ่มเสี่ยงที่ระบบคำนวณได้'];
    $sheet->fromArray($headers, NULL, 'A1');
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFEEEEEE');
    $sheet->getStyle('A1:I1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $row = 2;
    foreach ($exportData as $index => $patient) {
        $sheet->setCellValue('A' . $row, $index + 1);
        $sheet->setCellValue('B' . $row, $patient['visit_date']);
        $sheet->setCellValue('C' . $row, $patient['hn']);
        $sheet->setCellValue('D' . $row, $patient['name']);
        $sheet->setCellValue('E' . $row, $patient['sex']);
        $sheet->setCellValue('F' . $row, $patient['status']);
        $sheet->setCellValue('G' . $row, $patient['occupation']);
        $sheet->setCellValue('H' . $row, $patient['labs_taken']);
        $sheet->setCellValue('I' . $row, $patient['calculated_risk']);
        $row++;
    }

    // ปรับความกว้างคอลัมน์อัตโนมัติ
    foreach(range('A','I') as $col) { 
        $sheet->getColumnDimension($col)->setAutoSize(true); 
    }

    $writer = new Xlsx($spreadsheet);
    $fileName = "รายชื่อตรวจ_Lab_STI_" . date('Ymd_His') . ".xlsx";

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'. urlencode($fileName) .'"');
    header('Cache-Control: max-age=0');
    $writer->save('php://output');
    exit;
}

    /**
     * สัญชาติที่บันทึก (พิมพ์อิสระ ปนไทย/อังกฤษ/รหัส ISO) → กลุ่มรายงาน ไทย / เมียนมา / อื่นๆ / ไม่ระบุ
     * ช่องนี้ไม่มีตัวเลือกบังคับมาแต่แรก ค่าจริงมี "ไทย"·"THA"·"MMR"·"พม่า"·"เมียนมา" ปนกัน และบางแถวเป็นวันที่/ที่อยู่ที่กรอกผิดช่อง
     * ชาติพันธุ์ (ไทใหญ่ ไทลื้อ อาข่า ชาวเขา) ไม่ใช่สัญชาติ เดาไม่ได้ว่าไทยหรือเมียนมา → อื่นๆ
     * Excel มีชีตแสดงว่าค่าไหนถูกจัดกลุ่มไหน ให้หมอตรวจเองได้
     */
    public static function nationalityGroup(?string $raw): string
    {
        $v = trim((string) $raw);
        $k = mb_strtolower(preg_replace('/[\s\x{0E4C}]+/u', '', $v));   // ตัดช่องว่าง + การันต์ที่พิมพ์ติดมา (์N/A)
        if ($k === '' || in_array($k, ['-', 'n/a', 'na', 'none', '0', '*-', 'ไม่มี', 'ไม่ระบุ'], true)
            || preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}$/', $k) || mb_strlen($v) > 40) {
            return 'ไม่ระบุ';
        }
        if (in_array($k, ['ไทย', 'tha', 'th', 'thai', 'ไทยๆ', 'ๆทย', 'ไทย/อเมริกา'], true)) { return 'ไทย'; }
        if (in_array($k, ['mmr', 'mma', 'พม่า', 'เมียนมา', 'เมียนมาร์', 'แมียนมา', 'myanmar', 'burmese'], true)) { return 'เมียนมา'; }
        return 'อื่นๆ';
    }

    /**
     * Excel รายชื่อผู้รับบริการ + วินิจฉัย + สัญชาติ พร้อมตารางสรุปวินิจฉัย × สัญชาติ (หมอขอ 2569-10-30)
     * ช่วงวัน/คลินิกเดียวกับแดชบอร์ด (Visit::activeClinic) ตัวเลขจะได้เทียบกับหน้าจอได้
     * มีชื่อ-HN → ต้อง login (ลิงก์แชร์รายงานโหลดไม่ได้)
     */
    public function exportVisitDiagnosis(Request $request)
    {
        $startIn = $request->input('start_date') ?: date('Y') . '-01-01';
        $endIn   = $request->input('end_date') ?: date('Y-m-d');
        $start = \Carbon\Carbon::parse($startIn)->addYears(543)->format('Y-m-d');
        $end   = \Carbon\Carbon::parse($endIn)->addYears(543)->format('Y-m-d');
        $clinic = \App\Models\Visit::activeClinic();
        $clinicName = [1 => 'STI', 2 => 'TB', 3 => 'TMC'][$clinic] ?? (string) $clinic;

        $visits = DB::table('visits')
            ->join('patients', 'patients.id', '=', 'visits.patient_id')
            ->where('visits.clinic', $clinic)
            ->whereBetween('visits.date', [$start, $end])
            ->orderBy('visits.date')->orderBy('visits.id')
            ->get(['visits.id', 'visits.date', 'patients.id as pid', 'patients.code', 'patients.name', 'patients.surname',
                   'patients.sex', 'patients.birth_date', 'patients.nationality', 'patients.status', 'patients.occupation']);

        $ids = $visits->pluck('id')->all();
        $dx = collect(); $labs = collect();
        foreach (array_chunk($ids, 1000) as $chunk) {
            $dx = $dx->merge(DB::table('diagnoses')->whereIn('visit_id', $chunk)->orderBy('id')->get(['visit_id', 'disease', 'other_disease']));
            $labs = $labs->merge(DB::table('lab_subs')->join('labs', 'labs.id', '=', 'lab_subs.lab_id')
                ->whereIn('labs.visit_id', $chunk)->get(['labs.visit_id', 'lab_subs.method']));
        }
        $dxBy = $dx->groupBy('visit_id');
        $labBy = $labs->groupBy('visit_id');

        $dOpt = \App\Models\Diagnosi::$diseaseOption;
        $mOpt = \App\Models\LabSub::$methodOption;
        $groups = ['ไทย', 'เมียนมา', 'อื่นๆ', 'ไม่ระบุ'];
        $NONE = 'ไม่มีการวินิจฉัย';

        // สรุป: [โรค][กลุ่มสัญชาติ][เพศ] = ชุด patient id (นับคนไม่ซ้ำ) / จำนวนครั้ง (visit)
        // เพศ: ช / ญ · ไม่ได้บันทึก = '?' (นับเฉพาะช่องรวมทั้งหมด มีหมายเหตุบอก)
        $people = []; $times = []; $other = []; $natRaw = [];
        $NAT = '__all';   // แถว "ผู้รับบริการทั้งหมด" ใช้โครงเดียวกับแถวโรค
        $rows = [];
        foreach ($visits as $v) {
            $g = self::nationalityGroup($v->nationality);
            $sx = (int) $v->sex === 1 ? 'ช' : ((int) $v->sex === 2 ? 'ญ' : '?');
            $raw = trim((string) $v->nationality);
            $natRaw[$raw === '' ? '(ว่าง)' : $raw][$v->pid] = $g;
            $people[$NAT][$g][$sx][$v->pid] = true;
            $times[$NAT][$g][$sx] = ($times[$NAT][$g][$sx] ?? 0) + 1;

            $labels = [];
            $keys = [];
            foreach ($dxBy->get($v->id, collect()) as $d) {
                $name = $dOpt[$d->disease] ?? ('รหัส ' . $d->disease);
                $txt = trim((string) $d->other_disease);
                $labels[] = $txt !== '' && (int) $d->disease === 11 ? $name . ': ' . $txt : $name;
                $keys[(int) $d->disease] = $name;
                if ((int) $d->disease === 11) {
                    $ok = $txt === '' ? '(ไม่ได้ระบุ)' : mb_strtolower($txt);
                    $other[$ok]['label'] = $other[$ok]['label'] ?? ($txt === '' ? '(ไม่ได้ระบุ)' : $txt);
                    $other[$ok]['n'][$g][$sx] = ($other[$ok]['n'][$g][$sx] ?? 0) + 1;
                }
            }
            if (! $keys) { $keys = [0 => $NONE]; }
            foreach ($keys as $name) {          // visit เดียวมีโรคเดียวกันสองแถว = นับครั้งเดียว
                $people[$name][$g][$sx][$v->pid] = true;
                $times[$name][$g][$sx] = ($times[$name][$g][$sx] ?? 0) + 1;
            }

            $labNames = $labBy->get($v->id, collect())->map(fn ($l) => trim(ltrim((string) ($mOpt[$l->method] ?? ''), '# ')))
                ->filter()->unique()->values()->all();

            $age = null;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v->birth_date, $b) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', (string) $v->date, $vd)) {
                $by = (int) $b[1]; $by = $by > 2400 ? $by - 543 : $by;
                $vy = (int) $vd[1]; $vy = $vy > 2400 ? $vy - 543 : $vy;
                if (checkdate((int) $b[2], (int) $b[3], $by) && checkdate((int) $vd[2], (int) $vd[3], $vy)) {
                    $age = (int) \Carbon\Carbon::create($by, (int) $b[2], (int) $b[3])->diffInYears(\Carbon\Carbon::create($vy, (int) $vd[2], (int) $vd[3]), true);
                }
            }
            $dy = (int) substr((string) $v->date, 0, 4);
            $rows[] = [
                preg_match('/^\d{4}-(\d{2})-(\d{2})/', (string) $v->date, $m) ? $m[2] . '/' . $m[1] . '/' . ($dy < 2500 ? $dy + 543 : $dy) : (string) $v->date,
                (string) $v->code,
                trim($v->name . ' ' . $v->surname),
                $age,
                $g,
                $raw,
                \App\Models\Patient::$sexOption[$v->sex] ?? '',
                \App\Enums\PatientRiskGroup::BUCKET_LABELS[\App\Enums\PatientRiskGroup::reportBucket($v->status, $v->sex, $v->occupation)] ?? '',
                implode(', ', $labNames),
                $labels ? implode(', ', $labels) : '',
            ];
        }

        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('TH SarabunPSK')->setSize(14);
        $head = function ($sheet, string $range) {
            $st = $sheet->getStyle($range);
            $st->getFont()->setBold(true);
            $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDCE6F1');
            $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        };
        $border = fn ($sheet, string $range) => $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        $period = 'คลินิก ' . $clinicName . ' · วันที่ ' . \Carbon\Carbon::parse($startIn)->format('d/m/') . (\Carbon\Carbon::parse($startIn)->year + 543)
            . ' – ' . \Carbon\Carbon::parse($endIn)->format('d/m/') . (\Carbon\Carbon::parse($endIn)->year + 543);

        // ---- ชีต 1: รายชื่อ ----
        $s1 = $ss->getActiveSheet()->setTitle('รายชื่อ');
        $s1->setCellValue('A1', 'รายชื่อผู้รับบริการ พร้อมวินิจฉัยและสัญชาติ · ' . $period);
        $s1->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $hdr = ['ลำดับ', 'วันที่มารับบริการ', 'HN', 'ชื่อ-นามสกุล', 'อายุ (ปี)', 'สัญชาติ (กลุ่ม)', 'สัญชาติ (ที่บันทึก)', 'เพศ', 'กลุ่มเสี่ยง', 'รายการตรวจ Lab', 'วินิจฉัย'];
        $s1->fromArray($hdr, null, 'A3');
        $head($s1, 'A3:K3');
        $r = 4;
        foreach ($rows as $i => $row) {
            $s1->setCellValue('A' . $r, $i + 1);
            $s1->fromArray($row, null, 'B' . $r);
            $s1->setCellValueExplicit('C' . $r, $row[1], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);   // HN ขึ้นต้น 0 ได้
            $r++;
        }
        if ($r > 4) { $border($s1, 'A3:K' . ($r - 1)); }
        $s1->setAutoFilter('A3:K' . max(3, $r - 1));
        $s1->freezePane('A4');
        foreach (['A' => 7, 'B' => 14, 'C' => 13, 'D' => 28, 'E' => 8, 'F' => 12, 'G' => 14, 'H' => 6, 'I' => 18, 'J' => 30, 'K' => 45] as $c => $w) {
            $s1->getColumnDimension($c)->setWidth($w);
        }

        // ---- ชีต 2: สรุป ----
        // ทุกกลุ่มสัญชาติแยก ชาย / หญิง (หมอขอ 2569-10-30) · ช่องรวมท้าย = ชาย / หญิง / รวม
        $s2 = $ss->createSheet()->setTitle('สรุปวินิจฉัย×สัญชาติ');
        $s2->setCellValue('A1', 'สรุปวินิจฉัย แยกสัญชาติและเพศ · ' . $period);
        $s2->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $SEX = ['ช' => 'ชาย', 'ญ' => 'หญิง'];
        $ncol = count($groups) * 2 + 3;
        $L = fn (int $i) => \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);   // 1 = A
        $lastCol = $L($ncol + 1);

        // แถวตัวเลข 1 แถว จาก [กลุ่ม][เพศ] => จำนวน (หรือชุด id ถ้า $isSet)
        $nums = function (array $byG, bool $isSet) use ($groups, $SEX) {
            $cnt = fn ($x) => $isSet ? count($x ?? []) : (int) ($x ?? 0);
            $line = []; $tot = ['ช' => $isSet ? [] : 0, 'ญ' => $isSet ? [] : 0];
            $all = $isSet ? [] : 0;
            foreach ($groups as $g) {
                foreach (array_keys($SEX) as $sx) {
                    $line[] = $cnt($byG[$g][$sx] ?? null);
                    if ($isSet) { $tot[$sx] += $byG[$g][$sx] ?? []; } else { $tot[$sx] += (int) ($byG[$g][$sx] ?? 0); }
                }
                foreach (($byG[$g] ?? []) as $x) { if ($isSet) { $all += $x; } else { $all += (int) $x; } }
            }
            $line[] = $cnt($tot['ช']); $line[] = $cnt($tot['ญ']); $line[] = $isSet ? count($all) : $all;
            return array_map(fn ($n) => $n === 0 ? null : $n, $line);   // ช่องศูนย์เว้นว่าง อ่านง่ายกว่า
        };

        $table = function (int $r, string $title, string $first, array $body, ?array $foot = null, ?string $note = null)
            use ($s2, $head, $border, $groups, $SEX, $L, $lastCol) {
            $s2->setCellValue('A' . $r, $title);
            $s2->getStyle('A' . $r)->getFont()->setBold(true)->setSize(15);
            $r++;
            // หัวตาราง 2 ชั้น: สัญชาติ (รวมช่อง) / ชาย หญิง
            $s2->setCellValue('A' . $r, $first);
            $s2->mergeCells('A' . $r . ':A' . ($r + 1));
            $c = 2;
            foreach (array_merge($groups, ['รวม']) as $g) {
                $span = $g === 'รวม' ? 3 : 2;
                $s2->setCellValue($L($c) . $r, $g);
                $s2->mergeCells($L($c) . $r . ':' . $L($c + $span - 1) . $r);
                $subs = $g === 'รวม' ? ['ชาย', 'หญิง', 'รวม'] : array_values($SEX);
                foreach ($subs as $k => $sub) { $s2->setCellValue($L($c + $k) . ($r + 1), $sub); }
                $c += $span;
            }
            $head($s2, 'A' . $r . ':' . $lastCol . ($r + 1));
            $top = $r;
            $r++;
            foreach ($body as $line) { $r++; $s2->fromArray($line, null, 'A' . $r); }
            if ($foot) {
                $r++; $s2->fromArray($foot, null, 'A' . $r);
                $s2->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->setBold(true);
                $s2->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF2F2F2');
            }
            $border($s2, 'A' . $top . ':' . $lastCol . $r);
            $s2->getStyle('B' . ($top + 2) . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            // ช่องรวมท้ายตารางพื้นอ่อน แยกจากช่องสัญชาติ
            $s2->getStyle($L(count($groups) * 2 + 2) . ($top + 2) . ':' . $lastCol . $r)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFF8E1');
            if ($note) {
                $r++; $s2->setCellValue('A' . $r, $note);
                $s2->getStyle('A' . $r)->getFont()->setItalic(true)->setSize(12)->getColor()->setARGB('FF666666');
            }
            return $r + 2;
        };

        // ตาราง 1: จำนวนตามสัญชาติ
        $body = [
            array_merge(['จำนวนคน (ไม่ซ้ำ)'], $nums($people[$NAT] ?? [], true)),
            array_merge(['จำนวนครั้งที่มารับบริการ'], $nums($times[$NAT] ?? [], false)),
        ];
        $unknownSex = 0;
        foreach (($people[$NAT] ?? []) as $byS) { $unknownSex += count($byS['?'] ?? []); }
        $r = $table(3, '1) ผู้รับบริการแยกสัญชาติ', '', $body, null,
            $unknownSex ? 'มีผู้ไม่ได้บันทึกเพศ ' . $unknownSex . ' คน — นับอยู่ในช่อง "รวม" อย่างเดียว' : null);

        // ลำดับโรคตามรายการในระบบ · มีข้อมูลเท่านั้น · "ไม่มีการวินิจฉัย" ไว้ท้าย
        $order = array_values(array_filter(array_unique(array_values($dOpt)), fn ($n) => isset($people[$n])));
        $extra = array_diff(array_keys($people), $order, [$NONE, $NAT]);   // รหัสที่ไม่มีในรายการ
        $order = array_merge($order, array_values($extra), isset($people[$NONE]) ? [$NONE] : []);

        $body = [];
        foreach ($order as $n) { $body[] = array_merge([$n], $nums($people[$n], true)); }
        $r = $table($r, '2) วินิจฉัย แยกสัญชาติและเพศ (คน)', 'วินิจฉัย', $body,
            array_merge(['ผู้รับบริการทั้งหมด (ไม่ซ้ำ)'], $nums($people[$NAT] ?? [], true)),
            'คนเดียวมีได้หลายวินิจฉัย ยอดแต่ละแถวจึงรวมกันแล้วมากกว่าจำนวนคนทั้งหมด · "Other" รวมงานที่ไม่ใช่การวินิจฉัยโรค (เช่น วัคซีน คัดกรอง) ดูรายละเอียดตาราง 4');

        $body = [];
        foreach ($order as $n) { $body[] = array_merge([$n], $nums($times[$n], false)); }
        $r = $table($r, '3) วินิจฉัย แยกสัญชาติและเพศ (ครั้ง)', 'วินิจฉัย', $body, null,
            'นับเป็นจำนวนครั้งที่มารับบริการที่มีวินิจฉัยนั้น (คนเดียวมาหลายครั้งนับทุกครั้ง)');

        if ($other) {
            $sum = function ($o) { $t = 0; foreach ($o['n'] as $byS) { $t += array_sum($byS); } return $t; };
            uasort($other, fn ($a, $b) => $sum($b) <=> $sum($a));
            $body = [];
            foreach ($other as $o) { $body[] = array_merge([$o['label']], $nums($o['n'], false)); }
            $r = $table($r, '4) รายละเอียด "Other" ตามที่พิมพ์ไว้ (ครั้ง)', 'ข้อความที่บันทึก', $body);
        }
        $s2->getColumnDimension('A')->setWidth(40);
        for ($i = 2; $i <= $ncol + 1; $i++) { $s2->getColumnDimension($L($i))->setWidth(8); }
        $s2->freezePane('B1');

        // ---- ชีต 3: การจัดกลุ่มสัญชาติ ----
        $s3 = $ss->createSheet()->setTitle('การจัดกลุ่มสัญชาติ');
        $s3->setCellValue('A1', 'สัญชาติที่บันทึกไว้ → กลุ่มที่ใช้ในรายงาน (ช่องนี้พิมพ์อิสระ ถ้าจัดกลุ่มผิดแจ้งผู้ดูแลระบบ)');
        $s3->getStyle('A1')->getFont()->setBold(true);
        $s3->fromArray(['สัญชาติที่บันทึก', 'กลุ่ม', 'จำนวนคน'], null, 'A3');
        $head($s3, 'A3:C3');
        $list = [];
        foreach ($natRaw as $raw => $set) { $list[] = [(string) $raw, reset($set), count($set)]; }
        usort($list, fn ($a, $b) => [$a[1], -$a[2]] <=> [$b[1], -$b[2]]);
        $r = 4;
        foreach ($list as $l) { $s3->fromArray($l, null, 'A' . $r); $s3->setCellValueExplicit('A' . $r, $l[0], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING); $r++; }
        if ($r > 4) { $border($s3, 'A3:C' . ($r - 1)); }
        $s3->getColumnDimension('A')->setWidth(30);
        $s3->getColumnDimension('B')->setWidth(12);
        $s3->getColumnDimension('C')->setWidth(10);

        $ss->setActiveSheetIndex(1);   // เปิดไฟล์มาเจอตารางสรุปก่อน
        $fileName = 'วินิจฉัย_สัญชาติ_' . $clinicName . '_' . $start . '_ถึง_' . $end . '.xlsx';

        return response()->streamDownload(function () use ($ss) {
            (new Xlsx($ss))->save('php://output');
        }, $fileName, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}