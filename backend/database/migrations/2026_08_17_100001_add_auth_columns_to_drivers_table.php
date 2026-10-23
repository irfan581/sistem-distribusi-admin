<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            // Kode login dibuat sistem (DRV-001). Nullable dulu supaya baris lama
            // tidak menolak migrasi; diisi lalu dijadikan NOT NULL oleh migrasi
            // 2026_08_17_100002.
            $table->string('kode_driver', 20)->nullable()->unique()->after('id');
            $table->string('password')->nullable()->after('kode_driver');

            // Kelayakan login = status_kerja 'Aktif' AND mobile_aktif AND password ada.
            // Sengaja tidak menambah kolom is_active baru agar tidak ada dua sumber
            // kebenaran dengan status_kerja yang sudah dipakai admin.
            $table->boolean('mobile_aktif')->default(false)->after('status_kerja');
            $table->boolean('harus_ganti_password')->default(true)->after('mobile_aktif');

            $table->string('device_id')->nullable()->after('harus_ganti_password');
            $table->string('fcm_token')->nullable()->after('device_id');
            $table->timestamp('last_login_at')->nullable()->after('fcm_token');

            $table->index('mobile_aktif', 'drivers_mobile_aktif_idx');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropIndex('drivers_mobile_aktif_idx');
            $table->dropUnique(['kode_driver']);
            $table->dropColumn([
                'kode_driver',
                'password',
                'mobile_aktif',
                'harus_ganti_password',
                'device_id',
                'fcm_token',
                'last_login_at',
            ]);
        });
    }
};
