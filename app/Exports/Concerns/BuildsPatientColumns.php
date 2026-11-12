<?php

namespace App\Exports\Concerns;

use App\Models\Visit;
use Carbon\Carbon;

// คอลัมน์ข้อมูลผู้ป่วย/visit ที่ใช้ร่วมกันทุก sheet ของรายงาน Lab (LAB, Group People, Diagnosis, Contact, ...)
// แยกไว้ตรงนี้เพื่อไม่ให้ต้องเขียนซ้ำในทุก Export class
trait BuildsPatientColumns
{
    private function patientColumns(Visit $visit): array
    {
        $patient = $visit->patient2;
        $address = $patient?->address2;

        return [
            $patient->code ?? '',
            $patient->id_card_number ?? '',
            $patient->name ?? '',
            $patient->surname ?? '',
            $patient->sex ?? '',
            $patient->nationality ?? '',
            $patient->education ?? '',
            $this->beDate($patient->birth_date ?? null),
            $this->age($patient->birth_date ?? null),
            $patient->congenitaldisease ?? '',
            $this->beDate($visit->date ?? null),
            $patient->status ?? '',
            $patient->occupation ?? '',
            $address ? trim($address->house_no . ' ' . $address->address) : '',
            $address ? $address->sub_district() : '',
            $address ? $address->district() : '',
            $address ? $address->province() : '',
            $patient->tel ?? '',
        ];
    }

    private function labColumns($labSubs): array
    {
        $hiv = $labSubs->firstWhere('method', 6);
        $tpha = $labSubs->firstWhere('method', 7);
        $rpr = $labSubs->firstWhere('method', 8);

        // group ตาม (method, specimen) ก่อนใส่ช่อง LAB1/2/3 กันเคสตรวจซ้ำที่ตำแหน่งเดิม
        // จะได้นับเป็นรายการเดียว ไม่ใช่นับซ้ำเป็นคนละช่องของคนเดียวกัน
        $otherLabs = $labSubs->reject(function ($ls) {
            return in_array((int) $ls->method, [6, 7, 8], true);
        })->unique(function ($ls) {
            return $ls->method . '|' . $ls->specimen_from;
        })->values();

        $cols = [
            $hiv->result ?? '',
            $tpha->result ?? '',
            $rpr->result ?? '',
        ];

        for ($i = 0; $i < 3; $i++) {
            $labSub = $otherLabs->get($i);
            $cols[] = $labSub->method ?? '';
            $cols[] = $labSub->specimen_from ?? '';
            $cols[] = $labSub->result ?? '';
        }

        return $cols;
    }

    private function medicineField($medicines, int $index)
    {
        $vm = $medicines[$index] ?? null;
        if (!$vm) {
            return '';
        }

        $name = $vm->medicine->name ?? '';

        return trim("{$name}, {$vm->dose}, {$vm->route}, {$vm->amount}", ', ');
    }

    private function labSubsOf(Visit $visit)
    {
        return $visit->labs ? $visit->labs->labSubs : collect();
    }

    // Diagnosis / Diagnosis-Egasp / Diagnosis-GC NGU: 1 แถว = 1 diagnosis (ไม่ pivot เหมือน LAB/Group People)
    // ค่าวินิจฉัยของแถวนั้นจะลงในช่อง "วินิจฉัย1" เสมอ ช่อง 2/3 ปล่อยว่างไว้เพราะ explode มาเป็นคนละแถวแล้ว
    private function singleDiagnosisColumns($diagnosi): array
    {
        return [
            $diagnosi->disease ?? '',
            $diagnosi->term_syphilis ?? '',
            $diagnosi->disease_state ?? '',
            '', '', '',
            '', '', '',
        ];
    }

    // วันที่ในระบบเก็บเป็นปี พ.ศ. อยู่แล้ว (Y-m-d ที่ Y คือปี พ.ศ.) แค่จัดรูปแบบให้อ่านง่าย
    private function beDate(?string $date): string
    {
        if (!$date) {
            return '';
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable $e) {
            return $date;
        }
    }

    // birth_date เก็บเป็นปี พ.ศ. ต้องลบ 543 ปีก่อนเทียบกับวันนี้ (ปี ค.ศ.) ถึงจะได้อายุจริง
    private function age(?string $birthDate)
    {
        if (!$birthDate) {
            return '';
        }

        try {
            return Carbon::parse($birthDate)->subYears(543)->diffInYears(Carbon::now(), true);
        } catch (\Throwable $e) {
            return '';
        }
    }
}
