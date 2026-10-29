<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One person in a group. */
class GroupMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['group_id', 'user_id', 'joined_at'];

    protected $casts = [
        'group_id'  => 'integer',
        'user_id'   => 'integer',
        'joined_at' => 'datetime',
    ];

    public function group() { return $this->belongsTo(Group::class); }
    public function user() { return $this->belongsTo(User::class); }
}
