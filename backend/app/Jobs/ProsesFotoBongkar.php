<?php

namespace App\Jobs;

use App\Models\BongkarFoto;
use App\Services\Driver\ThumbnailBongkarService;
use App\Services\GeocodingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Pekerjaan lanjutan setelah foto bukti diterima: pindahkan berkasnya ke folder
 * tetap, buatkan versi kecilnya, dan lengkapi alamatnya. Ketiganya ditunda
 * supaya request unggah dari HP driver di jalan bisa langsung dijawab.
 */
class ProsesFotoBongkar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = 10;

    public function __construct(private readonly int $fotoId) {}

    public function handle(GeocodingService $geocoding, ThumbnailBongkarService $thumbnail): void
    {
        $foto = BongkarFoto::find($this->fotoId);

        if (!$foto instanceof BongkarFoto) {
            Log::warning("ProsesFotoBongkar: foto {$this->fotoId} tidak ditemukan.");
            return;
        }

        $this->pindahkanBerkas($foto);

        // Dibuat setelah berkasnya pindah supaya thumbnail tidak ikut menunjuk
        // ke folder temp yang akan dibersihkan.
        $thumbnail->buat($foto);

        // Digarap setelah pemindahan supaya Nominatim yang lambat atau mati
        // tidak menahan berkas di folder temp.
        if ($foto->alamat === null) {
            $alamat = $geocoding->reverseGeocode($foto->latitude, $foto->longitude);

            if ($alamat !== null) {
                $foto->updateQuietly(['alamat' => $alamat]);
            }
        }
    }

    private function pindahkanBerkas(BongkarFoto $foto): void
    {
        if (!str_starts_with((string) $foto->foto, 'temp-bongkar/')) {
            // Sudah dipindahkan percobaan sebelumnya. Job boleh diulang.
            return;
        }

        $disk = Storage::disk('local');

        if (!$disk->exists($foto->foto)) {
            $foto->updateQuietly(['status_proses' => 'gagal']);
            Log::warning("ProsesFotoBongkar: berkas {$foto->foto} hilang, foto {$foto->id} ditandai gagal.");
            return;
        }

        // Dikelompokkan per rit supaya satu folder tidak menampung puluhan ribu
        // berkas dan admin bisa menelusuri bukti satu rit sekaligus.
        $tujuan = "bongkar/{$foto->daftar_muat_id}/" . basename($foto->foto);

        $disk->makeDirectory("bongkar/{$foto->daftar_muat_id}");
        $disk->move($foto->foto, $tujuan);

        $foto->updateQuietly([
            'foto'          => $tujuan,
            'status_proses' => 'siap',
        ]);
    }
}
