<?php

namespace Tests\Feature\Driver;

use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan rit yang muncul di beranda sopir.
 *
 * Yang menentukan sebuah rit tampil adalah namanya sudah dimasukkan admin,
 * bukan tanggalnya persis hari ini. Kantor menginput rit sore untuk besok,
 * jadi saringan tanggal persis membuat sopir melihat "belum ada kiriman"
 * padahal tugasnya sudah ditetapkan semalam.
 */
class BerandaRitAktifTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private string $token;
    private kendaraans $kendaraan;

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

        $this->kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S 1234 AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);
    }

    private function buatRit(string $tanggal, array $override = []): DaftarMuat
    {
        $muat = DaftarMuat::create(array_merge([
            'kendaraan_id'     => $this->kendaraan->id,
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => $tanggal,
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
        ], $override));

        DaftarMuatItem::create([
            'daftar_muat_id'    => $muat->id,
            'urutan'            => 1,
            'lokasi_pengiriman' => 'Toko Makmur',
            'jenis_muatan'      => 'non_subsidi',
            'kode_area'         => 'BJN',
        ]);

        return $muat;
    }

    private function beranda()
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/driver/tugas/hari-ini');
    }

    private function idRit($response): array
    {
        return collect($response->json('data.rit'))->pluck('id')->sort()->values()->all();
    }

    /** Inti perubahannya: rit besok tidak lagi tersembunyi sampai besok pagi. */
    public function test_rit_besok_langsung_tampil_begitu_sopir_ditugaskan(): void
    {
        $besok = $this->buatRit(now()->addDay()->toDateString());

        $this->assertSame([$besok->id], $this->idRit($this->beranda()));
    }

    public function test_rit_hari_ini_tetap_tampil(): void
    {
        $ini = $this->buatRit(now()->toDateString());

        $this->assertSame([$ini->id], $this->idRit($this->beranda()));
    }

    /**
     * Rit susulan yang dibuat siang hari untuk hari itu juga ikut benar dengan
     * sendirinya — tidak perlu perlakuan khusus.
     */
    public function test_rit_susulan_hari_ini_ikut_tampil(): void
    {
        $pagi    = $this->buatRit(now()->toDateString(), ['nomor_rit' => 1]);
        $susulan = $this->buatRit(now()->toDateString(), ['nomor_rit' => 2]);

        $this->assertSame(
            collect([$pagi->id, $susulan->id])->sort()->values()->all(),
            $this->idRit($this->beranda()),
        );
    }

    /** Rit kemarin yang lupa ditutup masih bisa dibereskan sopir sendiri. */
    public function test_rit_kemarin_yang_belum_tuntas_masih_tampil(): void
    {
        $kemarin = $this->buatRit(now()->subDay()->toDateString());

        $this->assertSame([$kemarin->id], $this->idRit($this->beranda()));
    }

    /**
     * Inilah yang menjaga beranda tetap terpakai. Tanpa batas ini, 6.920
     * catatan pembukuan lama yang tidak pernah disentuh sopir ikut terbawa —
     * sebagian sopir sampai 153 rit sekaligus.
     */
    public function test_rit_lama_yang_belum_tuntas_tidak_ikut_membanjiri(): void
    {
        $this->buatRit(now()->subDays(30)->toDateString());
        $this->buatRit(now()->subDays(120)->toDateString());

        $this->assertSame([], $this->idRit($this->beranda()));
    }

    public function test_rit_kemarin_yang_sudah_selesai_tidak_tampil_lagi(): void
    {
        $this->buatRit(now()->subDay()->toDateString(), ['selesai_at' => now()->subDay()]);

        $this->assertSame([], $this->idRit($this->beranda()));
    }

    /** Sopir tetap melihat hasil kerjanya hari ini, bukan layar yang mendadak kosong. */
    public function test_rit_hari_ini_yang_sudah_selesai_tetap_tampil(): void
    {
        $selesai = $this->buatRit(now()->toDateString(), ['selesai_at' => now()]);

        $this->assertSame([$selesai->id], $this->idRit($this->beranda()));
    }

    public function test_rit_sopir_lain_tidak_pernah_ikut(): void
    {
        $lain = drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);

        $this->buatRit(now()->toDateString(), ['driver_id' => $lain->id]);

        $this->assertSame([], $this->idRit($this->beranda()));
    }

    /** Permintaan dengan tanggal tertentu perilakunya tidak berubah. */
    public function test_permintaan_dengan_tanggal_tetap_menyaring_tanggal_persis(): void
    {
        $this->buatRit(now()->toDateString());
        $besok = $this->buatRit(now()->addDay()->toDateString());

        $this->app['auth']->forgetGuards();
        $r = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/driver/tugas/hari-ini?tanggal=' . now()->addDay()->toDateString());

        $this->assertSame([$besok->id], $this->idRit($r));
    }
}
