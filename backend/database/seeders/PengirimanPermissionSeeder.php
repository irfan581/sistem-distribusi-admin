<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Assign permission pengiriman ke masing-masing role.
 * Jalankan: php artisan db:seed --class=PengirimanPermissionSeeder
 */
class PengirimanPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $allPengiriman = [
            'view_pengiriman', 'create_pengiriman', 'edit_pengiriman', 'delete_pengiriman',
            'muat_subsidi', 'muat_non_subsidi',
            'view_armada', 'manage_armada',
        ];

        $rolePermissions = [
            // developer & superadmin — full akses
            'developer'  => $allPengiriman,
            'superadmin' => $allPengiriman,

            // owner — semua akses pengiriman
            'owner' => $allPengiriman,

            // admin operasional — semua akses pengiriman
            'admin' => $allPengiriman,

            // SPV — lihat saja, tidak bisa kelola
            'spv' => ['view_pengiriman', 'muat_subsidi', 'muat_non_subsidi', 'view_armada'],

            // HRD — lihat data pengiriman & armada (keperluan laporan)
            'hrd' => ['view_pengiriman', 'muat_subsidi', 'muat_non_subsidi', 'view_armada'],

            // Keuangan — lihat daftar muat untuk rekap biaya
            'keuangan' => ['view_pengiriman', 'muat_subsidi', 'muat_non_subsidi'],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('nama', $roleName)->first();
            if (!$role) continue;

            // Gabungkan dengan permissions yang sudah ada (hindari duplikat)
            $existing  = $role->permissions ?? [];
            $merged    = array_unique(array_merge($existing, $permissions));
            $role->permissions = array_values($merged);
            $role->save();

            $this->command->info("✓ Role [{$roleName}] — " . count($permissions) . ' pengiriman permissions assigned.');
        }

        // Patch user yang punya personal permissions (mengoverride role permissions).
        // User-user ini tidak akan mendapat permission dari role secara otomatis,
        // jadi kita merge langsung ke kolom permissions-nya.
        $usersWithPersonalPerms = User::whereNotNull('permissions')
            ->where('permissions', '!=', '[]')
            ->where('permissions', '!=', 'null')
            ->get();

        foreach ($usersWithPersonalPerms as $user) {
            $userPerms = $user->permissions ?? [];
            if (empty($userPerms)) continue;

            // Tentukan permission yang seharusnya dimiliki berdasarkan role
            $role = $user->role;
            if (!$role) continue;

            $rolePerms = $role->permissions ?? [];
            if (empty($rolePerms)) continue;

            // Hanya tambahkan permissions pengiriman dari role ke user
            $pengirimanToAdd = array_intersect($rolePerms, $allPengiriman);
            if (empty($pengirimanToAdd)) continue;

            $merged = array_values(array_unique(array_merge($userPerms, $pengirimanToAdd)));
            $user->permissions = $merged;
            $user->save();

            $this->command->info("✓ User [{$user->name}] — patched personal permissions dengan " . count($pengirimanToAdd) . ' pengiriman permissions.');
        }
    }
}
