<?php

namespace Tests\Feature\Driver;

use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingkasanDriverTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private string $token;

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

        $this->token = $this->driver->createToken('driver_auth_token')->plainTextToken;
    }

    private function api(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->token}");
    }

    /**
     * @param  array<int, array{0: string|null, 1: string}>  $tujuan  [kode_area, status_bongkar]
     */
    private function buatRit(array $tujuan, array $override = []): DaftarMuat
    {
        $muat = DaftarMuat::create(array_merge([
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
        ], $override));

        foreach ($tujuan as $i => [$kode, $status]) {
            DaftarMuatItem::create([
                'daftar_muat_id'    => $muat->id,
                'urutan'            => $i + 1,
                'lokasi_pengiriman' => "Toko {$i}",
                'kode_area'         => $kode,
                'jenis_muatan'      => 'non_subsidi',
                'status_bongkar'    => $status,
            ]);
        }

        return $muat->refresh();
    }

    public function test_ringkasan_menghitung_rit_tujuan_dan_muatan(): void
    {
        $this->buatRit([['NGJ', 'Selesai'], ['NGW', 'Bermasalah']], [
            'status_perjalanan' => 'Selesai',
            'total_muatan_ton'  => 6,
        ]);
        $this->buatRit([['NGJ', 'Menunggu']], [
            'nomor_rit'        => 2,
            'total_muatan_ton' => 4,
        ]);

        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.rit.total', 2)
            ->assertJsonPath('data.rit.selesai', 1)
            ->assertJsonPath('data.rit.berjalan', 1)
            ->assertJsonPath('data.tujuan.total', 3)
            ->assertJsonPath('data.tujuan.selesai', 1)
            ->assertJsonPath('data.tujuan.bermasalah', 1)
            ->assertJsonPath('data.muatan_ton', 10);
    }

    public function test_rit_batal_tidak_ikut_dihitung_muatannya(): void
    {
        $this->buatRit([['NGJ', 'Menunggu']], [
            'status_perjalanan' => 'Batal',
            'total_muatan_ton'  => 7,
        ]);

        // Rit yang dibatalkan tidak pernah diangkut. Ikut menjumlah muatan
        // membuat driver terlihat membawa barang yang tidak pernah naik truk.
        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.rit.batal', 1)
            ->assertJsonPath('data.muatan_ton', 0);
    }

    public function test_ringkasan_memisahkan_subsidi_dan_non_subsidi(): void
    {
        $this->buatRit([['NGJ', 'Selesai']], ['jenis_muatan' => 'subsidi']);
        $this->buatRit([['NGW', 'Selesai']], ['nomor_rit' => 2]);

        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.jenis.subsidi', 1)
            ->assertJsonPath('data.jenis.non_subsidi', 1);
    }

    public function test_sebaran_wilayah_diurutkan_terbanyak_dulu(): void
    {
        $this->buatRit([
            ['NGJ', 'Selesai'],
            ['NGJ', 'Selesai'],
            ['NGW', 'Selesai'],
        ]);

        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.wilayah.0.kode', 'NGJ')
            ->assertJsonPath('data.wilayah.0.jumlah', 2)
            ->assertJsonPath('data.wilayah.1.kode', 'NGW')
            ->assertJsonPath('data.wilayah.1.jumlah', 1);
    }

    public function test_tujuan_tanpa_kode_area_tetap_dihitung(): void
    {
        // Seperempat tujuan di data produksi kode areanya kosong. Kalau baris
        // itu dibuang, jumlah wilayah tidak akan cocok dengan jumlah tujuan dan
        // driver bingung angkanya ke mana.
        $this->buatRit([['NGJ', 'Selesai'], [null, 'Selesai'], ['', 'Selesai']]);

        $respons = $this->api()->getJson('/api/driver/tugas/ringkasan')->assertOk();

        $wilayah = collect($respons->json('data.wilayah'));

        $this->assertSame(3, $wilayah->sum('jumlah'));
        $this->assertSame(2, $wilayah->firstWhere('kode', null)['jumlah']);
    }

    public function test_ringkasan_hanya_menghitung_rit_sendiri(): void
    {
        $lain = drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ]);

        $this->buatRit([['NGJ', 'Selesai']]);
        $this->buatRit([['NGW', 'Selesai']], ['driver_id' => $lain->id, 'nomor_rit' => 2]);

        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.rit.total', 1)
            ->assertJsonCount(1, 'data.wilayah');
    }

    public function test_rentang_bawaan_tiga_puluh_hari_terakhir(): void
    {
        $this->buatRit([['NGJ', 'Selesai']], [
            'tanggal_muatan' => now()->subDays(40)->toDateString(),
        ]);
        $this->buatRit([['NGW', 'Selesai']], [
            'nomor_rit'      => 2,
            'tanggal_muatan' => now()->subDays(5)->toDateString(),
        ]);

        $this->api()->getJson('/api/driver/tugas/ringkasan')
            ->assertOk()
            ->assertJsonPath('data.rit.total', 1);

        // Rentang yang diminta sendiri tetap dihormati.
        $dari = now()->subDays(60)->toDateString();

        $this->api()->getJson("/api/driver/tugas/ringkasan?dari={$dari}")
            ->assertOk()
            ->assertJsonPath('data.rit.total', 2);
    }

    public function test_ringkasan_menolak_tamu(): void
    {
        $this->getJson('/api/driver/tugas/ringkasan')->assertStatus(401);
    }
}
