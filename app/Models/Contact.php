<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $fillable = ['user_id', 'name', 'mobile', 'notes'];
    public function user() { return $this->belongsTo(User::class); }
    public function transactions() { return $this->hasMany(ContactTransaction::class); }
    public function getBalanceAttribute()
    {
        $lent = $this->transactions->where('type', 'lent')->sum('amount');
        $borrowed = $this->transactions->where('type', 'borrowed')->sum('amount');
        return $lent - $borrowed;
    }
}
