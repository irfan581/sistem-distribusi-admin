<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class BongkarFoto extends Model
{
    protected $fillable = [
        'daftar_muat_id',
        'daftar_muat_item_id',
        'driver_id',
        'urutan_snapshot',
        'lokasi_snapshot',
        'jenis',
        'foto',
        'thumb',
        'latitude',
        'longitude',
        'accuracy',
        'alamat',
        'is_mock',
        'diambil_pada',
        'catatan',
        'client_uuid',
        'status_proses',
    ];

    protected $casts = [
        'latitude'     => 'float',
        'longitude'    => 'float',
        'accuracy'     => 'float',
        'is_mock'      => 'boolean',
        'diambil_pada' => 'datetime',
    ];

    protected $appends = ['foto_url', 'thumb_url'];

    public function getFotoUrlAttribute(): ?string
    {
        // route(), bukan Storage::url(). Yang kedua menghasilkan /storage/…
        // yang disajikan Nginx langsung tanpa memeriksa siapa pun; route ini
        // lewat BerkasController yang menolak sopir lain dan tamu tanpa token.
        return empty($this->foto)
            ? null
            : URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
                'id' => $this->id, 'ukuran' => 'penuh',
            ]);
    }

    /**
     * Versi 320px untuk daftar. Foto lama yang belum sempat dibuatkan
     * thumbnail jatuh kembali ke ukuran penuh — lebih berat, tapi tetap
     * tampil; lebih baik daripada gambar rusak.
     */
    public function getThumbUrlAttribute(): ?string
    {
        return empty($this->thumb)
            ? $this->foto_url
            : URL::temporarySignedRoute('berkas.bongkar', now()->addHours(12), [
                'id' => $this->id, 'ukuran' => 'kecil',
            ]);
    }

    /**
     * Selisih antara jam HP driver dan jam server saat foto diterima. Dipakai
     * admin untuk menilai foto yang waktunya mencurigakan.
     */
    public function selisihWaktuDetik(): ?int
    {
        if (!$this->diambil_pada || !$this->created_at) {
            return null;
        }

        return abs($this->diambil_pada->diffInSeconds($this->created_at));
    }

    public function daftarMuat(): BelongsTo
    {
        return $this->belongsTo(DaftarMuat::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(DaftarMuatItem::class, 'daftar_muat_item_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(drivers::class, 'driver_id');
    }
}
