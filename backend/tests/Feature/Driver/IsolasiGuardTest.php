<?php

namespace Tests\Feature\Driver;

use App\Models\drivers;
use App\Models\Sales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menambah guard kedua ke auth:sanctum membuat token milik satu jenis akun bisa
 * sampai ke middleware milik jenis akun lain. Test ini menjaga agar keduanya
 * saling tolak — dan yang paling penting, saling tolak TANPA mencabut token.
 */
class IsolasiGuardTest extends TestCase
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

    private function buatSales(): Sales
    {
        return Sales::create([
            'nik'       => '1234567890',
            'nama'      => 'Sales Uji',
            'no_hp'     => '081200000000',
            'area'      => 'Bojonegoro',
            'password'  => 'rahasia123',
            'is_active' => true,
        ]);
    }

    public function test_token_driver_ditolak_di_endpoint_sales(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/profile')
            ->assertStatus(403)
            ->assertJsonPath('code', 'WRONG_ACCOUNT_TYPE');
    }

    public function test_token_driver_tidak_ikut_tercabut_saat_salah_pintu(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        // Inilah bug yang dicegah: CheckSalesActive dulu membaca $user->is_active
        // tanpa cek tipe, sehingga null pada model driver membuat seluruh token
        // driver dihapus dan driver ter-logout paksa tanpa sebab.
        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/profile');

        $this->assertSame(1, $driver->tokens()->count());

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')
            ->assertOk();
    }

    public function test_token_sales_ditolak_di_endpoint_driver(): void
    {
        $sales = $this->buatSales();
        $token = $sales->createToken('sales_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')
            ->assertStatus(403)
            ->assertJsonPath('code', 'WRONG_ACCOUNT_TYPE');

        $this->assertSame(1, $sales->tokens()->count());
    }

    public function test_token_driver_ditolak_di_endpoint_admin(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/pengiriman/drivers')
            ->assertStatus(403);
    }

    public function test_driver_yang_dinonaktifkan_kehilangan_tokennya(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $driver->forceFill(['mobile_aktif' => false])->save();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')
            ->assertStatus(403)
            ->assertJsonPath('code', 'ACCOUNT_INACTIVE');

        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_tanpa_token_ditolak(): void
    {
        $this->getJson('/api/driver/profil')->assertStatus(401);
    }
}
