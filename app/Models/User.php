<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'mobile', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
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
