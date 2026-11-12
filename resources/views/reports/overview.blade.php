@extends('reports.layout')
@section('report')
    <div class="columns is-multiline is-variable is-3">
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Visitas', 'icon' => 'fa-eye', 'k' => $kpis['views'], 'color' => '#0090ff', 'tip' => 'Carregamentos do formulário (bots excluídos)'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Visitantes únicos', 'icon' => 'fa-user', 'k' => $kpis['visitors'], 'color' => '#8e4ec6'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Inícios', 'icon' => 'fa-play', 'k' => $kpis['starts'], 'color' => '#8b7dff', 'tip' => 'Visitas em que a pessoa interagiu com ao menos uma pergunta'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Respostas', 'icon' => 'fa-paper-plane', 'k' => $kpis['submits'], 'color' => '#22a06b'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Taxa de início', 'icon' => 'fa-hand-pointer', 'k' => $kpis['start_rate'], 'format' => 'pct', 'tip' => 'Inícios ÷ visitas'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Taxa de conclusão', 'icon' => 'fa-flag-checkered', 'k' => $kpis['completion_rate'], 'format' => 'pct', 'color' => '#22a06b', 'tip' => 'Respostas ÷ inícios'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Conversão total', 'icon' => 'fa-bullseye', 'k' => $kpis['conversion'], 'format' => 'pct', 'color' => '#e5484d', 'tip' => 'Respostas ÷ visitas'])</div>
        <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Tempo médio de preenchimento', 'icon' => 'fa-stopwatch', 'k' => $kpis['avg_duration'], 'format' => 'duration', 'color' => '#f76b15', 'inverse' => true])</div>
    </div>

    <div class="columns is-variable is-3">
        <div class="column is-8">
            <div class="box">
                <div class="box-title"><i class="fa-solid fa-chart-line has-text-primary"></i> Evolução</div>
                <div class="chart-box"><canvas id="c-series"></canvas></div>
            </div>
        </div>
        <div class="column is-4">
            <div class="box" style="height:100%">
                <div class="box-title"><i class="fa-solid fa-filter has-text-primary"></i> Funil</div>
                @php $f = [['Visitas', $kpis['views']['value'], '#93a19c'], ['Inícios', $kpis['starts']['value'], '#e0a526'], ['Respostas', $kpis['submits']['value'], '#0e7c66']]; $top = max(1, $kpis['views']['value']); @endphp
                @foreach ($f as $i => [$l, $v, $c])
                    <div class="mb-4">
                        <div class="is-flex is-justify-content-space-between is-size-7 mb-1"><strong>{{ $l }}</strong><span>{{ number_format($v, 0, ',', '.') }} <span class="has-text-muted">({{ number_format($v / $top * 100, 1, ',', '.') }}%)</span></span></div>
                        <div class="funnel-track" style="height:34px"><div class="funnel-fill" style="width: {{ max(3, $v / $top * 100) }}%; background: {{ $c }}"></div></div>
                    </div>
                @endforeach
                <div class="notification is-light is-size-7 p-3 mt-2">
                    <strong>{{ number_format($kpis['abandons']['value'], 0, ',', '.') }}</strong> pessoas começaram e não enviaram.
                    @can('responses.view', $form) <a href="{{ route('submissions.abandoned', $form) }}">Ver respostas parciais →</a>@endcan
                    <br>Tempo médio até começar: <strong>{{ \App\Services\Reports\ReportService::duration($kpis['avg_time_to_start']['value']) }}</strong>
                    · Recorrentes: <strong>{{ number_format($kpis['returning_rate']['value'], 1, ',', '.') }}%</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="columns is-variable is-3 is-multiline">
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Canais', 'icon' => 'fa-route', 'rows' => $channels, 'segment' => 'channel', 'compact' => true, 'col' => 'Canal'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Campanhas', 'icon' => 'fa-bullhorn', 'rows' => $campaigns, 'segment' => 'utm_campaign', 'compact' => true, 'col' => 'utm_campaign'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Dispositivos', 'icon' => 'fa-mobile-screen', 'rows' => $devices, 'segment' => 'device_type', 'compact' => true, 'col' => 'Tipo'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Países', 'icon' => 'fa-earth-americas', 'rows' => $countries, 'segment' => 'country', 'compact' => true, 'col' => 'País'])</div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = @json($series);
    FP.line(document.getElementById('c-series'), s.map(x => x.label), [
        { label: 'Visitas', data: s.map(x => x.views), color: '#93a19c', dash: [4, 4] },
        { label: 'Inícios', data: s.map(x => x.starts), color: '#e0a526' },
        { label: 'Respostas', data: s.map(x => x.submits), color: '#0e7c66', width: 2.6, fill: true },
    ]);
});
</script>
@endpush
