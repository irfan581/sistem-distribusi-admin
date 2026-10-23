<?php

namespace App\Http\Resources\Driver;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TugasItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'urutan'             => (int) $this->urutan,
            'lokasi_pengiriman'  => $this->lokasi_pengiriman,
            'kode_area'          => $this->kode_area,
            // Hasil pencocokan otomatis ke kunjungan_tokos. Null berarti
            // belum ketemu tokonya atau toko itu belum pernah dikunjungi
            // sales — layar tujuan tetap pakai lokasi_pengiriman sebagai
            // fallback untuk tombol buka Maps.
            'alamat_otomatis'    => $this->alamat_otomatis,
            'alamat_lat'         => $this->alamat_lat === null ? null : (float) $this->alamat_lat,
            'alamat_lng'         => $this->alamat_lng === null ? null : (float) $this->alamat_lng,
            'jenis_muatan'       => $this->jenis_muatan ?? 'non_subsidi',
            'status_bongkar'     => $this->status_bongkar,
            'tiba_at'            => $this->tiba_at?->toIso8601String(),
            'bongkar_selesai_at' => $this->bongkar_selesai_at?->toIso8601String(),
            'catatan_admin'      => $this->catatan_admin,
            'catatan_driver'     => $this->catatan_driver,
            'jumlah_foto'        => $this->whenCounted('bongkarFotos', fn () => $this->bongkar_fotos_count, 0),
            // Hanya ikut kalau relasinya sengaja dimuat. Layar tugas harian
            // cukup dengan hitungannya; menarik seluruh baris foto di sana
            // membuat respons membengkak tanpa dipakai.
            'fotos'              => BongkarFotoResource::collection($this->whenLoaded('bongkarFotos')),
        ];
    }
}
