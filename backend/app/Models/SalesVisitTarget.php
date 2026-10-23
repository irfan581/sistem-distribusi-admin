<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesVisitTarget extends Model
{
    protected $fillable = [
        'sales_id',
        'target_per_day',
        'period_type',
        'period_label',
        'is_active',
        'set_by',
        'effective_from',
        'effective_until',
        'notes',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'target_per_day'  => 'integer',
        'effective_from'  => 'datetime',
        'effective_until' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sales_id');
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(TargetChangeLog::class, 'visit_target_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForSales($query, int $salesId)
    {
        return $query->where('sales_id', $salesId);
    }

    // ── Helpers ──────────────────────────────────────────────────

    public static function getActiveTargetForSales(int $salesId): int
    {
        $record = static::where('sales_id', $salesId)
            ->where('is_active', true)
            ->latest('effective_from')
            ->first();

        // Fallback cascade: dedicated table → sales.target_kunjungan → system default
        if ($record) {
            return $record->target_per_day;
        }

        $sales = Sales::find($salesId);
        return $sales?->target_kunjungan ?? 5;
    }
}
