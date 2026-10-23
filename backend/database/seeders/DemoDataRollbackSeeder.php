<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menghapus data yang dibuat DemoDataSeeder, berdasarkan rentang id
 * di storage/app/demo_data_ranges.json. Baris di luar rentang itu
 * (data lama) tidak disentuh.
 */
class DemoDataRollbackSeeder extends Seeder
{
    public function run(): void
    {
        if (! Storage::disk('local')->exists(DemoDataSeeder::MANIFEST)) {
            $this->command->error('Manifest tidak ditemukan: storage/app/' . DemoDataSeeder::MANIFEST);
            $this->command->line('Tidak ada yang dihapus.');

            return;
        }

        $ranges = json_decode(Storage::disk('local')->get(DemoDataSeeder::MANIFEST), true) ?: [];

        // Anak dulu, baru induk — hindari pelanggaran foreign key.
        $urutan = [
            'daftar_muat_items', 'daftar_muats', 'driver_izin',
            'presensis', 'kunjungan_tokos', 'izins',
            'checkers', 'kendaraans', 'drivers',
        ];

        $hasil = [];
        foreach ($urutan as $tabel) {
            if (! isset($ranges[$tabel])) {
                continue;
            }

            // Manifest bisa berisi beberapa rentang kalau seeder dijalankan
            // lebih dari sekali; format lama hanya satu rentang per tabel.
            $daftar = isset($ranges[$tabel]['from']) ? [$ranges[$tabel]] : $ranges[$tabel];

            $jumlah = 0;
            foreach ($daftar as $r) {
                $jumlah += DB::table($tabel)->whereBetween('id', [$r['from'], $r['to']])->delete();
            }

            $hasil[] = [$tabel, $jumlah];
        }

        Storage::disk('local')->delete(DemoDataSeeder::MANIFEST);

        $this->command->info('✓ Data demo dihapus:');
        $this->command->table(['Tabel', 'Baris dihapus'], $hasil);
    }
}
