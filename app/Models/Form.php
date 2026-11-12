<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Form extends Model
{
    use SoftDeletes;

    /** Modelos de apresentação do formulário. */
    public const LAYOUTS = ['classic', 'card', 'conversational', 'chat'];

    public const LAYOUT_LABELS = [
        'classic' => ['Clássico com painel', 'Painel lateral fixo com título e progresso; perguntas ao lado.', 'fa-table-columns'],
        'card' => ['Página única', 'Todas as perguntas em cartões, numa coluna central.', 'fa-list'],
        'conversational' => ['Uma por vez', 'Uma pergunta por tela em tela cheia, com letras grandes, transições e capa opcional.', 'fa-expand'],
        'chat' => ['Chat', 'Perguntas chegam como mensagens e as respostas viram balões, como num app de conversa.', 'fa-comment-dots'],
    ];

    public const DEFAULT_THEME = [
        'primary' => '#0e7c66',
        'background' => '#eef3f1',
        'text' => '#14231f',
        'font' => 'Instrument Sans',
        'radius' => 12,
        'logo_url' => null,
        'cover_url' => null,
    ];

    public const DEFAULT_SETTINGS = [
        'submit_label' => 'Enviar',
        'success_title' => 'Obrigado!',
        'success_message' => 'Sua resposta foi registrada com sucesso.',
        'redirect_url' => null,
        'redirect_pass_params' => true,
        'show_progress' => true,
        'closed_message' => 'Este formulário não está mais aceitando respostas.',
        'closes_at' => null,
        'max_submissions' => null,
        'one_per_visitor' => false,
        'notify_emails' => [],
        'webhook_url' => null,
        'collect_ip' => true,
        'anonymize_ip' => false,
        'save_partials' => true,
        'min_submit_seconds' => 3,
        'ga4_id' => null,
        'meta_pixel_id' => null,
        'gtm_id' => null,
        'show_branding' => true,
    ];

    protected $fillable = [
        'account_id', 'created_by', 'public_id', 'title', 'description', 'status',
        'layout', 'theme', 'settings', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'settings' => 'array',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Form $form) {
            $form->public_id ??= self::generatePublicId();
            $form->theme = array_merge(self::DEFAULT_THEME, $form->theme ?? []);
            $form->settings = array_merge(self::DEFAULT_SETTINGS, $form->settings ?? []);
        });
        // Exclusão definitiva remove também os arquivos enviados.
        static::forceDeleted(function (Form $form) {
            \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory('uploads/'.$form->id);
        });
    }

    public static function generatePublicId(): string
    {
        do {
            $id = Str::lower(Str::random(8));
        } while (self::withTrashed()->where('public_id', $id)->exists());

        return $id;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class)->orderBy('position');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(FormSession::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    public function trackingLinks(): HasMany
    {
        return $this->hasMany(TrackingLink::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get(array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []), $key, $default);
    }

    public function themeValue(string $key): mixed
    {
        return data_get(array_merge(self::DEFAULT_THEME, $this->theme ?? []), $key);
    }

    public function publicUrl(array $query = []): string
    {
        $url = route('public.form', $this->public_id);

        return $query ? $url.'?'.http_build_query(array_filter($query, fn ($v) => $v !== null && $v !== '')) : $url;
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** Motivo pelo qual o formulário não aceita respostas, ou null se aceita. */
    public function closedReason(): ?string
    {
        if ($this->account?->isSuspended()) {
            return 'suspended';
        }
        if ($this->status === 'draft') {
            return 'draft';
        }
        if ($this->status === 'closed') {
            return 'closed';
        }
        if (($closes = $this->setting('closes_at')) && now()->greaterThan($closes)) {
            return 'expired';
        }
        if (($max = (int) $this->setting('max_submissions')) > 0 && $this->submissions()->where('is_spam', false)->count() >= $max) {
            return 'limit';
        }

        return null;
    }

    public function layoutLabel(): string
    {
        return self::LAYOUT_LABELS[$this->layout][0] ?? 'Clássico';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'Publicado',
            'closed' => 'Encerrado',
            default => 'Rascunho',
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'published' => 'is-success',
            'closed' => 'is-danger',
            default => 'is-warning',
        };
    }

    /** Campos que coletam resposta (exclui seções de texto). */
    public function inputFields()
    {
        return $this->fields->filter(fn (FormField $f) => $f->type !== 'statement')->values();
    }
}
