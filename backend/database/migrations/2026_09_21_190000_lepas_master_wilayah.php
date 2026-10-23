<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melepas master wilayah berikut penandaan jauh/dekatnya.
 *
 * Fitur ini tidak pernah hidup di produksi: tabelnya nol baris, jadi setiap
 * kiriman selamanya berlabel "Jarak belum ditandai" dan halaman statistik
 * menampilkan kode area mentah alih-alih nama wilayah.
 *
 * Satu-satunya alasan tabel ini dibuat adalah supaya wilayah bisa ditandai
 * jauh atau dekat tanpa mengubah program. Begitu penandaan itu dilepas,
 * yang tersisa cuma empat belas nama kabupaten yang praktis tidak pernah
 * berubah — dan itu kembali ke dalam kode, di DaftarMuatController.
 *
 * `down()` mengembalikan tabel dan kolomnya persis seperti semula, tapi tidak
 * bisa mengembalikan isinya. Di produksi itu tidak masalah karena memang
 * kosong; di mesin lain yang terlanjur terisi, salin dulu sebelum menjalankan
 * migrasi ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Kolomnya dilepas lebih dulu supaya kunci asingnya tidak menahan
        // penghapusan tabel.
        if (Schema::hasColumn('tokos', 'wilayah_id')) {
            Schema::table('tokos', function (Blueprint $table) {
                $table->dropConstrainedForeignId('wilayah_id');
            });
        }

        Schema::dropIfExists('wilayahs');
    }

    public function down(): void
    {
        // Bentuknya disalin persis dari migrasi aslinya, termasuk panjang
        // kolom dan unique-nya, supaya rollback benar-benar mengembalikan
        // keadaan semula dan bukan sekadar tabel yang mirip.
        Schema::create('wilayahs', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->string('kode', 8)->nullable()->unique();
            $table->enum('kategori_jarak', ['dekat', 'jauh'])->nullable();
            $table->decimal('ongkos', 12, 2)->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::table('tokos', function (Blueprint $table) {
            $table->foreignId('wilayah_id')->nullable()->after('wilayah')
                ->constrained('wilayahs')->nullOnDelete();
        });
    }
};
