<?php

namespace App\Services\Driver;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Support\SiaranChat;
use Illuminate\Support\Facades\Cache;

/**
 * Meneruskan laporan bongkar driver ke grup chat.
 *
 * Menggantikan kebiasaan kantor mengirim foto bongkar ke grup WhatsApp satu
 * per satu. Yang berubah bukan siapa yang melihat — sales, kantor, dan bos
 * tetap melihat semuanya — melainkan bahwa buktinya tidak lagi tenggelam di
 * antara obrolan, tidak hilang saat memori HP penuh, dan tetap terikat ke
 * ritnya sehingga bisa ditelusuri berbulan-bulan kemudian.
 *
 * Laporan yang aman ikut dikirim, bukan cuma yang bermasalah: di grup lama pun
 * sopir mengirim "toko A barang lengkap", dan itu yang membuat sales tahu
 * pekerjaan berjalan tanpa harus bertanya.
 */
class LaporanKeGrupChat
{
    /**
     * @return bool true bila laporan ini utuh di grup setelah pemanggilan.
     */
    public function kirim(DaftarMuatItem $item, drivers $driver): bool
    {
        $grupId = (int) config('driver.grup_laporan_id');

        if (empty($grupId)) {
            return false;
        }

        // Kunci ini cuma menjaga dua worker tidak mengerjakan tujuan yang sama
        // berbarengan, dan selalu dilepas setelah selesai. Yang mencegah pesan
        // berganda adalah pemeriksaan per pesan di bawah — bukan kunci ini.
        //
        // Dulu penandanya sebuah kunci cache berumur sehari yang dipasang di
        // awal dan tidak pernah dilepas. Itu memang mencegah pesan berganda,
        // tapi sekalian mematikan percobaan ulang: job yang gagal di foto
        // ketiga dari lima meninggalkan laporan setengah jadi di grup selamanya,
        // karena percobaan berikutnya berhenti di kunci yang sama.
        $kunci = Cache::lock("laporan-grup:{$item->id}", 120);

        if (! $kunci->get()) {
            return false;
        }

        try {
            $item->loadMissing(['daftarMuat.kendaraan', 'bongkarFotos']);

            $teks  = $this->teks($item, $driver);
            $fotos = $item->bongkarFotos;

            // Laporan tanpa foto praktis tidak ada — simpanLaporan menolaknya.
            // Kalau toh terjadi, teksnya sendiri yang jadi penanda: isinya
            // memuat nama toko, rit, sopir, dan catatannya, jadi tidak akan
            // sama dengan laporan tujuan lain.
            if ($fotos->isEmpty()) {
                if (! $this->sudahAda($grupId, $driver, 'text', null, $teks)) {
                    $this->pesan($grupId, $driver, 'text', $teks, null, null);
                }

                return true;
            }

            // Foto pertama membawa keterangannya supaya isi laporan dan buktinya
            // tampil sebagai satu hal, bukan dua pesan yang bisa terpisah saat
            // grup sedang ramai. Sisanya menyusul tanpa keterangan.
            //
            // Path foto unik per foto, jadi ia sekaligus jadi penanda "yang ini
            // sudah terkirim". Percobaan ulang melanjutkan dari foto yang belum
            // sampai, bukan mengulang dari awal atau menyerah.
            $pertama = true;

            foreach ($fotos as $foto) {
                if (! $this->sudahAda($grupId, $driver, 'image', $foto->foto)) {
                    // Thumbnail ikut dibawa supaya gelembung chat tidak memuat
                    // berkas ukuran penuh. Foto lama yang belum pernah
                    // dilewati `bongkar:thumbnail` mengirim null, dan chat
                    // jatuh kembali ke berkas aslinya.
                    $this->pesan($grupId, $driver, 'image', $pertama ? $teks : '', $foto->foto, $foto->thumb);
                }

                $pertama = false;
            }

            return true;
        } finally {
            $kunci->release();
        }
    }

    /**
     * Apakah satu pesan laporan ini sudah ada di grup.
     *
     * Dipakai supaya percobaan ulang job tidak menggandakan pesan yang sudah
     * sampai. Penandanya baris chat itu sendiri — bukan kunci cache yang bisa
     * kedaluwarsa atau hilang saat cache dibersihkan.
     */
    private function sudahAda(
        int $grupId,
        drivers $driver,
        string $jenis,
        ?string $berkas,
        ?string $teks = null,
    ): bool {
        $kueri = ChatMessage::query()
            ->where('type', 'group')
            ->where('group_id', $grupId)
            ->where('sender_type', $driver->getMorphClass())
            ->where('sender_id', $driver->id)
            ->where('message_type', $jenis);

        return $berkas !== null
            ? $kueri->where('file_path', $berkas)->exists()
            : $kueri->where('message', $teks)->exists();
    }

    private function pesan(
        int $grupId,
        drivers $driver,
        string $jenis,
        string $teks,
        ?string $berkas,
        ?string $thumb,
    ): void {
        $chat = ChatMessage::create([
            'sender_type'  => $driver->getMorphClass(),
            'sender_id'    => $driver->id,
            'type'         => 'group',
            'group_id'     => $grupId,
            'message_type' => $jenis,
            'message'      => $teks,
            'file_path'    => $berkas,
            'thumb_path'   => $thumb,
        ]);

        // Pesannya sudah tersimpan; siaran realtime cuma mempercepat munculnya
        // di layar orang yang sedang membuka chat. Kalau server Reverb mati,
        // yang hilang hanya kecepatan itu — pesannya tetap terbaca begitu chat
        // dibuka ulang.
        //
        // Wajib ditangkap: tanpa ini, Reverb yang mati membuat seluruh
        // pengiriman laporan driver gagal, dan sopir di depan toko tidak bisa
        // menutup tujuannya sama sekali. Lewat SiaranChat supaya seragam
        // dengan tiga belas titik siaran chat yang lain.
        SiaranChat::coba(
            fn () => broadcast(new ChatMessageSent($chat)),
            'laporan bongkar',
        );
    }

    /**
     * Bentuknya sengaja menyerupai yang biasa diketik sopir di grup WhatsApp:
     * nama toko, hasilnya, lalu keterangan. Bukan tabel data.
     */
    private function teks(DaftarMuatItem $item, drivers $driver): string
    {
        $rit    = $item->daftarMuat;
        $status = $item->status_bongkar === 'Selesai' ? 'Selesai' : 'BERMASALAH';

        $baris = [$item->lokasi_pengiriman . ': ' . $status];

        $konteks = array_filter([
            'Rit ' . (int) ($rit->nomor_rit ?? 1),
            $driver->nama_driver,
            $rit->kendaraan?->nomor_polisi,
        ]);
        $baris[] = implode(' · ', $konteks);

        if (filled($item->catatan_driver)) {
            $baris[] = 'Catatan: ' . $item->catatan_driver;
        }

        return implode("\n", $baris);
    }
}
