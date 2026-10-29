<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One of the categories new accounts start with (Admin › Default categories). */
class DefaultCategory extends Model
{
    protected $fillable = ['name', 'icon', 'color', 'budget_minor', 'sort_order'];

    protected $casts = ['budget_minor' => 'integer', 'sort_order' => 'integer'];

    public static function ordered()
    {
        return static::query()->orderBy('sort_order')->orderBy('id');
    }
}
