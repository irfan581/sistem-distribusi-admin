<?php

namespace Tests\Feature\Driver;

use App\Models\BongkarFoto;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransisiStatusTest extends TestCase
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

    private function buatRit(int $jumlahTujuan = 2, array $override = []): DaftarMuat
    {
        $kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S ' . random_int(1000, 9999) . ' AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);

        $muat = DaftarMuat::create(array_merge([
            'kendaraan_id'     => $kendaraan->id,
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
        ], $override));

        for ($i = 1; $i <= $jumlahTujuan; $i++) {
            DaftarMuatItem::create([
                'daftar_muat_id'    => $muat->id,
                'urutan'            => $i,
                'lokasi_pengiriman' => "Toko Tujuan {$i}",
                'jenis_muatan'      => 'non_subsidi',
                'kode_area'         => 'BJN',
            ]);
        }

        return $muat->refresh();
    }

    private function fotoUntuk(DaftarMuatItem $item): BongkarFoto
    {
        return BongkarFoto::create([
            'daftar_muat_id'      => $item->daftar_muat_id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $this->driver->id,
            'urutan_snapshot'     => $item->urutan,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'bongkar/uji.jpg',
            'client_uuid'         => (string) \Illuminate\Support\Str::uuid(),
            'status_proses'       => 'siap',
        ]);
    }

    private function laporkan(DaftarMuat $muat, DaftarMuatItem $item, array $override = [])
    {
        return $this->api()->postJson(
            "/api/driver/tugas/{$muat->id}/item/{$item->id}/laporan",
            array_merge(['status' => 'Selesai', 'catatan' => 'Barang diterima penuh'], $override),
        );
    }

    // ── Tugas harian ────────────────────────────────────────────

    public function test_tugas_hari_ini_hanya_menampilkan_rit_sendiri(): void
    {
        $this->buatRit();

        $driverLain = drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ]);
        $this->buatRit(1, ['driver_id' => $driverLain->id, 'nomor_rit' => 2]);

        $this->api()->getJson('/api/driver/tugas/hari-ini')
            ->assertOk()
            ->assertJsonCount(1, 'data.rit')
            ->assertJsonPath('data.rit.0.status_perjalanan', 'Menunggu')
            ->assertJsonPath('data.rit.0.jumlah_tujuan', 2)
            ->assertJsonPath('data.rit.0.tujuan_tuntas', 0);
    }

    public function test_respons_tidak_lagi_membawa_aksi_berikutnya(): void
    {
        // Tahapan status rit sudah dicabut. Kalau kunci ini masih terkirim,
        // aplikasi lama akan menampilkan tombol yang endpointnya sudah hilang.
        $this->buatRit(1);

        $this->api()->getJson('/api/driver/tugas/hari-ini')
            ->assertOk()
            ->assertJsonMissingPath('data.rit.0.aksi_berikutnya');
    }

    /**
     * Dulu tes ini menuntut rit kemarin TIDAK muncul, karena beranda menyaring
     * tanggal persis hari ini. Aturannya sengaja diubah: yang menentukan rit
     * tampil adalah nama sopirnya sudah dimasukkan admin, bukan tanggalnya —
     * kantor menginput sore untuk besok, dan rit kemarin yang lupa ditutup
     * harus bisa dibereskan sopir sendiri.
     *
     * Yang masih dijaga di sini: permintaan dengan parameter tanggal tetap
     * menyaring tanggal persis. Batas jendela ke belakang diuji terpisah di
     * BerandaRitAktifTest.
     */
    public function test_tugas_hari_ini_membawa_rit_kemarin_yang_belum_tuntas(): void
    {
        $this->buatRit(1, ['tanggal_muatan' => now()->subDay()->toDateString()]);

        $this->api()->getJson('/api/driver/tugas/hari-ini')
            ->assertOk()->assertJsonCount(1, 'data.rit');

        $this->api()->getJson('/api/driver/tugas/hari-ini?tanggal=' . now()->subDay()->toDateString())
            ->assertOk()->assertJsonCount(1, 'data.rit');

        // Tanggal yang tidak ada ritnya tetap kosong.
        $this->api()->getJson('/api/driver/tugas/hari-ini?tanggal=' . now()->subDays(9)->toDateString())
            ->assertOk()->assertJsonCount(0, 'data.rit');
    }

    public function test_driver_tidak_bisa_melihat_rit_milik_orang_lain(): void
    {
        $driverLain = drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ]);
        $muat = $this->buatRit(1, ['driver_id' => $driverLain->id]);

        $this->api()->getJson("/api/driver/tugas/{$muat->id}")->assertStatus(403);
    }

    // ── Endpoint tahapan rit sudah tidak ada ────────────────────

    public function test_endpoint_status_rit_sudah_dihapus(): void
    {
        $muat = $this->buatRit(1);

        $this->api()->postJson("/api/driver/tugas/{$muat->id}/status", ['status' => 'Dimuat'])
            ->assertStatus(404);
    }

    // ── Laporan tujuan ──────────────────────────────────────────

    public function test_laporan_menutup_tujuan_tanpa_tahapan_apa_pun(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        // Tidak ada "mulai muat", "berangkat", atau "sudah sampai" lebih dulu.
        $this->laporkan($muat, $item)
            ->assertOk()
            ->assertJsonPath('data.status_bongkar', 'Selesai')
            ->assertJsonPath('data.catatan_driver', 'Barang diterima penuh');

        $item->refresh();

        $this->assertNotNull($item->bongkar_selesai_at);
        // Jam tiba tidak lagi ditekan terpisah, tapi tetap terisi supaya rekap
        // admin punya penanda kapan truk sampai.
        $this->assertNotNull($item->tiba_at);
    }

    public function test_rit_berangkat_sendiri_saat_laporan_pertama_masuk(): void
    {
        $muat = $this->buatRit(2);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $this->assertSame('Menunggu', $muat->status_perjalanan);

        $this->laporkan($muat, $item)->assertOk();

        $muat->refresh();

        $this->assertSame('Berangkat', $muat->status_perjalanan);
        $this->assertNotNull($muat->berangkat_at);
        // Masih ada satu tujuan tersisa, jadi ritnya belum boleh tertutup.
        $this->assertNull($muat->selesai_at);
    }

    public function test_rit_menutup_diri_saat_tujuan_terakhir_dilaporkan(): void
    {
        $muat = $this->buatRit(2);

        foreach ($muat->items as $item) {
            $this->fotoUntuk($item);
            $this->laporkan($muat, $item)->assertOk();
        }

        $muat->refresh();

        $this->assertSame('Selesai', $muat->status_perjalanan);
        $this->assertNotNull($muat->selesai_at);
    }

    public function test_tujuan_bermasalah_tetap_menutup_rit(): void
    {
        $muat = $this->buatRit(2);
        [$satu, $dua] = $muat->items->all();

        $this->fotoUntuk($satu);
        $this->laporkan($muat, $satu)->assertOk();

        $this->fotoUntuk($dua);
        $this->laporkan($muat, $dua, [
            'status'  => 'Bermasalah',
            'catatan' => 'Toko tutup, barang dibawa kembali',
        ])->assertOk()->assertJsonPath('data.status_bongkar', 'Bermasalah');

        $muat->refresh();

        // Bermasalah tetap dihitung tuntas: pekerjaan driver di tujuan itu
        // sudah selesai, urusan barangnya diselesaikan kantor lewat telepon.
        $this->assertSame('Selesai', $muat->status_perjalanan);
    }

    public function test_tujuan_yang_sudah_dilaporkan_tidak_bisa_dilaporkan_lagi(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $this->laporkan($muat, $item)->assertOk();

        $this->laporkan($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'TUJUAN_SUDAH_TUNTAS');
    }

    // ── Yang wajib ada di setiap laporan ────────────────────────

    public function test_laporan_wajib_ada_foto(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();

        $this->laporkan($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'FOTO_WAJIB');

        $this->assertSame('Menunggu', $item->refresh()->status_bongkar);
    }

    public function test_keterangan_wajib_diisi_walau_pengirimannya_lancar(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $this->laporkan($muat, $item, ['catatan' => ''])
            ->assertStatus(422);

        $this->assertSame('Menunggu', $item->refresh()->status_bongkar);
    }

    public function test_status_di_luar_selesai_dan_bermasalah_ditolak(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        foreach (['Tiba', 'Dimuat', 'Berangkat', 'Gagal'] as $status) {
            $this->laporkan($muat, $item, ['status' => $status])->assertStatus(422);
        }

        $this->assertSame('Menunggu', $item->refresh()->status_bongkar);
    }

    // ── Kepemilikan dan pembatalan ──────────────────────────────

    public function test_rit_yang_dibatalkan_admin_menolak_laporan(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $muat->update(['status_perjalanan' => 'Batal']);

        $this->laporkan($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'RIT_DIBATALKAN');
    }

    public function test_rit_batal_tidak_dihidupkan_lagi_oleh_laporan_susulan(): void
    {
        $muat = $this->buatRit(1);
        $muat->update(['status_perjalanan' => 'Batal']);

        $this->fotoUntuk($muat->items()->first());

        // Foto dari antrian offline boleh saja menyusul, tapi statusnya harus
        // tetap Batal — bukan ikut melompat ke Berangkat.
        $this->assertSame('Batal', $muat->refresh()->status_perjalanan);
    }

    public function test_driver_tidak_bisa_melaporkan_tujuan_rit_orang_lain(): void
    {
        $driverLain = drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
        ]);
        $muat = $this->buatRit(1, ['driver_id' => $driverLain->id]);
        $item = $muat->items()->first();

        $this->laporkan($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'BUKAN_TUGAS_ANDA');
    }

    public function test_tujuan_milik_rit_lain_tidak_bisa_disentuh(): void
    {
        $satu = $this->buatRit(1);
        $dua  = $this->buatRit(1, ['nomor_rit' => 2]);

        $itemMilikDua = $dua->items()->first();

        $this->laporkan($satu, $itemMilikDua)->assertStatus(404);
    }

    // ── Bentuk respons ──────────────────────────────────────────

    public function test_jumlah_foto_ikut_terbawa_di_respons_tingkat_rit(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();

        $this->fotoUntuk($item);
        $this->fotoUntuk($item);

        // Respons sempat melaporkan 0 foto karena relasi items terlanjur dimuat
        // tanpa hitungannya, sehingga tujuan yang sudah difoto tampak belum.
        $this->laporkan($muat, $item)
            ->assertOk()->assertJsonPath('data.jumlah_foto', 2);

        $this->api()->getJson('/api/driver/tugas/hari-ini')
            ->assertOk()->assertJsonPath('data.rit.0.items.0.jumlah_foto', 2);

        $this->api()->getJson("/api/driver/tugas/{$muat->id}")
            ->assertOk()->assertJsonPath('data.items.0.jumlah_foto', 2);
    }

    // ── Riwayat ─────────────────────────────────────────────────

    public function test_laporan_tercatat_atas_nama_driver(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $this->laporkan($muat, $item, [
            'latitude'  => -7.1503,
            'longitude' => 111.8817,
        ])->assertOk();

        $log = $muat->logs()->where('status', 'Tujuan 1 Selesai')->first();

        $this->assertNotNull($log);
        $this->assertSame('driver', $log->aktor);
        $this->assertSame($this->driver->id, $log->driver_id);
        // user_id menunjuk tabel users, jadi harus kosong saat pelakunya driver.
        $this->assertNull($log->user_id);
        $this->assertSame('Sukardi Wijaya', $log->namaPelaku());
        $this->assertEqualsWithDelta(-7.1503, (float) $log->latitude, 0.0001);
    }

    public function test_penutupan_rit_ikut_tercatat(): void
    {
        $muat = $this->buatRit(1);
        $item = $muat->items()->first();
        $this->fotoUntuk($item);

        $this->laporkan($muat, $item)->assertOk();

        // Driver tidak menekan apa pun untuk menutup rit, tapi riwayatnya harus
        // tetap menunjukkan kapan rit itu selesai dan atas laporan siapa.
        $log = $muat->logs()->where('status', 'Selesai')->first();

        $this->assertNotNull($log);
        $this->assertSame('driver', $log->aktor);
        $this->assertSame($this->driver->id, $log->driver_id);
    }
}
