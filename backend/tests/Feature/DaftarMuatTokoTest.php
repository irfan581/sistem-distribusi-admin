<?php

namespace Tests\Feature;

use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\KunjunganToko;
use App\Models\Sales;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DaftarMuatTokoTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions = ['create_pengiriman', 'edit_pengiriman', 'view_pengiriman', 'muat_subsidi', 'muat_non_subsidi']): User
    {
        return User::create([
            'name'        => 'Admin Uji',
            'email'       => 'admin@uji.test',
            'password'    => 'rahasia123',
            'is_admin'    => true,
            'permissions' => $permissions,
        ]);
    }

    private function sebagaiAdmin(array $permissions = ['create_pengiriman', 'edit_pengiriman', 'view_pengiriman', 'muat_subsidi', 'muat_non_subsidi']): self
    {
        $this->app['auth']->forgetGuards();
        $token = $this->admin($permissions)->createToken('admin')->plainTextToken;

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /**
     * Toko dengan riwayat kunjungan sales: dua kunjungan, alamat yang dipakai
     * harus yang paling baru — membuktikan resolusi tidak asal ambil baris
     * pertama.
     */
    private function tokoDenganKunjungan(): Toko
    {
        $sales = Sales::create([
            'nik'      => '3521' . random_int(1000000000, 9999999999),
            'nama'     => 'Sales Uji',
            'area'     => 'Bojonegoro',
            'password' => 'rahasia123',
        ]);

        $toko = Toko::create([
            'sales_id'  => $sales->id,
            'nama_kios' => 'Toko Makmur Jaya',
            'wilayah'   => 'Bojonegoro',
        ]);

        KunjunganToko::create([
            'sales_id'   => $sales->id,
            'toko_id'    => $toko->id,
            'nama_toko'  => $toko->nama_kios,
            'location'   => '-7.1500000,111.8800000',
            'alamat'     => 'Alamat lama, sebelum toko pindah',
            'lat'        => -7.1500000,
            'lng'        => 111.8800000,
            'created_at' => now()->subDays(10),
        ]);

        KunjunganToko::create([
            'sales_id'   => $sales->id,
            'toko_id'    => $toko->id,
            'nama_toko'  => $toko->nama_kios,
            'location'   => '-7.1503300,111.8817500',
            'alamat'     => 'Jl. Merdeka No. 10, Bojonegoro',
            'lat'        => -7.1503300,
            'lng'        => 111.8817500,
            'created_at' => now(),
        ]);

        return $toko;
    }

    /**
     * Kode area terisi sendiri dari nama wilayah tokonya.
     *
     * Dulu ini dibaca dari relasi ke tabel `wilayahs`. Tabel itu dilepas
     * September 2026 bersama penandaan jauh/dekat yang tidak pernah dipakai,
     * dan kode areanya kini dicocokkan langsung dari kolom teks `wilayah` di
     * master toko.
     *
     * Dijaga di sini karena kalau pencocokan itu diam-diam rusak, gejalanya
     * bukan galat melainkan kode area yang kosong — persis keadaan yang
     * hendak diperbaiki dulu, saat seperempat kiriman di produksi tidak bisa
     * dikategorikan sama sekali.
     */
    public function test_kode_area_terisi_dari_wilayah_toko(): void
    {
        $toko = $this->tokoDenganKunjungan();

        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['toko_id' => $toko->id, 'lokasi_pengiriman' => $toko->nama_kios],
            ],
        ])->assertCreated();

        $this->assertSame('BJN', DaftarMuatItem::firstOrFail()->kode_area);
    }

    /** Isian admin menang atas pencocokan otomatis. */
    public function test_kode_area_yang_diisi_admin_tidak_ditimpa(): void
    {
        $toko = $this->tokoDenganKunjungan();

        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['toko_id' => $toko->id, 'lokasi_pengiriman' => $toko->nama_kios, 'kode_area' => 'NGW'],
            ],
        ])->assertCreated();

        $this->assertSame('NGW', DaftarMuatItem::firstOrFail()->kode_area);
    }

    public function test_pilih_toko_langsung_mengisi_alamat_dari_kunjungan_terakhir(): void
    {
        $toko = $this->tokoDenganKunjungan();

        $response = $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['toko_id' => $toko->id, 'lokasi_pengiriman' => $toko->nama_kios],
            ],
        ]);

        $response->assertCreated();

        $item = DaftarMuatItem::firstOrFail();
        $this->assertSame($toko->id, $item->toko_id);
        $this->assertSame('Jl. Merdeka No. 10, Bojonegoro', $item->alamat_otomatis);
        $this->assertEqualsWithDelta(-7.1503300, (float) $item->alamat_lat, 0.0000001);
        $this->assertEqualsWithDelta(111.8817500, (float) $item->alamat_lng, 0.0000001);
        $this->assertNotNull($item->alamat_resolved_at);
    }

    public function test_toko_id_wajib_untuk_tujuan_non_subsidi(): void
    {
        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['lokasi_pengiriman' => 'Toko Ketikan Bebas'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.toko_id']);
    }

    public function test_toko_id_tidak_valid_ditolak(): void
    {
        $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['toko_id' => 999999, 'lokasi_pengiriman' => 'Toko Tidak Ada'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['items.0.toko_id']);
    }

    public function test_tujuan_subsidi_tetap_teks_bebas_tanpa_toko_id(): void
    {
        $response = $this->sebagaiAdmin()->postJson('/api/admin/pengiriman/daftar-muat', [
            'tanggal_muatan'   => now()->toDateString(),
            'jenis_muatan'     => 'subsidi',
            'total_muatan_ton' => 5,
            'items'            => [
                ['lokasi_pengiriman' => 'Kelompok Tani Subur Makmur'],
            ],
        ]);

        $response->assertCreated();

        $item = DaftarMuatItem::firstOrFail();
        $this->assertNull($item->toko_id);
        $this->assertSame('Kelompok Tani Subur Makmur', $item->lokasi_pengiriman);
        $this->assertNull($item->alamat_otomatis);
    }

    public function test_pencarian_toko_untuk_daftar_muat_menyertakan_alamat_terakhir(): void
    {
        $toko = $this->tokoDenganKunjungan();

        $response = $this->sebagaiAdmin()
            ->getJson('/api/admin/pengiriman/toko-tujuan?search=Makmur')
            ->assertOk();

        $response->assertJsonFragment([
            'id'              => $toko->id,
            'nama_kios'       => $toko->nama_kios,
            'alamat_terakhir' => 'Jl. Merdeka No. 10, Bojonegoro',
        ]);
    }
}
