<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progres per tujuan. Status rit saja tidak cukup: satu rit bisa punya beberapa
 * toko, dan driver harus bisa menandai tujuan kedua selesai sementara yang
 * ketiga belum disinggahi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->enum('status_bongkar', ['Menunggu', 'Tiba', 'Selesai', 'Gagal'])
                ->default('Menunggu')
                ->after('total_muatan_ton');

            $table->timestamp('tiba_at')->nullable()->after('status_bongkar');
            $table->timestamp('bongkar_selesai_at')->nullable()->after('tiba_at');
            $table->string('catatan_driver', 255)->nullable()->after('bongkar_selesai_at');

            $table->index(['daftar_muat_id', 'urutan'], 'daftar_muat_items_muat_urutan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daftar_muat_items', function (Blueprint $table) {
            $table->dropIndex('daftar_muat_items_muat_urutan_idx');
            $table->dropColumn(['status_bongkar', 'tiba_at', 'bongkar_selesai_at', 'catatan_driver']);
        });
    }
};
