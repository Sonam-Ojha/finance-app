<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadDebt extends Model
{
    protected $fillable = ['user_id', 'person_name', 'mobile_number', 'amount', 'date_given', 'reason', 'expected_return_date', 'status', 'received_amount', 'notes'];
    protected $casts = ['date_given' => 'date', 'expected_return_date' => 'date', 'amount' => 'decimal:2', 'received_amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function getPendingAmountAttribute() { return $this->amount - $this->received_amount; }
}
