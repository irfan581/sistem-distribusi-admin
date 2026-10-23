<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Toko extends Model
{
    public const JENIS_TOKO = 'toko';
    public const JENIS_KANTOR = 'kantor';

    protected $fillable = [
        'sales_id',
        'nama_kios', // Tetap ini karena DB production menggunakan kolom ini
        'jenis',
        'wilayah',
        'is_new',
        'alamat',
        'alamat_manual',
        'lat',
        'lng',
        'lokasi_sumber',
        'lokasi_diperbarui_at',
    ];

    protected $casts = [
        'is_new' => 'boolean',
        'alamat_manual' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'lokasi_diperbarui_at' => 'datetime',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sales_id');
    }

    public function kunjunganTokos(): HasMany
    {
        return $this->hasMany(KunjunganToko::class);
    }

    public function scopeBySales($query, int $salesId)
    {
        return $query->where('sales_id', $salesId);
    }

    /** Hanya toko pelanggan, tanpa entri kantor. */
    public function scopePelanggan($query)
    {
        return $query->where('jenis', self::JENIS_TOKO);
    }

    public function isKantor(): bool
    {
        return $this->jenis === self::JENIS_KANTOR;
    }

    /** Mengisi titik toko dari sebuah kunjungan, kecuali titiknya sudah dikoreksi manual. */
    public function isiLokasiDari(KunjunganToko $kunjungan): bool
    {
        if ($this->lokasi_sumber === 'manual') return false;
        if ($kunjungan->lat === null || $kunjungan->lng === null) return false;
        if ($kunjungan->is_suspicious) return false;
        if ($kunjungan->accuracy !== null && $kunjungan->accuracy > 200) return false;

        return $this->update([
            'alamat' => $this->alamat_manual && filled($this->alamat) ? $this->alamat : $kunjungan->alamat,
            'lat' => $kunjungan->lat,
            'lng' => $kunjungan->lng,
            'lokasi_sumber' => 'kunjungan',
            'lokasi_diperbarui_at' => $kunjungan->created_at,
        ]);
    }
}
