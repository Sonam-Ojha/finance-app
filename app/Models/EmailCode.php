<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A user's current email code; only its HMAC is kept (App\Support\EmailVerification). */
class EmailCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'attempts', 'expires_at', 'sent_at'];

    protected $casts = [
        'attempts'   => 'integer',
        'expires_at' => 'datetime',
        'sent_at'    => 'datetime',
    ];
}
