<?php

namespace Tests\Feature\Driver;

use App\Models\drivers;
use App\Services\Driver\DriverAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AkunDriverTest extends TestCase
{
    use RefreshDatabase;

    private function buatDriver(array $override = []): drivers
    {
        return drivers::create(array_merge([
            'kode_driver'  => 'DRV-001',
            'nama_driver'  => 'Sukardi Wijaya',
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ], $override));
    }

    public function test_kode_driver_dibuat_berurutan(): void
    {
        $service = app(DriverAuthService::class);

        $this->assertSame('DRV-001', $service->generateKode());

        $this->buatDriver(['kode_driver' => 'DRV-001']);
        $this->assertSame('DRV-002', $service->generateKode());
    }

    public function test_kode_melanjutkan_dari_nomor_tertinggi_bukan_jumlah_baris(): void
    {
        // Driver DRV-002 dihapus dari master. Kode berikutnya tetap DRV-004
        // supaya kode lama tidak pernah dipakai ulang orang berbeda.
        $this->buatDriver(['kode_driver' => 'DRV-001']);
        $this->buatDriver(['kode_driver' => 'DRV-003', 'no_telepon' => '081200000001']);

        $this->assertSame('DRV-004', app(DriverAuthService::class)->generateKode());
    }

    public function test_reset_password_mencabut_token_dan_menandai_wajib_ganti(): void
    {
        $driver = $this->buatDriver(['harus_ganti_password' => false]);
        $driver->createToken('driver_auth_token');

        $passwordBaru = app(DriverAuthService::class)->resetPassword($driver);

        $this->assertSame(8, strlen($passwordBaru));
        $this->assertTrue(Hash::check($passwordBaru, $driver->fresh()->password));
        $this->assertTrue($driver->fresh()->harus_ganti_password);
        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_password_sementara_tanpa_karakter_ambigu(): void
    {
        $driver = $this->buatDriver();

        for ($i = 0; $i < 30; $i++) {
            $password = app(DriverAuthService::class)->resetPassword($driver);
            $this->assertDoesNotMatchRegularExpression('/[0O1Il]/', $password);
        }
    }

    public function test_driver_bisa_ganti_password_sendiri(): void
    {
        $driver = $this->buatDriver(['harus_ganti_password' => true]);
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/driver/profil/ganti-password', [
                'password_lama'              => 'rahasia123',
                'password_baru'              => 'passwordbaru456',
                'password_baru_confirmation' => 'passwordbaru456',
            ])->assertOk();

        $driver->refresh();
        $this->assertTrue(Hash::check('passwordbaru456', $driver->password));
        $this->assertFalse($driver->harus_ganti_password);

        // Token yang sedang dipakai tetap hidup supaya driver tidak perlu login ulang.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/profil')->assertOk();
    }

    public function test_ganti_password_dengan_password_lama_salah_ditolak(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/driver/profil/ganti-password', [
                'password_lama'              => 'bukan ini',
                'password_baru'              => 'passwordbaru456',
                'password_baru_confirmation' => 'passwordbaru456',
            ])->assertStatus(422)->assertJsonPath('code', 'PASSWORD_LAMA_SALAH');
    }

    public function test_password_baru_harus_minimal_delapan_karakter(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/driver/profil/ganti-password', [
                'password_lama'              => 'rahasia123',
                'password_baru'              => 'pendek',
                'password_baru_confirmation' => 'pendek',
            ])->assertStatus(422);
    }

    public function test_logout_mencabut_token_yang_dipakai(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/driver/logout')->assertOk();

        $this->assertSame(0, $driver->tokens()->count());
    }

    public function test_fcm_token_bisa_diperbarui(): void
    {
        $driver = $this->buatDriver();
        $token = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/driver/profil/fcm-token', ['fcm_token' => 'token-fcm-baru'])
            ->assertOk();

        $this->assertSame('token-fcm-baru', $driver->fresh()->fcm_token);
    }
}
