<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class drivers extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'kode_driver',
        'nama_driver',
        'nama_panggilan',
        'no_telepon',
        'alamat',
        'status_kerja',
        'jenis_sim',
        'password',
        'mobile_aktif',
        'harus_ganti_password',
        'device_id',
        'fcm_token',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'device_id',
    ];

    protected $casts = [
        'password'             => 'hashed',
        'mobile_aktif'         => 'boolean',
        'harus_ganti_password' => 'boolean',
        'last_login_at'        => 'datetime',
    ];

    /**
     * Driver hanya boleh masuk aplikasi bila masih bekerja, akun mobilenya
     * diaktifkan admin, dan passwordnya sudah diset.
     */
    public function bolehLoginMobile(): bool
    {
        return $this->status_kerja === 'Aktif'
            && $this->mobile_aktif
            && !empty($this->password);
    }

    public function kendaraans(): HasMany
    {
        return $this->hasMany(kendaraans::class, 'driver_id');
    }

    public function daftarMuats(): HasMany
    {
        return $this->hasMany(DaftarMuat::class, 'driver_id');
    }

    public function izin(): HasMany
    {
        return $this->hasMany(DriverIzin::class, 'driver_id');
    }
}
