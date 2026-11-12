@extends('layouts.app')
@section('title', 'Visão geral')
@section('crumb'){{ $account->name }}@endsection
@section('actions')
    @can('forms.create')
        <a href="{{ route('forms.create') }}" class="button is-primary is-small"><i class="fa-solid fa-plus mr-2"></i> Novo formulário</a>
    @endcan
@endsection

@section('content')
    @if ($formCount === 0)
        <div class="box empty">
            <div class="icon-big"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
            <h3>Vamos criar seu primeiro formulário</h3>
            <p>Escolha um modelo pronto (captura de leads, NPS, eventos…) ou comece do zero. Cada visita é rastreada automaticamente: origem, UTMs, dispositivo e muito mais.</p>
            @can('forms.create')
                <a href="{{ route('forms.create') }}" class="button is-primary is-medium"><i class="fa-solid fa-plus mr-2"></i> Criar formulário</a>
            @else
                <p class="has-text-muted is-size-7">Peça a um editor ou administrador para criar um formulário.</p>
            @endcan
        </div>
    @else
        @include('partials.filters', ['filters' => $filters])

        <div class="columns is-multiline is-variable is-3">
            <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Visitas', 'icon' => 'fa-eye', 'k' => $kpis['views'], 'color' => '#0090ff'])</div>
            <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Respostas', 'icon' => 'fa-inbox', 'k' => $kpis['submits'], 'color' => '#22a06b'])</div>
            <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Taxa de conversão', 'icon' => 'fa-bullseye', 'k' => $kpis['conversion'], 'format' => 'pct', 'tip' => 'Respostas ÷ visitas'])</div>
            <div class="column is-6-tablet is-3-desktop">@include('partials.kpi', ['label' => 'Tempo médio', 'icon' => 'fa-stopwatch', 'k' => $kpis['avg_duration'], 'format' => 'duration', 'color' => '#f76b15', 'inverse' => true, 'tip' => 'Tempo médio de preenchimento de quem enviou'])</div>
        </div>

        <div class="columns is-variable is-3">
            <div class="column is-8">
                <div class="box">
                    <div class="box-title"><i class="fa-solid fa-chart-line has-text-primary"></i> Desempenho no período</div>
                    <div class="chart-box"><canvas id="chart-series"></canvas></div>
                </div>
            </div>
            <div class="column is-4">
                <div class="box" style="height:100%">
                    <div class="box-title"><i class="fa-solid fa-route has-text-primary"></i> Canais <a href="{{ route('campaigns') }}" class="right">Ver campanhas →</a></div>
                    @if (count($channels))
                        <div class="chart-box is-small"><canvas id="chart-channels"></canvas></div>
                    @else
                        <p class="has-text-muted is-size-7 has-text-centered py-6">Sem visitas no período.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="columns is-variable is-3">
            <div class="column is-7">
                <div class="box" style="height:100%">
                    <div class="box-title"><i class="fa-solid fa-trophy has-text-primary"></i> Formulários em destaque <a href="{{ route('forms.index') }}" class="right">Todos →</a></div>
                    <table class="table is-fullwidth is-hoverable">
                        <thead><tr><th>Formulário</th><th class="has-text-right">Visitas</th><th class="has-text-right">Respostas</th><th class="has-text-right">Conv.</th></tr></thead>
                        <tbody>
                        @foreach ($topForms as $f)
                            <tr>
                                <td><a href="{{ route('reports.show', $f) }}" class="has-text-weight-semibold" style="color:inherit">{{ $f->title }}</a>
                                    <br><span class="tag is-light {{ $f->status === 'published' ? 'is-success' : ($f->status === 'closed' ? 'is-danger' : 'is-warning') }} is-size-8">{{ ['published' => 'Publicado', 'closed' => 'Encerrado', 'draft' => 'Rascunho'][$f->status] }}</span></td>
                                <td class="has-text-right">{{ number_format($f->views, 0, ',', '.') }}</td>
                                <td class="has-text-right has-text-weight-semibold">{{ number_format($f->submits, 0, ',', '.') }}</td>
                                <td class="has-text-right">{{ number_format($f->conversion, 1, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="column is-5">
                @include('partials.breakdown', ['title' => 'Principais campanhas', 'icon' => 'fa-bullhorn', 'rows' => $campaigns, 'compact' => true, 'col' => 'utm_campaign'])
            </div>
        </div>

        @if ($recent->count())
            <div class="box">
                <div class="box-title"><i class="fa-solid fa-bolt has-text-primary"></i> Respostas recentes</div>
                <div class="table-wrap">
                <table class="table is-fullwidth is-hoverable">
                    <thead><tr><th>Resposta</th><th>Formulário</th><th>Origem</th><th>Dispositivo</th><th class="has-text-right">Quando</th></tr></thead>
                    <tbody>
                    @foreach ($recent as $sub)
                        <tr class="is-clickable" onclick="location='{{ route('submissions.show', [$sub->form, $sub]) }}'">
                            <td class="has-text-weight-semibold">{{ \Illuminate\Support\Str::limit($sub->headline($sub->form), 40) }}</td>
                            <td class="has-text-muted">{{ \Illuminate\Support\Str::limit($sub->form->title, 30) }}</td>
                            <td>
                                @if ($sub->session)
                                    <span class="legend-dot" style="background: {{ \App\Services\Tracking\ChannelClassifier::COLORS[$sub->session->channel] ?? '#ccc' }}"></span>{{ \App\Services\Tracking\ChannelClassifier::label($sub->session->channel) }}
                                    @if ($sub->session->utm_campaign)<span class="has-text-muted is-size-7">· {{ $sub->session->utm_campaign }}</span>@endif
                                @else — @endif
                            </td>
                            <td class="has-text-muted">@if ($sub->session)<i class="fa-solid {{ $sub->session->deviceIcon() }} mr-1"></i> {{ $sub->session->os_name }} · {{ $sub->session->browser_name }}@endif</td>
                            <td class="has-text-right has-text-muted nowrap" title="{{ $sub->submitted_at->setTimezone($account->timezone)->format('d/m/Y H:i') }}">{{ $sub->submitted_at->locale('pt_BR')->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = @json($series);
    const el = document.getElementById('chart-series');
    if (el) FP.line(el, s.map(x => x.label), [
        { label: 'Visitas', data: s.map(x => x.views), color: '#93a19c', dash: [4, 4] },
        { label: 'Inícios', data: s.map(x => x.starts), color: '#e0a526' },
        { label: 'Respostas', data: s.map(x => x.submits), color: '#0e7c66', width: 2.6, fill: true },
    ]);
    const ch = @json($channels);
    const c2 = document.getElementById('chart-channels');
    if (c2) FP.doughnut(c2, ch.map(x => x.label), ch.map(x => x.views), ch.map(x => x.color));
});
</script>
@endpush
