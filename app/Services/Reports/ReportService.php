<?php

namespace App\Services\Reports;

use App\Models\Form;
use App\Models\FormSession;
use App\Models\Submission;
use App\Services\Tracking\ChannelClassifier;
use App\Support\FieldTypes;
use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Agregações dos relatórios. Opera sobre um ou vários formulários (visão por conta).
 */
class ReportService
{
    private int $offset;

    /** @param int[] $formIds */
    public function __construct(public array $formIds, public ReportFilters $filters)
    {
        $this->offset = Sql::offsetMinutes($filters->timezone);
    }

    public static function forForm(Form $form, ReportFilters $f): self
    {
        return new self([$form->id], $f);
    }

    /** Sessões filtradas (exclui bots). */
    public function sessions(?ReportFilters $filters = null): Builder
    {
        $q = FormSession::query()->whereIn('form_sessions.form_id', $this->formIds)->where('form_sessions.is_bot', false);
        ($filters ?? $this->filters)->apply($q);

        return $q;
    }

    private function aggSelect(): string
    {
        return 'count(*) as views, '
            .'count(distinct visitor_id) as visitors, '
            .'sum(case when started_at is not null then 1 else 0 end) as starts, '
            ."sum(case when status = 'submitted' then 1 else 0 end) as submits";
    }

    // ------------------------------------------------------------------
    // Visão geral
    // ------------------------------------------------------------------

    public function kpis(?ReportFilters $filters = null): array
    {
        $row = $this->sessions($filters)->selectRaw($this->aggSelect()
            .", avg(case when status = 'submitted' then duration_ms end) as avg_duration"
            .', avg(case when started_at is not null then time_to_start_ms end) as avg_tts'
            .', sum(case when is_returning then 1 else 0 end) as returning_n')
            ->toBase()->first();

        $views = (int) $row->views;
        $starts = (int) $row->starts;
        $submits = (int) $row->submits;

        return [
            'views' => $views,
            'visitors' => (int) $row->visitors,
            'starts' => $starts,
            'submits' => $submits,
            'abandons' => max(0, $starts - $submits),
            'start_rate' => $this->pct($starts, $views),
            'completion_rate' => $this->pct($submits, $starts),
            'conversion' => $this->pct($submits, $views),
            'avg_duration' => $row->avg_duration ? (int) $row->avg_duration : null,
            'avg_time_to_start' => $row->avg_tts ? (int) $row->avg_tts : null,
            'returning_rate' => $this->pct((int) $row->returning_n, $views),
        ];
    }

    /** KPIs + variação percentual vs. período anterior. */
    public function kpisWithDelta(): array
    {
        $cur = $this->kpis();
        $prev = $this->filters->range === 'all' ? null : $this->kpis($this->filters->previous());
        $out = [];
        foreach ($cur as $k => $v) {
            $p = $prev[$k] ?? null;
            $out[$k] = ['value' => $v, 'prev' => $p, 'delta' => ($p === null || $v === null) ? null : ($p == 0 ? ($v > 0 ? 100 : 0) : round(($v - $p) / $p * 100, 1))];
        }

        return $out;
    }

    public function timeseries(): array
    {
        $date = Sql::date('form_sessions.created_at', $this->offset);
        $rows = $this->sessions()->selectRaw("$date as d, ".$this->aggSelect())->groupByRaw($date)->toBase()->get()->keyBy('d');

        $byMonth = $this->filters->days() > 120;
        $series = [];
        for ($day = $this->filters->from; $day->lte($this->filters->to); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $bucket = $byMonth ? $day->format('Y-m') : $key;
            $series[$bucket] ??= ['label' => $byMonth ? $day->translatedFormat('M/y') : $day->format('d/m'), 'views' => 0, 'starts' => 0, 'submits' => 0];
            if ($r = $rows->get($key)) {
                $series[$bucket]['views'] += (int) $r->views;
                $series[$bucket]['starts'] += (int) $r->starts;
                $series[$bucket]['submits'] += (int) $r->submits;
            }
        }

        return array_values($series);
    }

    // ------------------------------------------------------------------
    // Quebras (aquisição / audiência)
    // ------------------------------------------------------------------

