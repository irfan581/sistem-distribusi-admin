<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bongkar_fotos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daftar_muat_id')->constrained('daftar_muats')->cascadeOnDelete();
            $table->foreignId('daftar_muat_item_id')->nullable()->constrained('daftar_muat_items')->nullOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();

            // Bukti bongkar tidak boleh kehilangan konteks kalau admin mengedit
            // rit dan tujuannya berubah. Foto tetap terikat ke rit lewat
            // daftar_muat_id, dan dua kolom ini mengawetkan tujuan aslinya.
            $table->unsignedTinyInteger('urutan_snapshot');
            $table->string('lokasi_snapshot', 255);

            $table->enum('jenis', ['bongkar', 'surat_jalan', 'kendala'])->default('bongkar');
            $table->string('foto', 255);

            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->float('accuracy')->nullable();
            $table->string('alamat', 255)->nullable();
            $table->boolean('is_mock')->default(false);

            // Waktu menurut HP driver. Jam HP bisa diubah, jadi ini disimpan
            // sebagai klaim; created_at tetap jadi waktu server.
            $table->timestamp('diambil_pada')->nullable();
            $table->string('catatan', 255)->nullable();

            // Dibuat di HP sebelum kirim. Menjadikan unggahan idempoten sehingga
            // percobaan ulang dari antrian offline tidak menghasilkan foto ganda.
            $table->char('client_uuid', 36)->unique();

            $table->enum('status_proses', ['temp', 'siap', 'gagal'])->default('temp');

            $table->timestamps();

            $table->index(['daftar_muat_id', 'daftar_muat_item_id'], 'bongkar_fotos_muat_item_idx');
            $table->index(['driver_id', 'created_at'], 'bongkar_fotos_driver_waktu_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bongkar_fotos');
    }
};
