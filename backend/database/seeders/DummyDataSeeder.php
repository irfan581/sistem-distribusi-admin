<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Mulai seeding dummy data...');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        $this->seedSales();
        $this->seedTokos();
        $this->seedPresensi();
        $this->seedKunjungan();
        $this->seedIzin();
        $this->seedChatGroups();
        $this->seedChatMessages();
        $this->seedNotifications();
        $this->seedVisitTargets();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('✅ Semua dummy data berhasil di-seed!');
    }

    // ─────────────────────────────────────────────────────────────
    // 1. SALES (20 sales)
    // ─────────────────────────────────────────────────────────────
    private function seedSales(): void
    {
        $this->command->info('  → Seeding sales...');

        $areas = ['Jakarta Utara', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Pusat',
                  'Bekasi', 'Tangerang', 'Depok', 'Bogor', 'Cikarang'];

        $names = [
            'Ahmad Fauzi', 'Budi Santoso', 'Citra Dewi', 'Dian Permata', 'Eko Prasetyo',
            'Fitri Handayani', 'Galih Wicaksono', 'Hendra Kusuma', 'Indah Lestari', 'Joko Widodo',
            'Kartika Sari', 'Luthfi Hakim', 'Maya Anggraini', 'Nanda Putra', 'Oktaviani Rini',
            'Pandu Setiawan', 'Qori Rahayu', 'Rizky Firmansyah', 'Siti Nurhaliza', 'Taufik Hidayat',
        ];

        foreach ($names as $i => $name) {
            DB::table('sales')->updateOrInsert(
                ['nik' => str_pad($i + 1, 6, '0', STR_PAD_LEFT)],
                [
                    'nama'             => $name,
                    'no_hp'            => '08' . rand(100000000, 999999999),
                    'area'             => $areas[$i % count($areas)],
                    'alamat'           => 'Jl. ' . fake()->streetName() . ' No.' . rand(1, 99),
                    'password'         => Hash::make('password'),
                    'is_active'        => true,
                    'is_spv'           => $i < 3, // 3 SPV
                    'target_kunjungan' => rand(5, 10),
                    'created_at'       => now()->subDays(rand(30, 180)),
                    'updated_at'       => now(),
                ]
            );
        }

        $this->command->info('     ✓ ' . count($names) . ' sales');
    }

    // ─────────────────────────────────────────────────────────────
    // 2. TOKOS (200 toko, ~10 per sales)
    // ─────────────────────────────────────────────────────────────
    private function seedTokos(): void
    {
        $this->command->info('  → Seeding tokos...');

        $salesIds = DB::table('sales')->pluck('id')->toArray();
        $wilayahs = ['Jakarta Utara', 'Jakarta Selatan', 'Jakarta Barat', 'Jakarta Timur', 'Jakarta Pusat',
                     'Bekasi', 'Tangerang', 'Depok', 'Bogor', 'Cikarang'];

        $prefixes  = ['Toko', 'Warung', 'Minimarket', 'Kios', 'Toko Swalayan'];
        $tokoNames = [];
        $count     = 0;

        foreach ($salesIds as $salesId) {
            for ($j = 0; $j < 10; $j++) {
                $wilayah  = $wilayahs[array_rand($wilayahs)];
                $prefix   = $prefixes[array_rand($prefixes)];
                $namaKios = $prefix . ' ' . fake()->lastName() . ' ' . rand(1, 99);

                // Hindari duplicate nama_kios + wilayah
                $key = $namaKios . '|' . $wilayah;
                if (isset($tokoNames[$key])) {
                    $namaKios .= ' (' . $salesId . ')';
                    $key = $namaKios . '|' . $wilayah;
                }
                $tokoNames[$key] = true;

                DB::table('tokos')->updateOrInsert(
                    ['nama_kios' => $namaKios, 'wilayah' => $wilayah],
                    [
                        'sales_id'   => $salesId,
                        'is_new'     => rand(0, 3) === 0,
                        'created_at' => now()->subDays(rand(10, 120)),
                        'updated_at' => now(),
                    ]
                );
                $count++;
            }
        }

        $this->command->info("     ✓ {$count} tokos");
    }

    // ─────────────────────────────────────────────────────────────
    // 3. PRESENSI (300 records, 90 hari terakhir)
    // ─────────────────────────────────────────────────────────────
    private function seedPresensi(): void
    {
        $this->command->info('  → Seeding presensi (300 records)...');

        $salesIds = DB::table('sales')->pluck('id')->toArray();
        $statuses = ['tepat_waktu', 'tepat_waktu', 'tepat_waktu', 'terlambat', 'terlambat'];
        $locations = [
            ['Jakarta Utara', -6.1200, 106.8500],
            ['Jakarta Selatan', -6.2600, 106.8100],
            ['Jakarta Barat', -6.1700, 106.7600],
            ['Bekasi', -6.2400, 107.0000],
            ['Tangerang', -6.1700, 106.6300],
        ];

        $inserted = 0;
        $days     = 90;
        $target   = 300;

        while ($inserted < $target) {
            $salesId = $salesIds[array_rand($salesIds)];
            $daysAgo = rand(0, $days - 1);
            $tanggal = now()->subDays($daysAgo)->format('Y-m-d');

            // Skip jika sudah ada presensi sales ini di tanggal ini
            $exists = DB::table('presensis')
                ->where('sales_id', $salesId)
                ->where('tanggal', $tanggal)
                ->exists();
            if ($exists) continue;

            $status   = $statuses[array_rand($statuses)];
            $jamMasuk = $status === 'tepat_waktu'
                ? Carbon::parse($tanggal . ' 07:' . str_pad(rand(0, 30), 2, '0', STR_PAD_LEFT) . ':00')
                : Carbon::parse($tanggal . ' 08:' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00');

            $hasPulang = rand(0, 4) > 0; // 80% sudah pulang
            $jamPulang = $hasPulang
                ? $jamMasuk->copy()->addHours(rand(7, 10))->addMinutes(rand(0, 59))
                : null;

            $loc = $locations[array_rand($locations)];
            $lat = $loc[1] + (rand(-100, 100) / 10000);
            $lng = $loc[2] + (rand(-100, 100) / 10000);

            DB::table('presensis')->insert([
                'sales_id'             => $salesId,
                'tanggal'              => $tanggal,
                'jam_masuk'            => $jamMasuk->format('H:i:s'),
                'jam_perangkat_masuk'  => $jamMasuk->format('H:i:s'),
                'status'               => $status,
                'foto_masuk'           => 'dummy/foto_masuk.jpg',
                'location_masuk'       => $loc[0],
                'jam_pulang'           => $jamPulang?->format('H:i:s'),
                'jam_perangkat_pulang' => $jamPulang?->format('H:i:s'),
                'foto_pulang'          => $hasPulang ? 'dummy/foto_pulang.jpg' : null,
                'location_pulang'      => $hasPulang ? $loc[0] : null,
                'keterangan'           => null,
                'is_suspicious'        => rand(0, 9) === 0,
                'suspicious_reason'    => null,
                'created_at'           => $jamMasuk,
                'updated_at'           => $jamPulang ?? $jamMasuk,
            ]);
            $inserted++;
        }

        $this->command->info("     ✓ {$inserted} presensi");
    }

    // ─────────────────────────────────────────────────────────────
    // 4. KUNJUNGAN TOKO (400 records)
    // ─────────────────────────────────────────────────────────────
    private function seedKunjungan(): void
    {
        $this->command->info('  → Seeding kunjungan (400 records)...');

        $salesIds  = DB::table('sales')->pluck('id')->toArray();
        $tokos     = DB::table('tokos')->get(['id', 'nama_kios', 'wilayah']);
        $keterangan = [
            'Kunjungan rutin bulanan',
            'Penagihan pembayaran',
            'Presentasi produk baru',
            'Follow up pesanan',
            'Survey lokasi baru',
            'Pengiriman sampel produk',
            'Negosiasi kontrak',
            'Maintenance relasi',
            null, null, // beberapa tanpa keterangan
        ];

        $jakartaCoords = [
            [-6.1200, 106.8500], [-6.2600, 106.8100], [-6.1700, 106.7600],
            [-6.2400, 107.0000], [-6.1700, 106.6300], [-6.1800, 106.8300],
        ];

        $inserted = 0;
        while ($inserted < 400) {
            $salesId   = $salesIds[array_rand($salesIds)];
            $toko      = $tokos[array_rand($tokos->toArray())];
            $daysAgo   = rand(0, 89);
            $createdAt = now()->subDays($daysAgo)->setTime(rand(8, 17), rand(0, 59), 0);
            $checkoutAt = rand(0, 4) > 0
                ? $createdAt->copy()->addMinutes(rand(15, 90))
                : null;

            $coord = $jakartaCoords[array_rand($jakartaCoords)];
            $lat   = $coord[0] + (rand(-500, 500) / 10000);
            $lng   = $coord[1] + (rand(-500, 500) / 10000);

            DB::table('kunjungan_tokos')->insert([
                'sales_id'         => $salesId,
                'toko_id'          => $toko->id,
                'nama_toko'        => $toko->nama_kios,
                'location'         => $toko->wilayah,
                'lat'              => $lat,
                'lng'              => $lng,
                'foto_kunjungan'   => 'dummy/foto_kunjungan.jpg',
                'keterangan'       => $keterangan[array_rand($keterangan)] ?? 'Kunjungan rutin',
                'is_suspicious'    => rand(0, 14) === 0,
                'suspicious_reason'=> null,
                'archived_at'      => $daysAgo > 0 ? $createdAt->copy()->addHours(rand(2, 8)) : null,
                'checkout_at'      => $checkoutAt,
                'created_at'       => $createdAt,
                'updated_at'       => $checkoutAt ?? $createdAt,
            ]);
            $inserted++;
        }

        $this->command->info("     ✓ {$inserted} kunjungan");
    }

    // ─────────────────────────────────────────────────────────────
    // 5. IZIN (50 records)
    // ─────────────────────────────────────────────────────────────
    private function seedIzin(): void
    {
        $this->command->info('  → Seeding izin (50 records)...');

        $salesIds    = DB::table('sales')->pluck('id')->toArray();
        $jenisIzins  = ['sakit', 'sakit', 'izin', 'izin', 'cuti'];
        $statuses    = ['pending', 'disetujui', 'disetujui', 'disetujui', 'ditolak'];
        $keterangans = [
            'Sakit demam dan flu',
            'Keperluan keluarga mendadak',
            'Cuti tahunan',
            'Menghadiri pernikahan saudara',
            'Sakit kepala parah',
            'Urusan administrasi',
            'Acara duka keluarga',
            'Periksa ke dokter',
            'Dinas ke luar kota',
            'Keperluan pribadi',
        ];

        for ($i = 0; $i < 50; $i++) {
            $salesId     = $salesIds[array_rand($salesIds)];
            $tanggal     = now()->subDays(rand(1, 89));
            $durasi      = rand(1, 4);
            $sampaiTgl   = $tanggal->copy()->addDays($durasi - 1);
            $status      = $statuses[array_rand($statuses)];

            DB::table('izins')->insert([
                'sales_id'       => $salesId,
                'tanggal'        => $tanggal->format('Y-m-d'),
                'sampai_tanggal' => $sampaiTgl->format('Y-m-d'),
                'jenis_izin'     => $jenisIzins[array_rand($jenisIzins)],
                'keterangan'     => $keterangans[array_rand($keterangans)] ?? 'Keperluan pribadi',
                'bukti_foto'     => null,
                'status'         => $status,
                'alasan_tolak'   => $status === 'ditolak' ? 'Tidak ada pengganti yang tersedia' : null,
                'created_at'     => $tanggal->subDay(),
                'updated_at'     => now()->subDays(rand(0, 5)),
            ]);
        }

        $this->command->info('     ✓ 50 izin');
    }

    // ─────────────────────────────────────────────────────────────
    // 6. CHAT GROUPS
    // ─────────────────────────────────────────────────────────────
    private function seedChatGroups(): void
    {
        $this->command->info('  → Seeding chat groups...');

        $adminId   = DB::table('users')->where('email', 'admin@example.com')->value('id');
        $salesIds  = DB::table('sales')->limit(10)->pluck('id')->toArray();

        $groups = [
            ['nama' => 'Tim Sales Jakarta', 'is_broadcast' => false],
            ['nama' => 'Koordinasi HRD', 'is_broadcast' => false],
            ['nama' => 'Broadcast Pengumuman', 'is_broadcast' => true],
            ['nama' => 'Tim Sales Bekasi', 'is_broadcast' => false],
        ];

        foreach ($groups as $g) {
            $groupId = DB::table('chat_groups')->insertGetId([
                'nama'         => $g['nama'],
                'creator_type' => 'App\\Models\\User',
                'creator_id'   => $adminId,
                'is_broadcast' => $g['is_broadcast'],
                'created_at'   => now()->subDays(rand(10, 30)),
                'updated_at'   => now(),
            ]);

            // Add admin as member
            DB::table('chat_group_members')->insert([
                'group_id'    => $groupId,
                'member_type' => 'App\\Models\\User',
                'member_id'   => $adminId,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);

            // Add sales as members
            foreach (array_slice($salesIds, 0, rand(3, 7)) as $salesId) {
                DB::table('chat_group_members')->insert([
                    'group_id'    => $groupId,
                    'member_type' => 'App\\Models\\Sales',
                    'member_id'   => $salesId,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        $this->command->info('     ✓ ' . count($groups) . ' chat groups');
    }

    // ─────────────────────────────────────────────────────────────
    // 7. CHAT MESSAGES (100 pesan)
    // ─────────────────────────────────────────────────────────────
    private function seedChatMessages(): void
    {
        $this->command->info('  → Seeding chat messages...');

        $adminId  = DB::table('users')->where('email', 'admin@example.com')->value('id');
        $salesIds = DB::table('sales')->limit(8)->pluck('id')->toArray();
        $groupIds = DB::table('chat_groups')->pluck('id')->toArray();

        $dmMessages = [
            'Halo, ada yang bisa dibantu?',
            'Laporan kunjungan sudah saya kirim',
            'Baik, saya akan segera ke lokasi',
            'Target bulan ini sudah tercapai',
            'Ada kendala di lokasi pelanggan',
            'Mohon approval izin saya hari ini',
            'Terima kasih atas bantuannya',
            'Saya sedang dalam perjalanan ke toko',
            'Pelanggan minta diskon tambahan',
            'Stok di toko sudah hampir habis',
        ];

        $groupMessages = [
            'Selamat pagi semua!',
            'Jangan lupa update laporan harian',
            'Meeting online jam 2 siang ya',
            'Target minggu ini harus tercapai',
            'Ada yang butuh bantuan di area Bekasi?',
            'Pengumuman: libur nasional besok',
            'Mohon perhatian untuk semua sales',
            'Laporan bulan ini sudah bagus!',
        ];

        $count = 0;

        // DM messages antara admin dan sales
        foreach ($salesIds as $salesId) {
            for ($i = 0; $i < rand(5, 10); $i++) {
                $isFromAdmin = rand(0, 1);
                $createdAt   = now()->subHours(rand(1, 168));

                DB::table('chat_messages')->insert([
                    'sender_id'     => $isFromAdmin ? $adminId : $salesId,
                    'sender_type'   => $isFromAdmin ? 'App\\Models\\User' : 'App\\Models\\Sales',
                    'receiver_id'   => $isFromAdmin ? $salesId : $adminId,
                    'receiver_type' => $isFromAdmin ? 'App\\Models\\Sales' : 'App\\Models\\User',
                    'type'          => 'dm',
                    'message'       => $dmMessages[array_rand($dmMessages)],
                    'message_type'  => 'text',
                    'group_id'      => null,
                    'is_deleted'    => false,
                    'is_edited'     => false,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
                ]);
                $count++;
            }
        }

        // Group messages
        foreach ($groupIds as $groupId) {
            for ($i = 0; $i < rand(5, 15); $i++) {
                $isAdmin   = rand(0, 2) > 0;
                $senderId  = $isAdmin ? $adminId : $salesIds[array_rand($salesIds)];
                $senderType = $isAdmin ? 'App\\Models\\User' : 'App\\Models\\Sales';
                $createdAt  = now()->subHours(rand(1, 72));

                DB::table('chat_messages')->insert([
                    'sender_id'     => $senderId,
                    'sender_type'   => $senderType,
                    'receiver_id'   => 0,
                    'receiver_type' => 'admin',
                    'type'          => 'group',
                    'message'       => $groupMessages[array_rand($groupMessages)],
                    'message_type'  => 'text',
                    'group_id'      => $groupId,
                    'is_deleted'    => false,
                    'is_edited'     => false,
                    'created_at'    => $createdAt,
                    'updated_at'    => $createdAt,
                ]);
                $count++;
            }
        }

        $this->command->info("     ✓ {$count} chat messages");
    }

    // ─────────────────────────────────────────────────────────────
    // 8. NOTIFICATIONS (30 notifikasi)
    // ─────────────────────────────────────────────────────────────
    private function seedNotifications(): void
    {
        $this->command->info('  → Seeding notifications...');

        $users   = DB::table('users')->get(['id']);
        $salesIds = DB::table('sales')->limit(10)->pluck('id')->toArray();

        $messages = [
            ['title' => 'Izin Disetujui', 'body' => 'Pengajuan izin Anda telah disetujui.', 'type' => 'izin_approved'],
            ['title' => 'Target Kunjungan', 'body' => 'Target kunjungan Anda telah diperbarui.', 'type' => 'visit_target'],
            ['title' => 'Absensi Mencurigakan', 'body' => 'Terdeteksi lokasi absensi tidak sesuai.', 'type' => 'suspicious'],
            ['title' => 'Laporan Baru', 'body' => 'Laporan kunjungan baru telah masuk.', 'type' => 'report'],
            ['title' => 'Pengumuman', 'body' => 'Rapat koordinasi dijadwalkan besok pagi.', 'type' => 'announcement'],
        ];

        $count = 0;

        // Notifikasi untuk admin users
        foreach ($users as $user) {
            for ($i = 0; $i < rand(3, 6); $i++) {
                $msg       = $messages[array_rand($messages)];
                $createdAt = now()->subHours(rand(1, 240));
                $isRead    = rand(0, 2) > 0;

                DB::table('notifications')->insert([
                    'id'              => \Illuminate\Support\Str::uuid(),
                    'type'            => 'App\\Notifications\\' . ucfirst($msg['type']),
                    'notifiable_type' => 'App\\Models\\User',
                    'notifiable_id'   => $user->id,
                    'data'            => json_encode([
                        'type'    => $msg['type'],
                        'title'   => $msg['title'],
                        'message' => $msg['body'],
                    ]),
                    'read_at'    => $isRead ? $createdAt->copy()->addMinutes(rand(5, 60)) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $count++;
            }
        }

        // Notifikasi untuk sales
        foreach ($salesIds as $salesId) {
            for ($i = 0; $i < rand(2, 5); $i++) {
                $msg       = $messages[array_rand($messages)];
                $createdAt = now()->subHours(rand(1, 240));
                $isRead    = rand(0, 2) > 0;

                DB::table('notifications')->insert([
                    'id'              => \Illuminate\Support\Str::uuid(),
                    'type'            => 'App\\Notifications\\' . ucfirst($msg['type']),
                    'notifiable_type' => 'App\\Models\\Sales',
                    'notifiable_id'   => $salesId,
                    'data'            => json_encode([
                        'type'    => $msg['type'],
                        'title'   => $msg['title'],
                        'message' => $msg['body'],
                    ]),
                    'read_at'    => $isRead ? $createdAt->copy()->addMinutes(rand(5, 60)) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
                $count++;
            }
        }

        $this->command->info("     ✓ {$count} notifications");
    }

    // ─────────────────────────────────────────────────────────────
    // 9. VISIT TARGETS
    // ─────────────────────────────────────────────────────────────
    private function seedVisitTargets(): void
    {
        $this->command->info('  → Seeding visit targets...');

        $salesIds = DB::table('sales')->pluck('id')->toArray();
        $adminId  = DB::table('users')->where('email', 'admin@example.com')->value('id');
        $count    = 0;

        foreach ($salesIds as $salesId) {
            // Target aktif bulan ini
            DB::table('sales_visit_targets')->updateOrInsert(
                ['sales_id' => $salesId, 'is_active' => true],
                [
                    'target_per_day'  => rand(5, 10),
                    'period_type'     => 'monthly',
                    'period_label'    => now()->format('Y-m'),
                    'is_active'       => true,
                    'set_by'          => $adminId,
                    'effective_from'  => now()->startOfMonth()->format('Y-m-d'),
                    'effective_until' => now()->endOfMonth()->format('Y-m-d'),
                    'notes'           => null,
                    'created_at'      => now()->subDays(rand(1, 10)),
                    'updated_at'      => now(),
                ]
            );
            $count++;
        }

        $this->command->info("     ✓ {$count} visit targets");
    }
}
