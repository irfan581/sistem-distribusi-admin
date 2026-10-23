<?php

namespace App\Models;

use App\Models\Concerns\PunyaTanggapan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DaftarMuatItem extends Model
{
    use PunyaTanggapan;

    protected $fillable = [
        'daftar_muat_id',
        'urutan',
        'lokasi_pengiriman',
        'toko_id',
        'alamat_otomatis',
        'alamat_lat',
        'alamat_lng',
        'alamat_resolved_at',
        'jenis_muatan',
        'kode_area',
        'catatan_admin',
        'total_muatan_ton',
        'status_bongkar',
        'tiba_at',
        'bongkar_selesai_at',
        'catatan_driver',
    ];

    protected $casts = [
        'tiba_at'            => 'datetime',
        'bongkar_selesai_at' => 'datetime',
        'alamat_resolved_at' => 'datetime',
    ];

    /**
     * Status yang berarti tujuan ini sudah tidak dikerjakan lagi.
     *
     * "Gagal" dipertahankan untuk baris lama; aplikasi driver sekarang selalu
     * memakai "Bermasalah" karena barang bisa saja turun sebagian, dan "gagal"
     * membuat admin mengira tidak ada yang turun sama sekali.
     */
    public const STATUS_TUNTAS = ['Selesai', 'Bermasalah', 'Gagal'];

    /** Tujuan yang belum dilaporkan driver belum punya apa pun untuk ditanggapi. */
    public function scopeBelumDitanggapi(Builder $query): Builder
    {
        return $query->where('perlu_tanggapan', true)->whereNull('tanggapan_status')
            ->whereIn('status_bongkar', self::STATUS_TUNTAS);
    }

    public function tuntas(): bool
    {
        return in_array($this->status_bongkar, self::STATUS_TUNTAS, true);
    }

    public function daftarMuat()
    {
        return $this->belongsTo(DaftarMuat::class);
    }

    public function bongkarFotos()
    {
        return $this->hasMany(BongkarFoto::class, 'daftar_muat_item_id')->orderBy('id');
    }

    public function toko()
    {
        return $this->belongsTo(Toko::class);
    }
}
