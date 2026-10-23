<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NotifikasiSales extends Model
{
    use HasFactory;

    protected $table = 'notifikasi_sales';

    protected $fillable = [
        'sales_id',
        'title',
        'message',
        'is_read',
        'batch_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sales(): BelongsTo
    {
        // Pastikan model Sales kamu sudah ada
        return $this->belongsTo(Sales::class, 'sales_id');
    }
}