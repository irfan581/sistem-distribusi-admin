<?php

namespace Tests\Feature\Driver;

use App\Models\drivers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class KelolaAkunDriverAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['manage_armada', 'view_armada']): User
    {
        return User::create([
            'name'        => 'Admin Uji',
            'email'       => 'admin@uji.test',
            'password'    => 'rahasia123',
            'is_admin'    => true,
            'permissions' => $permissions,
        ]);
    }

    private function sebagaiAdmin(array $permissions = ['manage_armada', 'view_armada']): self
    {
        $token = $this->admin($permissions)->createToken('admin')->plainTextToken;

        return $this->sebagaiToken($token);
    }

    /**
     * Laravel tidak melupakan guard yang sudah ter-resolve di antara dua request
     * dalam satu test, sehingga request berikutnya bisa keliru dianggap sebagai
     * pengguna sebelumnya. Semua pergantian identitas lewat sini.
     */
    private function sebagaiToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    private function buatDriver(array $override = []): drivers
    {
        return drivers::create(array_merge([
            'kode_driver'  => 'DRV-001',
            'nama_driver'  => 'Sukardi Wijaya',
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
        ], $override));
    }

    public function test_driver_baru_langsung_mendapat_kode(): void
    {
        // kode_driver kini NOT NULL, jadi endpoint tambah driver harus
        // membuatkannya sendiri — form admin tidak memintanya.
        $response = $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/drivers', [
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000001',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ]);

        $response->assertCreated()->assertJsonPath('data.kode_driver', 'DRV-001');

        // Akun aplikasinya belum hidup sampai admin mengaktifkan.
        $this->assertFalse((bool) drivers::first()->mobile_aktif);
        $this->assertNull(drivers::first()->password);
    }

    public function test_kode_driver_baru_tidak_bentrok_dengan_yang_sudah_ada(): void
    {
        $this->buatDriver(['kode_driver' => 'DRV-007']);

        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/drivers', [
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000001',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ])->assertCreated()->assertJsonPath('data.kode_driver', 'DRV-008');
    }

    public function test_aktifkan_akun_mengembalikan_password_sementara(): void
    {
        $driver = $this->buatDriver();

        $response = $this->sebagaiAdmin()
            ->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/aktifkan")
            ->assertOk()
            ->assertJsonPath('data.kode_driver', 'DRV-001');

        $password = $response->json('data.password_sementara');
        $this->assertSame(8, strlen($password));

        $driver->refresh();
        $this->assertTrue($driver->mobile_aktif);
        $this->assertTrue($driver->harus_ganti_password);
        $this->assertTrue(Hash::check($password, $driver->password));

        // Driver bisa langsung login dengan password itu.
        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => $password,
        ])->assertOk()->assertJsonPath('data.harus_ganti_password', true);
    }

    public function test_aktifkan_akun_driver_yang_sudah_berhenti_ditolak(): void
    {
        $driver = $this->buatDriver(['status_kerja' => 'Tidak Aktif']);

        $this->sebagaiAdmin()
            ->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/aktifkan")
            ->assertStatus(422);

        $this->assertFalse((bool) $driver->fresh()->mobile_aktif);
    }

    public function test_reset_password_mencabut_sesi_driver(): void
    {
        $driver = $this->buatDriver(['mobile_aktif' => true, 'password' => 'lama12345']);
        $tokenDriver = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->sebagaiAdmin()
            ->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/reset-password")
            ->assertOk()
            ->assertJsonStructure(['data' => ['kode_driver', 'password_sementara']]);

        $this->sebagaiToken($tokenDriver)
            ->getJson('/api/driver/profil')
            ->assertStatus(401);
    }

    public function test_nonaktifkan_akun_menutup_akses_aplikasi(): void
    {
        $driver = $this->buatDriver(['mobile_aktif' => true, 'password' => 'rahasia123']);
        $tokenDriver = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->sebagaiAdmin()
            ->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/nonaktifkan")
            ->assertOk();

        $this->assertFalse((bool) $driver->fresh()->mobile_aktif);

        $this->sebagaiToken($tokenDriver)
            ->getJson('/api/driver/profil')
            ->assertStatus(401);

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertStatus(403)->assertJsonPath('code', 'AKUN_BELUM_AKTIF');
    }

    public function test_mengubah_status_kerja_jadi_tidak_aktif_mencabut_sesi(): void
    {
        $driver = $this->buatDriver(['mobile_aktif' => true, 'password' => 'rahasia123']);
        $tokenDriver = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->sebagaiAdmin()->putJson("/api/admin/pengiriman/drivers/{$driver->id}", [
            'nama_driver'  => $driver->nama_driver,
            'no_telepon'   => $driver->no_telepon,
            'alamat'       => $driver->alamat,
            'status_kerja' => 'Tidak Aktif',
        ])->assertOk();

        $this->sebagaiToken($tokenDriver)
            ->getJson('/api/driver/profil')
            ->assertStatus(401);
    }

    public function test_admin_tanpa_izin_manage_armada_ditolak(): void
    {
        $driver = $this->buatDriver();

        $this->sebagaiAdmin(['view_armada'])
            ->postJson("/api/admin/pengiriman/drivers/{$driver->id}/akun/aktifkan")
            ->assertStatus(403);
    }

    public function test_password_driver_tidak_pernah_ikut_di_daftar(): void
    {
        $this->buatDriver(['mobile_aktif' => true, 'password' => 'rahasia123']);

        $response = $this->sebagaiAdmin()->getJson('/api/admin/pengiriman/drivers')->assertOk();

        $this->assertArrayNotHasKey('password', $response->json('data.0'));
        $this->assertArrayNotHasKey('device_id', $response->json('data.0'));
        $this->assertSame('DRV-001', $response->json('data.0.kode_driver'));
    }
}
