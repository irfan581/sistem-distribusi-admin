<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tanggapan admin (diterima/ditolak + catatan) atas bukti foto dari lapangan.
 * Dipakai KunjunganToko (per kunjungan) dan DaftarMuatItem (per tujuan rit).
 */
trait PunyaTanggapan
{
    public const TANGGAPAN_DITERIMA = 'diterima';
    public const TANGGAPAN_DITOLAK  = 'ditolak';

    public function initializePunyaTanggapan(): void
    {
        $this->mergeFillable(['perlu_tanggapan', 'tanggapan_status', 'tanggapan_catatan', 'ditanggapi_oleh', 'ditanggapi_pada']);
        $this->mergeCasts(['perlu_tanggapan' => 'boolean', 'ditanggapi_pada' => 'datetime']);
    }

    public function penanggap(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditanggapi_oleh');
    }

    /** Wajib ditanggapi tapi belum. Data dari sebelum fitur ada tidak ikut (perlu_tanggapan = false). */
    public function scopeBelumDitanggapi(Builder $query): Builder
    {
        return $query->where('perlu_tanggapan', true)->whereNull('tanggapan_status');
    }

    /** Saringan dari panel admin: belum | diterima | ditolak. */
    public function scopeSaringTanggapan(Builder $query, ?string $saring): Builder
    {
        return match ($saring) {
            'belum'                  => $query->belumDitanggapi(),
            self::TANGGAPAN_DITERIMA,
            self::TANGGAPAN_DITOLAK  => $query->where('tanggapan_status', $saring),
            default                  => $query,
        };
    }

    /** @return bool true bila isinya berubah (dipakai untuk memutuskan perlu kirim notifikasi). */
    public function tanggapi(User $admin, string $status, ?string $catatan): bool
    {
        $catatan = filled($catatan) ? trim($catatan) : null;
        $berubah = $this->tanggapan_status !== $status || $this->tanggapan_catatan !== $catatan;

        $this->forceFill([
            'tanggapan_status'  => $status,
            'tanggapan_catatan' => $catatan,
            'ditanggapi_oleh'   => $admin->id,
            'ditanggapi_pada'   => now(),
        ])->save();

        return $berubah;
    }

    /**
     * Diterima oleh sistem, bukan admin: ditanggapi_oleh dibiarkan kosong agar
     * terbedakan dari tanggapan manual. Tanpa notifikasi, karena sales tidak
     * perlu diberi tahu tiap kunjungan yang lolos pemeriksaan lokasi.
     */
    public function terimaOtomatis(): void
    {
        $this->forceFill([
            'tanggapan_status'  => self::TANGGAPAN_DITERIMA,
            'tanggapan_catatan' => null,
            'ditanggapi_oleh'   => null,
            'ditanggapi_pada'   => now(),
        ])->save();
    }

    /** Bentuk yang sama untuk panel admin, aplikasi sales, dan aplikasi driver. */
    public function tanggapanArray(): array
    {
        return [
            'perlu'      => (bool) $this->perlu_tanggapan,
            'status'     => $this->tanggapan_status,
            'catatan'    => $this->tanggapan_catatan,
            'oleh'       => $this->relationLoaded('penanggap') ? $this->penanggap?->name : null,
            'otomatis'   => $this->tanggapan_status !== null && $this->ditanggapi_oleh === null,
            'pada'       => $this->ditanggapi_pada?->timezone('Asia/Jakarta')->format('d/m/Y H:i'),
        ];
    }
}
