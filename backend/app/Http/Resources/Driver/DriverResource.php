<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'kode_driver'          => $this->kode_driver,
            'nama_driver'          => $this->nama_driver,
            'nama_panggilan'       => $this->nama_panggilan,
            'no_telepon'           => $this->no_telepon,
            'alamat'               => $this->alamat,
            'jenis_sim'            => $this->jenis_sim,
            'status_kerja'         => $this->status_kerja,
            'harus_ganti_password' => (bool) $this->harus_ganti_password,

            // Nomor kantor ikut di sini, bukan dipaku di dalam aplikasi, supaya
            // bisa diganti tanpa membangun ulang APK dan membagikannya ulang ke
            // seluruh driver. Kosong berarti tombol teleponnya tidak muncul.
            'nomor_admin'          => config('driver.nomor_admin'),
            'kendaraan'            => $this->whenLoaded('kendaraans', fn () => $this->kendaraans
                ->map(fn ($k) => [
                    'id'            => $k->id,
                    'nomor_polisi'  => $k->nomor_polisi,
                    'jenis'         => $k->jenis_kendaraan,
                    // Cast eksplisit ke float — kolom decimal MySQL kalau
                    // dibiarkan apa adanya keluar sebagai string ("8.000")
                    // di JSON, dan itu bikin parsing di app driver gagal diam-
                    // diam (cast `as num?` di Flutter menolak String).
                    'kapasitas_ton' => (float) $k->kapasitas_muatan_ton,
                ])->values()),
        ];
    }
}
