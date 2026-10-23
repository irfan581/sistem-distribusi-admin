<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Notifications\DatabaseNotification;

class Sales extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $table = 'sales';

    protected $fillable = [
        'nik',
        'nama',
        'no_hp',
        'area',
        'alamat',
        'foto_profil',
        'device_id',
        'password',
        'is_active',
        'is_spv',
        'last_login_at',
        'fcm_token',
        'target_kunjungan',
        'last_seen_at',
        'is_online',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'device_id',
        'deleted_at',
    ];

    protected $casts = [
        'last_login_at' => 'datetime',
        'last_seen_at'  => 'datetime',
        'password'      => 'hashed',
        'is_active'     => 'boolean',
        'is_spv'        => 'boolean',
        'is_online'     => 'boolean',
    ];

    protected $appends = ['foto_profil_url'];

    public function getFotoProfilUrlAttribute(): string
    {
        return empty($this->foto_profil)
            ? 'https://ui-avatars.com/api/?name=' . urlencode($this->nama) . '&background=EA580C&color=fff&bold=true'
            : URL::temporarySignedRoute('berkas.avatar-sales', now()->addHours(12), ['id' => $this->id]);
    }

    public function presensi(): HasMany
    {
        return $this->hasMany(Presensi::class, 'sales_id');
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(NotifikasiSales::class, 'sales_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(DatabaseNotification::class, 'notifiable_id')
            ->where('notifiable_type', self::class)
            ->latest();
    }

    public function tokos(): HasMany
    {
        return $this->hasMany(Toko::class, 'sales_id');
    }

    public function visitTarget(): HasMany
    {
        return $this->hasMany(SalesVisitTarget::class, 'sales_id');
    }

    public function targetChangeLogs(): HasMany
    {
        return $this->hasMany(TargetChangeLog::class, 'sales_id');
    }

    public function updateLastLogin(): void
    {
        $this->forceFill(['last_login_at' => now()])->save();
    }
}
