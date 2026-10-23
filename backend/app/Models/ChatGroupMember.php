<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatGroupMember extends Model
{
    protected $fillable = ['group_id', 'member_type', 'member_id', 'is_admin'];
}
