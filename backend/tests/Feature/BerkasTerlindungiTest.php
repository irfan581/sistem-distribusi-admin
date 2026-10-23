<?php

namespace Tests\Feature;

use App\Models\BongkarFoto;
use App\Models\ChatGroupMember;
use App\Models\ChatMessage;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\Sales;
use App\Models\kendaraans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Foto bukti tidak boleh bisa dibuka siapa pun yang memegang URL-nya.
 *
 * Sampai 17 September 2026 seluruh foto disajikan Nginx langsung dari
 * `public/storage`, tanpa Laravel dilibatkan sama sekali. Diuji ke produksi
 * saat itu: `/api/driver/tugas/hari-ini` menjawab 401, tapi
 * `/storage/absen-sales/459c80cb-….jpg` menjawab 200 — swafoto presensi
 * karyawan terbuka untuk siapa saja.
 *
 * Uji ini mengunci perilaku penggantinya. Kalau suatu saat ada yang
 * mengembalikan penyajian langsung atau melonggarkan pemeriksaan pemilik,
 * uji ini yang memberi tahu — bukan tangkapan layar dari lapangan.
 */
class BerkasTerlindungiTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->driver = $this->buatDriver('DRV-001', 'Sukardi Wijaya');
        $this->token  = $this->driver->createToken('driver_auth_token')->plainTextToken;
    }

    // ── Yang dijaga ───────────────────────────────────────────────

    public function test_tanpa_token_ditolak(): void
    {
        $foto = $this->buatFoto($this->driver);

        $this->getJson("/api/berkas/bongkar/{$foto->id}")
            ->assertStatus(401);
    }

    public function test_driver_bisa_membuka_fotonya_sendiri(): void
    {
        $foto = $this->buatFoto($this->driver);

        $this->api()
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertOk();
    }

    public function test_driver_lain_ditolak_walau_nomornya_ditebak(): void
    {
        $foto = $this->buatFoto($this->driver);

        $lain      = $this->buatDriver('DRV-002', 'Joko Utomo');
        $tokenLain = $lain->createToken('driver_auth_token')->plainTextToken;

        $this->api($tokenLain)
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertStatus(403);
    }

    public function test_berkas_yang_tidak_ada_menjawab_404_bukan_galat(): void
    {
        $foto = $this->buatFoto($this->driver, adaBerkas: false);

        $this->api()
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertStatus(404);
    }

    /**
     * Selama perpindahan, sebagian berkas masih tertinggal di disk lama.
     * Keduanya harus sama-sama tampil lewat route yang sama, kalau tidak
     * pemindahan per folder akan membuat foto menghilang di tengah jalan.
     */
    public function test_berkas_yang_belum_pindah_tetap_tampil(): void
    {
        $foto = $this->buatFoto($this->driver, adaBerkas: false);

        Storage::disk('public')->put($foto->foto, 'isi foto lama');

        $this->api()
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertOk();
    }

    /**
     * `inline`, bukan `attachment`: fotonya harus tampil di dalam halaman,
     * bukan memicu unduhan tiap kali dibuka. Nama berkasnya tetap terpakai saat
     * admin menyimpannya — dan itu bedanya dengan penyajian langsung oleh
     * Nginx, yang hanya bisa memberi nama UUID di disk.
     */
    public function test_nama_berkas_terbaca_bukan_uuid(): void
    {
        $foto = $this->buatFoto($this->driver);

        $this->api()
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertOk()
            ->assertHeader(
                'content-disposition',
                'inline; filename=anto-barokah-tani-' . now()->format('d-m-Y') . '.jpg',
            );
    }

    /**
     * Dashboard memasang foto lewat `<img src>`, dan tag itu tidak pernah
     * mengirim header Authorization. Tanpa jalur tanda tangan, seluruh gambar
     * di dashboard mati — itu yang nyaris terjadi saat route ini pertama
     * ditulis.
     */
    public function test_tautan_bertanda_tangan_diterima_tanpa_token(): void
    {
        $foto = $this->buatFoto($this->driver);

        $url = URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
            'id' => $foto->id, 'ukuran' => 'penuh',
        ]);

        $this->get($url)->assertOk();
    }

    public function test_tanda_tangan_yang_diubah_tidak_membuka_foto_lain(): void
    {
        $milikku = $this->buatFoto($this->driver);
        $milikOrangLain = $this->buatFoto($this->buatDriver('DRV-003', 'Bambang'));

        $url = URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
            'id' => $milikku->id, 'ukuran' => 'penuh',
        ]);

        // Inilah yang dicoba orang yang memegang satu tautan lalu menebak
        // tautan lain: nomor fotonya diganti, tanda tangannya dibiarkan.
        $palsu = str_replace(
            "/bongkar/{$milikku->id}/",
            "/bongkar/{$milikOrangLain->id}/",
            $url,
        );

        // Tanda tangannya gugur, lalu jatuh ke pemeriksaan token — dan tidak
        // ada token di sini. Yang penting: bukan 200.
        $this->get($palsu)->assertStatus(401);
    }

    public function test_tanda_tangan_kedaluwarsa_tidak_lagi_berlaku(): void
    {
        $foto = $this->buatFoto($this->driver);

        $url = URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
            'id' => $foto->id, 'ukuran' => 'penuh',
        ]);

        $this->travel(13)->hours();

        // Inilah bedanya dengan /storage/ yang lama: tautan yang bocor berhenti
        // berlaku dengan sendirinya, tidak perlu ada yang mencabutnya.
        $this->get($url)->assertStatus(401);
    }

    /**
     * Tamu yang menebak nomor tidak boleh bisa membedakan nomor yang berisi
     * dari yang kosong. Sebelum perbaikan ini, nomor kosong dijawab "Data tidak
     * ditemukan" sementara nomor berisi dijawab 403 — dari beda itu saja ia bisa
     * memetakan seluruh isi tanpa melihat satu foto pun.
     */
    public function test_tamu_tidak_bisa_membedakan_nomor_yang_ada_dari_yang_kosong(): void
    {
        $foto = $this->buatFoto($this->driver);

        $adaIsinya = $this->getJson("/api/berkas/bongkar/{$foto->id}");
        $kosong    = $this->getJson('/api/berkas/bongkar/999999');

        $adaIsinya->assertStatus(401);
        $kosong->assertStatus(401);
    }

    /**
     * Avatar ikut dijaga bukan karena isinya rahasia, tapi karena selama satu
     * berkas pun masih disajikan lewat `public/storage`, symlink itu tidak bisa
     * dicabut — dan selama symlink-nya ada, seluruh folder di belakangnya tetap
     * terbuka untuk siapa saja.
     */
    public function test_avatar_sales_tidak_terbuka_tanpa_bukti(): void
    {
        $sales = Sales::create([
            'nik'       => 'S' . random_int(10000, 99999),
            'nama'      => 'Andi Wijaya',
            'no_hp'     => '081200000011',
            'area'      => 'Nganjuk',
            'alamat'    => 'Nganjuk',
            'password'  => 'rahasia123',
            'is_active' => true,
        ]);

        $this->getJson("/api/berkas/avatar-sales/{$sales->id}")
            ->assertStatus(401);
    }

    /**
     * Aplikasi sales menempelkan `?v=<waktu>` untuk memaksa foto profil dimuat
     * ulang setelah diganti. Laravel menghitung seluruh parameter saat
     * memeriksa tanda tangan, jadi tanpa pengecualian ini foto profil sales
     * jadi kosong — dan APK-nya sudah terpasang di HP orang, tidak bisa
     * ditarik begitu saja.
     */
    public function test_pemaksa_segar_tidak_membatalkan_tanda_tangan(): void
    {
        $foto = $this->buatFoto($this->driver);

        $url = URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
            'id' => $foto->id, 'ukuran' => 'penuh',
        ]);

        $this->get($url . '&v=' . time())->assertOk();
    }

    // ── Lampiran chat ─────────────────────────────────────────────

    /**
     * Grup laporan kiriman memuat foto bukti bongkar. Sebelum ini lampirannya
     * disajikan Nginx langsung dari `public/storage`, jadi satu alamat yang
     * tersalin ke luar berlaku selamanya dan tidak bisa dicabut.
     */
    public function test_lampiran_chat_tidak_terbuka_tanpa_bukti(): void
    {
        $pesan = $this->buatPesanGrup($this->driver);

        $this->getJson("/api/berkas/chat/{$pesan->id}")
            ->assertStatus(401);
    }

    public function test_anggota_grup_bisa_membuka_lampirannya(): void
    {
        $pesan = $this->buatPesanGrup($this->driver);

        $this->api()
            ->get("/api/berkas/chat/{$pesan->id}")
            ->assertOk();
    }

    /**
     * Inilah alasan endpoint ini ada: nomor pesan berurutan dan mudah ditebak,
     * jadi keanggotaan grup yang harus dibuktikan, bukan tahu nomornya.
     */
    public function test_bukan_anggota_grup_ditolak(): void
    {
        $pesan = $this->buatPesanGrup($this->driver);

        $lain = $this->buatDriver('DRV-004', 'Slamet Riyadi');

        $this->api($lain->createToken('driver_auth_token')->plainTextToken)
            ->get("/api/berkas/chat/{$pesan->id}")
            ->assertStatus(403);
    }

    /**
     * Pesan lama dari dashboard hanya menyimpan `file_url` berupa alamat
     * `/storage/...`, tanpa `file_path`. Kalau bagian belakangnya tidak
     * diambil, seluruh lampiran lama jadi kosong begitu symlink-nya dicabut.
     */
    public function test_lampiran_lama_yang_hanya_punya_alamat_tetap_tampil(): void
    {
        $pesan = $this->buatPesanGrup($this->driver, adaBerkas: false);

        $path = 'chat-files/' . Str::uuid() . '.jpg';
        Storage::disk('public')->put($path, 'lampiran lama');

        $pesan->updateQuietly([
            'file_path' => null,
            'file_url'  => 'https://api.example.com/storage/' . $path,
        ]);

        $this->api()
            ->get("/api/berkas/chat/{$pesan->id}")
            ->assertOk();
    }

    // ── Pembatas laju ─────────────────────────────────────────────

    /**
     * Foto tidak boleh ikut jatah 60 per menit milik endpoint data biasa.
     *
     * Sebelum dipindah ke route berpenjaga, foto disajikan Nginx tanpa batas
     * sama sekali. Satu halaman daftar kunjungan memuat 30 petak sekaligus,
     * dan batasnya dihitung per alamat IP — sedangkan seluruh admin di kantor
     * keluar lewat satu IP yang sama. Dengan jatah 60, foto berhenti muncul
     * sebelum satu halaman selesai digulir.
     */
    public function test_foto_tidak_ikut_jatah_sempit_endpoint_biasa(): void
    {
        $foto = $this->buatFoto($this->driver);

        for ($ke = 1; $ke <= 80; $ke++) {
            $this->api()
                ->get("/api/berkas/bongkar/{$foto->id}")
                ->assertOk();
        }
    }

    /**
     * Saat batasnya benar-benar kena, jawabannya harus 429 yang bisa dibaca.
     *
     * Pembatas laju melempar HttpResponseException, yang bukan turunan
     * HttpException Symfony. Tanpa penanganan khusus di bootstrap/app.php, ia
     * lolos ke cabang terakhir dan jadi 500 berpesan kosong — batas laju yang
     * kena jadi kelihatan seperti server rusak, dan sebabnya tidak kelihatan
     * sama sekali di mana pun. Persis itu yang menyembunyikan masalah foto.
     */
    public function test_batas_laju_dijawab_429_bukan_500_kosong(): void
    {
        // `->response()` wajib ada di sini, persis seperti pembatas
        // sungguhannya. Tanpa itu Laravel melempar ThrottleRequestsException
        // — turunan HttpException yang sudah tertangani — dan ujinya jadi
        // hijau tanpa pernah menyentuh jalur yang sebenarnya rusak.
        RateLimiter::for('berkas', fn () => Limit::perMinute(2)
            ->by('uji')
            ->response(fn () => response()->json([
                'success' => false,
                'message' => 'Terlalu banyak permintaan gambar. Muat ulang halaman sebentar lagi.',
            ], 429)));

        $foto = $this->buatFoto($this->driver);

        $this->api()->get("/api/berkas/bongkar/{$foto->id}")->assertOk();
        $this->api()->get("/api/berkas/bongkar/{$foto->id}")->assertOk();

        $this->api()
            ->get("/api/berkas/bongkar/{$foto->id}")
            ->assertStatus(429)
            ->assertJsonPath('message', 'Terlalu banyak permintaan gambar. Muat ulang halaman sebentar lagi.');
    }

    // ── Perkakas ──────────────────────────────────────────────────

    private function api(?string $token = null): self
    {
        // Guard menyimpan user yang sudah terlanjur diresolusi dalam satu test,
        // jadi token kedua akan diabaikan diam-diam tanpa ini.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $this->token));
    }

    private function buatDriver(string $kode, string $nama): drivers
    {
        return drivers::create([
            'kode_driver'  => $kode,
            'nama_driver'  => $nama,
            'no_telepon'   => '08120000' . random_int(1000, 9999),
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
            'password'     => 'rahasia123',
            'mobile_aktif' => true,
        ]);
    }

    private function buatFoto(drivers $driver, bool $adaBerkas = true): BongkarFoto
    {
        $kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S ' . random_int(1000, 9999) . ' AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);

        $muat = DaftarMuat::create([
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'kendaraan_id'     => $kendaraan->id,
            'driver_id'        => $driver->id,
            'total_muatan_ton' => 8,
            'jenis_muatan'     => 'non_subsidi',
        ]);

        $item = DaftarMuatItem::create([
            'daftar_muat_id'    => $muat->id,
            'urutan'            => 1,
            'lokasi_pengiriman' => 'Anto (Barokah Tani)',
            'muatan_ton'        => 8,
        ]);

        $path = 'bongkar/' . $muat->id . '/' . Str::uuid() . '.jpg';

        if ($adaBerkas) {
            Storage::disk('local')->put($path, 'isi foto');
        }

        return BongkarFoto::create([
            'daftar_muat_id'      => $muat->id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $driver->id,
            'urutan_snapshot'     => 1,
            'lokasi_snapshot'     => 'Anto (Barokah Tani)',
            'jenis'               => 'bongkar',
            'foto'                => $path,
            'client_uuid'         => (string) Str::uuid(),
        ]);
    }

    private function buatPesanGrup(drivers $driver, bool $adaBerkas = true): ChatMessage
    {
        $grupId = 77;

        ChatGroupMember::create([
            'group_id'    => $grupId,
            'member_type' => drivers::class,
            'member_id'   => $driver->id,
        ]);

        $path = 'chat-images/' . Str::uuid() . '.jpg';

        if ($adaBerkas) {
            Storage::disk('local')->put($path, 'isi lampiran');
        }

        return ChatMessage::create([
            'sender_type'  => drivers::class,
            'sender_id'    => $driver->id,
            'group_id'     => $grupId,
            'type'         => 'group',
            'message_type' => 'image',
            'message'      => 'bukti bongkar',
            'file_path'    => $path,
        ]);
    }
}
