<?php

namespace App\Models;

use App\Support\FieldTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    protected $fillable = [
        'form_id', 'key', 'type', 'label', 'description', 'placeholder',
        'required', 'options', 'settings', 'position',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
            'settings' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function typeLabel(): string
    {
        return FieldTypes::label($this->type);
    }

    public function icon(): string
    {
        return FieldTypes::icon($this->type);
    }

    /** Valores possíveis para campos de escala/opção (para relatórios). */
    public function choiceValues(): array
    {
        return match ($this->type) {
            'radio', 'checkbox', 'select' => array_values($this->options ?? []),
            'yes_no' => ['Sim', 'Não'],
            'consent' => ['Aceito'],
            'rating' => array_map('strval', range(1, (int) $this->setting('max', 5))),
            'nps' => array_map('strval', range(0, 10)),
            'scale' => array_map('strval', range((int) $this->setting('min', 1), (int) $this->setting('max', 5))),
            default => [],
        };
    }

    /** Formata um valor de resposta para exibição em texto (tabelas, CSV, e-mail). */
    public function display(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        return match ($this->type) {
            'consent' => $value ? 'Aceito' : '—',
            'date' => rescue(fn () => \Carbon\Carbon::parse($value)->format('d/m/Y'), (string) $value, false),
            'currency' => 'R$ '.number_format((float) $value, 2, ',', '.'),
            'slider' => rtrim(rtrim(number_format((float) $value, 2, ',', '.'), '0'), ',').($this->setting('unit') ? ' '.$this->setting('unit') : ''),
            'cpf_cnpj' => \App\Support\BrDocs::format((string) $value),
            'address' => is_array($value) ? self::addressLine($value) : (string) $value,
            'ranking' => is_array($value) ? implode(' › ', array_map(fn ($v, $i) => ($i + 1).'. '.$v, $value, array_keys(array_values($value)))) : (string) $value,
            'matrix' => is_array($value) ? implode('; ', array_map(fn ($r, $c) => "$r: $c", array_keys($value), $value)) : (string) $value,
            'file', 'signature' => is_array($value) ? implode(', ', array_map(fn ($f) => is_array($f) ? ($f['name'] ?? 'arquivo') : (string) $f, $value)) : (string) $value,
            default => is_array($value) ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)) : (string) $value,
        };
    }

    public static function addressLine(array $a): string
    {
        $line = trim(($a['street'] ?? '').(! empty($a['number']) ? ', '.$a['number'] : '').(! empty($a['complement']) ? ' – '.$a['complement'] : ''));
        $place = trim(($a['district'] ?? '').(! empty($a['city']) ? ', '.$a['city'] : '').(! empty($a['state']) ? '/'.$a['state'] : ''), ', ');

        return trim(implode(' · ', array_filter([$line, $place, ! empty($a['cep']) ? 'CEP '.\App\Support\BrDocs::format($a['cep']) : null])));
    }

    /** Linhas da matriz (perguntas) — as colunas ficam em $options. */
    public function matrixRows(): array
    {
        return array_values(array_filter((array) $this->setting('matrix_rows', []), fn ($r) => is_string($r) && $r !== ''));
    }

    public function toBuilderArray(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label,
            'description' => $this->description,
            'placeholder' => $this->placeholder,
            'required' => $this->required,
            'options' => $this->options ?? [],
            'settings' => (object) ($this->settings ?? []),
        ];
    }
}
