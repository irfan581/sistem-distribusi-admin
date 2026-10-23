<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class RekapSheet implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private string $sheetTitle,
        private Collection $rows
    ) {}

    public function title(): string
    {
        return substr($this->sheetTitle, 0, 31);
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'Waktu', 'Nama Sales', 'NIK', 'Area', 'Kategori', 'Aktivitas / Detail', 'Lokasi GPS', 'Keterangan'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFEA580C']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
