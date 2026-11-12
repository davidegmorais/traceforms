<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    protected $table = 'account_user';

    public $incrementing = true;

    protected $fillable = ['account_id', 'user_id', 'role', 'form_ids'];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'form_ids' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function can(string $ability): bool
    {
        return $this->role->can($ability);
    }

    /** Se o membro é restrito a formulários específicos. */
    public function isRestricted(): bool
    {
        return $this->role->scopable() && is_array($this->form_ids) && count($this->form_ids) > 0;
    }

    public function canAccessForm(Form $form): bool
    {
        if ($form->account_id !== $this->account_id) {
            return false;
        }

        return ! $this->isRestricted() || in_array($form->id, array_map('intval', $this->form_ids), true);
    }
}
