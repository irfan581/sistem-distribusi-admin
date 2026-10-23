<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // ─── PRESENSIS ────────────────────────────────────────────
        Schema::table('presensis', function (Blueprint $table) {
            if (!$this->indexExists('presensis', 'idx_presensis_sales_tanggal')) {
                $table->index(['sales_id', 'tanggal'], 'idx_presensis_sales_tanggal');
            }
            if (!$this->indexExists('presensis', 'idx_presensis_tanggal_desc')) {
                $table->index(['tanggal', 'id'], 'idx_presensis_tanggal_desc');
            }
        });

        // ─── KUNJUNGAN TOKOS ──────────────────────────────────────
        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            if (!$this->indexExists('kunjungan_tokos', 'idx_kunjungan_sales_created')) {
                $table->index(['sales_id', 'created_at'], 'idx_kunjungan_sales_created');
            }
            if (!$this->indexExists('kunjungan_tokos', 'idx_kunjungan_sales_nama')) {
                $table->index(['sales_id', 'nama_toko'], 'idx_kunjungan_sales_nama');
            }
        });

        // ─── SALES ────────────────────────────────────────────────
        Schema::table('sales', function (Blueprint $table) {
            if (!$this->indexExists('sales', 'idx_sales_nik')) {
                $table->index('nik', 'idx_sales_nik');
            }
            if (!$this->indexExists('sales', 'idx_sales_is_active')) {
                $table->index('is_active', 'idx_sales_is_active');
            }
        });

        // ─── IZINS ────────────────────────────────────────────────
        Schema::table('izins', function (Blueprint $table) {
            if (!$this->indexExists('izins', 'idx_izins_sales_status')) {
                $table->index(['sales_id', 'status'], 'idx_izins_sales_status');
            }
            if (!$this->indexExists('izins', 'idx_izins_status')) {
                $table->index('status', 'idx_izins_status');
            }
        });

        // Tabel ini baru dibuat oleh migrasi 2026_05_12_131716. Pada database
        // produksi ia sudah ada sejak awal, tapi pada migrate:fresh urutan
        // kronologisnya membuat tabel ini belum lahir di titik ini.
        if (Schema::hasTable('personal_access_tokens')) {
            Schema::table('personal_access_tokens', function (Blueprint $table) {
                if (!$this->indexExists('personal_access_tokens', 'idx_tokens_tokenable')) {
                    $table->index(['tokenable_type', 'tokenable_id'], 'idx_tokens_tokenable');
                }
            });
        }
    }

    public function down(): void
    {
        // Menggunakan logika pengecekan manual karena dropIndexIfExists tidak ada di Laravel

        Schema::table('presensis', function (Blueprint $table) {
            if ($this->indexExists('presensis', 'idx_presensis_sales_tanggal')) {
                $table->dropIndex('idx_presensis_sales_tanggal');
            }
            if ($this->indexExists('presensis', 'idx_presensis_tanggal_desc')) {
                $table->dropIndex('idx_presensis_tanggal_desc');
            }
        });

        Schema::table('kunjungan_tokos', function (Blueprint $table) {
            if ($this->indexExists('kunjungan_tokos', 'idx_kunjungan_sales_created')) {
                $table->dropIndex('idx_kunjungan_sales_created');
            }
            if ($this->indexExists('kunjungan_tokos', 'idx_kunjungan_sales_nama')) {
                $table->dropIndex('idx_kunjungan_sales_nama');
            }
        });

        Schema::table('sales', function (Blueprint $table) {
            if ($this->indexExists('sales', 'idx_sales_nik')) {
                $table->dropIndex('idx_sales_nik');
            }
            if ($this->indexExists('sales', 'idx_sales_is_active')) {
                $table->dropIndex('idx_sales_is_active');
            }
        });

        Schema::table('izins', function (Blueprint $table) {
            if ($this->indexExists('izins', 'idx_izins_sales_status')) {
                $table->dropIndex('idx_izins_sales_status');
            }
            if ($this->indexExists('izins', 'idx_izins_status')) {
                $table->dropIndex('idx_izins_status');
            }
        });

        Schema::table('personal_access_tokens', function (Blueprint $table) {
            if ($this->indexExists('personal_access_tokens', 'idx_tokens_tokenable')) {
                $table->dropIndex('idx_tokens_tokenable');
            }
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        // Tambahkan pengecekan tabel ada atau tidak agar tidak crash saat migrate:fresh
        if (!Schema::hasTable($table)) {
            return false;
        }

        // Introspeksi lewat schema builder, bukan "SHOW INDEX" mentah, supaya
        // migrasi ini juga jalan di SQLite yang dipakai test suite.
        return collect(Schema::getIndexes($table))
            ->pluck('name')
            ->contains($indexName);
    }
};
