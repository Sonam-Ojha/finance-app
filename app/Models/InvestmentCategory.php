<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentCategory extends Model
{
    protected $fillable = ['user_id', 'name', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];
}
