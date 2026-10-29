<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A payment between two members that settles (part of) a debt: from_user paid to_user. */
class GroupSettlement extends Model
{
    protected $fillable = ['group_id', 'from_user_id', 'to_user_id', 'amount_minor', 'settled_on', 'created_by'];

    protected $casts = [
        'group_id'     => 'integer',
        'from_user_id' => 'integer',
        'to_user_id'   => 'integer',
        'amount_minor' => 'integer',
        'created_by'   => 'integer',
        'settled_on'   => 'date',
    ];

    public function group() { return $this->belongsTo(Group::class); }
}
