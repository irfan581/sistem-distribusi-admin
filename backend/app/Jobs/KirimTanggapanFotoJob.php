<?php

namespace App\Jobs;

use App\Models\DaftarMuatItem;
use App\Models\KunjunganToko;
use App\Models\NotifikasiSales;
use App\Services\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Memberi tahu sales atau driver bahwa admin menanggapi bukti fotonya.
 */
class KirimTanggapanFotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public KunjunganToko|DaftarMuatItem $sumber)
    {
        $this->afterCommit();
    }

    public function handle(): void
    {
        $pesan = $this->isiPesan();

        if ($this->sumber instanceof KunjunganToko) {
            $sales = $this->sumber->sales;
            if (! $sales) return;

            // Disimpan juga ke notifikasi_sales agar tampil di daftar notifikasi aplikasi.
            NotifikasiSales::create([
                'sales_id' => $sales->id, 'title' => $pesan['judul'], 'message' => $pesan['isi'], 'is_read' => false,
            ]);
            $token = $sales->fcm_token ?? $sales->device_id;
        } else {
            $token = $this->sumber->daftarMuat?->driver?->fcm_token;
        }

        if (empty($token)) {
            Log::info('Notifikasi tanggapan dilewati: penerima belum punya token FCM.', ['sumber' => $pesan['data']]);
            return;
        }

        FcmService::sendNotification($token, $pesan['judul'], $pesan['isi'], $pesan['data']);
    }

    /** @return array{judul: string, isi: string, data: array<string, string>} */
    public function isiPesan(): array
    {
        $s = $this->sumber;
        $diterima = $s->tanggapan_status === KunjunganToko::TANGGAPAN_DITERIMA;
        $label = $diterima ? 'diterima' : 'ditolak';
        $catatan = $s->tanggapan_catatan ? "\nCatatan admin: {$s->tanggapan_catatan}" : '';

        if ($s instanceof KunjunganToko) {
            $tanggal = $s->created_at?->timezone('Asia/Jakarta')->format('d/m/Y');
            return [
                'judul' => 'Foto kunjungan ' . $label,
                'isi'   => "Kunjungan ke {$s->nama_toko} ({$tanggal}) {$label} admin.{$catatan}",
                'data'  => ['type' => 'tanggapan_kunjungan', 'kunjungan_id' => (string) $s->id, 'status' => $s->tanggapan_status],
            ];
        }

        $muat = $s->daftarMuat;
        return [
            'judul' => 'Laporan bongkar ' . $label,
            'isi'   => "Rit {$muat?->nomor_rit} · {$s->lokasi_pengiriman}: laporan {$label} admin.{$catatan}",
            'data'  => [
                'type' => 'tanggapan_laporan', 'daftar_muat_id' => (string) $s->daftar_muat_id,
                'item_id' => (string) $s->id, 'status' => $s->tanggapan_status,
            ],
        ];
    }
}