    /** Agrupa por coluna e retorna visitas, inícios, envios e conversão. */
    public function breakdown(string $column, int $limit = 10, ?callable $labeler = null): array
    {
        $col = str_contains($column, '(') ? $column : "form_sessions.$column";
        $rows = $this->sessions()
            ->selectRaw("$col as label, ".$this->aggSelect())
            ->groupByRaw($col)->orderByDesc('views')->limit($limit)
            ->toBase()->get();

        $total = max(1, (int) $this->sessions()->count());

        return $rows->map(fn ($r) => [
            'key' => $r->label,
            'label' => $labeler ? $labeler($r->label) : ($r->label === null || $r->label === '' ? '(nenhum)' : $r->label),
            'views' => (int) $r->views,
            'visitors' => (int) $r->visitors,
            'starts' => (int) $r->starts,
            'submits' => (int) $r->submits,
            'share' => round($r->views / $total * 100, 1),
            'conversion' => $this->pct((int) $r->submits, (int) $r->views),
        ])->all();
    }

    public function channels(): array
    {
        return array_map(fn ($r) => $r + ['color' => ChannelClassifier::COLORS[$r['key']] ?? '#b9bbc6'],
            $this->breakdown('channel', 12, fn ($v) => ChannelClassifier::label($v)));
    }

    public function sourceMedium(int $limit = 15): array
    {
        $expr = match (Sql::driver()) {
            'mysql', 'mariadb' => "CONCAT(COALESCE(form_sessions.utm_source, '(direto)'), ' / ', COALESCE(form_sessions.utm_medium, '(nenhum)'))",
            default => "COALESCE(form_sessions.utm_source, '(direto)') || ' / ' || COALESCE(form_sessions.utm_medium, '(nenhum)')",
        };

        return $this->breakdown($expr, $limit);
    }

    /** Atribuição de primeiro toque × último toque por campanha (somente envios). */
    public function attribution(): array
    {
        $first = [];
        $last = [];
        $this->sessions()->where('status', 'submitted')->select(['utm_campaign', 'utm_source', 'first_touch'])
            ->orderBy('id')->chunk(2000, function ($rows) use (&$first, &$last) {
                foreach ($rows as $s) {
                    $l = $s->utm_campaign ?: ($s->utm_source ? $s->utm_source.' (sem campanha)' : '(direto)');
                    $ft = $s->first_touch ?? [];
                    $f = $ft['utm_campaign'] ?? (isset($ft['utm_source']) ? $ft['utm_source'].' (sem campanha)' : ($l));
                    $last[$l] = ($last[$l] ?? 0) + 1;
                    $first[$f] = ($first[$f] ?? 0) + 1;
                }
            });
        $keys = array_unique(array_merge(array_keys($first), array_keys($last)));
        $rows = array_map(fn ($k) => ['label' => $k, 'first' => $first[$k] ?? 0, 'last' => $last[$k] ?? 0], $keys);
        usort($rows, fn ($a, $b) => ($b['first'] + $b['last']) <=> ($a['first'] + $a['last']));

        return array_slice($rows, 0, 12);
    }

    public function landingPages(int $limit = 10): array
    {
        $rows = $this->sessions()->selectRaw('coalesce(parent_url, landing_url) as url, '.$this->aggSelect())
            ->groupByRaw('coalesce(parent_url, landing_url)')->orderByDesc('views')->limit(500)->toBase()->get();
        $agg = [];
        foreach ($rows as $r) {
            $p = parse_url((string) $r->url);
            $k = isset($p['host']) ? preg_replace('/^www\./', '', $p['host']).($p['path'] ?? '/') : '(desconhecida)';
            $agg[$k] ??= ['label' => $k, 'views' => 0, 'starts' => 0, 'submits' => 0];
            $agg[$k]['views'] += $r->views;
            $agg[$k]['starts'] += $r->starts;
            $agg[$k]['submits'] += $r->submits;
        }
        usort($agg, fn ($a, $b) => $b['views'] <=> $a['views']);

        return array_map(fn ($r) => $r + ['conversion' => $this->pct($r['submits'], $r['views'])], array_slice($agg, 0, $limit));
    }

    // ------------------------------------------------------------------
    // Comportamento
    // ------------------------------------------------------------------

    /** Mapa de calor dia da semana × hora (envios). */
    public function heatmap(string $metric = 'submits'): array
    {
        $col = $metric === 'submits' ? 'form_sessions.submitted_at' : 'form_sessions.created_at';
        $wd = Sql::weekday($col, $this->offset);
        $hr = Sql::hour($col, $this->offset);
        $q = $this->sessions()->selectRaw("$wd as wd, $hr as hr, count(*) as c")->whereNotNull($col)->groupByRaw("$wd, $hr");
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($q->toBase()->get() as $r) {
            $grid[(int) $r->wd][(int) $r->hr] = (int) $r->c;
        }

        return $grid;
    }

