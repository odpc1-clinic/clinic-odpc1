<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LabExport implements WithMultipleSheets
{
    use Exportable;

    protected $start_date;
    protected $end_date;

    public function __construct($start_date, $end_date) {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function sheets(): array {
        return [
            new LabDS1Export($this->start_date, $this->end_date),
            new GroupPeopleExport($this->start_date, $this->end_date),
            new DiagnosisExport($this->start_date, $this->end_date),
            new DiagnosisEgaspExport($this->start_date, $this->end_date),
            new DiagnosisGcNguExport($this->start_date, $this->end_date),
            new ContactExport($this->start_date, $this->end_date),
        ];
    }
}
