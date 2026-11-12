{{--
    Tabela de quebra com barras proporcionais.
    Params: $title, $rows, $icon?, $segment? (coluna p/ filtrar ao clicar), $route? (rota base), $compact?, $help?
--}}
@php
    $max = collect($rows)->max('views') ?: 1;
    $compact = $compact ?? false;
    $convClass = fn ($c) => $c >= 30 ? 'has-background-success-light has-text-success-dark' : ($c >= 10 ? 'has-background-warning-light has-text-warning-dark' : 'has-background-light has-text-grey');
@endphp
<div class="box" style="height:100%">
    <div class="box-title">
        @isset($icon)<i class="fa-solid {{ $icon }} has-text-primary"></i>@endisset
        {{ $title }}
        @isset($help)<i class="fa-regular fa-circle-question help-icon" title="{{ $help }}"></i>@endisset
    </div>
    @if (empty($rows))
        <p class="has-text-muted is-size-7 py-4 has-text-centered">Sem dados no período.</p>
    @else
        <div class="table-wrap">
            <table class="table is-fullwidth is-narrow is-hoverable mb-0">
                <thead><tr>
                    <th>{{ $col ?? 'Valor' }}</th>
                    <th class="has-text-right">Visitas</th>
                    @unless ($compact)<th class="has-text-right">Inícios</th>@endunless
                    <th class="has-text-right">Envios</th>
                    <th class="has-text-right">Conv.</th>
                </tr></thead>
                <tbody>
                @foreach ($rows as $r)
                    <tr>
                        <td class="bar-cell" style="max-width:260px">
                            <div class="bar" style="width: {{ max(2, $r['views'] / $max * 100) }}%"></div>
                            @if (! empty($segment) && isset($r['key']) && isset($filters))
                                <a href="{{ request()->fullUrlWithQuery([$segment => $r['key'] ?? '(nenhum)', 'page' => null]) }}" class="truncate has-text-weight-medium" style="position:relative;z-index:1;color:inherit" title="Filtrar por {{ $r['label'] }}">
                                    @isset($r['color'])<span class="legend-dot" style="background:{{ $r['color'] }}"></span>@endisset{{ $r['label'] }}
                                </a>
                            @else
                                <span class="truncate has-text-weight-medium" title="{{ $r['label'] }}">@isset($r['color'])<span class="legend-dot" style="background:{{ $r['color'] }}"></span>@endisset{{ $r['label'] }}</span>
                            @endif
                        </td>
                        <td class="has-text-right nowrap">{{ number_format($r['views'], 0, ',', '.') }}</td>
                        @unless ($compact)<td class="has-text-right nowrap">{{ number_format($r['starts'], 0, ',', '.') }}</td>@endunless
                        <td class="has-text-right nowrap has-text-weight-semibold">{{ number_format($r['submits'], 0, ',', '.') }}</td>
                        <td class="has-text-right"><span class="conv-pill {{ $convClass($r['conversion']) }}">{{ number_format($r['conversion'], 1, ',', '.') }}%</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
