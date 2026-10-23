<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'nama',
        'label',
        'parent_id',
        'level',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active'   => 'boolean',
        'level'       => 'integer',
    ];

    // ─── RELASI ───────────────────────────────────────────────

    /**
     * Parent role (misal: admin → parent dari hrd/spv/keuangan)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_id');
    }

    /**
     * Sub-roles (misal: admin punya children hrd, spv, keuangan)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Role::class, 'parent_id');
    }

    /**
     * Users yang punya role ini
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }

    // ─── SCOPES ───────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeParentOnly($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeChildOf($query, int $parentId)
    {
        return $query->where('parent_id', $parentId);
    }

    // ─── HELPERS ──────────────────────────────────────────────

    /**
     * Cek apakah role ini sub-role (punya parent)
     */
    public function isSubRole(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Cek apakah role ini role utama (tidak punya parent)
     */
    public function isMainRole(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Cek apakah role ini atau parent-nya adalah role tertentu
     */
    public function isOrChildOf(string $roleName): bool
    {
        if ($this->nama === $roleName) {
            return true;
        }

        return $this->parent?->nama === $roleName;
    }

    /**
     * Ambil semua permission (gabungan parent + role sendiri)
     */
    public function getAllPermissions(): array
    {
        $ownPermissions = $this->permissions ?? [];

        if ($this->parent && $this->parent->permissions) {
            return array_unique(array_merge($this->parent->permissions, $ownPermissions));
        }

        return $ownPermissions;
    }
}