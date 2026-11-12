<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LabSheet implements FromCollection, WithTitle, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $labs;
    public function __construct($labs) { $this->labs = $labs; }
    public function collection() { return $this->labs; }
    public function title(): string { return 'LAB'; }
    public function styles(Worksheet $sheet) { return [1 => ['font' => ['bold' => true]]]; }

    public function headings(): array
    {
        return [
            'HN', 'เลขบัตร ID/passport', 'ชื่อ', 'สกุล', 'เพศ', 'สัญชาติ', 'ระดับการศึกษา', 'วันเกิด', 'อายุ', 'โรคประจำตัว', 'วันที่มารับบริการ', 'กลุ่มเป้าหมาย(status)', 'อาชีพ', 'ที่อยู่(home address)', 'ที่อยู่(ตำบล)', 'ที่อยู่(อำเภอ)', 'ที่อยู่(จังหวัด)', 'เบอร์โทรศัพท์',
            'HIV(Q2=6)', 'TPHA(Q2=7)', 'RPR(Q2=8)', 'LAB1(method)', 'LAB1(specimen)', 'LAB1(result)', 'LAB2(method)', 'LAB2(specimen)', 'LAB2(result)', 'LAB3(method)', 'LAB3(specimen)', 'LAB3(result)',
            'วินิจฉัย1(โรค)', 'ระยะโรค1', 'วินิจฉัย1(สถานะโรค)', 'วินิจฉัย2(โรค)', 'วินิจฉัย2(สถานะโรค)', 'ระยะโรค2', 'วินิจฉัย3(โรค)', 'วินิจฉัย3(สถานะโรค)', 'ระยะโรค3',
            'การรักษา(ยา,dose,route,จำนวน)', 'การรักษา2(ยา,dose,route,จำนวน)', 'การรักษา3(ยา,dose,route,จำนวน)',
            'จำนวนคู่สัมผัส', 'เพศคู่สัมผัส(1)', 'ประเภทคู่สัมผัส(1)', 'สถานะการติดตาม(1)', 'สถานะการติดตาม(fail)', 'ประเภทคู่สัมผัส(2)', 'สถานะการติดตาม(2)', 'เพศคู่สัมผัส(3)', 'ประเภทคู่สัมผัส(3)', 'สถานะการติดตาม(3)',
            'วันนัด', 'เหตุผลการนัด', '', 'BDนับพนักงานบริการแยกเพศช=11ญช12', 'ชายตรวจTPHA=1', 'หญิงตรวจTPHA=1', 'พนักงานบริการหญิงตรวจTPHA=1'
        ];
    }

    public function map($row): array
    {
        // 1. ดึงความสัมพันธ์ต่างๆ มารอไว้ก่อนเพื่อลดความซับซ้อนของโค้ด
        $patient = $row->patient2 ?? null;
        
        // สมมติว่ามี Relation ชื่อ addressRecord ใน Model Patient (ถ้าไม่มีให้ปรับชื่อตามที่คุณกานใช้)
        $address = $patient->addressRecord ?? null; 
        
        // ข้อมูลผลเลือด (Lab Blood)
        $blood = $row->labBlood ?? null; // ปรับชื่อ Relation ตามที่มีใน Model Visit

        // ข้อมูลวินิจฉัย (ดึงมา 3 ลำดับแรก)
        $dx = $row->diagnoses ?? collect();
        
        // ข้อมูลยา (ดึงมา 4 ลำดับแรก)
        $meds = $row->visitMedicines ?? collect();
        
        // ข้อมูลคู่สัมผัส (ดึงมา 3 ลำดับแรก)
        $contacts = $row->contactPeople ?? collect();

        // ข้อมูล Lab ย่อย (ดึงจาก Lab -> LabSubs)
        $labSubs = collect();
        if ($row->labs) {
            foreach ($row->labs as $lab) {
                if ($lab->labSubs) {
                    $labSubs = $labSubs->merge($lab->labSubs);
                }
            }
        }

        // 2. คำนวณอายุจาก birth_date (ถ้ามี)
        $age = '-';
        if ($patient && $patient->birth_date) {
            $age = \Carbon\Carbon::parse($patient->birth_date)->age;
        }

        // 3. เริ่ม Map ข้อมูลลงคอลัมน์ Excel
        return [
            // ==========================================
            // หมวด: ข้อมูลผู้ป่วย (จากตาราง patients และ visits)
            // ==========================================
            $patient->code ?? '-',                               // HN
            $patient->id_card_number ?? '-',                     // เลขบัตร ID/passport
            $patient->name ?? '-',                               // ชื่อ
            $patient->surname ?? '-',                            // สกุล
            $patient->sex ?? '-',                                // เพศ
            $patient->nationality ?? 'THA',                      // สัญชาติ
            $patient->education ?? '-',                          // ระดับการศึกษา
            $patient->birth_date ?? '-',                         // วันเกิด
            $age,                                                // อายุ
            $patient->congenitaldisease ?? 'ปฏิเสธ',              // โรคประจำตัว
            $row->date ?? '-',                                   // วันที่มารับบริการ (จาก visits)
            $patient->status ?? '-',                             // กลุ่มเป้าหมาย(status)
            $patient->occupation ?? '-',                         // อาชีพ
            ($address->house_no ?? '') . ' ' . ($address->address ?? '-'), // ที่อยู่(home address)
            $address->sub_district ?? '-',                       // ที่อยู่(ตำบล)
            $address->district ?? '-',                           // ที่อยู่(อำเภอ)
            $address->province ?? '-',                           // ที่อยู่(จังหวัด)
            $patient->tel ?? '-',                                // เบอร์โทรศัพท์
            
            // ==========================================
            // หมวด: ผลเลือด (จากตาราง lab_bloods)
            // ==========================================
            $blood->hiv ?? '',                                   // HIV(Q2=6)
            $blood->syphilis ?? '',                              // TPHA(Q2=7)
            $blood->rpr ?? '',                                   // RPR(Q2=8)

            // ==========================================
            // หมวด: ผลแล็บย่อย 1-3 (จากตาราง lab_subs)
            // ==========================================
            $labSubs[0]->method ?? '',                           // LAB1(method)
            $labSubs[0]->specimen_from ?? '',                    // LAB1(specimen)
            $labSubs[0]->result ?? '',                           // LAB1(result)
            $labSubs[1]->method ?? '',                           // LAB2(method)
            $labSubs[1]->specimen_from ?? '',                    // LAB2(specimen)
            $labSubs[1]->result ?? '',                           // LAB2(result)
            $labSubs[2]->method ?? '',                           // LAB3(method)
            $labSubs[2]->specimen_from ?? '',                    // LAB3(specimen)
            $labSubs[2]->result ?? '',                           // LAB3(result)
            
            // ==========================================
            // หมวด: การวินิจฉัย 1-3 (จากตาราง diagnoses)
            // ==========================================
            $dx[0]->disease ?? '',                               // วินิจฉัย1(โรค)
            $dx[0]->disease_state ?? '',                         // ระยะโรค1
            $dx[0]->disease_state_other ?? '',                   // วินิจฉัย1(สถานะโรค)
            // $dx[0]->egasp_info ?? '',                         // ***(เปิดใช้เฉพาะ Sheet Egasp)***
            
            $dx[1]->disease ?? '',                               // วินิจฉัย2(โรค)
            $dx[1]->disease_state_other ?? '',                   // วินิจฉัย2(สถานะโรค)
            $dx[1]->disease_state ?? '',                         // ระยะโรค2
            
            $dx[2]->disease ?? '',                               // วินิจฉัย3(โรค)
            $dx[2]->disease_state_other ?? '',                   // วินิจฉัย3(สถานะโรค)
            $dx[2]->disease_state ?? '',                         // ระยะโรค3
            
            // ==========================================
            // หมวด: การรักษา 1-4 (จากตาราง visit_medicines)
            // ==========================================
            isset($meds[0]) ? ($meds[0]->medicine_id . ' ' . $meds[0]->dose . ' ' . $meds[0]->route . ' ' . $meds[0]->amount) : '', // การรักษา 1
            isset($meds[1]) ? ($meds[1]->medicine_id . ' ' . $meds[1]->dose . ' ' . $meds[1]->route . ' ' . $meds[1]->amount) : '', // การรักษา 2
            isset($meds[2]) ? ($meds[2]->medicine_id . ' ' . $meds[2]->dose . ' ' . $meds[2]->route . ' ' . $meds[2]->amount) : '', // การรักษา 3
            isset($meds[3]) ? ($meds[3]->medicine_id . ' ' . $meds[3]->dose . ' ' . $meds[3]->route . ' ' . $meds[3]->amount) : '', // การรักษา 4
            
            // ==========================================
            // หมวด: คู่สัมผัส (จากตาราง contact_people)
            // ==========================================
            // $contacts->count(),                               // จำนวนคู่สัมผัส ***(เปิดใช้เฉพาะ Sheet LAB)***
            
            $contacts[0]->cpsex ?? '',                           // เพศคู่สัมผัส(1)
            $contacts[0]->type ?? '',                            // ประเภทคู่สัมผัส(1)
            $contacts[0]->customer_ty ?? '',                     // สถานะการติดตาม(1)
            // $contacts[0]->name_surname ?? '',                 // ชื่อคู่สัมผัส ***(เปิดใช้เฉพาะ Sheet Contact)***
            
            $row->touch_tracing_fail ?? '',                      // สถานะการติดตาม(fail) (จาก visits)
            
            $contacts[1]->type ?? '',                            // ประเภทคู่สัมผัส(2)
            $contacts[1]->customer_ty ?? '',                     // สถานะการติดตาม(2)
            
            $contacts[2]->cpsex ?? '',                           // เพศคู่สัมผัส(3)
            $contacts[2]->type ?? '',                            // ประเภทคู่สัมผัส(3)
            $contacts[2]->customer_ty ?? '',                     // สถานะการติดตาม(3)
            
            // ==========================================
            // หมวด: ท้ายตาราง (จากตาราง visits และคำนวณ)
            // ==========================================
            $row->appointment ?? '',                             // วันนัด
            $row->appointment_reason ?? '',                      // เหตุผลการนัด
            '',                                                  // (คอลัมน์ว่าง)
            
            // ค่าพวกนี้ถ้ามีการเก็บในระบบให้ใส่ตัวแปร ถ้าไม่มีให้เว้นว่างหรือหา logic คำนวณใส่ครับ
            '',                                                  // BDนับพนักงานบริการแยกเพศช=11ญช12
            '',                                                  // ชายตรวจTPHA=1
            '',                                                  // หญิงตรวจTPHA=1
            ''                                                   // พนักงานบริการหญิงตรวจTPHA=1
        ];
    }
}