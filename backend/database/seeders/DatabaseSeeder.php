<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Seed roles (upsert agar aman dijalankan berulang)
        $roles = [
            ['id' => 1, 'nama' => 'owner',    'label' => 'Owner',    'parent_id' => null, 'level' => 0],
            ['id' => 2, 'nama' => 'boss',     'label' => 'Boss',     'parent_id' => null, 'level' => 1],
            ['id' => 3, 'nama' => 'admin',    'label' => 'Admin',    'parent_id' => null, 'level' => 2],
            ['id' => 4, 'nama' => 'hrd',      'label' => 'HRD',      'parent_id' => 3,    'level' => 3],
            ['id' => 5, 'nama' => 'spv',      'label' => 'SPV',      'parent_id' => 3,    'level' => 3],
            ['id' => 6, 'nama' => 'keuangan', 'label' => 'Keuangan', 'parent_id' => 3,    'level' => 3],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }

        // Developer / Owner — akun developer (full access)
        User::updateOrCreate(
            ['email' => 'owner@example.com'],
            [
                'name'     => 'Irfan Fajar Hidayat',
                'password' => bcrypt('password'),
                'role_id'  => 1, // owner
                'is_admin' => true,
            ]
        );

        // Boss — pemilik perusahaan (full access sama seperti owner)
        User::updateOrCreate(
            ['email' => 'boss@example.com'],
            [
                'name'     => 'Pimpinan TMK',
                'password' => bcrypt('password'),
                'role_id'  => 2, // boss
                'is_admin' => true,
            ]
        );

        // Admin operasional
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Admin TMK',
                'password' => bcrypt('password'),
                'role_id'  => 3, // admin
                'is_admin' => true,
            ]
        );

        // HRD
        User::updateOrCreate(
            ['email' => 'hrd@example.com'],
            [
                'name'     => 'HRD TMK',
                'password' => bcrypt('password'),
                'role_id'  => 4,
                'is_admin' => true,
            ]
        );

        // Keuangan
        User::updateOrCreate(
            ['email' => 'keuangan@example.com'],
            [
                'name'     => 'Keuangan TMK',
                'password' => bcrypt('password'),
                'role_id'  => 6,
                'is_admin' => true,
            ]
        );

        $this->command->info('✓ Roles dan admin accounts berhasil di-seed.');
        $this->call(DummyDataSeeder::class);
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Owner (Developer)', 'owner@example.com',    'password'],
                ['Boss (Pemilik)',    'boss@example.com',     'password'],
                ['Admin',             'admin@example.com',    'password'],
                ['HRD',               'hrd@example.com',      'password'],
                ['Keuangan',          'keuangan@example.com', 'password'],
            ]
        );
    }
}
