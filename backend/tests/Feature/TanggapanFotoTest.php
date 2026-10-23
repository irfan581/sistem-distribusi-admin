<?php

namespace Tests\Feature;

use App\Jobs\KirimTanggapanFotoJob;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\KunjunganToko;
use App\Models\NotifikasiSales;
use App\Models\Sales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Admin menanggapi foto kunjungan sales dan laporan driver per tujuan:
 * diterima/ditolak + catatan, lalu sales/driver diberi tahu.
 */
class TanggapanFotoTest extends TestCase
{
    use RefreshDatabase;

    private Sales $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->sales = Sales::create([
            'nik' => '3521' . random_int(1000000000, 9999999999), 'nama' => 'Sales Uji', 'area' => 'Madiun', 'password' => 'rahasia123',
        ]);
    }

    private function admin(array $izin): User
    {
        return User::create([
            'name' => 'Admin Uji', 'email' => uniqid() . '@uji.test', 'password' => 'rahasia123', 'is_admin' => true, 'permissions' => $izin,
        ]);
    }

    private function sebagai(User $u): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer ' . $u->createToken('admin')->plainTextToken);
    }

    private function kunjungan(array $atribut = []): KunjunganToko
    {
        return KunjunganToko::create(array_merge([
            'sales_id' => $this->sales->id, 'nama_toko' => 'Toko Uji', 'location' => '-7.6,111.5', 'foto_kunjungan' => 'kunjungan/uji.jpg',
        ], $atribut));
    }

    private function rit(string $jenis = 'non_subsidi', string $status = 'Selesai'): DaftarMuat
    {
        $driver = drivers::create([
            'kode_driver' => 'DRV-' . random_int(100, 999), 'nama_driver' => 'Sopir Uji', 'no_telepon' => '0812' . random_int(1000000, 9999999),
            'alamat' => 'Madiun', 'status_kerja' => 'Aktif', 'password' => 'rahasia123',
        ]);
        $muat = DaftarMuat::create([
            'driver_id' => $driver->id, 'tanggal_muatan' => now()->toDateString(), 'nomor_rit' => 1,
            'jenis_muatan' => $jenis, 'total_muatan_ton' => 5,
        ]);
        DaftarMuatItem::create([
            'daftar_muat_id' => $muat->id, 'urutan' => 1, 'lokasi_pengiriman' => 'Toko Tujuan', 'jenis_muatan' => $jenis, 'status_bongkar' => $status,
        ]);

        return $muat->fresh('items');
    }

    public function test_admin_menerima_foto_kunjungan_dan_sales_diberi_tahu(): void
    {
        $k = $this->kunjungan();

        $this->sebagai($this->admin(['view_kunjungan', 'edit_kunjungan']))
            ->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'diterima'])
            ->assertOk()->assertJsonPath('data.status', 'diterima')->assertJsonPath('data.oleh', 'Admin Uji');

        $this->assertSame('diterima', $k->fresh()->tanggapan_status);
        Queue::assertPushed(KirimTanggapanFotoJob::class, fn ($job) => $job->sumber->is($k));
    }

    public function test_menolak_wajib_menyertakan_alasan(): void
    {
        $k = $this->kunjungan();
        $admin = $this->admin(['view_kunjungan', 'edit_kunjungan']);

        $this->sebagai($admin)->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'ditolak'])
            ->assertStatus(422)->assertJsonValidationErrors('catatan');

        $this->sebagai($admin)->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'ditolak', 'catatan' => 'Foto buram'])
            ->assertOk();
        $this->assertSame('Foto buram', $k->fresh()->tanggapan_catatan);
    }

    public function test_tanpa_hak_edit_kunjungan_ditolak(): void
    {
        $k = $this->kunjungan();

        $this->sebagai($this->admin(['view_kunjungan']))
            ->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'diterima'])
            ->assertForbidden();
        Queue::assertNotPushed(KirimTanggapanFotoJob::class);
        $this->assertNull($k->fresh()->tanggapan_status);
    }

    public function test_tanggapan_sama_dikirim_ulang_tidak_mengirim_notifikasi_lagi(): void
    {
        $k = $this->kunjungan();
        $admin = $this->admin(['view_kunjungan', 'edit_kunjungan']);

        $this->sebagai($admin)->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'diterima'])->assertOk();
        $this->sebagai($admin)->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'diterima'])->assertOk();

        Queue::assertPushed(KirimTanggapanFotoJob::class, 1);
    }

    public function test_data_lama_tidak_dihitung_belum_ditanggapi(): void
    {
        $this->kunjungan();
        $this->kunjungan(['perlu_tanggapan' => false]);      // seperti data sebelum fitur dipasang
        $this->kunjungan(['tanggapan_status' => 'diterima']);

        $this->sebagai($this->admin(['view_kunjungan']))
            ->getJson('/api/admin/tanggapan/ringkasan')
            ->assertOk()->assertJsonPath('data.kunjungan', 1);

        $this->sebagai($this->admin(['view_kunjungan']))
            ->getJson('/api/admin/kunjungan?tanggapan=belum')
            ->assertOk()->assertJsonPath('data.total', 1);
    }

    public function test_migrasi_menandai_data_lama_tidak_perlu_ditanggapi(): void
    {
        // RefreshDatabase menjalankan migrasi pada tabel kosong; jalankan ulang up() pada data yang sudah ada.
        $migrasi = require database_path('migrations/2026_10_23_090000_add_tanggapan_to_kunjungan_dan_tujuan.php');
        $k = $this->kunjungan();
        $migrasi->down();
        $migrasi->up();

        $this->assertFalse((bool) \DB::table('kunjungan_tokos')->where('id', $k->id)->value('perlu_tanggapan'));
        $this->assertTrue($this->kunjungan(['nama_toko' => 'Baru'])->fresh()->perlu_tanggapan);
    }

    public function test_admin_menanggapi_laporan_driver_per_tujuan(): void
    {
        $muat = $this->rit();
        $item = $muat->items->first();

        $this->sebagai($this->admin(['view_pengiriman', 'edit_pengiriman', 'muat_non_subsidi']))
            ->postJson("/api/admin/pengiriman/daftar-muat/{$muat->id}/item/{$item->id}/tanggapan", ['status' => 'ditolak', 'catatan' => 'Surat jalan tidak terbaca'])
            ->assertOk()->assertJsonPath('data.status', 'ditolak');

        Queue::assertPushed(KirimTanggapanFotoJob::class, fn ($job) => $job->sumber->is($item));
    }

    public function test_tujuan_yang_belum_dilaporkan_tidak_bisa_ditanggapi(): void
    {
        $muat = $this->rit(status: 'Menunggu');

        $this->sebagai($this->admin(['view_pengiriman', 'edit_pengiriman', 'muat_non_subsidi']))
            ->postJson("/api/admin/pengiriman/daftar-muat/{$muat->id}/item/{$muat->items->first()->id}/tanggapan", ['status' => 'diterima'])
            ->assertStatus(422);
    }

    public function test_admin_tidak_bisa_menanggapi_rit_jenis_muatan_lain(): void
    {
        $muat = $this->rit('subsidi');

        $this->sebagai($this->admin(['view_pengiriman', 'edit_pengiriman', 'muat_non_subsidi']))
            ->postJson("/api/admin/pengiriman/daftar-muat/{$muat->id}/item/{$muat->items->first()->id}/tanggapan", ['status' => 'diterima'])
            ->assertNotFound();
    }

    public function test_ringkasan_laporan_hanya_jenis_muatan_yang_dipegang(): void
    {
        $this->rit('non_subsidi');
        $this->rit('subsidi');
        $this->rit('non_subsidi', 'Tiba');   // belum dilaporkan, tidak dihitung

        $this->sebagai($this->admin(['view_pengiriman', 'muat_non_subsidi']))
            ->getJson('/api/admin/tanggapan/ringkasan')
            ->assertOk()->assertJsonPath('data.laporan', 1)->assertJsonPath('data.kunjungan', 0);
    }

    public function test_notifikasi_sales_tersimpan_dengan_isi_tanggapan(): void
    {
        $k = $this->kunjungan();
        $k->tanggapi($this->admin([]), 'ditolak', 'Bukan di toko');

        (new KirimTanggapanFotoJob($k->fresh()))->handle();

        $n = NotifikasiSales::where('sales_id', $this->sales->id)->latest('id')->first();
        $this->assertSame('Foto kunjungan ditolak', $n->title);
        $this->assertStringContainsString('Bukan di toko', $n->message);
    }

    public function test_riwayat_kunjungan_di_aplikasi_sales_membawa_tanggapan(): void
    {
        $k = $this->kunjungan();
        $k->tanggapi($this->admin([]), 'ditolak', 'Bukan di toko');
        $this->kunjungan(['nama_toko' => 'Toko Baru']);

        $this->sales->update(['is_active' => true]);
        $token = $this->sales->createToken('sales_auth_token')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $data = collect($this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/riwayat-kunjungan')
            ->assertOk()->json('data'))->keyBy('nama_toko');

        $this->assertSame(['perlu' => true, 'status' => 'ditolak', 'catatan' => 'Bukan di toko'],
            array_intersect_key($data['Toko Uji']['tanggapan'], array_flip(['perlu', 'status', 'catatan'])));
        $this->assertTrue($data['Toko Baru']['tanggapan']['perlu']);
        $this->assertNull($data['Toko Baru']['tanggapan']['status']);
        // Nama admin tidak dikirim ke aplikasi sales.
        $this->assertArrayNotHasKey('oleh', $data['Toko Uji']['tanggapan']);
    }

    private function kirimKunjunganDariAplikasi(array $tambahan = []): \Illuminate\Testing\TestResponse
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->mock(\App\Services\GeocodingService::class)->shouldReceive('reverseGeocode')->andReturn('Madiun');

        $this->sales->update(['is_active' => true]);
        // Kunjungan hanya boleh setelah absen masuk hari ini.
        \App\Models\Presensi::firstOrCreate(
            ['sales_id' => $this->sales->id, 'tanggal' => now('Asia/Jakarta')->toDateString()],
            ['jam_masuk' => '07:00:00', 'status' => 'hadir', 'location_masuk' => '-7.6298,111.4522'],
        );
        $token = $this->sales->createToken('sales_auth_token')->plainTextToken;
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}")->post('/api/kunjungan-toko', array_merge([
            'nama_toko'      => 'Toko Aplikasi ' . uniqid(),
            'location'       => '-7.6298,111.4522',
            'accuracy'       => 12,
            'foto_kunjungan' => \Illuminate\Http\UploadedFile::fake()->image('bukti.jpg', 640, 480),
        ], $tambahan), ['Accept' => 'application/json']);
    }

    public function test_kunjungan_dengan_lokasi_aman_otomatis_diterima_tanpa_notifikasi(): void
    {
        $this->kirimKunjunganDariAplikasi()->assertCreated()
            ->assertJsonPath('data.tanggapan.status', 'diterima')
            ->assertJsonPath('data.tanggapan.otomatis', true);

        $k = KunjunganToko::latest('id')->first();
        $this->assertSame('diterima', $k->tanggapan_status);
        $this->assertNull($k->ditanggapi_oleh);
        $this->assertSame(0, KunjunganToko::belumDitanggapi()->count());
        Queue::assertNotPushed(KirimTanggapanFotoJob::class);
    }

    public function test_kunjungan_dengan_fake_gps_menunggu_admin(): void
    {
        $this->kirimKunjunganDariAplikasi(['is_mock_location' => 1])->assertCreated()
            ->assertJsonPath('data.tanggapan.status', null)
            ->assertJsonPath('data.tanggapan.perlu', true);

        $this->assertSame(1, KunjunganToko::belumDitanggapi()->count());
    }

    public function test_admin_tetap_bisa_menolak_yang_diterima_otomatis(): void
    {
        $this->kirimKunjunganDariAplikasi()->assertCreated();
        $k = KunjunganToko::latest('id')->first();

        $this->sebagai($this->admin(['view_kunjungan', 'edit_kunjungan']))
            ->postJson("/api/admin/kunjungan/{$k->id}/tanggapan", ['status' => 'ditolak', 'catatan' => 'Bukan foto toko'])
            ->assertOk()->assertJsonPath('data.otomatis', false)->assertJsonPath('data.oleh', 'Admin Uji');

        Queue::assertPushed(KirimTanggapanFotoJob::class);
    }

    public function test_laporan_driver_dan_daftar_rit_menampilkan_tanggapan(): void
    {
        $muat = $this->rit();
        $admin = $this->admin(['view_pengiriman', 'edit_pengiriman', 'muat_non_subsidi']);

        $this->sebagai($admin)->getJson('/api/admin/pengiriman/daftar-muat?tanggapan=belum')
            ->assertOk()->assertJsonPath('data.0.tujuan_belum_ditanggapi', 1);

        $muat->items->first()->tanggapi($admin, 'diterima', null);

        $this->sebagai($admin)->getJson("/api/admin/pengiriman/daftar-muat/{$muat->id}/laporan-driver")
            ->assertOk()
            ->assertJsonPath('data.tujuan.0.tanggapan.status', 'diterima')
            ->assertJsonPath('data.ringkasan.belum_ditanggapi', 0);
    }
}
