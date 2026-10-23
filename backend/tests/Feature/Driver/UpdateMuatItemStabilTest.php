<?php

namespace Tests\Feature\Driver;

use App\Models\BongkarFoto;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use App\Services\DaftarMuatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * updateMuat() dulu menghapus semua tujuan lalu membuatnya ulang, sehingga id
 * item berganti setiap admin menyimpan. Sejak tujuan punya status bongkar dan
 * foto yang menempel padanya, id yang berganti berarti progres driver hilang.
 */
class UpdateMuatItemStabilTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DaftarMuatService
    {
        return app(DaftarMuatService::class);
    }

    private function driver(): drivers
    {
        return drivers::create([
            'kode_driver'  => 'DRV-001',
            'nama_driver'  => 'Sukardi Wijaya',
            'no_telepon'   => '081234567890',
            'alamat'       => 'Bojonegoro',
            'status_kerja' => 'Aktif',
        ]);
    }

    private function buatRit(array $lokasi, array $override = []): DaftarMuat
    {
        $kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S ' . random_int(1000, 9999) . ' AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);

        return $this->service()->prosesPendaftaranMuat(array_merge([
            'kendaraan_id'     => $kendaraan->id,
            'driver_id'        => $this->driver()->id,
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
            'items'            => array_map(fn ($l) => ['lokasi_pengiriman' => $l, 'kode_area' => 'BJN'], $lokasi),
        ], $override));
    }

    private function payload(DaftarMuat $muat, array $lokasi): array
    {
        return [
            'kendaraan_id'     => $muat->kendaraan_id,
            'driver_id'        => $muat->driver_id,
            'tanggal_muatan'   => $muat->tanggal_muatan,
            'nomor_rit'        => $muat->nomor_rit,
            'jenis_muatan'     => $muat->jenis_muatan,
            'total_muatan_ton' => $muat->total_muatan_ton,
            'items'            => array_map(fn ($l) => ['lokasi_pengiriman' => $l, 'kode_area' => 'BJN'], $lokasi),
        ];
    }

    private function fotoUntuk(DaftarMuatItem $item): BongkarFoto
    {
        return BongkarFoto::create([
            'daftar_muat_id'      => $item->daftar_muat_id,
            'daftar_muat_item_id' => $item->id,
            'driver_id'           => $item->daftarMuat->driver_id,
            'urutan_snapshot'     => $item->urutan,
            'lokasi_snapshot'     => $item->lokasi_pengiriman,
            'foto'                => 'bongkar/uji.jpg',
            'client_uuid'         => (string) Str::uuid(),
            'status_proses'       => 'siap',
        ]);
    }

    public function test_id_item_tidak_berubah_saat_nama_tujuan_dibetulkan(): void
    {
        $muat = $this->buatRit(['Toko Makmur', 'Toko Sejahtera']);
        $idAwal = $muat->items()->pluck('id')->all();

        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Makmur Jaya', 'Toko Sejahtera']));

        $this->assertSame($idAwal, $muat->fresh()->items()->pluck('id')->all());
        $this->assertSame('Toko Makmur Jaya', $muat->fresh()->items()->first()->lokasi_pengiriman);
    }

    public function test_progres_driver_bertahan_setelah_admin_menyimpan_ulang(): void
    {
        $muat = $this->buatRit(['Toko Makmur', 'Toko Sejahtera']);
        $item = $muat->items()->first();

        $item->update(['status_bongkar' => 'Selesai', 'bongkar_selesai_at' => now()]);
        $foto = $this->fotoUntuk($item);

        // Admin hanya membetulkan ejaan tujuan kedua.
        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Makmur', 'Toko Sejahtera Abadi']));

        $item->refresh();
        $this->assertSame('Selesai', $item->status_bongkar);
        $this->assertSame($item->id, $foto->fresh()->daftar_muat_item_id);
        $this->assertSame(1, $item->bongkarFotos()->count());
    }

    public function test_menambah_tujuan_tidak_mengganggu_yang_sudah_ada(): void
    {
        $muat = $this->buatRit(['Toko Makmur']);
        $idAwal = $muat->items()->first()->id;

        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Makmur', 'Toko Baru']));

        $items = $muat->fresh()->items;
        $this->assertCount(2, $items);
        $this->assertSame($idAwal, $items->first()->id);
        $this->assertSame([1, 2], $items->pluck('urutan')->all());
    }

    public function test_mengurangi_tujuan_menghapus_yang_di_ekor(): void
    {
        $muat = $this->buatRit(['Toko Makmur', 'Toko Sejahtera', 'Toko Ketiga']);

        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Makmur']));

        $this->assertCount(1, $muat->fresh()->items);
        $this->assertSame(0, DaftarMuatItem::where('daftar_muat_id', $muat->id)->where('urutan', '>', 1)->count());
    }

    public function test_tujuan_dengan_foto_bongkar_tidak_bisa_dihapus(): void
    {
        $muat = $this->buatRit(['Toko Makmur', 'Toko Sejahtera']);
        $this->fotoUntuk($muat->items()->where('urutan', 2)->first());

        $this->expectExceptionMessage('sudah punya foto bongkar');

        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Makmur']));
    }

    public function test_tujuan_tidak_bisa_diubah_saat_rit_sudah_berjalan(): void
    {
        $muat = $this->buatRit(['Toko Makmur']);
        $muat->update(['status_perjalanan' => 'Berangkat']);

        $this->expectExceptionMessage('Rit ini sudah berstatus "Berangkat"');

        $this->service()->updateMuat($muat, $this->payload($muat, ['Toko Lain']));
    }

    public function test_kendaraan_masih_boleh_diganti_pada_rit_yang_berjalan(): void
    {
        $muat = $this->buatRit(['Toko Makmur']);
        $muat->update(['status_perjalanan' => 'Berangkat']);

        $penggganti = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Kecil',
            'nomor_polisi'         => 'S 9999 ZZ',
            'merk'                 => 'Isuzu',
            'tahun_pembuatan'      => 2021,
            'kapasitas_muatan_ton' => 6,
            'status_kendaraan'     => 'Tersedia',
        ]);

        // Truk mogok di jalan lalu diganti. Daftar tujuannya tidak berubah,
        // jadi penyimpanan ini harus tetap diterima.
        $data = $this->payload($muat, ['Toko Makmur']);
        $data['kendaraan_id'] = $penggganti->id;

        $this->service()->updateMuat($muat, $data);

        $this->assertSame($penggganti->id, $muat->fresh()->kendaraan_id);
    }
}
