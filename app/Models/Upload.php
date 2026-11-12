<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Arquivo enviado por um respondente. Fica em disco privado; só membros da conta baixam. */
class Upload extends Model
{
    protected $fillable = ['uuid', 'form_id', 'form_session_id', 'submission_id', 'field_key', 'original_name', 'mime', 'size', 'disk', 'path'];

    protected static function booted(): void
    {
        static::deleting(function (Upload $u) {
            Storage::disk($u->disk)->delete($u->path);
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** Representação gravada dentro da resposta. */
    public function toAnswer(): array
    {
        return ['id' => $this->uuid, 'name' => $this->original_name, 'size' => $this->size, 'mime' => $this->mime];
    }

    public static function humanSize(int $bytes): string
    {
        return $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : max(1, (int) round($bytes / 1024)).' KB';
    }
}
