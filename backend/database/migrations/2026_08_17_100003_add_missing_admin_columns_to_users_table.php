<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyusulkan tiga kolom users yang selama ini hanya ada di database berjalan
 * karena ditambahkan manual, tanpa migrasi. Akibatnya deploy dari nol
 * menghasilkan tabel users tanpa is_admin, sedangkan User::isAdminUser() dan
 * middleware EnsureIsAdmin bergantung padanya — tidak ada admin yang bisa masuk.
 *
 * Semua dijaga hasColumn supaya aman dijalankan di database yang sudah punya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('password');
            }

            if (!Schema::hasColumn('users', 'allowed_ip')) {
                $table->string('allowed_ip', 45)->nullable()->after('is_admin');
            }

            if (!Schema::hasColumn('users', 'session_version')) {
                $table->unsignedInteger('session_version')->default(1)->after('allowed_ip');
            }
        });
    }

    public function down(): void
    {
        // Sengaja tidak menghapus apa pun: kolom-kolom ini sudah dipakai data
        // produksi jauh sebelum migrasi ini ada.
    }
};