    /**
     * Funil por pergunta: quantas sessões alcançaram cada campo, onde abandonaram,
     * tempo médio de preenchimento e erros.
     */
    public function fieldFunnel(Form $form): array
    {
        $fields = $form->fields->values();
        $base = $this->sessions()->whereNotNull('started_at');
        $started = (int) (clone $base)->count();

        $reach = [];
        $lastField = (clone $base)->where('status', '!=', 'submitted')->selectRaw('last_field_key, count(*) as c')
            ->groupBy('last_field_key')->pluck('c', 'last_field_key');

        $stats = [];
        (clone $base)->select(['id', 'field_stats', 'max_step'])->whereNotNull('field_stats')->orderBy('id')
            ->chunk(2000, function ($rows) use (&$stats) {
                foreach ($rows as $s) {
                    foreach ((array) $s->field_stats as $k => $v) {
                        $stats[$k]['n'] = ($stats[$k]['n'] ?? 0) + 1;
                        $stats[$k]['ms'] = ($stats[$k]['ms'] ?? 0) + (int) ($v['ms'] ?? 0);
                        $stats[$k]['errors'] = ($stats[$k]['errors'] ?? 0) + (int) ($v['errors'] ?? 0);
                        $stats[$k]['changes'] = ($stats[$k]['changes'] ?? 0) + (int) ($v['changes'] ?? 0);
                        $stats[$k]['refocus'] = ($stats[$k]['refocus'] ?? 0) + max(0, (int) ($v['focus'] ?? 0) - 1);
                    }
                }
            });

        $steps = (clone $base)->selectRaw('max_step, count(*) as c')->groupBy('max_step')->pluck('c', 'max_step')->all();
        krsort($steps);
        $cum = 0;
        $reachAt = [];
        foreach ($steps as $step => $c) {
            $cum += $c;
            $reachAt[(int) $step] = $cum;
        }

        $out = [];
        $prevReach = $started;
        $pos = 0;
        foreach ($fields as $i => $f) {
            if ($f->type === 'hidden') {
                continue;
            }
            $pos++;
            // Sessões que chegaram até pelo menos esta posição.
            $r = 0;
            foreach ($reachAt as $step => $c) {
                if ($step >= $i + 1) {
                    $r = max($r, $c);
                }
            }
            $r = $i === 0 ? $started : min($prevReach, $r);
            $s = $stats[$f->key] ?? null;
            $out[] = [
                'key' => $f->key,
                'label' => $f->label,
                'type' => $f->type,
                'icon' => $f->icon(),
                'position' => $pos,
                'reached' => $r,
                'reach_pct' => $this->pct($r, $started),
                'dropped_here' => (int) ($lastField[$f->key] ?? 0),
                'avg_ms' => $s && $s['n'] ? (int) ($s['ms'] / $s['n']) : null,
                'errors' => $s['errors'] ?? 0,
                'error_rate' => $s && $s['n'] ? $this->pct($s['errors'], $s['n']) : 0,
                'refocus' => $s['refocus'] ?? 0,
                'interactions' => $s['n'] ?? 0,
            ];
            $prevReach = $r;
        }

        return ['started' => $started, 'fields' => $out];
    }

    public function engagement(): array
    {
        $row = $this->sessions()->whereNotNull('started_at')->selectRaw(
            'avg(active_ms) as active, avg(duration_ms) as duration, avg(tab_switches) as tabs, '
            .'sum(case when paste_count > 0 then 1 else 0 end) as pasted, count(*) as n'
        )->toBase()->first();

        $buckets = ['< 30s' => 0, '30s–1min' => 0, '1–2 min' => 0, '2–5 min' => 0, '5–10 min' => 0, '> 10 min' => 0];
        $this->sessions()->where('status', 'submitted')->whereNotNull('duration_ms')->select('duration_ms')->orderBy('id')
            ->chunk(5000, function ($rows) use (&$buckets) {
                foreach ($rows as $r) {
                    $s = $r->duration_ms / 1000;
                    $k = $s < 30 ? '< 30s' : ($s < 60 ? '30s–1min' : ($s < 120 ? '1–2 min' : ($s < 300 ? '2–5 min' : ($s < 600 ? '5–10 min' : '> 10 min'))));
                    $buckets[$k]++;
                }
            });

        return [
            'avg_active_ms' => (int) $row->active,
            'avg_duration_ms' => (int) $row->duration,
            'avg_tab_switches' => round((float) $row->tabs, 1),
            'paste_rate' => $this->pct((int) $row->pasted, (int) $row->n),
            'duration_buckets' => $buckets,
        ];
    }

