<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverIzin extends Model
{
    protected $table = 'driver_izin';

    protected $fillable = ['driver_id', 'tanggal', 'keterangan', 'catatan'];

    protected $casts = ['tanggal' => 'date'];

    public function driver()
    {
        return $this->belongsTo(drivers::class, 'driver_id');
    }
}
