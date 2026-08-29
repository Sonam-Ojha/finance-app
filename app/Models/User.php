<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'mobile', 'password', 'pin', 'onboarded', 'currency', 'theme_mode', 'import_completed_at'];

    protected $hidden = ['password', 'remember_token', 'pin'];

    protected function casts(): array
    {
        return [
            'email_verified_at'   => 'datetime',
            'import_completed_at' => 'datetime',
            'password'            => 'hashed',
            'onboarded'           => 'boolean',
        ];
    }

    public function getHasPinAttribute(): bool
    {
        return ! is_null($this->getAttributes()['pin'] ?? null);
    }

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
