<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['user_id', 'name', 'icon', 'color', 'budget_minor', 'sort_order'];

    protected $casts = ['budget_minor' => 'integer', 'sort_order' => 'integer'];

    public function user() { return $this->belongsTo(User::class); }
    public function appExpenses() { return $this->hasMany(AppExpense::class); }
}
