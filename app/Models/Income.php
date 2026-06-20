<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Income extends Model
{
    protected $fillable = [
        'user_id', 'type', 'date', 'amount', 'payment_mode', 'note',
        'company_name', 'salary_month', 'client_name', 'policy_number',
        'plan_name', 'commission_type', 'business_name', 'person_name',
        'mobile_number', 'reason', 'category_name', 'description',
    ];

    protected $casts = ['date' => 'date', 'amount' => 'decimal:2'];

    public function user() { return $this->belongsTo(User::class); }

    public static function typeLabel($type): string
    {
        return match($type) {
            'salary' => 'Salary',
            'lic_commission' => 'LIC Commission',
            'business' => 'Business Income',
            'received_from' => 'Received From Person',
            default => 'Other Income',
        };
    }
}
