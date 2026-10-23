<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatGroup extends Model
{
    protected $fillable = ['nama', 'creator_type', 'creator_id', 'is_broadcast'];

    public function members()
    {
        return $this->hasMany(ChatGroupMember::class, 'group_id');
    }
}
