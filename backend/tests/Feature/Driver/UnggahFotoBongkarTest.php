<?php

namespace Tests\Feature\Driver;

use App\Jobs\ProsesFotoBongkar;
use App\Models\BongkarFoto;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnggahFotoBongkarTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
        // Nominatim tidak boleh disentuh dari test; job memanggilnya lewat
        // GeocodingService yang gagal diam-diam kalau responsnya kosong.
        Http::fake(['*nominatim*' => Http::response(['display_name' => 'Jalan Uji, Bojonegoro'])]);

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

    private function api(?string $token = null): self
    {
        // forgetGuards: aplikasi tidak dibangun ulang antar request dalam satu
        // test, jadi guard menyimpan user yang sudah terlanjur diresolusi dan
        // token kedua akan diabaikan diam-diam.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $this->token));
    }

    private function driverLain(): drivers
    {
        return drivers::create([
            'kode_driver'  => 'DRV-002',
            'nama_driver'  => 'Joko Utomo',
            'no_telepon'   => '081200000002',
            'alamat'       => 'Tuban',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);
    }

    private function buatRit(int $jumlahTujuan = 1, array $override = []): DaftarMuat
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

    /**
     * Rit siap dibongkar. Sejak tahapan status rit dicabut, tidak ada lagi
     * yang perlu ditekan lebih dulu — driver langsung menjepret begitu sampai.
     */
    private function ritSiapBongkar(): array
    {
        $muat = $this->buatRit();

        return [$muat->refresh(), $muat->items()->first()];
    }

    private function kirimLaporan(DaftarMuat $muat, DaftarMuatItem $item, array $override = [])
    {
        return $this->api()->postJson(
            "/api/driver/tugas/{$muat->id}/item/{$item->id}/laporan",
            array_merge(['status' => 'Selesai', 'catatan' => 'Barang diterima penuh'], $override),
        );
    }

    private function kirimFoto(DaftarMuat $muat, DaftarMuatItem $item, array $override = [], ?string $token = null)
    {
        return $this->api($token)->post("/api/driver/tugas/{$muat->id}/item/{$item->id}/foto", array_merge([
            'foto'        => UploadedFile::fake()->image('bongkar.jpg', 800, 600),
            'client_uuid' => (string) Str::uuid(),
        ], $override));
    }

    // ── Jalur normal ────────────────────────────────────────────

    public function test_driver_bisa_mengunggah_foto_bongkar(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $response = $this->kirimFoto($muat, $item, [
            'jenis'     => 'surat_jalan',
            'latitude'  => -7.15,
            'longitude' => 111.88,
            'accuracy'  => 12.5,
            'catatan'   => 'Surat jalan ditandatangani',
        ])->assertCreated()
            ->assertJsonPath('data.jenis', 'surat_jalan')
            ->assertJsonPath('data.catatan', 'Surat jalan ditandatangani');

        $foto = BongkarFoto::firstOrFail();

        $this->assertSame($this->driver->id, $foto->driver_id);
        $this->assertSame($item->id, $foto->daftar_muat_item_id);
        // Konteks tujuan diawetkan supaya bukti tetap terbaca kalau admin
        // mengedit rit belakangan.
        $this->assertSame((int) $item->urutan, (int) $foto->urutan_snapshot);
        $this->assertSame($item->lokasi_pengiriman, $foto->lokasi_snapshot);
        $this->assertNotEmpty($response->json('data.foto_url'));

        $this->api()->getJson("/api/driver/tugas/{$muat->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.jumlah_foto', 1);
    }

    public function test_foto_dipindahkan_ke_folder_tetap_dan_dialamatkan(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $this->kirimFoto($muat, $item, ['latitude' => -7.15, 'longitude' => 111.88])->assertCreated();

        $foto = BongkarFoto::firstOrFail();

        // QUEUE_CONNECTION=sync di test, jadi job sudah jalan saat ini.
        $this->assertSame('siap', $foto->status_proses);
        $this->assertStringStartsWith("bongkar/{$muat->id}/", $foto->foto);
        $this->assertSame('Jalan Uji, Bojonegoro', $foto->alamat);

        // Disk privat, bukan `public`: foto bukti tidak boleh lagi lahir di
        // folder yang disajikan Nginx langsung tanpa Laravel dilibatkan.
        Storage::disk('local')->assertExists($foto->foto);
        Storage::disk('public')->assertMissing($foto->foto);
        $this->assertEmpty(Storage::disk('local')->files('temp-bongkar'));
    }

    public function test_foto_ditandai_gagal_kalau_berkasnya_hilang(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $foto = BongkarFoto::create([
            'daftar_muat_id'      => $muat->id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $this->driver->id,
            'urutan_snapshot'     => $item->urutan,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'temp-bongkar/tidak-ada.jpg',
            'client_uuid'         => (string) Str::uuid(),
            'status_proses'       => 'temp',
        ]);

        ProsesFotoBongkar::dispatch($foto->id);

        $this->assertSame('gagal', $foto->refresh()->status_proses);
    }

    public function test_daftar_foto_bisa_dibaca_kembali(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $this->kirimFoto($muat, $item)->assertCreated();
        $this->kirimFoto($muat, $item)->assertCreated();

        $this->api()->getJson("/api/driver/tugas/{$muat->id}/item/{$item->id}/foto")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_foto_membuka_kunci_laporan_dan_rit_menutup_sendiri(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        // Tanpa foto, FOTO_WAJIB menahan laporannya.
        $this->kirimLaporan($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'FOTO_WAJIB');

        $this->kirimFoto($muat, $item)->assertCreated();

        $this->kirimLaporan($muat, $item)
            ->assertOk()
            ->assertJsonPath('data.status_bongkar', 'Selesai');

        // Rit ini cuma punya satu tujuan, jadi begitu tujuannya dilaporkan
        // ritnya ikut tertutup tanpa driver menekan apa pun.
        $this->assertSame('Selesai', $muat->refresh()->status_perjalanan);
        $this->assertNotNull($muat->selesai_at);
    }

    // ── Idempotensi ─────────────────────────────────────────────

    public function test_kiriman_ulang_dengan_client_uuid_sama_tidak_menggandakan_foto(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $uuid = (string) Str::uuid();

        $pertama = $this->kirimFoto($muat, $item, ['client_uuid' => $uuid])->assertCreated();

        $kedua = $this->kirimFoto($muat, $item, ['client_uuid' => $uuid])
            ->assertOk()
            ->assertJsonPath('data.id', $pertama->json('data.id'));

        $this->assertSame(1, BongkarFoto::count());
        $this->assertCount(1, Storage::disk('local')->files("bongkar/{$muat->id}"));
        $this->assertSame($pertama->json('data.foto_url'), $kedua->json('data.foto_url'));
    }

    public function test_kiriman_ulang_tetap_berhasil_setelah_tujuan_ditandai_selesai(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $uuid = (string) Str::uuid();

        $this->kirimFoto($muat, $item, ['client_uuid' => $uuid])->assertCreated();

        $this->kirimLaporan($muat, $item)->assertOk();

        // Antrian offline yang telat terkirim tidak boleh dijawab error, kalau
        // tidak driver akan melihat kegagalan permanen atas foto yang sudah
        // diterima server.
        $this->kirimFoto($muat, $item, ['client_uuid' => $uuid])->assertOk();

        $this->assertSame(1, BongkarFoto::count());
    }

    public function test_client_uuid_milik_driver_lain_ditolak(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $lain  = $this->driverLain();
        $uuid  = (string) Str::uuid();

        BongkarFoto::create([
            'daftar_muat_id'      => $muat->id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $lain->id,
            'urutan_snapshot'     => $item->urutan,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'bongkar/lain.jpg',
            'client_uuid'         => $uuid,
            'status_proses'       => 'siap',
        ]);

        $this->kirimFoto($muat, $item, ['client_uuid' => $uuid])
            ->assertStatus(422)
            ->assertJsonPath('code', 'CLIENT_UUID_BENTROK');
    }

    // ── Aturan yang dijaga server ───────────────────────────────

    public function test_foto_diterima_tanpa_perlu_menandai_rit_berangkat(): void
    {
        // Dulu foto ditolak RIT_BELUM_BERANGKAT sampai driver menekan tahapan
        // rit. Tahapan itu sudah dicabut, jadi foto pertama harus diterima
        // apa adanya — kalau tidak, tidak ada tujuan yang bisa dilaporkan.
        $muat = $this->buatRit();
        $item = $muat->items()->first();

        $this->kirimFoto($muat, $item)->assertCreated();

        $this->assertSame(1, BongkarFoto::count());

        // Foto pertama sekaligus menandai ritnya sudah jalan, supaya admin
        // terkunci dari mengedit daftar tujuan yang sedang dikerjakan.
        $this->assertSame('Berangkat', $muat->refresh()->status_perjalanan);

        // Dan itu harus meninggalkan jejak. Tanpa ini rit yang mulai jalan
        // lewat foto tidak punya penanda "Berangkat" di riwayat admin.
        $log = $muat->logs()->where('status', 'Berangkat')->first();

        $this->assertNotNull($log);
        $this->assertSame('driver', $log->aktor);
    }

    public function test_tidak_bisa_mengunggah_ke_rit_driver_lain(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $lain      = $this->driverLain();
        $tokenLain = $lain->createToken('driver_auth_token')->plainTextToken;

        $this->kirimFoto($muat, $item, [], $tokenLain)
            ->assertStatus(422)
            ->assertJsonPath('code', 'BUKAN_TUGAS_ANDA');
    }

    public function test_tidak_bisa_mengunggah_setelah_tujuan_tuntas(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $this->kirimFoto($muat, $item)->assertCreated();

        $this->kirimLaporan($muat, $item)->assertOk();

        $this->kirimFoto($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'TUJUAN_SUDAH_TUNTAS');
    }

    public function test_foto_dibatasi_sepuluh_per_tujuan(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        for ($i = 0; $i < 10; $i++) {
            $this->kirimFoto($muat, $item)->assertCreated();
        }

        $this->kirimFoto($muat, $item)
            ->assertStatus(422)
            ->assertJsonPath('code', 'BATAS_FOTO');

        $this->assertSame(10, BongkarFoto::count());
    }

    public function test_berkas_bukan_gambar_ditolak(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $this->kirimFoto($muat, $item, ['foto' => UploadedFile::fake()->create('dokumen.pdf', 100)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('foto');
    }

    // ── Hapus ───────────────────────────────────────────────────

    public function test_driver_bisa_menghapus_foto_salah_jepret(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $id = $this->kirimFoto($muat, $item)->assertCreated()->json('data.id');

        $path = BongkarFoto::findOrFail($id)->foto;

        $this->api()->deleteJson("/api/driver/tugas/{$muat->id}/item/{$item->id}/foto/{$id}")
            ->assertOk();

        $this->assertSame(0, BongkarFoto::count());
        Storage::disk('local')->assertMissing($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_tidak_bisa_menghapus_foto_setelah_tujuan_tuntas(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $id = $this->kirimFoto($muat, $item)->assertCreated()->json('data.id');

        $this->kirimLaporan($muat, $item)->assertOk();

        $this->api()->deleteJson("/api/driver/tugas/{$muat->id}/item/{$item->id}/foto/{$id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'TUJUAN_SUDAH_TUNTAS');

        $this->assertSame(1, BongkarFoto::count());
    }

    public function test_tidak_bisa_menghapus_foto_driver_lain(): void
    {
        [$muat, $item] = $this->ritSiapBongkar();

        $lain = $this->driverLain();

        $foto = BongkarFoto::create([
            'daftar_muat_id'      => $muat->id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $lain->id,
            'urutan_snapshot'     => $item->urutan,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'bongkar/lain.jpg',
            'client_uuid'         => (string) Str::uuid(),
            'status_proses'       => 'siap',
        ]);

        $this->api()->deleteJson("/api/driver/tugas/{$muat->id}/item/{$item->id}/foto/{$foto->id}")
            ->assertStatus(422)
            ->assertJsonPath('code', 'BUKAN_FOTO_ANDA');
    }
}
