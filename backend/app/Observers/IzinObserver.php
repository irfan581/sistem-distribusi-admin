<?php

namespace App\Observers;

use App\Models\Izin;
use App\Models\User;
use App\Models\NotifikasiSales;
use App\Jobs\ProcessIzin;
use App\Services\FcmService;
use App\Helpers\KirimNotifikasiAdmin;
use App\Support\Berkas;
use Illuminate\Support\Facades\Cache;

class IzinObserver
{
    public function created(Izin $izin): void
    {
        if ($izin->bukti_foto) {
            ProcessIzin::dispatch($izin->id, $izin->bukti_foto);
        }

        // ✅ Notif ke ADMIN di panel (tulis ke tabel notifications)
        $nama   = $izin->sales->nama ?? 'Sales';
        $admins = User::where('is_admin', true)->get();

        if ($admins->isNotEmpty()) {
            KirimNotifikasiAdmin::kirim(
                $admins,
                'Pengajuan Izin Baru',
                "{$nama} mengajukan izin {$izin->jenis_izin}.",
                'warning',
                'izin',
            );
        }

        Cache::forget('izin_pending_count');
    }

    public function updated(Izin $izin): void
    {
        if (!$izin->wasChanged('status')) return;

        $sales = $izin->sales;
        if (!$sales) {
            Cache::forget('izin_pending_count');
            return;
        }

        $isApproved   = $izin->status === 'disetujui';
        $statusLabel  = $isApproved ? 'DISETUJUI' : 'DITOLAK';
        $alasan       = $izin->alasan_tolak ? " Alasan: {$izin->alasan_tolak}" : "";
        $tglFormatted = $izin->tanggal ? $izin->tanggal->format('d/m/Y') : '-';
        $jenisLabel   = ucfirst($izin->jenis_izin);

        $judul      = "Izin {$jenisLabel}: {$statusLabel}";
        $pesan      = "Pengajuan izin {$jenisLabel} Anda untuk tanggal {$tglFormatted} telah {$statusLabel}.{$alasan}";

        // ✅ 1. Simpan ke notifikasi_sales (dibaca Flutter)
        NotifikasiSales::create([
            'sales_id' => $izin->sales_id,
            'title'    => $judul,
            'message'  => $pesan,
            'is_read'  => false,
        ]);

        // ✅ 2. Push notifikasi ke HP via FCM
        $token = $sales->fcm_token ?? $sales->device_id ?? null;
        if ($token) {
            FcmService::sendNotification($token, $judul, $pesan);
        }

        Cache::forget('izin_pending_count');
    }

    public function deleted(Izin $izin): void
    {
        Berkas::hapus($izin->bukti_foto);
        Cache::forget('izin_pending_count');
    }
}
