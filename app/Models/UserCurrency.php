<?php

namespace App\Models;

use App\Support\Currencies;
use Illuminate\Database\Eloquent\Model;

/** A user's rate for a built-in currency, or a currency they added. */
class UserCurrency extends Model
{
    protected $fillable = ['user_id', 'code', 'label', 'symbol', 'rate'];

    public function user() { return $this->belongsTo(User::class); }

    public function isCustom(): bool
    {
        return ! Currencies::isBuiltIn($this->code);
    }

    public function toApiShape(): array
    {
        return [
            'code'   => $this->code,
            'label'  => $this->label,
            'symbol' => $this->symbol,
            'rate'   => (float) $this->rate,
        ];
    }
}
