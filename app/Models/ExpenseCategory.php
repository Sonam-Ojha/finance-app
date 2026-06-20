<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'group', 'icon', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
}
