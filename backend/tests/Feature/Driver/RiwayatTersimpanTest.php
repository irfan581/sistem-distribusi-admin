<?php

namespace Tests\Feature\Driver;

use App\Models\BongkarFoto;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Riwayat kiriman harus tetap tersimpan: tidak dibatasi 90 hari di aplikasi sopir,
 * dan rit yang sudah dikerjakan sopir tidak bisa dihapus admin.
 */
class RiwayatTersimpanTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = drivers::create([
            'kode_driver'  => 'DRV-001',
            'nama_driver'  => 'Sukardi Wijaya',
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);
    }

    private function buatRit(string $tanggal, array $override = []): DaftarMuat
    {
        $muat = DaftarMuat::create(array_merge([
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => $tanggal,
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
        ], $override));

        DaftarMuatItem::create([
            'daftar_muat_id'    => $muat->id,
            'urutan'            => 1,
            'lokasi_pengiriman' => 'Toko Tujuan 1',
            'jenis_muatan'      => 'non_subsidi',
            'kode_area'         => 'BJN',
        ]);

        return $muat->refresh();
    }

    private function sebagaiAdmin(): self
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => 'admin@uji.test', 'password' => 'rahasia123',
            'is_admin' => true, 'permissions' => ['view_pengiriman', 'delete_pengiriman', 'muat_subsidi', 'muat_non_subsidi'],
        ]);
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('admin')->plainTextToken);
    }

    public function test_riwayat_sopir_memuat_rit_yang_lebih_tua_dari_90_hari(): void
    {
        $lama = $this->buatRit(now()->subDays(200)->toDateString(), ['status_perjalanan' => 'Selesai']);
        $baru = $this->buatRit(now()->subDays(3)->toDateString(), ['nomor_rit' => 2, 'status_perjalanan' => 'Selesai']);
        $token = $this->driver->createToken('driver_auth_token')->plainTextToken;

        $res = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/driver/tugas/riwayat')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 2);

        $this->assertEqualsCanonicalizing(
            [$lama->id, $baru->id],
            collect($res->json('data.rit'))->pluck('id')->all(),
        );
    }

    public function test_rit_yang_sudah_selesai_tidak_bisa_dihapus(): void
    {
        $muat = $this->buatRit(now()->toDateString(), ['status_perjalanan' => 'Selesai']);

        $this->sebagaiAdmin()->deleteJson("/api/admin/pengiriman/daftar-muat/{$muat->id}")
            ->assertStatus(422);

        $this->assertModelExists($muat);
    }

    public function test_rit_menunggu_yang_sudah_ada_foto_bukti_tidak_bisa_dihapus(): void
    {
        $muat = $this->buatRit(now()->toDateString());
        $item = $muat->items()->first();
        BongkarFoto::create([
            'daftar_muat_id'      => $muat->id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $this->driver->id,
            'urutan_snapshot'     => 1,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'bongkar/uji.jpg',
            'client_uuid'         => (string) Str::uuid(),
            'status_proses'       => 'siap',
        ]);

        $this->sebagaiAdmin()->deleteJson("/api/admin/pengiriman/daftar-muat/{$muat->id}")
            ->assertStatus(422);

        $this->assertModelExists($muat);
    }

    public function test_rencana_yang_belum_dikerjakan_tetap_bisa_dihapus(): void
    {
        $muat = $this->buatRit(now()->toDateString());

        $this->sebagaiAdmin()->deleteJson("/api/admin/pengiriman/daftar-muat/{$muat->id}")
            ->assertOk();

        $this->assertModelMissing($muat);
    }
}
