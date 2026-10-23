<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, LogsActivity;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'is_admin',
        'allowed_ip',
        'session_version',
        'avatar_url',
        'fcm_token',
        'permissions',
        'last_seen_at',
        'is_online',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'is_admin'          => 'boolean',
        'session_version'   => 'integer',
        'permissions'       => 'array',
        'last_seen_at'      => 'datetime',
        'is_online'         => 'boolean',
    ];

    // ─── RELASI ───────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    // ─── ACTIVITY LOG ─────────────────────────────────────────

    public function getActivitylogOptions(): LogOptions
    {
        return (new LogOptions())
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // ─── AVATAR ───────────────────────────────────────────────

    public function getAvatarUrlFullAttribute(): ?string
    {
        if (!$this->avatar_url) return null;

        return URL::temporarySignedRoute('berkas.avatar-pengguna', now()->addHours(12), ['id' => $this->id]);
    }

    // ─── ROLE HELPERS ─────────────────────────────────────────

    public function hasRole(string $roleName): bool
    {
        return $this->role?->nama === $roleName;
    }

    public function hasRoleOrChildOf(string $roleName): bool
    {
        return $this->role?->isOrChildOf($roleName) ?? false;
    }

    public function isOwner(): bool
    {
        return $this->hasRole('owner');
    }

    public function isAdminUser(): bool
    {
        return $this->is_admin || $this->hasRoleOrChildOf('admin');
    }

    public function hasPermission(string $permission): bool
    {
        // Owner & developer punya akses penuh ke semua fitur
        if ($this->isFullAccess()) return true;

        $userPermissions = $this->permissions ?? [];
        if (in_array($permission, $userPermissions)) return true;

        return in_array($permission, $this->role?->getAllPermissions() ?? []);
    }

    public function getRoleLevel(): int
    {
        return $this->role?->level ?? 99;
    }

    public function getEffectivePermissions(): array
    {
        // Jika user punya permissions sendiri, pakai itu
        $userPerms = $this->permissions ?? [];
        if (!empty($userPerms)) return $userPerms;

        // Fallback ke permissions dari role
        return $this->role?->getAllPermissions() ?? [];
    }

    public function isFullAccess(): bool
    {
        return in_array($this->role?->nama, ['owner', 'developer'])
            || in_array('*', $this->getEffectivePermissions());
    }

    /**
     * Jenis muatan yang boleh dipegang admin ini (subsidi dan non subsidi
     * diurus admin berbeda). Memakai izin efektif, bukan hasPermission(),
     * supaya izin yang dicabut di pengguna tidak tetap lolos lewat role-nya.
     */
    public function jenisMuatanDiizinkan(): array
    {
        if ($this->isFullAccess()) return ['subsidi', 'non_subsidi'];

        $perms = $this->getEffectivePermissions();
        $jenis = [];
        if (in_array('muat_subsidi', $perms))     $jenis[] = 'subsidi';
        if (in_array('muat_non_subsidi', $perms)) $jenis[] = 'non_subsidi';

        return $jenis;
    }

    /**
     * Siapa yang boleh membuat grup broadcast (pengumuman) dan kirim pesan di
     * dalamnya. Anggota lain di grup broadcast tetap bisa membaca, tidak kirim.
     */
    public function canBroadcast(): bool
    {
        return in_array($this->role?->nama, ['owner', 'developer', 'hrd']);
    }

}
