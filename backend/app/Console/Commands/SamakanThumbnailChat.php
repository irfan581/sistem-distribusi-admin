<?php

namespace App\Console\Commands;

use App\Models\BongkarFoto;
use App\Models\ChatMessage;
use Illuminate\Console\Command;

/**
 * Mengisi `thumb_path` pesan chat yang fotonya sudah punya thumbnail.
 *
 * Laporan bongkar yang diteruskan sebelum kolom ini ada tetap memuat berkas
 * ukuran penuh di gelembung chat selamanya, karena `bongkar:thumbnail` hanya
 * mengisi tabel `bongkar_fotos` dan tidak pernah menyentuh `chat_messages`.
 * Perintah ini menyambungkan keduanya lewat path fotonya.
 *
 * Jalankan setelah `bongkar:thumbnail`. Aman diulang: yang sudah terisi
 * dilewati, kecuali --paksa.
 */
class SamakanThumbnailChat extends Command
{
    protected $signature = 'chat:thumbnail
                            {--paksa : Isi ulang meski thumb_path-nya sudah ada}
                            {--kering : Hanya menghitung, tidak menyimpan}';

    protected $description = 'Menyalin thumbnail foto bongkar ke pesan chat yang sudah terlanjur terkirim';

    public function handle(): int
    {
        $pesan = ChatMessage::query()
            ->where('message_type', 'image')
            ->whereNotNull('file_path')
            ->when(! $this->option('paksa'), fn ($q) => $q->whereNull('thumb_path'));

        $total = $pesan->count();

        if ($total === 0) {
            $this->info('Tidak ada pesan chat yang perlu disamakan.');

            return self::SUCCESS;
        }

        $this->info("Memeriksa {$total} pesan bergambar…");
        $bar = $this->output->createProgressBar($total);

        $terisi = 0;
        $lewat  = 0;

        // Di-chunk karena tabel chat tumbuh terus; memuat semuanya sekaligus
        // akan menghabiskan memori di server produksi.
        $pesan->chunkById(200, function ($baris) use ($bar, &$terisi, &$lewat) {
            // Satu kueri per potongan, bukan satu per pesan.
            $thumbs = BongkarFoto::query()
                ->whereIn('foto', $baris->pluck('file_path')->all())
                ->whereNotNull('thumb')
                ->pluck('thumb', 'foto');

            foreach ($baris as $chat) {
                $thumb = $thumbs[$chat->file_path] ?? null;

                if ($thumb === null) {
                    // Gambar chat biasa, atau foto bongkar yang memang belum
                    // pernah dibuatkan thumbnail. Dua-duanya bukan kesalahan.
                    $lewat++;
                    $bar->advance();

                    continue;
                }

                if (! $this->option('kering')) {
                    // updateQuietly: menyentuh updated_at akan mengacak urutan
                    // dan penanda "baru" di daftar chat.
                    $chat->updateQuietly(['thumb_path' => $thumb]);
                }

                $terisi++;
                $bar->advance();
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        if ($this->option('kering')) {
            $this->info("Uji kering: {$terisi} pesan akan diisi, {$lewat} dilewati.");

            return self::SUCCESS;
        }

        $this->info("Selesai: {$terisi} pesan terisi, {$lewat} dilewati.");

        return self::SUCCESS;
    }
}
