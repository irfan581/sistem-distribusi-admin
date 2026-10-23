<?php

namespace App\Jobs;

use App\Models\KunjunganToko;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessKunjungan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = 10;

    protected int    $kunjunganId;
    protected string $tempPath;

    // ✅ FIX: Terima kunjunganId + tempPath, bukan array data penuh
    // Sebelum: construct($data) → handle() → KunjunganToko::create() → record sudah ada di DB = duplicate!
    // Sesudah: KunjunganToko sudah di DB via Service, Job hanya pindah foto
    public function __construct(int $kunjunganId, string $tempPath)
    {
        $this->kunjunganId = $kunjunganId;
        $this->tempPath    = $tempPath;
    }

    public function handle(): void
    {
        try {
            if (!str_contains($this->tempPath, 'temp-kunjungan/')) {
                Log::info("ProcessKunjungan: Path bukan temp, skip. ID {$this->kunjunganId}");
                return;
            }

            /** @var KunjunganToko|null $kunjungan */
            $kunjungan = KunjunganToko::find($this->kunjunganId);

            if (!$kunjungan instanceof KunjunganToko) {
                Log::warning("ProcessKunjungan: ID {$this->kunjunganId} tidak ditemukan.");
                return;
            }

            $newPath = str_replace('temp-kunjungan/', 'kunjungan/', $this->tempPath);

            if (Storage::disk('local')->exists($this->tempPath)) {
                Storage::disk('local')->makeDirectory('kunjungan');
                Storage::disk('local')->move($this->tempPath, $newPath);
                $kunjungan->updateQuietly(['foto_kunjungan' => $newPath]);
                Log::info("ProcessKunjungan: Foto dipindahkan → {$newPath}");
            } else {
                // File tidak ada — kosongkan path agar Flutter tampilkan placeholder
                $kunjungan->updateQuietly(['foto_kunjungan' => '']);
                Log::warning("ProcessKunjungan: File tidak ditemukan di {$this->tempPath}, foto dikosongkan. ID {$this->kunjunganId}");
            }

        } catch (\Exception $e) {
            Log::error("ProcessKunjungan gagal ID {$this->kunjunganId}: " . $e->getMessage());
            throw $e;
        }
    }
}
