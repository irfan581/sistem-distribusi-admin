<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TugasResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'tanggal_muatan'    => $this->tanggal_muatan,
            'nomor_rit'         => (int) ($this->nomor_rit ?? 1),
            'jenis_muatan'      => $this->jenis_muatan ?? 'non_subsidi',
            'total_muatan_ton'  => (float) ($this->total_muatan_ton ?? 0),
            'status_perjalanan' => $this->status_perjalanan,

            'dimuat_at'         => $this->dimuat_at?->toIso8601String(),
            'berangkat_at'      => $this->berangkat_at?->toIso8601String(),
            'selesai_at'        => $this->selesai_at?->toIso8601String(),

            'jumlah_tujuan'     => $this->whenLoaded('items', fn () => $this->items->count(), 0),
            'tujuan_tuntas'     => $this->whenLoaded('items', fn () => $this->items->filter->tuntas()->count(), 0),
            'items'             => TugasItemResource::collection($this->whenLoaded('items')),

            'kendaraan'         => $this->whenLoaded('kendaraan', fn () => $this->kendaraan ? [
                'id'            => $this->kendaraan->id,
                'nomor_polisi'  => $this->kendaraan->nomor_polisi,
                'jenis'         => $this->kendaraan->jenis_kendaraan,
                'kapasitas_ton' => (float) $this->kendaraan->kapasitas_muatan_ton,
            ] : null),

            'checker'           => $this->whenLoaded('checker', fn () => $this->checker?->nama),

            'logs'              => $this->whenLoaded('logs', fn () => $this->logs->map(fn ($log) => [
                'status'     => $log->status,
                'keterangan' => $log->keterangan,
                'pelaku'     => $log->namaPelaku(),
                'waktu'      => $log->created_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
            ])->values()),
        ];
    }
}
