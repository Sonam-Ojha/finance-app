<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCardTransaction extends Model
{
    protected $fillable = ['user_id', 'credit_card_id', 'type', 'date', 'amount', 'category', 'description'];
    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function creditCard() { return $this->belongsTo(CreditCard::class); }
}
