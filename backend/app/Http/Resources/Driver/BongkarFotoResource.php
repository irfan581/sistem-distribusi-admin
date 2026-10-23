<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BongkarFotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'jenis'               => $this->jenis,
            'foto_url'            => $this->foto_url,
            // Petak foto di layar laporan lebarnya cuma sepertiga layar, tapi
            // selama ini memuat berkas ukuran penuh. Yang null jatuh kembali
            // ke foto_url — foto lama sebelum `bongkar:thumbnail` dijalankan.
            'thumb_url'           => $this->thumb_url,
            'catatan'             => $this->catatan,
            'latitude'            => $this->latitude,
            'longitude'           => $this->longitude,
            'accuracy'            => $this->accuracy,
            'alamat'              => $this->alamat,
            'is_mock'             => (bool) $this->is_mock,
            'diambil_pada'        => $this->diambil_pada?->toIso8601String(),
            // Selisih jam HP dengan jam server. Dikirim balik supaya driver
            // tahu jamnya meleset sebelum admin yang menegur.
            'selisih_waktu_detik' => $this->selisihWaktuDetik(),
            'status_proses'       => $this->status_proses,
            'client_uuid'         => $this->client_uuid,
            'created_at'          => $this->created_at?->toIso8601String(),
        ];
    }
}
