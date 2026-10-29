<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone (or browser) a user is signed in on, by its Firebase push token. */
class DeviceToken extends Model
{
    public const PLATFORMS = ['android', 'ios', 'web'];

    // access_token_id: the app sign-in it was registered under; deleting that
    // sign-in deletes this (App\Models\PersonalAccessToken, the foreign key).
    protected $fillable = ['user_id', 'access_token_id', 'token', 'platform'];

    public function user() { return $this->belongsTo(User::class); }
}
