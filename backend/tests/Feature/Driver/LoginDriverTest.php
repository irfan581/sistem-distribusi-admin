<?php

namespace Tests\Feature\Driver;

use App\Models\drivers;
use App\Models\kendaraans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginDriverTest extends TestCase
{
    use RefreshDatabase;

    private function buatDriver(array $override = []): drivers
    {
        return drivers::create(array_merge([
            'kode_driver'          => 'DRV-001',
            'nama_driver'          => 'Sukardi Wijaya',
            'no_telepon'           => '081234567890',
            'alamat'               => 'Bojonegoro',
            'status_kerja'         => 'Aktif',
            'jenis_sim'            => 'B2 Umum',
            'password'             => 'rahasia123',
            'mobile_aktif'         => true,
            'harus_ganti_password' => false,
        ], $override));
    }

    public function test_driver_aktif_bisa_login(): void
    {
        $this->buatDriver();

        $response = $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
            'device_id'   => 'hp-uji',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver.kode_driver', 'DRV-001')
            ->assertJsonPath('data.harus_ganti_password', false)
            ->assertJsonStructure(['data' => ['token', 'driver' => ['id', 'nama_driver']]]);

        // Password tidak boleh ikut terkirim ke aplikasi.
        $response->assertJsonMissingPath('data.driver.password');

        $this->assertSame('hp-uji', drivers::first()->device_id);
        $this->assertNotNull(drivers::first()->last_login_at);
    }

    public function test_login_ikut_mengirim_kendaraan_tetap(): void
    {
        $driver = $this->buatDriver();
        kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'AD 8020 OY',
            'merk'                 => 'Isuzu',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 9,
            'status_kendaraan'     => 'Tersedia',
            'driver_id'            => $driver->id,
        ]);

        // Aplikasi menampilkan profil dari respons login; tanpa ini baris Kendaraan kosong.
        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertOk()
            ->assertJsonPath('data.driver.kendaraan.0.nomor_polisi', 'AD 8020 OY')
            ->assertJsonPath('data.driver.kendaraan.0.kapasitas_ton', 9);
    }

    public function test_kode_driver_tidak_peka_huruf_besar_kecil(): void
    {
        $this->buatDriver();

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'drv-001',
            'password'    => 'rahasia123',
        ])->assertOk();
    }

    public function test_password_salah_ditolak(): void
    {
        $this->buatDriver();

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'salah',
        ])->assertStatus(401)->assertJsonPath('code', 'KREDENSIAL_SALAH');
    }

    public function test_kode_tidak_dikenal_ditolak_dengan_pesan_yang_sama(): void
    {
        $this->buatDriver();

        // Pesannya sengaja disamakan dengan password salah supaya kode driver
        // yang valid tidak bisa ditebak dari respons.
        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-999',
            'password'    => 'apa saja',
        ])->assertStatus(401)->assertJsonPath('code', 'KREDENSIAL_SALAH');
    }

    public function test_driver_yang_sudah_berhenti_ditolak(): void
    {
        $this->buatDriver(['status_kerja' => 'Tidak Aktif']);

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertStatus(403)->assertJsonPath('code', 'DRIVER_NONAKTIF');
    }

    public function test_akun_yang_belum_diaktifkan_admin_ditolak(): void
    {
        $this->buatDriver(['mobile_aktif' => false]);

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertStatus(403)->assertJsonPath('code', 'AKUN_BELUM_AKTIF');
    }

    public function test_driver_tanpa_password_ditolak(): void
    {
        $this->buatDriver(['password' => null]);

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'apa saja',
        ])->assertStatus(401)->assertJsonPath('code', 'KREDENSIAL_SALAH');
    }

    public function test_login_baru_mencabut_token_lama(): void
    {
        $driver = $this->buatDriver();
        $tokenLama = $driver->createToken('driver_auth_token')->plainTextToken;

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertOk();

        // Satu akun satu HP: token dari perangkat sebelumnya harus mati.
        $this->withHeader('Authorization', "Bearer {$tokenLama}")
            ->getJson('/api/driver/profil')
            ->assertStatus(401);
    }

    public function test_ganti_password_wajib_ditandai_setelah_reset(): void
    {
        $this->buatDriver(['harus_ganti_password' => true]);

        $this->postJson('/api/driver/login', [
            'kode_driver' => 'DRV-001',
            'password'    => 'rahasia123',
        ])->assertOk()->assertJsonPath('data.harus_ganti_password', true);
    }
}
