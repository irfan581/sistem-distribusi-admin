<?php

namespace App\Jobs;

use App\Models\DaftarMuatItem;
use App\Models\drivers;
use App\Services\Driver\LaporanKeGrupChat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Meneruskan satu laporan tujuan ke grup chat, di belakang layar.
 *
 * Ditunda ke antrian supaya HP driver yang sedang di depan toko dengan sinyal
 * seadanya tidak ikut menunggu pesan chat terkirim. Yang penting bagi driver
 * adalah laporannya tersimpan; sampainya ke grup boleh menyusul sedetik
 * kemudian.
 */
class TeruskanLaporanKeGrup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = 10;

    public function __construct(
        private readonly int $itemId,
        private readonly int $driverId,
    ) {}

    public function handle(LaporanKeGrupChat $penerus): void
    {
        $item   = DaftarMuatItem::find($this->itemId);
        $driver = drivers::find($this->driverId);

        if (! $item instanceof DaftarMuatItem || ! $driver instanceof drivers) {
            Log::warning("TeruskanLaporanKeGrup: tujuan {$this->itemId} atau driver {$this->driverId} tidak ditemukan.");

            return;
        }

        $penerus->kirim($item, $driver);
    }
}
