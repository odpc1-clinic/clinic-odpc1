<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsPatientColumns;
use App\Models\Visit;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

// Sheet "Group People": รายชื่อผู้รับบริการทุกคนในช่วงวันที่ที่เลือก (ไม่ต้องมีผลแล็บก็ได้ ต่างจาก sheet LAB)
// ใช้เป็นทะเบียนหลักสำหรับนับจัดกลุ่มเป้าหมาย (สถานะ/อาชีพ) ในคอลัมน์นับท้ายรายงาน
class GroupPeopleExport implements FromArray, WithHeadings, WithTitle
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
        return 'Group People';
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
            'การรักษา(ยา,dose,route,จำนวน)', 'การรักษา2(ยา,dose,route,จำนวน)', 'การรักษา3(ยา,dose,route,จำนวน)', 'การรักษา4(ยา,dose,route,จำนวน)',
            'เพศคู่สัมผัส(1)', 'ประเภทคู่สัมผัส(1)', 'สถานะการติดตาม(1)', 'สถานะการติดตาม(fail)',
            'ประเภทคู่สัมผัส(2)', 'สถานะการติดตาม(2)',
            'เพศคู่สัมผัส(3)', 'ประเภทคู่สัมผัส(3)', 'สถานะการติดตาม(3)',
            'วันนัด', 'เหตุผลการนัด',
        ];
    }

    public function array(): array
    {
        $visits = Visit::whereBetween('date', [$this->startDate, $this->endDate])
            ->with([
                'patient2', 'patient2.address2',
                'diagnoses', 'visitMedicines.medicine',
                'labs.labSubs', 'contactPersons',
            ])
            ->orderBy('date')
            ->get();

        return $visits->map(fn (Visit $visit) => $this->buildRow($visit))->all();
    }

    private function buildRow(Visit $visit): array
    {
        $diagnoses = $visit->diagnoses;
        $medicines = $visit->visitMedicines;
        $contacts = $visit->contactPersons;

        return array_merge(
            $this->patientColumns($visit),
            $this->labColumns($this->labSubsOf($visit)),
            [
                $diagnoses[0]->disease ?? '',
                $diagnoses[0]->term_syphilis ?? '',
                $diagnoses[0]->disease_state ?? '',
                $diagnoses[1]->disease ?? '',
                $diagnoses[1]->disease_state ?? '',
                $diagnoses[1]->term_syphilis ?? '',
                $diagnoses[2]->disease ?? '',
                $diagnoses[2]->disease_state ?? '',
                $diagnoses[2]->term_syphilis ?? '',
                $this->medicineField($medicines, 0),
                $this->medicineField($medicines, 1),
                $this->medicineField($medicines, 2),
                $this->medicineField($medicines, 3),
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
