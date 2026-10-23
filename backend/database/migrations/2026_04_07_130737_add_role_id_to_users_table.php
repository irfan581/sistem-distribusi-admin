<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom role_id (nullable dulu, supaya migrasi data aman)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')
                  ->nullable()
                  ->after('email')
                  ->constrained('roles')
                  ->nullOnDelete();
        });

        // 2. Migrasi data: mapping role string lama → role_id baru
        $roleMap = DB::table('roles')->pluck('id', 'nama')->toArray();

        if (!empty($roleMap)) {
            // Map role lama ke role baru
            $mapping = [
                'owner' => $roleMap['owner'] ?? null,
                'boss'  => $roleMap['boss']  ?? null,
                'admin' => $roleMap['admin'] ?? null,
            ];

            foreach ($mapping as $oldRole => $newRoleId) {
                if ($newRoleId) {
                    DB::table('users')
                        ->where('role', $oldRole)
                        ->update(['role_id' => $newRoleId]);
                }
            }

            // User yang belum ke-mapping → set ke owner (default)
            $ownerId = $roleMap['owner'] ?? null;
            if ($ownerId) {
                DB::table('users')
                    ->whereNull('role_id')
                    ->update(['role_id' => $ownerId]);
            }
        }

        // 3. Hapus kolom role string lama
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        // Kembalikan kolom role string
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('owner')->after('email');
        });

        // Migrasi balik: role_id → role string
        $roles = DB::table('roles')->pluck('nama', 'id')->toArray();
        $users = DB::table('users')->whereNotNull('role_id')->get(['id', 'role_id']);

        foreach ($users as $user) {
            $roleName = $roles[$user->role_id] ?? 'owner';
            // Sub-role (hrd/spv/keuangan) dikembalikan jadi 'admin'
            if (in_array($roleName, ['hrd', 'spv', 'keuangan'])) {
                $roleName = 'admin';
            }
            DB::table('users')->where('id', $user->id)->update(['role' => $roleName]);
        }

        // Hapus kolom role_id
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }
};