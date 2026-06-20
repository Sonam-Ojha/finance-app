<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCard extends Model
{
    protected $fillable = ['user_id', 'card_name', 'bank_name', 'credit_limit', 'outstanding_amount', 'due_date_day', 'notes'];
    protected $casts = ['credit_limit' => 'decimal:2', 'outstanding_amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(CreditCardTransaction::class); }
    public function getAvailableLimitAttribute() { return $this->credit_limit - $this->outstanding_amount; }
}
