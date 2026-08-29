<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMode extends Model
{
    protected $fillable = ['user_id', 'name', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];
}
