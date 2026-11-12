@extends('reports.layout')
@section('report')
    <div class="columns is-variable is-3">
        @foreach ([['Dispositivos', 'c-dev', $devices], ['Sistemas', 'c-os', $os], ['Navegadores', 'c-br', $browsers]] as [$t, $id, $rows])
            <div class="column is-4">
                <div class="box" style="height:100%">
                    <div class="box-title">{{ $t }}</div>
                    @if (count($rows))<div class="chart-box is-small"><canvas id="{{ $id }}"></canvas></div>
                    @else <p class="has-text-muted is-size-7 has-text-centered py-6">Sem dados.</p> @endif
                </div>
            </div>
        @endforeach
    </div>

    <h2 class="section-title"><i class="fa-solid fa-mobile-screen has-text-primary"></i> Tecnologia</h2>
    <div class="columns is-variable is-3 is-multiline">
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Tipo de dispositivo', 'rows' => $devices, 'segment' => 'device_type', 'col' => 'Tipo'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Sistema operacional', 'rows' => $os, 'segment' => 'os_name', 'col' => 'Sistema'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Navegador', 'rows' => $browsers, 'segment' => 'browser_name', 'col' => 'Navegador'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Marca do aparelho', 'rows' => $brands, 'compact' => true, 'col' => 'Marca'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Modelo do aparelho', 'rows' => $models, 'compact' => true, 'col' => 'Modelo'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Resolução de tela', 'rows' => $screens, 'compact' => true, 'col' => 'Tela'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Conexão', 'rows' => $connections, 'compact' => true, 'col' => 'Tipo', 'help' => 'Qualidade estimada pela API Network Information (Chrome/Android).'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Novos × recorrentes', 'rows' => $returning, 'compact' => true, 'col' => 'Visitante'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Tema do sistema', 'rows' => $darkmode, 'compact' => true, 'col' => 'Preferência'])</div>
    </div>

    <h2 class="section-title"><i class="fa-solid fa-earth-americas has-text-primary"></i> Localização e idioma</h2>
    <div class="columns is-variable is-3 is-multiline">
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Países', 'rows' => $countries, 'segment' => 'country', 'col' => 'País'])</div>
        <div class="column is-6">@include('partials.breakdown', ['title' => 'Regiões / estados', 'rows' => $regions, 'compact' => true, 'col' => 'Região', 'help' => 'Requer CDN com geolocalização (Cloudflare, CloudFront, Vercel…).'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Cidades', 'rows' => $cities, 'compact' => true, 'col' => 'Cidade'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Idiomas', 'rows' => $languages, 'compact' => true, 'col' => 'Idioma'])</div>
        <div class="column is-4">@include('partials.breakdown', ['title' => 'Fusos horários', 'rows' => $timezones, 'compact' => true, 'col' => 'Fuso'])</div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    [['c-dev', @json($devices)], ['c-os', @json($os)], ['c-br', @json($browsers)]].forEach(([id, rows]) => {
        const el = document.getElementById(id);
        if (el) FP.doughnut(el, rows.slice(0, 6).map(x => x.label), rows.slice(0, 6).map(x => x.views));
    });
});
</script>
@endpush
