<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Resposta enviada a um formulário. */
class Submission extends Model
{
    protected $table = 'submissions';

    protected $fillable = [
        'uuid', 'form_id', 'form_session_id', 'data', 'is_spam', 'spam_reason',
        'starred', 'notes', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_spam' => 'boolean',
            'starred' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(FormSession::class, 'form_session_id');
    }

    public function uploads(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function value(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /** Primeiro e-mail encontrado nas respostas (para título amigável). */
    public function headline(Form $form): string
    {
        foreach ($form->fields as $f) {
            if (in_array($f->type, ['email', 'short_text'], true) && ($v = $this->value($f->key))) {
                return (string) $v;
            }
        }

        return 'Resposta #'.$this->id;
    }
}
