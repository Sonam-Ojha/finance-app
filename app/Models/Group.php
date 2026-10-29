<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A shared-expenses group (Splitwise-style). Only its members see it; the
 * money maths lives in App\Support\GroupLedger, the rules in App\Support\Groups.
 */
class Group extends Model
{
    public const DEFAULT_EMOJI = '👥';

    protected $fillable = ['name', 'emoji', 'created_by'];

    protected $casts = ['created_by' => 'integer'];

    public function members() { return $this->hasMany(GroupMember::class); }
    public function expenses() { return $this->hasMany(GroupExpense::class); }
    public function settlements() { return $this->hasMany(GroupSettlement::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    /** For with() and load(): the members in the order they joined, each with their account. */
    public static function withMembers(): array
    {
        return ['members' => fn ($q) => $q->orderBy('joined_at')->orderBy('id'), 'members.user'];
    }

    /** The groups $user is in. */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
    }

    public function hasMember(int $userId): bool
    {
        return $this->relationLoaded('members')
            ? $this->members->contains('user_id', $userId)
            : $this->members()->where('user_id', $userId)->exists();
    }

    /** "🏖️ Goa trip" */
    public function label(): string
    {
        return trim(($this->emoji ?: self::DEFAULT_EMOJI) . ' ' . $this->name);
    }
}
