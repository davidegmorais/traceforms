<?php

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Filtros globais dos relatórios (período + segmentos). */
class ReportFilters
{
    public const SEGMENTS = [
        'channel' => 'Canal', 'utm_source' => 'Origem', 'utm_medium' => 'Mídia', 'utm_campaign' => 'Campanha',
        'utm_content' => 'Conteúdo', 'utm_term' => 'Termo', 'device_type' => 'Dispositivo', 'os_name' => 'Sistema',
        'browser_name' => 'Navegador', 'country' => 'País', 'referrer_domain' => 'Referência', 'tracking_link_id' => 'Link rastreado',
    ];

    public const RANGES = ['today' => 'Hoje', '7d' => 'Últimos 7 dias', '30d' => 'Últimos 30 dias', '90d' => 'Últimos 90 dias', '12m' => 'Últimos 12 meses', 'all' => 'Todo o período', 'custom' => 'Personalizado'];

    public CarbonImmutable $from;

    public CarbonImmutable $to;

    public string $range;

    public array $segments = [];

    public function __construct(public string $timezone) {}

    public static function fromRequest(Request $request, string $timezone, ?CarbonImmutable $earliest = null): self
    {
        $f = new self($timezone);
        $now = CarbonImmutable::now($timezone);
        $f->range = array_key_exists($request->query('range'), self::RANGES) ? $request->query('range') : '30d';

        [$from, $to] = match ($f->range) {
            'today' => [$now->startOfDay(), $now->endOfDay()],
            '7d' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            '12m' => [$now->subMonths(12)->addDay()->startOfDay(), $now->endOfDay()],
            'all' => [($earliest ?? $now->subDays(29))->setTimezone($timezone)->startOfDay(), $now->endOfDay()],
            'custom' => [
                self::parse($request->query('from'), $timezone) ?? $now->subDays(29)->startOfDay(),
                (self::parse($request->query('to'), $timezone) ?? $now)->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }
        $f->from = $from;
        $f->to = $to;

        foreach (array_keys(self::SEGMENTS) as $k) {
            $v = $request->query($k);
            if (is_string($v) && $v !== '') {
                $f->segments[$k] = mb_substr($v, 0, 200);
            }
        }

        return $f;
    }

    private static function parse(?string $v, string $tz): ?CarbonImmutable
    {
        try {
            return $v ? CarbonImmutable::createFromFormat('Y-m-d', $v, $tz)->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to) + 1;
    }

    /** Período anterior de mesmo tamanho, para comparação. */
    public function previous(): self
    {
        $p = clone $this;
        $days = $this->days();
        $p->from = $this->from->subDays($days);
        $p->to = $this->to->subDays($days);

        return $p;
    }

    public function apply(Builder|\Illuminate\Database\Query\Builder $q, string $dateCol = 'form_sessions.created_at'): Builder|\Illuminate\Database\Query\Builder
    {
        $q->whereBetween($dateCol, [$this->from->utc(), $this->to->utc()]);
        foreach ($this->segments as $col => $val) {
            $val === '(nenhum)' ? $q->whereNull("form_sessions.$col") : $q->where("form_sessions.$col", $val);
        }

        return $q;
    }

    public function query(array $extra = []): array
    {
        return array_filter(array_merge([
            'range' => $this->range,
            'from' => $this->range === 'custom' ? $this->from->format('Y-m-d') : null,
            'to' => $this->range === 'custom' ? $this->to->format('Y-m-d') : null,
        ], $this->segments, $extra), fn ($v) => $v !== null && $v !== '');
    }

    public function label(): string
    {
        return $this->range === 'custom' || $this->range === 'all'
            ? $this->from->format('d/m/Y').' – '.$this->to->format('d/m/Y')
            : self::RANGES[$this->range];
    }
}
