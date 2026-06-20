<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    protected $fillable = ['user_id', 'investment_name', 'category', 'date', 'amount_invested', 'current_value', 'maturity_date', 'returns', 'notes'];
    protected $casts = ['date' => 'date', 'maturity_date' => 'date', 'amount_invested' => 'decimal:2', 'current_value' => 'decimal:2', 'returns' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
}
