<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder demo untuk keperluan screenshot / presentasi.
 *
 * Hanya MENAMBAH data — tidak menghapus atau mengubah baris yang sudah ada.
 * Rentang id tiap tabel dicatat ke storage/app/demo_data_ranges.json supaya
 * bisa dibersihkan lagi lewat DemoDataRollbackSeeder.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *   php artisan db:seed --class=DemoDataRollbackSeeder
 */
class DemoDataSeeder extends Seeder
{
    public const MANIFEST = 'demo_data_ranges.json';

    /** @var array<string, array<int, array{from:int, to:int}>> */
    private array $ranges = [];

    public function run(): void
    {
        // Volume diatur lewat env supaya bisa dipakai top-up tanpa ubah kode:
        //   DEMO_MUAT=5000 DEMO_HARI=90 DEMO_ONLY=pengiriman php artisan db:seed --class=DemoDataSeeder
        $only = env('DEMO_ONLY', 'semua');

        $this->command->info('Menyiapkan data demo (hanya menambah, data lama tidak disentuh)…');
        $this->muatMuatanManifest();

        if ($only === 'semua' || $only === 'pengiriman') {
            $driverIds    = $this->seedDrivers((int) env('DEMO_DRIVER', 20));
            $kendaraanIds = $this->seedKendaraan((int) env('DEMO_ARMADA', 18), $driverIds);
            $checkerIds   = $this->seedChecker((int) env('DEMO_CHECKER', 6));

            $this->seedPengiriman(
                (int) env('DEMO_MUAT', 2500),
                (int) env('DEMO_HARI', 150),
                $driverIds,
                $kendaraanIds,
                $checkerIds,
            );
            $this->seedIzinDriver((int) env('DEMO_IZIN_DRIVER', 150), $driverIds);
        }

        if ($only === 'semua' || $only === 'sales') {
            $this->seedPresensi((int) env('DEMO_PRESENSI', 7500));
            $this->seedKunjungan((int) env('DEMO_KUNJUNGAN', 6000));
            $this->seedIzinSales((int) env('DEMO_IZIN', 450));
        }

        Storage::disk('local')->put(self::MANIFEST, json_encode($this->ranges, JSON_PRETTY_PRINT));

        $this->command->newLine();
        $this->command->info('✓ Selesai. Total tercatat untuk rollback:');
        $this->command->table(
            ['Tabel', 'Baris', 'Jumlah rentang id'],
            collect($this->ranges)
                ->map(fn ($daftar, $tabel) => [
                    $tabel,
                    collect($daftar)->sum(fn ($r) => $r['to'] - $r['from'] + 1),
                    count($daftar),
                ])
                ->values()->all()
        );
        $this->command->warn('Bersihkan: php artisan db:seed --class=DemoDataRollbackSeeder');
    }

    /**
     * Manifest dari run sebelumnya ikut dibawa, supaya seeder bisa
     * dijalankan berkali-kali (top-up) tanpa kehilangan jejak batch lama.
     */
    private function muatMuatanManifest(): void
    {
        if (! Storage::disk('local')->exists(self::MANIFEST)) {
            return;
        }

        $lama = json_decode(Storage::disk('local')->get(self::MANIFEST), true) ?: [];

        foreach ($lama as $tabel => $isi) {
            // Format lama menyimpan satu rentang per tabel, bukan daftar.
            $this->ranges[$tabel] = isset($isi['from']) ? [$isi] : $isi;
        }

        $jumlah = collect($this->ranges)->sum(fn ($d) => count($d));
        $this->command->line("  (melanjutkan manifest lama: {$jumlah} rentang)");
    }

    // ─────────────────────────────────────────────────────────────
    // Master data
    // ─────────────────────────────────────────────────────────────

