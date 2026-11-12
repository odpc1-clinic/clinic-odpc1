<?php

namespace App\Exports;

use App\Models\Visit;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;
use App\Models\Patient;

class VisitExport implements FromCollection, WithHeadings
{
   public function collection()
    {

        // กรองข้อมูลตามเงื่อนไขจาก URL query string
        $query = Visit::with(['patient2', 'diagnoses', 'followups']);

        // กรองตาม HN (ถ้ามีการกรอก)
        if (request('hn')) {
            $query->whereHas('patient2', function ($q) {
                $q->where('code', 'like', '%' . request('hn') . '%');
            });
        }

        // กรองตามเหตุผลการนัดหมาย (ถ้ามีการเลือก)
        if (request('reason')) {
            $query->where('appointment_reason', 'like', '%' . request('reason') . '%');
        }

    // ตรวจสอบวันที่ start และ end ว่ามีการกรอกหรือไม่
    $startDate = request('start') ? Carbon::createFromFormat('d/m/Y', request('start'))->format('Y-m-d') : null;
    $endDate = request('end') ? Carbon::createFromFormat('d/m/Y', request('end'))->format('Y-m-d') : null;

    // กรองวันที่ ถ้ามีการกรอก
    if ($startDate && $endDate) {
        // ถ้ามีการกรอก start และ end ทั้งสองตัว
        $query->whereBetween('appointment', [$startDate, $endDate]);
    } elseif ($startDate) {
        // ถ้ามีการกรอกแค่ start
        $query->whereDate('appointment', '>=', $startDate);
    } elseif ($endDate) {
        // ถ้ามีการกรอกแค่ end
        $query->whereDate('appointment', '<=', $endDate);
    }

    // ถ้าไม่มีการกรอกทั้ง start และ end ให้ดึงข้อมูลทั้งหมด (ไม่ใช้เงื่อนไขวัน)
    if (!$startDate && !$endDate) {
        // ไม่มีการกรอกวันที่ ให้ดึงข้อมูลทั้งหมด (ไม่ใช้เงื่อนไขวัน)
    }

        // เพิ่มการเรียงลำดับตาม patient_code
        $query->whereHas('patient2', function ($q) {
            $q->orderBy('code', 'desc');  // เรียงลำดับตาม patient code
        });

        // ดึงข้อมูลทั้งหมดที่กรองตามเงื่อนไข
        return $query->get()->map(function ($visit, $index) {
    // ค้นหาวันที่มาครั้งถัดไป
    $nextVisit = Visit::where('patient_id', $visit->patient_id)
    ->whereDate('date', '>', $visit->date)
    ->orderBy('date', 'asc')
    ->first();


    // รวมข้อมูลการติดตามเป็นข้อความ (อาจมีหลายรายการใน 1 visit)
$followupsText = $visit->followups->map(function ($f) {
    return $f->method . ' (' . $f->followup_count . ' ครั้ง, ' . $f->status . ', ' . $f->created_at->format('d/m/Y H:i') . ')';
})->join("\n"); // ใช้ขึ้นบรรทัดใหม่ใน Excel
                  

    // รวม diagnosis
    $diagnoses = $visit->diagnoses->map(function ($diagnosis) {
        return \App\Models\Diagnosi::$diseaseOption[$diagnosis->disease] ?? 'ไม่ทราบ';
    })->join(', '); // รวมเป็นข้อความคั่นด้วยเครื่องหมายคอมมา

    // ตรวจสอบว่าผู้ป่วยมาก่อนวันนัดหรือไม่

$nextVisitDate = 'ยังไม่มา'; // ค่า default
$appointmentDateObj = $visit->appointment ? Carbon::parse($visit->appointment) : null;

if ($nextVisit) {
    $nextVisitDateObj = Carbon::parse($nextVisit->date);

    if ($appointmentDateObj) {
        if ($nextVisitDateObj->lt($appointmentDateObj)) {
            $nextVisitDate = $nextVisitDateObj->format('d/m/Y') . ' (มาก่อนนัด)';
        } elseif ($nextVisitDateObj->eq($appointmentDateObj)) {
            $nextVisitDate = $nextVisitDateObj->format('d/m/Y') . ' (มาตามนัด)';
        } elseif ($nextVisitDateObj->gt($appointmentDateObj)) {
            $nextVisitDate = $nextVisitDateObj->format('d/m/Y') . ' (มาหลังนัด)';;
        }
    } else {
        $nextVisitDate = $nextVisitDateObj->format('d/m/Y'); // กรณีไม่มีวันนัด
    }
}

    return [
    'index' => $index + 1,
    'patient_code' => $visit->patient2->code,
    'patient_name' => $visit->patient2->name,
    'patient_surname' => $visit->patient2->surname,
    'sex' => Patient::$sexOption[$visit->patient2->sex] ?? 'ไม่ระบุ',
    'status' => Patient::$statusOption[$visit->patient2->status] ?? 'ไม่ระบุ',
    'date_visit' => Carbon::parse($visit->date)->format('d/m/Y'),
    'diagnoses' => $diagnoses,
    'appointment' => Carbon::parse($visit->appointment)->format('d/m/Y'),
    'appointment_reason' => $visit->appointment_reason,
    'next_visit_date' => $nextVisitDate,
    'Tel' => $visit->patient2->tel,
    'followups' => $followupsText, // ✅ เพิ่มการติดตาม
];

});

    }

    public function headings(): array
    {
        return [
            'ลำดับ',
            'Patient Code',
            'Patient Name',
            'Patient Surname',
            'Sex',
            'Status',
            'Date of Visit',
            'Diagnosis',
            'Appointment Date',
            'Appointment Reason',
            'Next Visit Date',
            'โทรศัพท์',
            'ติดตามผู้ป่วย',
        ];
    }
}
