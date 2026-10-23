<?php

namespace App\Imports;

use App\Models\Sales;
use App\Models\Toko;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithUpserts;

class TokoImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsOnError, WithUpserts
{
    use SkipsErrors;

    private array $salesCache = [];

    public function model(array $row): ?Toko
    {
        $namaToko = trim($row['nama_toko'] ?? '');
        $wilayah  = trim($row['kode_wilayah'] ?? $row['wilayah'] ?? '');

        if (empty($namaToko) || empty($wilayah)) {
            return null;
        }

        $salesId = $this->resolveSalesId($wilayah);

        if (!$salesId) {
            return null;
        }

        return new Toko([
            'sales_id'  => $salesId,
            'nama_kios' => $namaToko,
            'wilayah'   => $wilayah,
            'is_new'    => true,
        ]);
    }

    private function resolveSalesId(string $wilayah): ?int
    {
        $key = strtolower(trim($wilayah));

        if (array_key_exists($key, $this->salesCache)) {
            return $this->salesCache[$key];
        }

        $sales = Sales::whereRaw('LOWER(area) LIKE ?', ['%' . $key . '%'])
            ->where('is_active', true)
            ->first();

        return $this->salesCache[$key] = $sales?->id;
    }

    public function uniqueBy(): array
    {
        return ['nama_kios', 'wilayah'];
    }

    public function chunkSize(): int
    {
        return 250;
    }
}
