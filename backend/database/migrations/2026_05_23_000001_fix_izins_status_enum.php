<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fix enum 'status' di tabel izins.
 *
 * Migration lama hanya punya: ['pending', 'disetujui', 'ditolak']
 * Kode controller pakai   : 'approved' dan 'rejected'
 *
 * Solusi: ubah kolom ke VARCHAR(20) agar fleksibel dan migrate data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Ubah dari ENUM ke VARCHAR agar tidak perlu alter enum tiap tambah nilai
        DB::statement("ALTER TABLE izins MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'");

        // Migrate nilai lama ke nilai baru yang seragam
        DB::table('izins')->where('status', 'disetujui')->update(['status' => 'approved']);
        DB::table('izins')->where('status', 'ditolak')->update(['status' => 'rejected']);
    }

    public function down(): void
    {
        // Kembalikan ke enum (data 'approved'/'rejected' jadi 'disetujui'/'ditolak')
        DB::table('izins')->where('status', 'approved')->update(['status' => 'disetujui']);
        DB::table('izins')->where('status', 'rejected')->update(['status' => 'ditolak']);

        DB::statement("ALTER TABLE izins MODIFY COLUMN status ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'");
    }
};
