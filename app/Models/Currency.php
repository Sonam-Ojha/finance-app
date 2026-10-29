<?php

namespace App\Models;

use App\Support\Currencies;
use Illuminate\Database\Eloquent\Model;

/** A currency the app and the website offer (Admin › Currencies). Read through App\Support\Currencies. */
class Currency extends Model
{
    protected $fillable = ['code', 'name', 'symbol', 'decimals', 'default_rate', 'enabled', 'sort_order'];

    protected $casts = ['decimals' => 'integer', 'enabled' => 'boolean', 'sort_order' => 'integer'];

    protected static function booted(): void
    {
        // Every change shows in the app's list and the website straight away.
        static::saved(fn () => Currencies::forgetCache());
        static::deleted(fn () => Currencies::forgetCache());
    }

    public function isBase(): bool
    {
        return $this->code === Currencies::BASE;
    }

    /** "83.5" — the rate without trailing zeros, or null. */
    public function rateText(): ?string
    {
        return $this->default_rate === null ? null : rtrim(rtrim(number_format((float) $this->default_rate, 6, '.', ''), '0'), '.');
    }
}
