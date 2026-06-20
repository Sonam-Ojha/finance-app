<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cashback extends Model
{
    protected $fillable = ['user_id', 'date', 'platform_name', 'amount', 'status', 'notes'];
    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
}
