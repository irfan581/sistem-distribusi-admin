<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DaftarMuatLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'daftar_muat_id',
        'status',
        'keterangan',
        'user_id',
        'driver_id',
        'aktor',
        'latitude',
        'longitude',
        'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function daftarMuat()
    {
        return $this->belongsTo(DaftarMuat::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driver()
    {
        return $this->belongsTo(drivers::class, 'driver_id');
    }

    /**
     * Nama pelaku untuk ditampilkan, dari kolom mana pun yang terisi.
     */
    public function namaPelaku(): string
    {
        return match ($this->aktor) {
            'driver' => $this->driver?->nama_driver ?? 'Driver',
            'admin'  => $this->user?->name ?? 'Admin',
            default  => 'Sistem',
        };
    }
}
