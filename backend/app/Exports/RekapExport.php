<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RekapExport implements WithMultipleSheets
{
    public function __construct(private array $sheets) {}

    public function sheets(): array
    {
        return $this->sheets;
    }
}
