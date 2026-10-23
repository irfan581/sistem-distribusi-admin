<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class kendaraans extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_kendaraan',
        'nomor_polisi',
        'merk',
        'model',
        'tahun_pembuatan',
        'kapasitas_muatan_ton',
        'driver_id',
        'status_kendaraan',
    ];

    public function driver()
    {
        return $this->belongsTo(drivers::class, 'driver_id');
    }

    public function daftarMuats()
    {
        return $this->hasMany(DaftarMuat::class, 'kendaraan_id');
    }
}