    /** @return int[] semua driver aktif (lama + baru) */
    private function seedDrivers(int $jumlah): array
    {
        $depan = [
            'Joko', 'Marno', 'Tukiman', 'Bagus', 'Hendra', 'Sutrisno', 'Yanto', 'Kasmuri',
            'Rohmat', 'Sugeng', 'Darmaji', 'Feri', 'Waluyo', 'Iskandar', 'Ponimin', 'Sarwono',
            'Mulyadi', 'Teguh', 'Karyono', 'Basuki', 'Slamet', 'Warsito', 'Painem', 'Gunawan',
            'Sukardi', 'Riyanto', 'Purwadi', 'Sumarno', 'Hariyanto', 'Tarmuji',
        ];
        $belakang = [
            'Susilo', 'Setiawan', 'Gunawan', 'Prabowo', 'Hidayat', 'Riyanto', 'Kurnia',
            'Muda', 'Santoso', 'Rahmat', 'Wibowo', 'Saputra', 'Nugroho', 'Utomo', 'Pranoto',
            'Wijaya', 'Sanjaya', 'Permana', 'Firmansyah', 'Maulana',
        ];

        // Nama yang sudah ada di DB ikut dihindari, supaya run kedua tidak makmur.
        $terpakai = DB::table('drivers')->pluck('nama_driver')->flip();
        $urutHp   = (int) DB::table('drivers')->count();

        $rows = [];
        $coba = 0;
        while (count($rows) < $jumlah && $coba < $jumlah * 50) {
            $coba++;
            $nama = $depan[array_rand($depan)] . ' ' . $belakang[array_rand($belakang)];

            if ($terpakai->has($nama)) {
                continue;
            }
            $terpakai[$nama] = true;

            $urutHp++;
            $rows[] = [
                'nama_driver'    => $nama,
                'nama_panggilan' => explode(' ', $nama)[0],
                'no_telepon'     => '08123456' . str_pad((string) (7000 + $urutHp), 4, '0', STR_PAD_LEFT),
                'alamat'         => 'Jl. Demo No. ' . (10 + ($urutHp % 90)) . ', Ngawi',
                'status_kerja'   => 'Aktif',
                'jenis_sim'      => $urutHp % 3 === 0 ? 'B2' : 'B1 Umum',
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        $this->insertTercatat('drivers', $rows);

        return DB::table('drivers')->where('status_kerja', 'Aktif')->pluck('id')->all();
    }

    /** @param int[] $driverIds @return int[] */
    private function seedKendaraan(int $jumlah, array $driverIds): array
    {
        $armada = [
            ['ISUZU ELF MACAN HD', 'Isuzu', 'Elf NMR 71 HD', 6.0],
            ['ISUZU GIGA FVR', 'Isuzu', 'Giga FVR 34', 12.0],
            ['ISUZU ENGKEL 100 PS', 'Isuzu', 'Elf NLR 55', 4.0],
            ['HINO DUTRO 130 HD', 'Hino', 'Dutro 130 HD', 6.0],
            ['HINO RANGER FM 260', 'Hino', 'Ranger FM 260 JD', 10.0],
            ['MITSUBISHI FUSO FN 527', 'Mitsubishi', 'Fuso Fighter FN 527', 9.0],
            ['MITSUBISHI CDD LONG', 'Mitsubishi', 'Colt Diesel FE 84G', 5.0],
            ['ENGKEL MERAH', 'Isuzu', 'Elf NLR 55 BE', 3.5],
            ['TRONTON HINO FL', 'Hino', 'FL 235 JW', 20.0],
            ['ISUZU GIGA NMR EURO 4', 'Isuzu', 'Giga NMR 81 T', 8.0],
            ['MITSUBISHI FUSO TRONTON', 'Mitsubishi', 'Fuso FN 62 F', 18.0],
            ['HINO DUTRO 110 SDL', 'Hino', 'Dutro 110 SDL', 4.0],
            ['ISUZU ELF ENGKEL BOX', 'Isuzu', 'Elf NLR 71 T', 3.0],
            ['TOYOTA DYNA 130 HT', 'Toyota', 'Dyna 130 HT', 5.0],
            ['MITSUBISHI CANTER HD', 'Mitsubishi', 'Canter FE 74 HD', 6.0],
            ['ISUZU GIGA FVZ TRONTON', 'Isuzu', 'Giga FVZ 34', 16.0],
            ['HINO LOHAN SG', 'Hino', 'Lohan SG 260 J', 14.0],
            ['MITSUBISHI CANTER 125 PS', 'Mitsubishi', 'Canter FE 71', 4.5],
        ];

        $huruf = ['UT', 'BD', 'GE', 'UF', 'GG', 'OY', 'PA', 'KL', 'ZR', 'MN', 'TW', 'QA', 'RS', 'DV', 'XC', 'JH', 'LP', 'WE'];
        $kode  = ['AE', 'AD', 'AG', 'S', 'W', 'N'];

        // nomor_polisi bertipe UNIQUE — plat yang sudah ada harus dihindari.
        $terpakai = DB::table('kendaraans')->pluck('nomor_polisi')->flip();

        $rows = [];
        $coba = 0;
        while (count($rows) < $jumlah && $coba < $jumlah * 50) {
            $coba++;
            $plat = sprintf(
                '%s %04d %s',
                $kode[array_rand($kode)],
                random_int(1000, 9999),
                $huruf[array_rand($huruf)]
            );

            if ($terpakai->has($plat)) {
                continue;
            }
            $terpakai[$plat] = true;

            $i = count($rows);
            [$jenis, $merk, $model, $kapasitas] = $armada[$i % count($armada)];

            $rows[] = [
                'jenis_kendaraan'      => $jenis,
                'nomor_polisi'         => $plat,
                'merk'                 => $merk,
                'model'                => $model,
                'tahun_pembuatan'      => 2016 + ($i % 9),
                'kapasitas_muatan_ton' => $kapasitas,
                'driver_id'            => $driverIds[$i % count($driverIds)] ?? null,
                'status_kendaraan'     => 'Tersedia',
                'created_at'           => now(),
                'updated_at'           => now(),
            ];
        }

        $this->insertTercatat('kendaraans', $rows);

        return DB::table('kendaraans')->where('status_kendaraan', 'Tersedia')->pluck('id')->all();
    }

    /** @return int[] semua checker aktif (lama + baru) */
    private function seedChecker(int $jumlah): array
    {
        $nama = [
            'Andi Nugroho', 'Bayu Saputra', 'Catur Widodo', 'Dani Prakoso', 'Edi Santoso',
            'Fajar Ramadhan', 'Galih Pratama', 'Hendri Kusuma', 'Imam Syafii', 'Jefri Setiawan',
            'Krisna Aditya', 'Luthfi Hakim',
        ];

        $terpakai = DB::table('checkers')->pluck('nama')->flip();

        $rows = [];
        foreach ($nama as $n) {
            if (count($rows) >= $jumlah) {
                break;
            }
            if ($terpakai->has($n)) {
                continue;
            }
            $rows[] = ['nama' => $n, 'status' => 'aktif', 'created_at' => now(), 'updated_at' => now()];
        }

        $this->insertTercatat('checkers', $rows);

        return DB::table('checkers')->pluck('id')->all();
    }

    // ─────────────────────────────────────────────────────────────
    // Pengiriman — daftar muat + item tujuan
    // ─────────────────────────────────────────────────────────────

    /**
     * @param int[] $driverIds
     * @param int[] $kendaraanIds
     * @param int[] $checkerIds
     */
    private function seedPengiriman(int $jumlah, int $hari, array $driverIds, array $kendaraanIds, array $checkerIds): void
    {
        $this->command->info('  → Rencana muat (' . number_format($jumlah, 0, ',', '.') . ' baris / ' . $hari . ' hari)…');

        $tokoNonSubsidi = [
            ['UD SUMBER TANI', 'NGW'], ['TOKO MAKMUR JAYA', 'MDN'], ['UD TANI SUBUR', 'MGT'],
            ['CV BERKAH AGRO', 'NGJ'], ['TOKO SRI REJEKI', 'KDR'], ['UD PANEN RAYA', 'BLT'],
            ['TOKO AGRO MANDIRI', 'TLG'], ['UD TANI MULYA', 'TRG'], ['CV DWI TANI', 'PNR'],
            ['TOKO SUMBER REJEKI', 'WNG'], ['UD KARYA TANI', 'BJN'], ['TOKO BUMI SUBUR', 'TRT'],
            ['CV LUMBUNG PADI', 'J86'], ['UD SIDO MAKMUR', 'NGW'], ['TOKO TANI JAYA', 'MDN'],
            ['UD REJEKI AGUNG', 'KDR'], ['CV AGRO LESTARI', 'NGJ'], ['TOKO PANEN MAS', 'BLT'],
            ['UD SUBUR MAKMUR', 'MGT'], ['TOKO MITRA TANI', 'PNR'], ['CV SAWAH SUBUR', 'TLG'],
            ['UD HARAPAN TANI', 'WNG'], ['TOKO ANEKA TANI', 'TRT'], ['UD MITRA AGRO', 'BJN'],
            ['TOKO TANI SEJAHTERA', 'TTSJ'], ['UD GUDANG TANI', 'J86'], ['CV PUPUK BERKAH', 'TRG'],
            ['TOKO SARANA TANI', 'MDN'], ['UD MAJU TANI', 'NGW'], ['CV TANI NUSANTARA', 'KDR'],
        ];

        $kelompokTani = [
            ['POKTAN SUMBER MAKMUR', 'NGW'], ['POKTAN TANI RUKUN', 'MDN'], ['POKTAN SIDO DADI', 'MGT'],
            ['POKTAN NGUDI RAHAYU', 'NGJ'], ['POKTAN TANI MULYO', 'KDR'], ['POKTAN SUMBER REJEKI', 'BLT'],
            ['POKTAN MARGO UTOMO', 'TLG'], ['POKTAN TANI MAKMUR', 'PNR'], ['POKTAN SRI REJEKI', 'WNG'],
            ['POKTAN BUMI LESTARI', 'BJN'], ['POKTAN KARYA TANI', 'TRT'], ['POKTAN SIDO MULYO', 'J86'],
            ['POKTAN TANI BAHAGIA', 'TRG'], ['POKTAN REJEKI TANI', 'TTSJ'], ['POKTAN MEKAR SARI', 'NGW'],
        ];

        $hariIni     = Carbon::today();
        $tanggalAwal = $hariIni->copy()->subDays($hari);
        $totalHari   = $hari + 2; // + hari ini + besok
        $perHari     = (int) ceil($jumlah / $totalHari);

        $muatRows   = [];
        $rencanaKe  = 0;
        $ritCounter = [];

        // Tahap 1: insert semua daftar_muats dulu, supaya id-nya berurutan
        // dan item bisa dipetakan tanpa query balik per baris.
        for ($h = 0; $h < $totalHari && $rencanaKe < $jumlah; $h++) {
            $tanggal = $tanggalAwal->copy()->addDays($h);
            $lampau  = $tanggal->lt($hariIni);

            // Minggu lebih sepi.
            $jatah = $tanggal->isSunday() ? (int) ceil($perHari / 3) : $perHari;

            for ($n = 0; $n < $jatah && $rencanaKe < $jumlah; $n++) {
                $subsidi     = random_int(1, 100) <= 18;
                $punyaDriver = random_int(1, 100) <= 88;

                $driverId    = $punyaDriver ? $driverIds[array_rand($driverIds)] : null;
                $kendaraanId = $punyaDriver ? $kendaraanIds[array_rand($kendaraanIds)] : null;

                // Driver yang sama bisa jalan lebih dari sekali dalam sehari.
                $kunci = $tanggal->toDateString() . '-' . ($driverId ?? 'x' . $n);
                $ritCounter[$kunci] = ($ritCounter[$kunci] ?? 0) + 1;

                $muatRows[] = [
                    'kendaraan_id'     => $kendaraanId,
                    'driver_id'        => $driverId,
                    'tanggal_muatan'   => $tanggal->toDateString(),
                    'nomor_rit'        => min($ritCounter[$kunci], 3),
                    'jenis_muatan'     => $subsidi ? 'subsidi' : 'non_subsidi',
                    // Total dihitung per truk (bukan per toko) — 3,0 s/d 9,0 ton.
                    'total_muatan_ton' => random_int(30, 90) / 10,
                    'checker_id'       => $lampau ? $checkerIds[array_rand($checkerIds)] : null,
                    'created_at'       => $tanggal->copy()->subDay()->setTime(16, random_int(0, 59)),
                    'updated_at'       => $tanggal->copy()->setTime(7, random_int(0, 59)),
                ];
                $rencanaKe++;
            }
        }

        $muatIds = $this->insertTercatat('daftar_muats', $muatRows);

        // Tahap 2: item tujuan, dipetakan ke id yang baru saja terbentuk.
        $this->command->info('  → Tujuan pengiriman…');

        $itemRows = [];

        foreach ($muatRows as $offset => $muat) {
            $subsidi = $muat['jenis_muatan'] === 'subsidi';
            $sumber  = $subsidi ? $kelompokTani : $tokoNonSubsidi;
            $pilihan = (array) array_rand($sumber, random_int(2, 4));

            foreach ($pilihan as $urutan => $idx) {
                [$nama, $area] = $sumber[$idx];
                $itemRows[] = [
                    'daftar_muat_id'    => $muatIds[$offset],
                    'urutan'            => $urutan + 1,
                    // Tanpa kode area — resource yang menempelkannya saat ditampilkan.
                    'lokasi_pengiriman' => $nama,
                    'jenis_muatan'      => $muat['jenis_muatan'],
                    'kode_area'         => $area,
                    'total_muatan_ton'  => null,
                    'penerima'          => null,
                    'no_telepon_penerima' => null,
                    'catatan'           => null,
                    'created_at'        => $muat['created_at'],
                    'updated_at'        => $muat['updated_at'],
                ];
            }
        }

        $this->insertTercatat('daftar_muat_items', $itemRows);
    }

    /** @param int[] $driverIds */
    private function seedIzinDriver(int $jumlah, array $driverIds): void
    {
        $keterangan = ['Sakit', 'Cuti', 'Izin', 'Lainnya'];
        $catatan    = ['Demam', 'Acara keluarga', 'Servis kendaraan', 'Keperluan pribadi', null];

        // Unik per (driver, tanggal) — pasangan yang sudah ada ikut dihitung.
        $terpakai = DB::table('driver_izin')
            ->select('driver_id', 'tanggal')
            ->get()
            ->map(fn ($r) => $r->driver_id . '|' . Carbon::parse($r->tanggal)->toDateString())
            ->flip();

        $rows = [];
        $coba = 0;
        while (count($rows) < $jumlah && $coba < $jumlah * 20) {
            $coba++;
            $driverId = $driverIds[array_rand($driverIds)];
            $tanggal  = Carbon::today()->subDays(random_int(0, (int) env('DEMO_HARI', 150)));

            $kunci = $driverId . '|' . $tanggal->toDateString();
            if ($terpakai->has($kunci)) {
                continue;
            }
            $terpakai[$kunci] = true;

            $rows[] = [
                'driver_id'  => $driverId,
                'tanggal'    => $tanggal->toDateString(),
                'keterangan' => $keterangan[array_rand($keterangan)],
                'catatan'    => $catatan[array_rand($catatan)],
                'created_at' => $tanggal,
                'updated_at' => $tanggal,
            ];
        }

        $this->insertTercatat('driver_izin', $rows);
    }

    // ─────────────────────────────────────────────────────────────
    // Sales — presensi, kunjungan, izin
    // ─────────────────────────────────────────────────────────────

    private function seedPresensi(int $jumlah): void
    {
        $this->command->info('  → Presensi…');

        $salesIds = DB::table('sales')->pluck('id')->all();
        if (! $salesIds) {
            $this->command->warn('     dilewati — tabel sales kosong');

            return;
        }

        // Pasangan (sales, tanggal) yang sudah terpakai — kolomnya unik per hari.
        $terpakai = DB::table('presensis')
            ->select('sales_id', 'tanggal')
            ->get()
            ->map(fn ($r) => $r->sales_id . '|' . Carbon::parse($r->tanggal)->toDateString())
            ->flip();

        $lokasi = [
            ['Ngawi', -7.4050, 111.4460], ['Madiun', -7.6300, 111.5230],
            ['Magetan', -7.6460, 111.3290], ['Ponorogo', -7.8650, 111.4690],
            ['Nganjuk', -7.6050, 111.9030], ['Kediri', -7.8480, 112.0180],
            ['Bojonegoro', -7.1500, 111.8820], ['Tuban', -6.8970, 112.0650],
        ];
        $status = ['tepat_waktu', 'tepat_waktu', 'tepat_waktu', 'tepat_waktu', 'terlambat'];

        $rows  = [];
        $hari  = 270;
        $coba  = 0;
        $batas = $jumlah * 5;

        while (count($rows) < $jumlah && $coba < $batas) {
            $coba++;
            $salesId = $salesIds[array_rand($salesIds)];
            $tanggal = Carbon::today()->subDays(random_int(0, $hari - 1));

            if ($tanggal->isSunday()) {
                continue;
            }

            $kunci = $salesId . '|' . $tanggal->toDateString();
            if ($terpakai->has($kunci)) {
                continue;
            }
            $terpakai[$kunci] = true;

            $st       = $status[array_rand($status)];
            $jamMasuk = $st === 'tepat_waktu'
                ? $tanggal->copy()->setTime(7, random_int(0, 30))
                : $tanggal->copy()->setTime(8, random_int(0, 59));

            $pulang    = random_int(0, 4) > 0;
            $jamPulang = $pulang ? $jamMasuk->copy()->addHours(random_int(7, 10))->addMinutes(random_int(0, 59)) : null;
            $loc       = $lokasi[array_rand($lokasi)];

            $rows[] = [
                'sales_id'             => $salesId,
                'tanggal'              => $tanggal->toDateString(),
                'jam_masuk'            => $jamMasuk->format('H:i:s'),
                'jam_perangkat_masuk'  => $jamMasuk->format('H:i:s'),
                'status'               => $st,
                'foto_masuk'           => 'dummy/foto_masuk.jpg',
                'location_masuk'       => $loc[0],
                'jam_pulang'           => $jamPulang?->format('H:i:s'),
                'jam_perangkat_pulang' => $jamPulang?->format('H:i:s'),
                'foto_pulang'          => $pulang ? 'dummy/foto_pulang.jpg' : null,
                'location_pulang'      => $pulang ? $loc[0] : null,
                'keterangan'           => null,
                'is_suspicious'        => random_int(0, 19) === 0,
                'suspicious_reason'    => null,
                'created_at'           => $jamMasuk,
                'updated_at'           => $jamPulang ?? $jamMasuk,
            ];
        }

        $this->insertTercatat('presensis', $rows);
    }

    private function seedKunjungan(int $jumlah): void
    {
        $this->command->info('  → Kunjungan toko…');

        $salesIds = DB::table('sales')->pluck('id')->all();
        $tokos    = DB::table('tokos')->get(['id', 'nama_kios', 'wilayah']);

        if (! $salesIds || $tokos->isEmpty()) {
            $this->command->warn('     dilewati — sales/toko kosong');

            return;
        }

        $tokoArr    = $tokos->all();
        $keterangan = [
            'Kunjungan rutin bulanan', 'Penagihan pembayaran', 'Presentasi produk baru',
            'Follow up pesanan', 'Survey lokasi baru', 'Pengiriman sampel produk',
            'Negosiasi kontrak', 'Maintenance relasi', 'Cek stok di kios', null,
        ];

        $rows = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $toko    = $tokoArr[array_rand($tokoArr)];
            $hariLlu = random_int(0, 269);
            $dibuat  = Carbon::today()->subDays($hariLlu)->setTime(random_int(8, 17), random_int(0, 59));

            if ($dibuat->isSunday()) {
                continue;
            }

            $checkout = random_int(0, 4) > 0 ? $dibuat->copy()->addMinutes(random_int(15, 90)) : null;

            $rows[] = [
                'sales_id'          => $salesIds[array_rand($salesIds)],
                'toko_id'           => $toko->id,
                'nama_toko'         => $toko->nama_kios,
                'location'          => $toko->wilayah,
                'lat'               => -7.4050 + (random_int(-4000, 4000) / 10000),
                'lng'               => 111.4460 + (random_int(-4000, 4000) / 10000),
                'foto_kunjungan'    => 'dummy/foto_kunjungan.jpg',
                'keterangan'        => $keterangan[array_rand($keterangan)] ?? 'Kunjungan rutin',
                'is_suspicious'     => random_int(0, 19) === 0,
                'suspicious_reason' => null,
                'archived_at'       => $hariLlu > 0 ? $dibuat->copy()->addHours(random_int(2, 8)) : null,
                'checkout_at'       => $checkout,
                'created_at'        => $dibuat,
                'updated_at'        => $checkout ?? $dibuat,
            ];
        }

        $this->insertTercatat('kunjungan_tokos', $rows);
    }

    private function seedIzinSales(int $jumlah): void
    {
        $salesIds = DB::table('sales')->pluck('id')->all();
        if (! $salesIds) {
            return;
        }

        $jenis  = ['sakit', 'sakit', 'izin', 'izin', 'cuti'];
        $status = ['pending', 'disetujui', 'disetujui', 'disetujui', 'ditolak'];
        $alasan = [
            'Sakit demam dan flu', 'Keperluan keluarga mendadak', 'Cuti tahunan',
            'Menghadiri pernikahan saudara', 'Periksa ke dokter', 'Acara duka keluarga',
            'Urusan administrasi', 'Keperluan pribadi',
        ];

        $rows = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $tanggal = Carbon::today()->subDays(random_int(1, 269));
            $durasi  = random_int(1, 4);
            $st      = $status[array_rand($status)];

            $rows[] = [
                'sales_id'       => $salesIds[array_rand($salesIds)],
                'tanggal'        => $tanggal->toDateString(),
                'sampai_tanggal' => $tanggal->copy()->addDays($durasi - 1)->toDateString(),
                'jenis_izin'     => $jenis[array_rand($jenis)],
                'durasi_hari'    => $durasi,
                'keterangan'     => $alasan[array_rand($alasan)],
                'bukti_foto'     => null,
                'status'         => $st,
                'alasan_tolak'   => $st === 'ditolak' ? 'Tidak ada pengganti yang tersedia' : null,
                'created_at'     => $tanggal->copy()->subDay(),
                'updated_at'     => $tanggal,
            ];
        }

        $this->insertTercatat('izins', $rows);
    }

    // ─────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────

    /**
     * Insert massal sambil mencatat id yang benar-benar terbentuk,
     * supaya rollback bisa menghapus tepat baris ini saja.
     *
     * Id awal tidak bisa ditebak dari max(id)+1 — auto_increment bisa
     * jauh di depan kalau pernah ada baris yang dihapus.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return int[] id baru, urut sesuai urutan $rows
     */
    private function insertTercatat(string $tabel, array $rows): array
    {
        if (! $rows) {
            return [];
        }

        $sebelum = (int) DB::table($tabel)->max('id');

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($tabel)->insert($chunk);
        }

        // Urutan id = urutan insert, jadi bisa dipetakan balik ke $rows.
        $idBaru = DB::table($tabel)->where('id', '>', $sebelum)->orderBy('id')->pluck('id')->all();

        $this->ranges[$tabel][] = ['from' => $idBaru[0], 'to' => end($idBaru)];
        $this->command->line("     ✓ {$tabel}: " . count($idBaru));

        return $idBaru;
    }
}
