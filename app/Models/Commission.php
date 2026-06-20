<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = ['user_id', 'date', 'source_name', 'client_name', 'product_name', 'commission_amount', 'status', 'notes'];
    protected $casts = ['date' => 'date', 'commission_amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
}
