<?php

namespace Tests\Feature\Driver;

use App\Models\BongkarFoto;
use App\Models\ChatGroup;
use App\Models\ChatMessage;
use App\Models\DaftarMuat;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Models\kendaraans;
use App\Services\Driver\LaporanKeGrupChat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penerusan laporan bongkar ke grup chat harus tahan diulang.
 *
 * Job-nya punya tiga percobaan. Yang dijaga di sini: percobaan kedua tidak
 * menggandakan pesan yang sudah sampai, dan tidak juga menyerah — ia
 * melanjutkan dari foto yang belum terkirim.
 */
class LaporanKeGrupChatTest extends TestCase
{
    use RefreshDatabase;

    private drivers $driver;
    private ChatGroup $grup;

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

        $this->grup = ChatGroup::create([
            'nama'         => 'Laporan Bongkar',
            'creator_type' => $this->driver->getMorphClass(),
            'creator_id'   => $this->driver->id,
        ]);

        config(['driver.grup_laporan_id' => $this->grup->id]);
    }

    private function tujuanSelesai(int $jumlahFoto): DaftarMuatItem
    {
        $kendaraan = kendaraans::create([
            'jenis_kendaraan'      => 'Truk Besar',
            'nomor_polisi'         => 'S 1234 AB',
            'merk'                 => 'Hino',
            'tahun_pembuatan'      => 2020,
            'kapasitas_muatan_ton' => 8,
            'status_kendaraan'     => 'Tersedia',
        ]);

        $muat = DaftarMuat::create([
            'kendaraan_id'     => $kendaraan->id,
            'driver_id'        => $this->driver->id,
            'tanggal_muatan'   => now()->toDateString(),
            'nomor_rit'        => 1,
            'jenis_muatan'     => 'non_subsidi',
            'total_muatan_ton' => 5,
        ]);

        $item = DaftarMuatItem::create([
            'daftar_muat_id'    => $muat->id,
            'urutan'            => 1,
            'lokasi_pengiriman' => 'Toko Makmur',
            'jenis_muatan'      => 'non_subsidi',
            'kode_area'         => 'BJN',
            'status_bongkar'    => 'Selesai',
            'catatan_driver'    => 'Barang diterima penuh',
        ]);

        for ($i = 1; $i <= $jumlahFoto; $i++) {
            BongkarFoto::create([
                'daftar_muat_id'      => $muat->id,
                'daftar_muat_item_id' => $item->id,
                'driver_id'           => $this->driver->id,
                'urutan_snapshot'     => 1,
                'lokasi_snapshot'     => 'Toko Makmur',
                'foto'                => "bongkar/foto-{$i}.jpg",
                'thumb'               => "bongkar/thumb/foto-{$i}.jpg",
                'client_uuid'         => "uuid-{$i}",
            ]);
        }

        return $item->refresh();
    }

    private function pesanGrup()
    {
        return ChatMessage::where('group_id', $this->grup->id)->orderBy('id')->get();
    }

    public function test_laporan_dikirim_sekali_dengan_semua_fotonya(): void
    {
        $item = $this->tujuanSelesai(3);

        app(LaporanKeGrupChat::class)->kirim($item, $this->driver);

        $pesan = $this->pesanGrup();

        $this->assertCount(3, $pesan);
        $this->assertStringContainsString('Toko Makmur', $pesan[0]->message);
        $this->assertStringContainsString('Barang diterima penuh', $pesan[0]->message);
        $this->assertSame('', $pesan[1]->message);
        $this->assertSame('', $pesan[2]->message);
    }

    public function test_pengiriman_ulang_tidak_menggandakan_pesan(): void
    {
        $item = $this->tujuanSelesai(3);
        $penerus = app(LaporanKeGrupChat::class);

        $penerus->kirim($item, $this->driver);
        $penerus->kirim($item, $this->driver);

        $this->assertCount(3, $this->pesanGrup());
    }

    /**
     * Inti perbaikannya: dulu kunci cache sehari dipasang sebelum foto pertama
     * dikirim dan tidak pernah dilepas, jadi job yang gagal di tengah
     * meninggalkan laporan setengah jadi di grup selamanya.
     */
    public function test_percobaan_ulang_melanjutkan_foto_yang_belum_terkirim(): void
    {
        $item = $this->tujuanSelesai(3);

        // Foto pertama saja yang sempat sampai sebelum job gagal.
        ChatMessage::create([
            'sender_type'  => $this->driver->getMorphClass(),
            'sender_id'    => $this->driver->id,
            'type'         => 'group',
            'group_id'     => $this->grup->id,
            'message_type' => 'image',
            'message'      => "Toko Makmur — Selesai\nRit 1 · Sukardi Wijaya · S 1234 AB\nCatatan: Barang diterima penuh",
            'file_path'    => 'bongkar/foto-1.jpg',
        ]);

        app(LaporanKeGrupChat::class)->kirim($item, $this->driver);

        $pesan = $this->pesanGrup();

        $this->assertCount(3, $pesan);
        $this->assertSame(
            ['bongkar/foto-1.jpg', 'bongkar/foto-2.jpg', 'bongkar/foto-3.jpg'],
            $pesan->pluck('file_path')->all(),
        );
        // Keterangannya tidak diulang — sudah dibawa foto pertama.
        $this->assertSame('', $pesan[1]->message);
        $this->assertSame('', $pesan[2]->message);
    }

    /**
     * Gelembung chat lebarnya cuma 300px. Tanpa kolom ini ia memuat berkas
     * ukuran penuh, dan dua puluh foto sekali gulir berarti megabyte kuota
     * sopir.
     */
    public function test_thumbnail_foto_ikut_dibawa_ke_pesan_chat(): void
    {
        $item = $this->tujuanSelesai(2);

        app(LaporanKeGrupChat::class)->kirim($item, $this->driver);

        $this->assertSame(
            ['bongkar/thumb/foto-1.jpg', 'bongkar/thumb/foto-2.jpg'],
            $this->pesanGrup()->pluck('thumb_path')->all(),
        );
    }

    public function test_penerusan_mati_saat_grup_tidak_dikonfigurasi(): void
    {
        config(['driver.grup_laporan_id' => null]);

        $item = $this->tujuanSelesai(2);

        $this->assertFalse(app(LaporanKeGrupChat::class)->kirim($item, $this->driver));
        $this->assertCount(0, $this->pesanGrup());
    }
}
