<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Account extends Model
{
    protected $fillable = ['name', 'slug', 'owner_id', 'timezone', 'brand_color', 'settings', 'suspended_at', 'suspension_reason'];

    protected $hidden = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'suspended_at' => 'datetime'];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public static function createFor(User $owner, string $name): self
    {
        $account = self::create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'owner_id' => $owner->id,
        ]);
        $account->users()->attach($owner->id, ['role' => Role::Owner->value]);
        $owner->forceFill(['current_account_id' => $account->id])->save();

        return $account;
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Membership::class)
            ->withPivot(['id', 'role', 'form_ids'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function sessions(): HasManyThrough
    {
        return $this->hasManyThrough(FormSession::class, Form::class);
    }

    public function submissions(): HasManyThrough
    {
        return $this->hasManyThrough(Submission::class, Form::class);
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
