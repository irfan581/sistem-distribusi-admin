<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checker extends Model
{
    protected $fillable = ['nama', 'status'];

    public function daftarMuats()
    {
        return $this->hasMany(DaftarMuat::class);
    }
}
