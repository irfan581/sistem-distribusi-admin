<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class PresensiResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $createdAt = $this->created_at instanceof Carbon 
            ? $this->created_at->timezone('Asia/Jakarta') 
            : null;

        // Helper untuk memecah location string "-7.72,111.53" menjadi array [lat, lng]
        $locMasuk  = $this->location_masuk ? explode(',', $this->location_masuk) : [0, 0];
        $locPulang = $this->location_pulang ? explode(',', $this->location_pulang) : [0, 0];

        return [
            'id'           => $this->id,
            'sales_id'     => $this->sales_id,
            'tanggal'      => $this->tanggal instanceof Carbon ? $this->tanggal->format('Y-m-d') : $this->tanggal,
            'status'       => $this->status,
            'status_label' => $this->status_label ?? $this->status, // Fallback
            
            // Detail Masuk
            'masuk' => [
                'jam'           => $this->jam_masuk,
                'foto'          => $this->foto_masuk ? URL::temporarySignedRoute('berkas.presensi', now()->addHours(12), ['id' => $this->id, 'jenis' => 'masuk']) : null,
                'lokasi'        => $this->location_masuk,
                'jam_perangkat' => $this->jam_perangkat_masuk,
                // Parsing manual dari string location_masuk
                'lat'           => (float) ($locMasuk[0] ?? 0),
                'lng'           => (float) ($locMasuk[1] ?? 0),
                'alamat'        => $this->alamat_masuk,
            ],

            // Detail Pulang
            'pulang' => $this->jam_pulang ? [
                'jam'           => $this->jam_pulang,
                'foto'          => $this->foto_pulang ? URL::temporarySignedRoute('berkas.presensi', now()->addHours(12), ['id' => $this->id, 'jenis' => 'pulang']) : null,
                'lokasi'        => $this->location_pulang,
                'jam_perangkat' => $this->jam_perangkat_pulang,
                // Parsing manual dari string location_pulang
                'lat'           => (float) ($locPulang[0] ?? 0),
                'lng'           => (float) ($locPulang[1] ?? 0),
                'alamat'        => $this->alamat_pulang,
            ] : null,

            'keterangan'      => $this->keterangan,
            'is_suspicious'   => (bool) $this->is_suspicious,
            'dibuat_pada'     => $createdAt ? $createdAt->format('d-m-Y H:i') : null,
        ];
    }
}