<?php

namespace App\Exports;

use App\Exports\Concerns\BuildsPatientColumns;
use App\Models\ContactPerson;
use App\Models\Visit;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

// Sheet "Contact": 1 แถว = 1 คู่สัมผัส (contact_people 1 รายการ) ของผู้ป่วยที่มารับบริการในช่วงวันที่ที่เลือก
class ContactExport implements FromArray, WithHeadings, WithTitle
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
        return 'Contact';
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
            'เพศคู่สัมผัส(1)', 'ประเภทคู่สัมผัส(1)', 'สถานะการติดตาม(1)', 'ชื่อ',
            'ประเภทคู่สัมผัส(2)', 'สถานะการติดตาม(2)',
            'เพศคู่สัมผัส(3)', 'ประเภทคู่สัมผัส(3)', 'สถานะการติดตาม(3)',
            'วันนัด', 'เหตุผลการนัด',
        ];
    }

    public function array(): array
    {
        // ContactPerson::visit() ไม่ใช่ Eloquent relation (เป็น method ธรรมดาที่ query เอง) เลย join ตรงด้วย visit_id แทน
        $visitIds = Visit::whereBetween('date', [$this->startDate, $this->endDate])->pluck('id');

        $contacts = ContactPerson::whereIn('visit_id', $visitIds)
            ->orderBy('visit_id')
            ->get();

        $visits = Visit::whereIn('id', $contacts->pluck('visit_id')->unique())
            ->with([
                'patient2', 'patient2.address2',
                'diagnoses', 'visitMedicines.medicine',
                'labs.labSubs', 'contactPersons',
            ])
            ->get()
            ->keyBy('id');

        return $contacts->map(function (ContactPerson $contact) use ($visits) {
            return $this->buildRow($contact, $visits->get($contact->visit_id));
        })->filter()->all();
    }

    private function buildRow(ContactPerson $contact, ?Visit $visit): ?array
    {
        if (!$visit) {
            return null;
        }

        $diagnoses = $visit->diagnoses;
        $medicines = $visit->visitMedicines;

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
                $contact->cpsex ?? '',
                $contact->type ?? '',
                $contact->trush_tracks ?? '',
                $contact->name_surname ?? '',
                '', '', '', '', '',
                $this->beDate($visit->appointment ?? null),
                $visit->appointment_reason ?? '',
            ]
        );
    }
}
