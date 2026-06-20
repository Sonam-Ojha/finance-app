<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Loan extends Model
{
    protected $fillable = ['user_id', 'loan_name', 'bank_or_person_name', 'total_amount', 'interest_rate', 'emi_amount', 'start_date', 'end_date', 'pending_amount', 'status', 'notes'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'total_amount' => 'decimal:2', 'emi_amount' => 'decimal:2', 'pending_amount' => 'decimal:2'];
    public function user() { return $this->belongsTo(User::class); }
    public function payments() { return $this->hasMany(LoanPayment::class); }
}
