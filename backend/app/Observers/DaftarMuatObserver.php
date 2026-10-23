<?php

namespace App\Observers;

use App\Models\DaftarMuat;
use App\Models\DaftarMuatLog;
use App\Models\User;
use App\Models\drivers;
use App\Jobs\KirimNotifikasiDriverJob;

class DaftarMuatObserver
{
    public function created(DaftarMuat $daftarMuat): void
    {
        // user_id menunjuk tabel users. Memakai auth()->id() apa adanya berbahaya
        // sejak ada guard driver: id driver akan masuk ke kolom itu dan entah
        // melanggar foreign key, atau lebih buruk, mencatat perbuatan itu atas
        // nama admin yang kebetulan ber-id sama.
        $pelaku = auth()->user();

        DaftarMuatLog::create(array_merge([
            'daftar_muat_id' => $daftarMuat->id,
            'status'         => 'Dibuat',
            'keterangan'     => 'Rencana pengiriman dibuat',
        ], match (true) {
            $pelaku instanceof User    => ['user_id' => $pelaku->id, 'aktor' => 'admin'],
            $pelaku instanceof drivers => ['driver_id' => $pelaku->id, 'aktor' => 'driver'],
            default                    => ['aktor' => 'sistem'],
        }));

        if ($daftarMuat->driver_id) {
            KirimNotifikasiDriverJob::dispatch($daftarMuat);
        }
    }

    public function updated(DaftarMuat $daftarMuat): void
    {
        // Kirim notif jika driver baru ditetapkan
        if ($daftarMuat->isDirty('driver_id') && $daftarMuat->driver_id) {
            KirimNotifikasiDriverJob::dispatch($daftarMuat);
        }
    }

    public function deleted(DaftarMuat $daftarMuat): void {}
    public function restored(DaftarMuat $daftarMuat): void {}
    public function forceDeleted(DaftarMuat $daftarMuat): void {}
}
