<?php

namespace App\Observers;

use App\Models\Presensi;
use App\Models\User;
use App\Helpers\KirimNotifikasiAdmin;
use App\Support\Berkas;
use Illuminate\Contracts\Queue\ShouldQueue;

class PresensiObserver implements ShouldQueue
{
    public string $queue = 'notifications';

    public function created(Presensi $presensi): void
    {
        $presensi->loadMissing('sales');
        $admins = User::where('is_admin', true)
            ->orWhereHas('role', fn($q) => $q->where('nama', 'admin'))
            ->get();

        KirimNotifikasiAdmin::kirim(
            $admins,
            'Sales Masuk',
            "{$presensi->sales->nama} baru saja absen masuk.",
            'success',
            'presensi',
        );
    }

    public function updated(Presensi $presensi): void
    {
        if ($presensi->wasChanged('jam_pulang') && !empty($presensi->jam_pulang)) {
            $presensi->loadMissing('sales');
            $admins = User::where('is_admin', true)
                ->orWhereHas('role', fn($q) => $q->where('nama', 'admin'))
                ->get();

            KirimNotifikasiAdmin::kirim(
                $admins,
                'Sales Pulang',
                "{$presensi->sales->nama} sudah absen pulang.",
                'info',
                'presensi',
            );
        }
    }

    public function deleted(Presensi $presensi): void
    {
        Berkas::hapus([$presensi->foto_masuk, $presensi->foto_pulang]);
    }
}
