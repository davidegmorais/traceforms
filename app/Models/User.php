<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'current_account_id', 'locale', 'last_login_at', 'avatar_path'];

    // trace_id (sub do Trace ID) é definido só pelo login (TraceIdSync), nunca por formulário.

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class)
            ->using(Membership::class)
            ->withPivot(['id', 'role', 'form_ids'])
            ->withTimestamps();
    }

    public function currentAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'current_account_id');
    }

    public function membershipFor(Account $account): ?Membership
    {
        return Membership::where('account_id', $account->id)->where('user_id', $this->id)->first();
    }

    public function roleIn(Account $account): ?Role
    {
        return $this->membershipFor($account)?->role;
    }

    public function switchAccount(Account $account): bool
    {
        if (! $this->membershipFor($account)) {
            return false;
        }
        $this->forceFill(['current_account_id' => $account->id])->save();
        $this->setRelation('currentAccount', $account);

        return true;
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? route('avatars.show', ['user' => $this->id, 'v' => substr(md5($this->avatar_path), 0, 8)]) : null;
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name));

        return mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1).mb_substr(count($parts) > 1 ? end($parts) : '', 0, 1));
    }
}
