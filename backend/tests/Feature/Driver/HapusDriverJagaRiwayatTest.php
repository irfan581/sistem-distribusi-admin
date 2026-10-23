<?php

namespace Tests\Feature\Driver;

use App\Models\DaftarMuat;
use App\Models\drivers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menghapus driver ikut menghapus rit dan foto bukti lewat ON DELETE CASCADE,
 * jadi driver yang sudah punya riwayat hanya boleh dinonaktifkan.
 */
class HapusDriverJagaRiwayatTest extends TestCase
{
    use RefreshDatabase;

    private function sebagaiAdmin(): self
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@uji.test', 'password' => 'rahasia123',
            'is_admin' => true, 'permissions' => ['manage_armada', 'view_armada'],
        ]);
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('admin')->plainTextToken);
    }

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
            'device_id'    => 'hp-sopir',
            'fcm_token'    => 'token-fcm',
        ]);
    }

    public function test_driver_dengan_riwayat_pengiriman_tidak_bisa_dihapus(): void
    {
        $driver = $this->buatDriver();
        $muat = DaftarMuat::create([
            'driver_id' => $driver->id, 'tanggal_muatan' => now()->toDateString(),
            'nomor_rit' => 1, 'total_muatan_ton' => 5,
        ]);

        $this->sebagaiAdmin()->deleteJson("/api/admin/pengiriman/drivers/{$driver->id}")
            ->assertStatus(422);

        $this->assertModelExists($driver);
        $this->assertModelExists($muat);
    }

    public function test_driver_tanpa_riwayat_tetap_bisa_dihapus(): void
    {
        $driver = $this->buatDriver();

        $this->sebagaiAdmin()->deleteJson("/api/admin/pengiriman/drivers/{$driver->id}")
            ->assertOk();

        $this->assertModelMissing($driver);
    }

    public function test_nonaktifkan_akun_mengosongkan_data_perangkat(): void
    {
        $driver = $this->buatDriver();
        $driver->createToken('driver_auth_token');

        $this->sebagaiAdmin()->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/nonaktifkan")
            ->assertOk();

        $driver->refresh();
        $this->assertFalse((bool) $driver->mobile_aktif);
        $this->assertNull($driver->device_id);
        $this->assertNull($driver->fcm_token);
        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_status_tidak_aktif_mengosongkan_data_perangkat(): void
    {
        $driver = $this->buatDriver();

        $this->sebagaiAdmin()->putJson("/api/admin/pengiriman/drivers/{$driver->id}", [
            'nama_driver'  => $driver->nama_driver,
            'no_telepon'   => $driver->no_telepon,
            'alamat'       => $driver->alamat,
            'status_kerja' => 'Tidak Aktif',
        ])->assertOk();

        $driver->refresh();
        $this->assertNull($driver->device_id);
        $this->assertNull($driver->fcm_token);
    }
}
