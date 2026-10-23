<?php

namespace Tests\Feature;

use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\Toko;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * toko:impor-wa memasukkan hasil periksa toko dari grup WA. Titiknya dikunci
 * 'manual' agar tidak ditimpa kunjungan, dan bawaannya hanya simulasi.
 */
class ImporTokoWaTest extends TestCase
{
    use RefreshDatabase;

    private Sales $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->sales = Sales::create([
            'nik'      => '3521' . random_int(1000000000, 9999999999),
            'nama'     => 'Sales Uji',
            'area'     => 'Madiun',
            'password' => 'rahasia123',
        ]);
    }

    private function berkas(array $baris): string
    {
        $jalur = tempnam(sys_get_temp_dir(), 'impor') . '.json';
        file_put_contents($jalur, json_encode($baris));

        return $jalur;
    }

    private function tokoLama(array $atribut = []): Toko
    {
        return Toko::create(array_merge([
            'sales_id' => $this->sales->id, 'nama_kios' => 'Hartoyo (Berkah Tani)', 'wilayah' => 'Madiun',
            'lat' => -7.70, 'lng' => 111.50, 'lokasi_sumber' => 'kunjungan',
        ], $atribut));
    }

    private function barisBaru(array $ubah = []): array
    {
        return array_merge([
            'kode' => 'WA-001', 'aksi' => 'buat', 'nama_kios' => 'Sri (Sumber Tani)', 'wilayah' => 'Madiun',
            'sales_id' => $this->sales->id, 'alamat' => 'Mlilir, Dolopo, Kab. Madiun', 'lat' => -7.75, 'lng' => 111.51,
        ], $ubah);
    }

    public function test_bawaan_hanya_simulasi(): void
    {
        $toko = $this->tokoLama();

        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([
            $this->barisBaru(),
            ['kode' => 'WA-002', 'aksi' => 'titik', 'toko_id' => $toko->id, 'lat' => -7.80, 'lng' => 111.60],
        ])])->assertSuccessful();

        $this->assertSame(1, Toko::count());
        $this->assertEqualsWithDelta(-7.70, $toko->fresh()->lat, 1e-9);
    }

    public function test_simpan_membuat_toko_baru_lengkap_dengan_titik_terkunci(): void
    {
        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([$this->barisBaru()]), '--simpan' => true])->assertSuccessful();

        $toko = Toko::where('nama_kios', 'Sri (Sumber Tani)')->firstOrFail();
        $this->assertSame('Mlilir, Dolopo, Kab. Madiun', $toko->alamat);
        $this->assertTrue($toko->alamat_manual);
        $this->assertSame('manual', $toko->lokasi_sumber);
        $this->assertSame($this->sales->id, $toko->sales_id);
        $this->assertTrue($toko->is_new);
    }

    public function test_titik_baru_tidak_tertimpa_kunjungan_berikutnya(): void
    {
        $toko = $this->tokoLama();
        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([
            ['kode' => 'WA-002', 'aksi' => 'titik', 'toko_id' => $toko->id, 'lat' => -7.80, 'lng' => 111.60],
        ]), '--simpan' => true])->assertSuccessful();

        $kunjungan = new KunjunganToko(['lat' => -7.00, 'lng' => 110.00]);
        $this->assertFalse($toko->fresh()->isiLokasiDari($kunjungan));
        $this->assertEqualsWithDelta(-7.80, $toko->fresh()->lat, 1e-9);
    }

    public function test_alamat_toko_lama_hanya_diisi_bila_kosong(): void
    {
        $beralamat = $this->tokoLama(['alamat' => 'Ketandan, Madiun']);
        $kosong = $this->tokoLama(['nama_kios' => 'Toko Kosong', 'alamat' => null]);

        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([
            ['kode' => 'WA-003', 'aksi' => 'titik', 'toko_id' => $beralamat->id, 'lat' => -7.81, 'lng' => 111.61, 'alamat' => 'Alamat Google'],
            ['kode' => 'WA-004', 'aksi' => 'titik', 'toko_id' => $kosong->id, 'lat' => -7.82, 'lng' => 111.62, 'alamat' => 'Alamat Google'],
        ]), '--simpan' => true])->assertSuccessful();

        $this->assertSame('Ketandan, Madiun', $beralamat->fresh()->alamat);
        $this->assertSame('Alamat Google', $kosong->fresh()->alamat);
    }

    public function test_nama_yang_sudah_ada_di_wilayah_sama_dilewati(): void
    {
        $this->tokoLama(['nama_kios' => 'SRI (SUMBER TANI)']);

        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([$this->barisBaru()]), '--simpan' => true])
            ->expectsOutputToContain('sudah ada')
            ->assertSuccessful();

        $this->assertSame(1, Toko::count());
    }

    public function test_baris_tanpa_nama_atau_sales_dilewati(): void
    {
        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([
            $this->barisBaru(['nama_kios' => '']),
            $this->barisBaru(['kode' => 'WA-009', 'nama_kios' => 'Toko Lain', 'sales_id' => 999999]),
        ]), '--simpan' => true])->assertSuccessful();

        $this->assertSame(0, Toko::count());
    }

    public function test_kembalikan_memulihkan_titik_dan_menghapus_toko_baru(): void
    {
        $toko = $this->tokoLama();
        $this->artisan('toko:impor-wa', ['berkas' => $this->berkas([
            $this->barisBaru(),
            ['kode' => 'WA-002', 'aksi' => 'titik', 'toko_id' => $toko->id, 'lat' => -7.80, 'lng' => 111.60],
        ]), '--simpan' => true])->assertSuccessful();

        $cadangan = Storage::disk('local')->path(Storage::disk('local')->files('impor-wa')[0]);
        $this->artisan('toko:impor-wa', ['berkas' => '-', '--kembalikan' => $cadangan])->assertSuccessful();

        $this->assertSame(1, Toko::count());
        $this->assertEqualsWithDelta(-7.70, $toko->fresh()->lat, 1e-9);
        $this->assertSame('kunjungan', $toko->fresh()->lokasi_sumber);
    }
}
