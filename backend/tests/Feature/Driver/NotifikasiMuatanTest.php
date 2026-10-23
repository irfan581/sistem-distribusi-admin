<?php

namespace Tests\Feature\Driver;

use App\Jobs\KirimNotifikasiDriverJob;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use App\Services\DaftarMuatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Kapan notifikasi "muatan baru" dijadwalkan dan apa isinya. Pengiriman ke
 * Firebase sendiri tidak diuji di sini.
 */
class NotifikasiMuatanTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private kendaraans $kendaraan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = $this->buatDriver('DRV-001', 'Sukardi Wijaya');

        $this->kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S 1234 AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);
    }

    private function buatDriver(string $kode, string $nama): drivers
    {
        return drivers::create([
            'kode_driver'  => $kode,
            'nama_driver'  => $nama,
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);
    }

    private function dataRit(array $override = []): array
    {
        return array_merge([
            'kendaraan_id'     => $this->kendaraan->id,
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => '2026-10-14',
            'nomor_rit'        => 2,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['lokasi_pengiriman' => 'Toko Makmur', 'kode_area' => 'BJN'],
                ['lokasi_pengiriman' => 'Toko Subur', 'kode_area' => 'BJN'],
            ],
        ], $override);
    }

    public function test_rit_baru_dengan_driver_menjadwalkan_notifikasi(): void
    {
        Queue::fake();

        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());

        Queue::assertPushed(KirimNotifikasiDriverJob::class, 1);
        Queue::assertPushed(
            KirimNotifikasiDriverJob::class,
            fn (KirimNotifikasiDriverJob $job) => $job->isiPesan()['data']['daftar_muat_id'] === (string) $muat->id,
        );
    }

    public function test_rit_tanpa_driver_tidak_menjadwalkan_notifikasi(): void
    {
        Queue::fake();

        app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit(['driver_id' => null]));

        Queue::assertNotPushed(KirimNotifikasiDriverJob::class);
    }

    public function test_mengganti_driver_menjadwalkan_notifikasi_untuk_driver_baru(): void
    {
        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());
        $pengganti = $this->buatDriver('DRV-002', 'Bambang');

        Queue::fake();

        app(DaftarMuatService::class)->updateMuat($muat, $this->dataRit(['driver_id' => $pengganti->id]));

        Queue::assertPushed(
            KirimNotifikasiDriverJob::class,
            fn (KirimNotifikasiDriverJob $job) => $muat->fresh()->driver_id === $pengganti->id,
        );
        Queue::assertPushed(KirimNotifikasiDriverJob::class, 1);
    }

    public function test_menyimpan_ulang_tanpa_ganti_driver_tidak_mengirim_notifikasi_lagi(): void
    {
        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());

        Queue::fake();

        app(DaftarMuatService::class)->updateMuat($muat, $this->dataRit(['total_muatan_ton' => 6]));

        Queue::assertNotPushed(KirimNotifikasiDriverJob::class);
    }

    /** Memakai antrean sync sungguhan: saat job jalan, tujuan harus sudah ada. */
    public function test_notifikasi_dijalankan_setelah_tujuan_tersimpan(): void
    {
        $jumlahTujuanSaatJobJalan = null;

        Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$jumlahTujuanSaatJobJalan) {
            if ($event->job->resolveName() === KirimNotifikasiDriverJob::class) {
                $jumlahTujuanSaatJobJalan = DaftarMuatItem::count();
            }
        });

        app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());

        $this->assertSame(2, $jumlahTujuanSaatJobJalan);
    }

    public function test_isi_notifikasi_memuat_rit_tanggal_truk_dan_tujuan(): void
    {
        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());

        $pesan = (new KirimNotifikasiDriverJob($muat->fresh()))->isiPesan();

        $this->assertSame('Muatan baru untuk Anda', $pesan['judul']);
        $this->assertSame(
            "Rit 2 · Rabu, 14 Oktober 2026 · S 1234 AB\n2 tujuan: Toko Makmur, Toko Subur",
            $pesan['isi'],
        );
        $this->assertSame(
            ['type' => 'muatan_baru', 'daftar_muat_id' => (string) $muat->id],
            $pesan['data'],
        );
    }

    public function test_tujuan_yang_banyak_diringkas(): void
    {
        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit([
            'items' => array_map(
                fn (int $n) => ['lokasi_pengiriman' => "Toko {$n}", 'kode_area' => 'BJN'],
                range(1, 5),
            ),
        ]));

        $pesan = (new KirimNotifikasiDriverJob($muat->fresh()))->isiPesan();

        $this->assertStringContainsString('5 tujuan: Toko 1, Toko 2, Toko 3, +2 lagi', $pesan['isi']);
    }

    public function test_rit_batal_tidak_diberitahukan(): void
    {
        $muat = app(DaftarMuatService::class)->prosesPendaftaranMuat($this->dataRit());
        $muat->forceFill(['status_perjalanan' => 'Batal'])->save();

        $this->assertNull((new KirimNotifikasiDriverJob($muat->fresh()))->isiPesan());
    }

    public function test_logout_menghapus_token_fcm(): void
    {
        $this->driver->forceFill(['fcm_token' => 'token-hp-lama'])->save();
        $token = $this->driver->createToken('driver_auth_token')->plainTextToken;

        $this->withToken($token)->postJson('/api/driver/logout')->assertOk();

        $this->assertNull($this->driver->fresh()->fcm_token);
    }
}
