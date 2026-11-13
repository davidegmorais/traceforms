<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Uma visita a um formulário: tudo o que sabemos sobre o visitante,
 * de onde ele veio e como interagiu com cada campo.
 */
class FormSession extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_returning' => 'boolean',
            'is_bot' => 'boolean',
            'touch' => 'boolean',
            'dark_mode' => 'boolean',
            'cookies_enabled' => 'boolean',
            'do_not_track' => 'boolean',
            'first_touch' => 'array',
            'query_params' => 'array',
            'hidden_fields' => 'array',
            'field_stats' => 'array',
            'partial_data' => 'array',
            'pixel_ratio' => 'float',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function trackingLink(): BelongsTo
    {
        return $this->belongsTo(TrackingLink::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(Submission::class);
    }

    public function screenLabel(): ?string
    {
        return $this->screen_width ? "{$this->screen_width}×{$this->screen_height}" : null;
    }

    public function deviceIcon(): string
    {
        return match ($this->device_type) {
            'smartphone', 'phablet', 'feature phone' => 'fa-mobile-screen',
            'tablet' => 'fa-tablet-screen-button',
            'tv', 'smart display' => 'fa-tv',
            'console' => 'fa-gamepad',
            'car browser' => 'fa-car',
            default => 'fa-desktop',
        };
    }
}
