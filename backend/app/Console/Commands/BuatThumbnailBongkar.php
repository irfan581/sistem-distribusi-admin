<?php

namespace App\Console\Commands;

use App\Models\BongkarFoto;
use App\Services\Driver\ThumbnailBongkarService;
use Illuminate\Console\Command;

/**
 * Membuatkan thumbnail untuk foto bukti yang sudah terlanjur tersimpan sebelum
 * fitur ini ada. Foto baru sudah diurus ProsesFotoBongkar.
 *
 * Aman diulang: foto yang thumbnail-nya sudah ada dilewati, kecuali --paksa.
 */
class BuatThumbnailBongkar extends Command
{
    protected $signature = 'bongkar:thumbnail
                            {--paksa : Buat ulang meski thumbnail-nya sudah ada}
                            {--batas=0 : Berhenti setelah sekian foto (0 = semua)}';

    protected $description = 'Membuat thumbnail foto bukti bongkar yang belum punya';

    public function handle(ThumbnailBongkarService $thumbnail): int
    {
        $query = BongkarFoto::query()
            ->whereNotNull('foto')
            ->when(!$this->option('paksa'), fn ($q) => $q->whereNull('thumb'));

        $total = $query->count();

        if ($total === 0) {
            $this->info('Tidak ada foto yang perlu dibuatkan thumbnail.');
            return self::SUCCESS;
        }

        $batas = (int) $this->option('batas');
        if ($batas > 0) {
            $total = min($total, $batas);
        }

        $this->info("Memproses {$total} foto…");
        $bar = $this->output->createProgressBar($total);

        $berhasil = 0;
        $gagal    = 0;

        // Di-chunk karena tabel ini tumbuh terus dan memuat semuanya sekaligus
        // akan menghabiskan memori di server produksi.
        $query->chunkById(100, function ($fotos) use ($thumbnail, $bar, &$berhasil, &$gagal, $batas) {
            foreach ($fotos as $foto) {
                $thumbnail->buat($foto, (bool) $this->option('paksa')) ? $berhasil++ : $gagal++;
                $bar->advance();

                if ($batas > 0 && ($berhasil + $gagal) >= $batas) {
                    return false;
                }
            }

            return true;
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Selesai. Berhasil: {$berhasil}.");

        if ($gagal > 0) {
            // Umumnya karena berkas aslinya memang sudah hilang di server —
            // baris yang status_proses-nya 'gagal'. Bukan alasan untuk
            // menganggap perintah ini error.
            $this->warn("Dilewati karena berkasnya tidak terbaca: {$gagal}.");
        }

        return self::SUCCESS;
    }
}