    // ------------------------------------------------------------------
    // Respostas por pergunta
    // ------------------------------------------------------------------

    public function questionSummaries(Form $form): array
    {
        $sessionIds = $this->filters->segments ? $this->sessions()->select('form_sessions.id') : null;
        $q = Submission::query()->where('form_id', $form->id)->where('is_spam', false)
            ->whereBetween('submitted_at', [$this->filters->from->utc(), $this->filters->to->utc()]);
        if ($sessionIds) {
            $q->whereIn('form_session_id', $sessionIds);
        }

        $fields = $form->fields->filter(fn ($f) => $f->type !== 'statement');
        $agg = [];
        $total = 0;
        $q->select(['id', 'data', 'submitted_at'])->orderByDesc('submitted_at')->limit(50000)->get()->each(function ($s) use ($fields, &$agg, &$total) {
            $total++;
            foreach ($fields as $f) {
                $v = $s->data[$f->key] ?? null;
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }
                $a = &$agg[$f->key];
                $a['answered'] = ($a['answered'] ?? 0) + 1;
                $count = function (string $label) use (&$a) {
                    $a['counts'][$label] = ($a['counts'][$label] ?? 0) + 1;
                };

                switch ($f->type) {
                    case 'checkbox':
                        foreach ((array) $v as $x) {
                            $count((string) $x);
                        }
                        break;
                    case 'ranking':
                        foreach (array_values((array) $v) as $pos => $opt) {
                            $a['rank'][$opt]['sum'] = ($a['rank'][$opt]['sum'] ?? 0) + $pos + 1;
                            $a['rank'][$opt]['n'] = ($a['rank'][$opt]['n'] ?? 0) + 1;
                            if ($pos === 0) {
                                $a['rank'][$opt]['first'] = ($a['rank'][$opt]['first'] ?? 0) + 1;
                            }
                        }
                        break;
                    case 'matrix':
                        foreach ((array) $v as $row => $col) {
                            $a['grid'][$row][$col] = ($a['grid'][$row][$col] ?? 0) + 1;
                        }
                        break;
                    case 'address':
                        if (is_array($v)) {
                            $count(trim(($v['city'] ?? '?').'/'.($v['state'] ?? '?')));
                        }
                        break;
                    case 'cpf_cnpj':
                        $count(\App\Support\BrDocs::kind($v) ?? 'Outro');
                        break;
                    case 'time':
                        $count(substr((string) $v, 0, 2).'h');
                        break;
                    case 'file':
                    case 'signature':
                        $a['files'] = ($a['files'] ?? 0) + count((array) $v);
                        break;
                    case 'number':
                    case 'currency':
                    case 'slider':
                        if (is_numeric($v)) {
                            $a['nums'][] = (float) $v;
                        }
                        break;
                    default:
                        if (FieldTypes::isChartable($f->type) || $f->type === 'hidden') {
                            $count((string) $v);
                            if (is_numeric($v)) {
                                $a['sum'] = ($a['sum'] ?? 0) + $v;
                            }
                        } elseif (count($a['samples'] ?? []) < 8 && is_scalar($v)) {
                            $a['samples'][] = ['text' => mb_strimwidth((string) $v, 0, 280, '…'), 'at' => $s->submitted_at];
                        }
                }
                unset($a);
            }
        });

