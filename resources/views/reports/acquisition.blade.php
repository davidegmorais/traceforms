@extends('reports.layout')
@section('report')
    <div class="columns is-variable is-3">
        <div class="column is-5">
            <div class="box" style="height:100%">
                <div class="box-title"><i class="fa-solid fa-route has-text-primary"></i> Canais <i class="fa-regular fa-circle-question help-icon" title="Classificação automática no padrão do GA4, combinando UTMs, click IDs e referrer."></i></div>
                @if (count($channels))
                    <div class="chart-box is-small"><canvas id="c-channels"></canvas></div>
                @else <p class="has-text-muted is-size-7 has-text-centered py-6">Sem dados.</p> @endif
            </div>
        </div>
        <div class="column is-7">@include('partials.breakdown', ['title' => 'Desempenho por canal', 'rows' => $channels, 'segment' => 'channel', 'col' => 'Canal'])</div>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-tags has-text-primary"></i> Parâmetros UTM</h2>
    <div class="columns is-variable is-3 is-multiline">
        <div class="column is-12">@include('partials.breakdown', ['title' => 'Origem / Mídia', 'icon' => 'fa-shuffle', 'rows' => $sourceMedium, 'col' => 'source / medium'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Campanhas', 'rows' => $campaigns, 'segment' => 'utm_campaign', 'col' => 'utm_campaign'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Origens', 'rows' => $sources, 'segment' => 'utm_source', 'col' => 'utm_source'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Mídias', 'rows' => $mediums, 'segment' => 'utm_medium', 'col' => 'utm_medium'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Conteúdos (criativos)', 'rows' => $contents, 'segment' => 'utm_content', 'col' => 'utm_content'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Termos', 'rows' => $terms, 'segment' => 'utm_term', 'col' => 'utm_term'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Links rastreados', 'icon' => 'fa-link', 'rows' => $links, 'segment' => 'tracking_link_id', 'col' => 'Link'])</div>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-diagram-project has-text-primary"></i> Atribuição</h2>
    <div class="columns is-variable is-3">
        <div class="column is-7">
            <div class="box" style="height:100%">
                <div class="box-title">Primeiro toque × último toque <i class="fa-regular fa-circle-question help-icon" title="Primeiro toque: a campanha que trouxe o visitante pela primeira vez (até 90 dias). Último toque: a campanha da visita em que ele respondeu."></i></div>
                @if (count($attribution))
                    <div class="chart-box"><canvas id="c-attr"></canvas></div>
                @else <p class="has-text-muted is-size-7 has-text-centered py-6">Sem respostas no período.</p> @endif
            </div>
        </div>
        <div class="column is-5">@include('partials.breakdown', ['title' => 'Click IDs de anúncios', 'icon' => 'fa-arrow-pointer', 'rows' => $clickIds, 'segment' => null, 'compact' => true, 'col' => 'Plataforma', 'help' => 'Identificadores adicionados automaticamente pelas plataformas de anúncio.'])</div>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-globe has-text-primary"></i> Referências e páginas</h2>
    <div class="columns is-variable is-3 is-multiline">
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Sites de referência', 'rows' => $referrers, 'segment' => 'referrer_domain', 'compact' => true, 'col' => 'Domínio'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Páginas de entrada / onde foi incorporado', 'rows' => $landings, 'compact' => true, 'col' => 'Página'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Forma de acesso', 'rows' => $embed, 'compact' => true, 'col' => 'Modo'])</div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ch = @json($channels);
    const c1 = document.getElementById('c-channels');
    if (c1) FP.doughnut(c1, ch.map(x => x.label), ch.map(x => x.views), ch.map(x => x.color));
    const at = @json($attribution);
    const c2 = document.getElementById('c-attr');
    if (c2) {
        FP.chartDefaults();
        new Chart(c2, {
            type: 'bar',
            data: { labels: at.map(x => x.label), datasets: [
                { label: 'Primeiro toque', data: at.map(x => x.first), backgroundColor: '#e0a526', borderRadius: 4 },
                { label: 'Último toque', data: at.map(x => x.last), backgroundColor: '#0e7c66', borderRadius: 4 },
            ]},
            options: { indexAxis: 'y', scales: { x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f0eff6' } }, y: { grid: { display: false } } }, plugins: { legend: { position: 'top', align: 'end' } } },
        });
    }
});
</script>
@endpush
