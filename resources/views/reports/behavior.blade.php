@extends('reports.layout')
@section('report')
    @php
        use App\Services\Reports\ReportService;
        $e = $engagement;
        $worst = collect($funnel['fields'])->sortByDesc('dropped_here')->first();
        $slowest = collect($funnel['fields'])->sortByDesc('avg_ms')->first();
        $errorest = collect($funnel['fields'])->sortByDesc('error_rate')->first();
    @endphp

    <div class="columns is-multiline is-variable is-3">
        <div class="column is-3">@include('partials.kpi', ['label' => 'Tempo ativo médio', 'icon' => 'fa-hourglass-half', 'k' => ['value' => $e['avg_active_ms'], 'delta' => null, 'prev' => null], 'format' => 'duration', 'tip' => 'Tempo com a aba visível e interação recente'])</div>
        <div class="column is-3">@include('partials.kpi', ['label' => 'Trocas de aba (média)', 'icon' => 'fa-window-restore', 'k' => ['value' => null, 'delta' => null, 'prev' => null], 'display' => number_format($e['avg_tab_switches'], 1, ',', '.'), 'color' => '#f76b15', 'tip' => 'Quantas vezes, em média, a pessoa saiu da aba durante o preenchimento'])</div>
        <div class="column is-3">@include('partials.kpi', ['label' => 'Colaram texto', 'icon' => 'fa-paste', 'k' => ['value' => $e['paste_rate'], 'delta' => null, 'prev' => null], 'format' => 'pct', 'color' => '#8e4ec6', 'tip' => '% de sessões que usaram colar — útil para detectar respostas copiadas ou automação'])</div>
        <div class="column is-3">@include('partials.kpi', ['label' => 'Iniciaram', 'icon' => 'fa-play', 'k' => ['value' => $funnel['started'], 'delta' => null, 'prev' => null], 'color' => '#22a06b'])</div>
    </div>

    @if ($funnel['started'] > 0)
        <div class="columns is-variable is-3">
            @if ($worst && $worst['dropped_here'])
                <div class="column"><div class="notification is-danger is-light is-size-7"><i class="fa-solid fa-person-walking-arrow-right mr-1"></i> <strong>Maior abandono:</strong> “{{ \Illuminate\Support\Str::limit($worst['label'], 60) }}” — {{ $worst['dropped_here'] }} pessoas pararam aqui.</div></div>
            @endif
            @if ($slowest && $slowest['avg_ms'])
                <div class="column"><div class="notification is-warning is-light is-size-7"><i class="fa-solid fa-hourglass mr-1"></i> <strong>Mais demorada:</strong> “{{ \Illuminate\Support\Str::limit($slowest['label'], 60) }}” — {{ ReportService::duration($slowest['avg_ms']) }} em média.</div></div>
            @endif
            @if ($errorest && $errorest['error_rate'] > 0)
                <div class="column"><div class="notification is-info is-light is-size-7"><i class="fa-solid fa-circle-exclamation mr-1"></i> <strong>Mais erros:</strong> “{{ \Illuminate\Support\Str::limit($errorest['label'], 60) }}” — {{ number_format($errorest['error_rate'], 1, ',', '.') }}% das interações.</div></div>
            @endif
        </div>
    @endif

    <div class="box">
        <div class="box-title"><i class="fa-solid fa-filter has-text-primary"></i> Funil por pergunta
            <span class="right has-text-muted">De {{ number_format($funnel['started'], 0, ',', '.') }} pessoas que começaram</span></div>
        @if (! $funnel['started'])
            <p class="has-text-muted is-size-7 has-text-centered py-5">Ninguém iniciou o formulário no período.</p>
        @else
            <div class="funnel-row head"><span>#</span><span>Pergunta</span><span>Alcançaram</span><span class="has-text-right">Pararam aqui</span><span class="has-text-right">Tempo médio</span><span class="has-text-right">Erros</span></div>
            @foreach ($funnel['fields'] as $row)
                <div class="funnel-row">
                    <span class="has-text-muted"><i class="fa-solid {{ $row['icon'] }}"></i></span>
                    <span class="funnel-q truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                    <div class="funnel-track"><div class="funnel-fill" style="width: {{ max(4, $row['reach_pct']) }}%">{{ number_format($row['reach_pct'], 0) }}%</div></div>
                    <span class="has-text-right {{ $row['dropped_here'] ? 'has-text-danger has-text-weight-semibold' : 'has-text-muted' }}">{{ $row['dropped_here'] ?: '—' }}</span>
                    <span class="has-text-right">{{ ReportService::duration($row['avg_ms']) }}</span>
                    <span class="has-text-right {{ $row['error_rate'] > 10 ? 'has-text-warning-dark has-text-weight-semibold' : 'has-text-muted' }}">{{ $row['errors'] ? number_format($row['error_rate'], 1, ',', '.').'%' : '—' }}</span>
                </div>
            @endforeach
        @endif
    </div>

    <div class="columns is-variable is-3">
        <div class="column is-7">
            <div class="box" style="height:100%" x-data="{ m: 'submits' }">
                <div class="box-title"><i class="fa-solid fa-calendar-week has-text-primary"></i> Quando as pessoas respondem
                    <span class="right"><span class="buttons has-addons are-small mb-0">
                        <button class="button" :class="m === 'submits' && 'is-primary is-selected'" @click="m = 'submits'">Respostas</button>
                        <button class="button" :class="m === 'views' && 'is-primary is-selected'" @click="m = 'views'">Visitas</button>
                    </span></span>
                </div>
                @php $days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb']; @endphp
                @foreach (['submits' => $heatmap, 'views' => $heatmapViews] as $key => $grid)
                    @php $mx = max(1, max(array_map('max', $grid))); @endphp
                    <div class="heatmap" x-show="m === '{{ $key }}'">
                        <span></span>
                        @for ($h = 0; $h < 24; $h++)<span class="hhead">{{ $h % 3 === 0 ? $h.'h' : '' }}</span>@endfor
                        @foreach ([1, 2, 3, 4, 5, 6, 0] as $d)
                            <span class="hlabel">{{ $days[$d] }}</span>
                            @for ($h = 0; $h < 24; $h++)
                                @php $v = $grid[$d][$h]; $a = $v ? 0.12 + 0.88 * ($v / $mx) : 0; @endphp
                                <span class="hcell" style="background: {{ $v ? "rgba(14,124,102,$a)" : '#eef2f0' }}" title="{{ $days[$d] }} {{ $h }}h: {{ $v }}"></span>
                            @endfor
                        @endforeach
                    </div>
                @endforeach
                <p class="help mt-3">Horário no fuso da conta ({{ $account->timezone }}). Use para programar e-mails, posts e verba de mídia.</p>
            </div>
        </div>
        <div class="column is-5">
            <div class="box" style="height:100%">
                <div class="box-title"><i class="fa-solid fa-stopwatch has-text-primary"></i> Tempo até enviar</div>
                <div class="chart-box is-small"><canvas id="c-dur"></canvas></div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const b = @json($engagement['duration_buckets']);
    FP.bars(document.getElementById('c-dur'), Object.keys(b), Object.values(b), { label: 'Respostas', suffix: ' respostas' });
});
</script>
@endpush
