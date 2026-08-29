<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankMaster extends Model
{
    protected $fillable = ['user_id', 'name', 'short_name', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];
}