        $out = [];
        foreach ($fields as $f) {
            $a = $agg[$f->key] ?? [];
            $answered = $a['answered'] ?? 0;
            $item = [
                'field' => $f,
                'answered' => $answered,
                'skipped' => max(0, $total - $answered),
                'response_rate' => $this->pct($answered, $total),
                'chart' => null, 'samples' => $a['samples'] ?? [], 'stat' => null, 'grid' => null, 'ranking' => null,
            ];
            $counts = $a['counts'] ?? [];

            if (FieldTypes::isChartable($f->type)) {
                $labels = $f->choiceValues() ?: array_keys($counts);
                foreach (array_keys($counts) as $extra) {
                    if (! in_array((string) $extra, array_map('strval', $labels), true)) {
                        $labels[] = $extra; // respostas "Outro"
                    }
                }
                $item['chart'] = array_map(fn ($l) => ['label' => (string) $l, 'count' => $counts[(string) $l] ?? 0, 'pct' => $this->pct($counts[(string) $l] ?? 0, $answered)], $labels);
            } elseif ($counts) { // endereço, CPF/CNPJ, hora, campo oculto
                $f->type === 'time' ? ksort($counts) : arsort($counts);
                $item['chart'] = array_map(fn ($l) => ['label' => (string) $l, 'count' => $counts[$l], 'pct' => $this->pct($counts[$l], $answered)], array_slice(array_keys($counts), 0, 12));
            }

            if (in_array($f->type, ['rating', 'scale'], true) && $answered) {
                $item['stat'] = ['label' => 'Média', 'value' => number_format(($a['sum'] ?? 0) / $answered, 2, ',', '.'), 'suffix' => '/ '.$f->setting('max', 5)];
            }
            if ($f->type === 'nps' && $answered) {
                $pro = ($counts['9'] ?? 0) + ($counts['10'] ?? 0);
                $det = array_sum(array_map(fn ($n) => $counts[(string) $n] ?? 0, range(0, 6)));
                $item['stat'] = ['label' => 'NPS', 'value' => (string) round(($pro - $det) / $answered * 100), 'suffix' => '',
                    'nps' => ['promoters' => $this->pct($pro, $answered), 'passives' => $this->pct($answered - $pro - $det, $answered), 'detractors' => $this->pct($det, $answered)]];
            }
            if (! empty($a['nums'])) {
                sort($a['nums']);
                $n = count($a['nums']);
                $fmt = fn ($x) => $f->type === 'currency' ? 'R$ '.number_format($x, 2, ',', '.') : number_format($x, 2, ',', '.');
                $item['stat'] = ['label' => 'Média', 'value' => $fmt(array_sum($a['nums']) / $n),
                    'suffix' => 'mediana '.$fmt($a['nums'][intdiv($n, 2)]).($f->type === 'currency' ? ' · total '.$fmt(array_sum($a['nums'])) : '')];
            }
            if ($f->type === 'ranking' && ! empty($a['rank'])) {
                $rows = [];
                foreach ($f->options ?? [] as $opt) {
                    $r = $a['rank'][$opt] ?? null;
                    $rows[] = ['label' => $opt, 'avg' => $r ? round($r['sum'] / $r['n'], 2) : null, 'first' => $r['first'] ?? 0, 'first_pct' => $this->pct($r['first'] ?? 0, $answered)];
                }
                usort($rows, fn ($x, $y) => ($x['avg'] ?? 99) <=> ($y['avg'] ?? 99));
                $item['ranking'] = $rows;
            }
            if ($f->type === 'matrix') {
                $item['grid'] = ['rows' => $f->matrixRows(), 'cols' => $f->options ?? [], 'counts' => $a['grid'] ?? []];
            }
            if (FieldTypes::isFile($f->type) && $answered) {
                $item['stat'] = ['label' => 'Arquivos recebidos', 'value' => number_format($a['files'] ?? 0, 0, ',', '.'), 'suffix' => 'em '.$answered.' respostas'];
            }
            $out[] = $item;
        }

        return ['total' => $total, 'items' => $out];
    }

    // ------------------------------------------------------------------

    public function earliest(): ?\Carbon\CarbonImmutable
    {
        $min = FormSession::whereIn('form_id', $this->formIds)->min('created_at');

        return $min ? \Carbon\CarbonImmutable::parse($min) : null;
    }

    public function pct(int|float $a, int|float $b): float
    {
        return $b > 0 ? round($a / $b * 100, 1) : 0.0;
    }

    /** Valores distintos de um segmento (para os filtros). */
    public function segmentValues(string $col, int $limit = 50): array
    {
        return FormSession::whereIn('form_id', $this->formIds)->where('is_bot', false)
            ->whereNotNull($col)->where('created_at', '>=', now()->subDays(365))
            ->select($col)->distinct()->limit($limit)->pluck($col)->sort()->values()->all();
    }

    public static function duration(?int $ms): string
    {
        if (! $ms) {
            return '—';
        }
        $s = (int) round($ms / 1000);
        if ($s < 60) {
            return $s.'s';
        }
        $m = intdiv($s, 60);

        return $m < 60 ? $m.'min '.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT).'s' : intdiv($m, 60).'h '.($m % 60).'min';
    }

    public static function forDb(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection();
    }
}
