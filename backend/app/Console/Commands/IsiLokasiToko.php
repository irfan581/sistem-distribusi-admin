<?php

namespace App\Console\Commands;
use App\Models\Toko;
use App\Models\KunjunganToko;
use Illuminate\Console\Command;

class IsiLokasiToko extends Command
{

    protected $signature = 'toko:isi-lokasi {--coba : Hanya Menghitung, tidak menyimpan}';
    protected $description = 'Mengisi alamat dan koordinat toko dari kunjungan yang valid';


    public function handle(): int
    {
        $terisi = 0;
        $kosong = 0;

        Toko::query()->orderBy('id')->chunkById(200, function ($tokos) use (&$terisi, &$kosong) {
            foreach ($tokos as $toko) {
                $kunjungan = KunjunganToko::where('toko_id', $toko->id)
                    ->whereNotNull('lat')
                    ->whereNotNull('lng')
                    ->where('is_suspicious', false)
                    ->where(fn ($q) => $q->whereNull('accuracy')->orWhere('accuracy', '<=', 200))
                    ->latest('created_at')
                    ->first();

                if  (! $kunjungan || $toko->lokasi_sumber === 'manual') {
                    $kosong++;
                    continue;
                }

                if (! $this->option('coba')) {
                    $toko->isiLokasiDari($kunjungan);
                }
                $terisi++;
            }
        });

        $this->info(($this->option('coba') ? '[COBA] ' : '') . "Selesai. Terisi: {$terisi}, Kosong: {$kosong}");

        return self::SUCCESS;
    }
}
