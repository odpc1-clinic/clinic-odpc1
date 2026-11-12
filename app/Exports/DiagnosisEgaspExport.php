<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsPatientColumns;
use App\Models\Diagnosi;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

// Sheet "Diagnosis-Egasp": เหมือน sheet Diagnosis ทุกอย่าง แค่เพิ่มคอลัมน์ Egasp (diagnoses.egasp_info)
// ต่อท้ายวินิจฉัย1(สถานะโรค) — ใช้กับ diagnosis ที่เป็น Egasp 1/Egasp 2 (เฝ้าระวังเชื้อหนองในดื้อยา)
class DiagnosisEgaspExport implements FromArray, WithHeadings, WithTitle
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
        return 'Diagnosis-Egasp';
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
            'วินิจฉัย1(โรค)', 'ระยะโรค1', 'วินิจฉัย1(สถานะโรค)', 'Egasp',
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
        $diagnoses = Diagnosi::whereHas('visit', function ($q) {
            $q->whereBetween('date', [$this->startDate, $this->endDate]);
        })
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

        $diagnosisCols = $this->singleDiagnosisColumns($diagnosi);
        // แทรก egasp_info เข้าไปหลัง วินิจฉัย1(สถานะโรค) (index 2 ของ singleDiagnosisColumns)
        array_splice($diagnosisCols, 3, 0, [$diagnosi->egasp_info ?? '']);

        return array_merge(
            $this->patientColumns($visit),
            $this->labColumns($this->labSubsOf($visit)),
            $diagnosisCols,
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
