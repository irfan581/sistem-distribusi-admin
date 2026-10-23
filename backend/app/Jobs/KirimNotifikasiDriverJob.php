<?php

namespace App\Jobs;

use App\Models\DaftarMuat;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Memberi tahu driver lewat notifikasi HP bahwa ia mendapat muatan.
 */
class KirimNotifikasiDriverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const BATAS_NAMA_TUJUAN = 3;

    /** Channel dan nada milik aplikasi driver (ServisNotifikasi di Flutter). */
    public const CHANNEL = 'muatan_baru';
    public const NADA    = 'muatan_baru';

    protected $daftarMuat;

    public function __construct(DaftarMuat $daftarMuat)
    {
        $this->daftarMuat = $daftarMuat;

        // Tujuan disimpan sesudah rit; tanpa ini job jalan saat tujuan belum ada.
        $this->afterCommit();
    }

    public function handle(): void
    {
        $driver = $this->daftarMuat->driver;
        $pesan  = $this->isiPesan();

        if (!$driver || !$pesan) {
            return;
        }

        if (empty($driver->fcm_token)) {
            Log::info("Notifikasi muatan dilewati: driver {$driver->kode_driver} belum punya token FCM.");
            return;
        }

        FcmService::sendNotification(
            $driver->fcm_token,
            $pesan['judul'],
            $pesan['isi'],
            $pesan['data'],
            self::CHANNEL,
            self::NADA,
        );
    }

    /**
     * @return array{judul: string, isi: string, data: array<string, string>}|null
     *         null bila rit ini tidak perlu diberitahukan.
     */
    public function isiPesan(): ?array
    {
        $muat = $this->daftarMuat;

        if (!$muat->driver_id || in_array($muat->statusPerjalanan(), ['Selesai', 'Batal'], true)) {
            return null;
        }

        $tanggal = Carbon::parse($muat->tanggal_muatan)->locale('id')->translatedFormat('l, j F Y');
        $bagian  = ["Rit {$muat->nomor_rit}", $tanggal];

        if ($muat->kendaraan) {
            $bagian[] = $muat->kendaraan->nomor_polisi;
        }

        $isi = implode(' · ', $bagian);

        $tujuan = $muat->items()->pluck('lokasi_pengiriman')->filter()->values();
        if ($tujuan->isNotEmpty()) {
            $nama = $tujuan->take(self::BATAS_NAMA_TUJUAN)->implode(', ');
            $sisa = $tujuan->count() - self::BATAS_NAMA_TUJUAN;
            $isi .= "\n{$tujuan->count()} tujuan: {$nama}" . ($sisa > 0 ? ", +{$sisa} lagi" : '');
        }

        return [
            'judul' => 'Muatan baru untuk Anda',
            'isi'   => $isi,
            'data'  => [
                'type'           => 'muatan_baru',
                'daftar_muat_id' => (string) $muat->id,
            ],
        ];
    }
}
