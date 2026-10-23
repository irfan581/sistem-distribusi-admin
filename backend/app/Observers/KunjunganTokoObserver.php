<?php

namespace App\Observers;

use App\Models\KunjunganToko;
use App\Models\User;
use App\Helpers\KirimNotifikasiAdmin;
use Illuminate\Contracts\Queue\ShouldQueue;

class KunjunganTokoObserver implements ShouldQueue
{
    public string $queue = 'notifications';

    public function created(KunjunganToko $kunjungan): void
    {
        $kunjungan->loadMissing('sales');

        $admins = User::where('is_admin', true)
            ->orWhereHas('role', fn($q) => $q->where('nama', 'admin'))
            ->select(['id'])
            ->get();

        if ($admins->isEmpty()) return;

        KirimNotifikasiAdmin::kirim(
            $admins,
            'Kunjungan Toko Baru',
            "{$kunjungan->sales->nama} di {$kunjungan->nama_toko}.",
            'info',
            'kunjungan',
        );
    }

    public function updated(KunjunganToko $kunjungan): void {}
    public function deleted(KunjunganToko $kunjungan): void {}
    public function restored(KunjunganToko $kunjungan): void {}
    public function forceDeleted(KunjunganToko $kunjungan): void {}
}
