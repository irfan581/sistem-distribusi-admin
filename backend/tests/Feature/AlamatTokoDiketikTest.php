<?php

namespace Tests\Feature;

use App\Models\DaftarMuatItem;
use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Toko yang belum punya alamat bisa diberi alamat oleh admin dari form muat
 * non subsidi. Alamat itu tersimpan ke Data Toko dan tidak tertimpa
 * kunjungan sales berikutnya.
 */
class AlamatTokoDiketikTest extends TestCase
{
    use RefreshDatabase;

    private Sales $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sales = Sales::create([
            'nik'      => '3521' . random_int(1000000000, 9999999999),
            'nama'     => 'Sales Uji',
            'area'     => 'Bojonegoro',
            'password' => 'rahasia123',
        ]);
    }

    private function sebagaiAdmin(): self
    {
        $admin = User::create([
            'name' => 'Admin Uji', 'email' => uniqid() . '@uji.test', 'password' => 'rahasia123', 'is_admin' => true,
            'permissions' => ['view_pengiriman', 'create_pengiriman', 'edit_pengiriman', 'muat_non_subsidi'],
        ]);
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $admin->createToken('admin')->plainTextToken);
    }

    private function toko(array $atribut = []): Toko
    {
        return Toko::create(array_merge([
            'sales_id'  => $this->sales->id,
            'nama_kios' => 'Toko Tanpa Alamat',
            'wilayah'   => 'Bojonegoro',
        ], $atribut));
    }

    private function kirim(Toko $toko, ?string $alamat): void
    {
        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [['toko_id' => $toko->id, 'lokasi_pengiriman' => $toko->nama_kios, 'alamat' => $alamat]],
        ])->assertCreated();
    }

    public function test_alamat_ketikan_tersimpan_ke_toko_dan_tujuan(): void
    {
        $toko = $this->toko();

        $this->kirim($toko, '  Jl. Raya Kalitidu No. 5  ');

        $toko->refresh();
        $this->assertSame('Jl. Raya Kalitidu No. 5', $toko->alamat);
        $this->assertTrue($toko->alamat_manual);
        $this->assertSame('Jl. Raya Kalitidu No. 5', DaftarMuatItem::firstOrFail()->alamat_otomatis);
    }

    public function test_alamat_yang_sudah_ada_tidak_bisa_ditimpa_dari_form_muat(): void
    {
        $toko = $this->toko(['alamat' => 'Ketandan, Madiun', 'lat' => -7.6, 'lng' => 111.5, 'lokasi_sumber' => 'kunjungan']);

        $this->kirim($toko, 'Alamat lain');

        $this->assertSame('Ketandan, Madiun', $toko->fresh()->alamat);
        $this->assertFalse($toko->fresh()->alamat_manual);
        $this->assertSame('Ketandan, Madiun', DaftarMuatItem::firstOrFail()->alamat_otomatis);
    }

    public function test_kunjungan_berikutnya_mengisi_titik_tanpa_menimpa_alamat_ketikan(): void
    {
        $toko = $this->toko(['alamat' => 'Jl. Raya Kalitidu No. 5', 'alamat_manual' => true]);
        $kunjungan = KunjunganToko::create([
            'sales_id'  => $this->sales->id,
            'toko_id'   => $toko->id,
            'nama_toko' => $toko->nama_kios,
            'location'  => '-7.15,111.88',
            'alamat'    => 'Kalitidu, Bojonegoro, Jawa Timur, Indonesia',
            'lat'       => -7.15,
            'lng'       => 111.88,
        ]);

        $toko->isiLokasiDari($kunjungan);

        $toko->refresh();
        $this->assertSame('Jl. Raya Kalitidu No. 5', $toko->alamat);
        $this->assertEqualsWithDelta(-7.15, $toko->lat, 0.0001);
    }

    public function test_pencarian_toko_menampilkan_alamat_ketikan(): void
    {
        $this->toko(['alamat' => 'Jl. Raya Kalitidu No. 5', 'alamat_manual' => true]);

        $hasil = $this->sebagaiAdmin()->getJson('/api/admin/pengiriman/toko-tujuan?search=Tanpa')->assertOk()->json('data');

        $this->assertSame('Jl. Raya Kalitidu No. 5', $hasil[0]['alamat_terakhir']);
    }
}
