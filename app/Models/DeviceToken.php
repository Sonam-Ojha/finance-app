<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone (or browser) a user is signed in on, by its Firebase push token. */
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    protected $fillable = ['user_id', 'token', 'platform'];

    public function user() { return $this->belongsTo(User::class); }
}
