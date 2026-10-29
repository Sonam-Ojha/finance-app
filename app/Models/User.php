<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'mobile', 'password', 'pin', 'avatar', 'onboarded', 'currency', 'theme_mode', 'sidebar_color', 'import_completed_at', 'birthday', 'email_notifications', 'google_id', 'apple_id', 'daily_reminder_at', 'budget_alert_push', 'monthly_summary'];

    protected $hidden = ['password', 'remember_token', 'pin', 'google_id', 'apple_id'];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'import_completed_at' => 'datetime',
            'password'            => 'hashed',
            'onboarded'           => 'boolean',
            'birthday'            => 'date',
            'email_notifications' => 'boolean',
            'budget_alert_push'   => 'boolean',
            'monthly_summary'     => 'boolean',
            'last_reminded_on'    => 'date',
        ];
    }

    public function getHasPinAttribute(): bool
    {
        return ! is_null($this->getAttributes()['pin'] ?? null);
    }

    /** False for an account made with Google or Apple and never given one. */
    public function getHasPasswordAttribute(): bool
    {
        return ! is_null($this->getAttributes()['password'] ?? null);
    }

    public const ROLE_USER  = 'user';
    public const ROLE_ADMIN = 'admin';

    /* `role` is deliberately not fillable: no form or API body can make
     * someone an admin. It changes through forceFill in the admin pages and
     * the `user:admin` command only. */
    public function isAdmin(): bool
    {
        return ($this->role ?? self::ROLE_USER) === self::ROLE_ADMIN;
    }

    public function deviceTokens() { return $this->hasMany(DeviceToken::class); }

    public function categories() { return $this->hasMany(Category::class); }
    public function appExpenses() { return $this->hasMany(AppExpense::class); }
    public function appIncomes() { return $this->hasMany(AppIncome::class); }
    public function budgets() { return $this->hasMany(Budget::class); }
    public function userCurrencies() { return $this->hasMany(UserCurrency::class); }

    public function incomes() { return $this->hasMany(Income::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
    public function bankAccounts() { return $this->hasMany(BankAccount::class); }
    public function creditCards() { return $this->hasMany(CreditCard::class); }
    public function loans() { return $this->hasMany(Loan::class); }
    public function investments() { return $this->hasMany(Investment::class); }
    public function commissions() { return $this->hasMany(Commission::class); }
    public function cashbacks() { return $this->hasMany(Cashback::class); }
    public function badDebts() { return $this->hasMany(BadDebt::class); }
    public function contacts() { return $this->hasMany(Contact::class); }
    public function reminders() { return $this->hasMany(Reminder::class); }
}
