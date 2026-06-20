<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IncomeCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];
    public function user() { return $this->belongsTo(User::class); }
}
