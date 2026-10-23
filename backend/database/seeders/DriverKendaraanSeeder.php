<?php

namespace Database\Seeders;

use App\Models\drivers;
use App\Models\kendaraans;
use Illuminate\Database\Seeder;

class DriverKendaraanSeeder extends Seeder
{
    public function run(): void
    {
        // ── Driver ────────────────────────────────────────────
        $driverData = [
            ['nama_driver' => 'Slamet Riyadi',    'no_telepon' => '081234567001', 'alamat' => 'Jl. Mawar No. 12, Banyuwangi',       'status_kerja' => 'Aktif',      'jenis_sim' => 'B2'],
            ['nama_driver' => 'Suparno',           'no_telepon' => '081234567002', 'alamat' => 'Jl. Melati No. 5, Banyuwangi',        'status_kerja' => 'Aktif',      'jenis_sim' => 'B2'],
            ['nama_driver' => 'Agus Santoso',      'no_telepon' => '081234567003', 'alamat' => 'Jl. Kenanga No. 8, Genteng',          'status_kerja' => 'Aktif',      'jenis_sim' => 'B1'],
            ['nama_driver' => 'Heru Purnomo',      'no_telepon' => '081234567004', 'alamat' => 'Jl. Flamboyan No. 3, Srono',          'status_kerja' => 'Aktif',      'jenis_sim' => 'B2'],
            ['nama_driver' => 'Bambang Wijaya',    'no_telepon' => '081234567005', 'alamat' => 'Jl. Dahlia No. 17, Rogojampi',        'status_kerja' => 'Aktif',      'jenis_sim' => 'B1 Umum'],
            ['nama_driver' => 'Eko Prasetyo',      'no_telepon' => '081234567006', 'alamat' => 'Jl. Anggrek No. 22, Muncar',          'status_kerja' => 'Aktif',      'jenis_sim' => 'B2'],
            ['nama_driver' => 'Dwi Hartono',       'no_telepon' => '081234567007', 'alamat' => 'Jl. Cempaka No. 9, Cluring',          'status_kerja' => 'Tidak Aktif','jenis_sim' => 'B1'],
        ];

        $drivers = [];
        foreach ($driverData as $d) {
            $drivers[] = drivers::create($d);
        }

        // ── Kendaraan (dipasangkan dengan driver) ─────────────
        $kendaraanData = [
            [
                'jenis_kendaraan'      => 'Truk Besar',
                'nomor_polisi'         => 'P 1234 AB',
                'merk'                 => 'Hino',
                'model'                => 'Ranger FM260JD',
                'tahun_pembuatan'      => 2019,
                'kapasitas_muatan_ton' => 8.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 0, // Slamet Riyadi
            ],
            [
                'jenis_kendaraan'      => 'Truk Besar',
                'nomor_polisi'         => 'P 2345 CD',
                'merk'                 => 'Mitsubishi',
                'model'                => 'Fuso Fighter FN527',
                'tahun_pembuatan'      => 2020,
                'kapasitas_muatan_ton' => 10.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 1, // Suparno
            ],
            [
                'jenis_kendaraan'      => 'Truk Sedang',
                'nomor_polisi'         => 'P 3456 EF',
                'merk'                 => 'Mitsubishi',
                'model'                => 'Colt Diesel FE84G',
                'tahun_pembuatan'      => 2021,
                'kapasitas_muatan_ton' => 5.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 2, // Agus Santoso
            ],
            [
                'jenis_kendaraan'      => 'Truk Besar',
                'nomor_polisi'         => 'P 4567 GH',
                'merk'                 => 'Isuzu',
                'model'                => 'Giga FVR34',
                'tahun_pembuatan'      => 2018,
                'kapasitas_muatan_ton' => 12.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 3, // Heru Purnomo
            ],
            [
                'jenis_kendaraan'      => 'Pickup',
                'nomor_polisi'         => 'P 5678 IJ',
                'merk'                 => 'Toyota',
                'model'                => 'Hilux Single Cab',
                'tahun_pembuatan'      => 2022,
                'kapasitas_muatan_ton' => 1.50,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 4, // Bambang Wijaya
            ],
            [
                'jenis_kendaraan'      => 'Truk Sedang',
                'nomor_polisi'         => 'P 6789 KL',
                'merk'                 => 'Hino',
                'model'                => 'Dutro 130 HD',
                'tahun_pembuatan'      => 2020,
                'kapasitas_muatan_ton' => 6.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => 5, // Eko Prasetyo
            ],
            [
                'jenis_kendaraan'      => 'Pickup',
                'nomor_polisi'         => 'P 7890 MN',
                'merk'                 => 'Daihatsu',
                'model'                => 'Gran Max Pick Up',
                'tahun_pembuatan'      => 2023,
                'kapasitas_muatan_ton' => 1.00,
                'status_kendaraan'     => 'Tersedia',
                'driver_idx'           => null, // belum ada driver tetap
            ],
        ];

        foreach ($kendaraanData as $k) {
            $driverIdx = $k['driver_idx'];
            unset($k['driver_idx']);
            $k['driver_id'] = $driverIdx !== null ? $drivers[$driverIdx]->id : null;
            kendaraans::create($k);
        }
    }
}
