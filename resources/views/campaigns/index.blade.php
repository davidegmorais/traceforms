@extends('layouts.app')
@section('title', 'Campanhas & UTMs')
@section('crumb')Todos os formulários de {{ $account->name }}@endsection

@section('content')
@php
    $formNames = $forms->pluck('title', 'id');
    $byCampaign = $matrix->groupBy('utm_campaign');
    $matrixForms = $matrix->pluck('form_id')->unique()->values();
@endphp

@include('partials.filters', ['filters' => $filters, 'segmentOptions' => $segmentOptions, 'extra' => new \Illuminate\Support\HtmlString(
    '<div class="select is-small"><select name="form" onchange="this.form.submit()"><option value="">Todos os formulários</option>'.
    $forms->map(fn ($f) => '<option value="'.$f->id.'"'.(request('form') == $f->id ? ' selected' : '').'>'.e(\Illuminate\Support\Str::limit($f->title, 40)).'</option>')->implode('').
    '</select></div>'
)])

<div class="columns is-multiline is-variable is-3">
    <div class="column is-3">@include('partials.kpi', ['label' => 'Visitas', 'icon' => 'fa-eye', 'k' => $kpis['views'], 'color' => '#0090ff'])</div>
    <div class="column is-3">@include('partials.kpi', ['label' => 'Respostas', 'icon' => 'fa-paper-plane', 'k' => $kpis['submits'], 'color' => '#22a06b'])</div>
    <div class="column is-3">@include('partials.kpi', ['label' => 'Conversão', 'icon' => 'fa-bullseye', 'k' => $kpis['conversion'], 'format' => 'pct'])</div>
    <div class="column is-3">@include('partials.kpi', ['label' => 'Visitantes únicos', 'icon' => 'fa-user', 'k' => $kpis['visitors'], 'color' => '#8e4ec6'])</div>
</div>

<div class="columns is-variable is-3">
    <div class="column is-4">
        <div class="box" style="height:100%">
            <div class="box-title"><i class="fa-solid fa-route has-text-primary"></i> Respostas por canal</div>
            @if (collect($channels)->sum('submits'))<div class="chart-box is-small"><canvas id="c-ch"></canvas></div>
            @else <p class="has-text-muted is-size-7 has-text-centered py-6">Sem respostas no período.</p> @endif
        </div>
    </div>
    <div class="column is-8">@include('partials.breakdown', ['title' => 'Canais', 'rows' => $channels, 'segment' => 'channel', 'col' => 'Canal'])</div>
</div>

<div class="columns is-variable is-3 is-multiline">
    <div class="column is-12">@include('partials.breakdown', ['title' => 'Campanhas (utm_campaign)', 'icon' => 'fa-bullhorn', 'rows' => $campaigns, 'segment' => 'utm_campaign', 'col' => 'Campanha'])</div>
    <div class="column is-6">@include('partials.breakdown', ['title' => 'Origem / Mídia', 'rows' => $sourceMedium, 'compact' => true, 'col' => 'source / medium'])</div>
    <div class="column is-6">@include('partials.breakdown', ['title' => 'Origens', 'rows' => $sources, 'segment' => 'utm_source', 'compact' => true, 'col' => 'utm_source'])</div>
    <div class="column is-6">@include('partials.breakdown', ['title' => 'Criativos (utm_content)', 'rows' => $contents, 'compact' => true, 'col' => 'utm_content'])</div>
    <div class="column is-6">@include('partials.breakdown', ['title' => 'Termos (utm_term)', 'rows' => $terms, 'compact' => true, 'col' => 'utm_term'])</div>
</div>

@if ($byCampaign->count() && $matrixForms->count() > 1)
    <div class="box">
        <div class="box-title"><i class="fa-solid fa-table-cells has-text-primary"></i> Campanha × formulário <span class="right has-text-muted">respostas / visitas</span></div>
        <div class="table-wrap">
            <table class="table is-fullwidth is-narrow is-hoverable">
                <thead><tr><th>Campanha</th>@foreach ($matrixForms as $fid)<th class="has-text-right">{{ \Illuminate\Support\Str::limit($formNames[$fid] ?? '#'.$fid, 22) }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($byCampaign->sortByDesc(fn ($rows) => $rows->sum('submits'))->take(25) as $camp => $rows)
                    <tr><td class="has-text-weight-semibold">{{ $camp }}</td>
                        @foreach ($matrixForms as $fid)
                            @php $c = $rows->firstWhere('form_id', $fid); @endphp
                            <td class="has-text-right nowrap">@if ($c)<strong>{{ $c->submits }}</strong> <span class="has-text-muted">/ {{ $c->views }}</span>@else <span class="has-text-grey-lighter">—</span> @endif</td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="box">
    <div class="box-title"><i class="fa-solid fa-diagram-project has-text-primary"></i> Atribuição: primeiro × último toque
        <i class="fa-regular fa-circle-question help-icon" title="Compara a campanha que trouxe o visitante pela primeira vez com a campanha da visita em que ele respondeu."></i></div>
    @if (count($attribution))
        <table class="table is-fullwidth is-narrow">
            <thead><tr><th>Campanha</th><th class="has-text-right">1º toque</th><th class="has-text-right">Último toque</th><th>Leitura</th></tr></thead>
            <tbody>
            @foreach ($attribution as $a)
                <tr><td>{{ $a['label'] }}</td><td class="has-text-right">{{ $a['first'] }}</td><td class="has-text-right">{{ $a['last'] }}</td>
                    <td class="is-size-7 has-text-muted">{{ $a['first'] > $a['last'] ? 'Mais forte em gerar descoberta' : ($a['last'] > $a['first'] ? 'Mais forte em fechar conversão' : 'Equilibrada') }}</td></tr>
            @endforeach
            </tbody>
        </table>
    @else <p class="has-text-muted is-size-7">Sem respostas no período.</p> @endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ch = @json($channels).filter(x => x.submits > 0);
    const el = document.getElementById('c-ch');
    if (el) FP.doughnut(el, ch.map(x => x.label), ch.map(x => x.submits), ch.map(x => x.color));
});
</script>
@endpush
