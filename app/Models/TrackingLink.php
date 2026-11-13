<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Link curto rastreado com UTMs pré-definidas. */
class TrackingLink extends Model
{
    protected $fillable = [
        'form_id', 'created_by', 'code', 'name', 'utm_source', 'utm_medium',
        'utm_campaign', 'utm_term', 'utm_content', 'extra_params', 'clicks',
    ];

    protected function casts(): array
    {
        return ['extra_params' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(function (TrackingLink $link) {
            do {
                $code = Str::random(7);
            } while (self::where('code', $code)->exists());
            $link->code ??= $code;
        });
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FormSession::class);
    }

    public function utmParams(): array
    {
        return array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
        ] + ($this->extra_params ?? []), fn ($v) => $v !== null && $v !== '');
    }

    public function shortUrl(): string
    {
        return route('public.link', $this->code);
    }

    public function fullUrl(): string
    {
        return $this->form->publicUrl($this->utmParams());
    }
}
