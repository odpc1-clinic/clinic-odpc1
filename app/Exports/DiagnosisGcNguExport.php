<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsPatientColumns;
use App\Models\Diagnosi;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

// Sheet "Diagnosis-GC NGU": เฉพาะวินิจฉัยที่เป็น "โรคที่ 2 หรือ 3" ของ visit นั้น (ไม่เอาวินิจฉัยแรก)
// ใช้จับโรคร่วม/โรคแทรกซ้อนที่วินิจฉัยเพิ่มเติมจากโรคหลัก
// ใช้ ROW_NUMBER() OVER (PARTITION BY visit_id ORDER BY id) เอา rn IN (2,3)
class DiagnosisGcNguExport implements FromArray, WithHeadings, WithTitle
{
    use BuildsPatientColumns;

    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    public function title(): string
    {
        return 'Diagnosis-GC NGU';
    }

    public function headings(): array
    {
        return [
            'HN', 'เลขบัตร ID/passport', 'ชื่อ', 'สกุล', 'เพศ', 'สัญชาติ', 'ระดับการศึกษา',
            'วันเกิด', 'อายุ', 'โรคประจำตัว', 'วันที่มารับบริการ', 'กลุ่มเป้าหมาย(status)', 'อาชีพ',
            'ที่อยู่(home address)', 'ที่อยู่(ตำบล)', 'ที่อยู่(อำเภอ)', 'ที่อยู่(จังหวัด)', 'เบอร์โทรศัพท์',
            'HIV(Q2=6)', 'TPHA(Q2=7)', 'RPR(Q2=8)',
            'LAB1(method)', 'LAB1(specimen)', 'LAB1(result)',
            'LAB2(method)', 'LAB2(specimen)', 'LAB2(result)',
            'LAB3(method)', 'LAB3(specimen)', 'LAB3(result)',
            'วินิจฉัย1(โรค)', 'ระยะโรค1', 'วินิจฉัย1(สถานะโรค)',
            'วินิจฉัย2(โรค)', 'วินิจฉัย2(สถานะโรค)', 'ระยะโรค2',
            'วินิจฉัย3(โรค)', 'วินิจฉัย3(สถานะโรค)', 'ระยะโรค3',
            'การรักษา(ยา,dose,route,จำนวน)', 'การรักษา2(ยา,dose,route,จำนวน)', 'การรักษา3(ยา,dose,route,จำนวน)',
            'จำนวนคู่สัมผัส',
            'เพศคู่สัมผัส(1)', 'ประเภทคู่สัมผัส(1)', 'สถานะการติดตาม(1)', 'สถานะการติดตาม(fail)',
            'ประเภทคู่สัมผัส(2)', 'สถานะการติดตาม(2)',
            'เพศคู่สัมผัส(3)', 'ประเภทคู่สัมผัส(3)', 'สถานะการติดตาม(3)',
            'วันนัด', 'เหตุผลการนัด',
        ];
    }

    public function array(): array
    {
        $secondaryIds = DB::select(
            "WITH ranked_diagnoses AS (
                SELECT diagnoses.id, diagnoses.visit_id,
                    ROW_NUMBER() OVER (PARTITION BY diagnoses.visit_id ORDER BY diagnoses.id) AS rn
                FROM diagnoses
                INNER JOIN visits ON visits.id = diagnoses.visit_id
                WHERE visits.date BETWEEN ? AND ?
            )
            SELECT id FROM ranked_diagnoses WHERE rn IN (2, 3)",
            [$this->startDate, $this->endDate]
        );
        $secondaryIds = array_column($secondaryIds, 'id');

        $diagnoses = Diagnosi::whereIn('id', $secondaryIds)
            ->with([
                'visit', 'visit.patient2', 'visit.patient2.address2',
                'visit.visitMedicines.medicine', 'visit.labs.labSubs', 'visit.contactPersons',
            ])
            ->orderBy('visit_id')
            ->get();

        return $diagnoses->map(fn (Diagnosi $diagnosi) => $this->buildRow($diagnosi))->all();
    }

    private function buildRow(Diagnosi $diagnosi): array
    {
        $visit = $diagnosi->visit;
        $medicines = $visit->visitMedicines;
        $contacts = $visit->contactPersons;

        return array_merge(
            $this->patientColumns($visit),
            $this->labColumns($this->labSubsOf($visit)),
            $this->singleDiagnosisColumns($diagnosi),
            [
                $this->medicineField($medicines, 0),
                $this->medicineField($medicines, 1),
                $this->medicineField($medicines, 2),
                $contacts->count(),
                $contacts[0]->cpsex ?? '',
                $contacts[0]->type ?? '',
                $contacts[0]->trush_tracks ?? '',
                $visit->touch_tracing_fail ?? '',
                $contacts[1]->type ?? '',
                $contacts[1]->trush_tracks ?? '',
                $contacts[2]->cpsex ?? '',
                $contacts[2]->type ?? '',
                $contacts[2]->trush_tracks ?? '',
                $this->beDate($visit->appointment ?? null),
                $visit->appointment_reason ?? '',
            ]
        );
    }
}
