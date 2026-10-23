<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLocation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'lat',
        'lng',
        'akurasi',
        'recorded_at',
        'created_at',
    ];

    protected $casts = [
        'lat'         => 'float',
        'lng'         => 'float',
        'akurasi'     => 'float',
        'recorded_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
