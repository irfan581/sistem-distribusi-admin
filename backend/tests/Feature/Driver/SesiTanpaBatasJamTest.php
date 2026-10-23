<?php

namespace Tests\Feature\Driver;

use App\Models\drivers;
use App\Models\Sales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sopir cukup login sekali: tokennya tidak ikut batas 12 jam milik sales.
 * Sesi sopir hanya berakhir lewat logout, login di HP lain, reset password,
 * atau akun dinonaktifkan — semuanya mencabut token.
 */
class SesiTanpaBatasJamTest extends TestCase
{
    use RefreshDatabase;

    private function buatDriver(): drivers
    {
        return drivers::create([
            'kode_driver'  => 'DRV-001',
            'nama_driver'  => 'Sukardi Wijaya',
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);
    }

    public function test_token_driver_tetap_berlaku_setelah_lewat_12_jam(): void
    {
        $token = $this->buatDriver()->createToken('driver_auth_token')->plainTextToken;

        $this->travel(30)->days();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')
            ->assertOk();
    }

    public function test_token_sales_tetap_kedaluwarsa_setelah_12_jam(): void
    {
        $sales = Sales::create([
            'nik'       => '1234567890',
            'nama'      => 'Sales Uji',
            'no_hp'     => '081200000000',
            'area'      => 'Bojonegoro',
            'password'  => 'rahasia123',
            'is_active' => true,
        ]);
        $token = $sales->createToken('sales_auth_token')->plainTextToken;

        $this->travel(13)->hours();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile')
            ->assertUnauthorized();
    }

    public function test_token_driver_lama_tetap_tidak_bisa_masuk_endpoint_sales(): void
    {
        $token = $this->buatDriver()->createToken('driver_auth_token')->plainTextToken;

        $this->travel(30)->days();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile')
            ->assertStatus(403)
            ->assertJsonPath('code', 'WRONG_ACCOUNT_TYPE');
    }

    public function test_token_driver_dengan_expires_at_lewat_tetap_ditolak(): void
    {
        $token = $this->buatDriver()
            ->createToken('driver_auth_token', ['*'], now()->addDay())
            ->plainTextToken;

        $this->travel(2)->days();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')
            ->assertUnauthorized();
    }
}
