<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sales_id',
        'visit_target_id',
        'old_target',
        'new_target',
        'changed_by_type',
        'changed_by_id',
        'action',
        'reason',
        'metadata',
        'changed_at',
    ];

    protected $casts = [
        'old_target' => 'integer',
        'new_target' => 'integer',
        'metadata'   => 'array',
        'changed_at' => 'datetime',
    ];

    public function sales(): BelongsTo
    {
        return $this->belongsTo(Sales::class, 'sales_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_id');
    }

    public function visitTarget(): BelongsTo
    {
        return $this->belongsTo(SalesVisitTarget::class, 'visit_target_id');
    }
}
