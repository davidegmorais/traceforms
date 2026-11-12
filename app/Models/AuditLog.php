<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['account_id', 'user_id', 'action', 'subject_type', 'subject_id', 'description', 'meta', 'ip', 'created_at'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, string $description, ?Model $subject = null, array $meta = []): void
    {
        $account = app()->bound('account') ? app('account') : null;
        if (! $account) {
            return;
        }
        self::create([
            'account_id' => $account->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => $meta ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function icon(): string
    {
        return match (explode('.', $this->action)[0]) {
            'form' => 'fa-file-lines',
            'member', 'invitation' => 'fa-user-group',
            'submission' => 'fa-inbox',
            'account' => 'fa-building',
            'link' => 'fa-link',
            'export' => 'fa-file-export',
            default => 'fa-circle-info',
        };
    }
}
