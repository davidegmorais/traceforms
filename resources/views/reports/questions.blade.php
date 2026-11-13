@extends('reports.layout')
@section('report')
    @if (! $summary['total'])
        <div class="box empty">
            <div class="icon-big"><i class="fa-solid fa-square-poll-vertical"></i></div>
            <h3>Nenhuma resposta no período</h3>
            <p>Ajuste o período ou os filtros acima, ou compartilhe o formulário para começar a receber respostas.</p>
            <a href="{{ route('forms.share', $form) }}" class="button is-primary">Compartilhar</a>
        </div>
    @else
        <p class="is-size-7 has-text-muted mb-4">Baseado em <strong>{{ number_format($summary['total'], 0, ',', '.') }}</strong> respostas (spam excluído).</p>
        @foreach ($summary['items'] as $i => $item)
            @php $f = $item['field']; @endphp
            <div class="box">
                <div class="is-flex is-justify-content-space-between is-align-items-flex-start mb-3" style="gap:1rem">
                    <div>
                        <p class="is-size-8 has-text-muted has-text-weight-bold mb-1"><i class="fa-solid {{ $f->icon() }} mr-1"></i> {{ $f->typeLabel() }}</p>
                        <p class="has-text-weight-bold is-size-6">{{ $f->label }}</p>
                    </div>
                    <div class="has-text-right is-size-7 has-text-muted nowrap">
                        <strong class="has-text-dark">{{ number_format($item['answered'], 0, ',', '.') }}</strong> responderam
                        @if ($item['skipped']) · {{ number_format($item['skipped'], 0, ',', '.') }} pularam @endif
                        <div class="mini-bar mt-1" style="width:140px;margin-left:auto"><div style="width: {{ $item['response_rate'] }}%"></div></div>
                    </div>
                </div>

                @if ($item['stat'])
                    <div class="is-flex is-align-items-center mb-4" style="gap:1.5rem">
                        <div><span class="is-size-8 has-text-muted has-text-weight-bold">{{ $item['stat']['label'] }}</span>
                            <p style="font-size:2rem;font-weight:800;letter-spacing:-.03em;line-height:1.1" class="{{ isset($item['stat']['nps']) ? ((int) $item['stat']['value'] >= 50 ? 'has-text-success' : ((int) $item['stat']['value'] >= 0 ? 'has-text-warning-dark' : 'has-text-danger')) : '' }}">{{ $item['stat']['value'] }} <small class="is-size-6 has-text-muted">{{ $item['stat']['suffix'] }}</small></p></div>
                        @isset($item['stat']['nps'])
                            @php $n = $item['stat']['nps']; @endphp
                            <div style="flex:1;max-width:520px">
                                <div class="is-flex" style="height:14px;border-radius:99px;overflow:hidden">
                                    <div style="width:{{ $n['detractors'] }}%;background:#c4320a"></div><div style="width:{{ $n['passives'] }}%;background:#e0a526"></div><div style="width:{{ $n['promoters'] }}%;background:#0e7c66"></div>
                                </div>
                                <div class="is-flex is-justify-content-space-between is-size-7 mt-1">
                                    <span><span class="legend-dot" style="background:#c4320a"></span>Detratores {{ number_format($n['detractors'], 1, ',', '.') }}%</span>
                                    <span><span class="legend-dot" style="background:#e0a526"></span>Neutros {{ number_format($n['passives'], 1, ',', '.') }}%</span>
                                    <span><span class="legend-dot" style="background:#0e7c66"></span>Promotores {{ number_format($n['promoters'], 1, ',', '.') }}%</span>
                                </div>
                            </div>
                        @endisset
                    </div>
                @endif

                @if ($item['ranking'])
                    @php $n = max(1, count($item['ranking'])); @endphp
                    <table class="table is-fullwidth is-narrow">
                        <thead><tr><th style="width:40px">#</th><th>Item</th><th>Posição média</th><th class="has-text-right">Escolhido em 1º</th></tr></thead>
                        <tbody>
                        @foreach ($item['ranking'] as $pos => $r)
                            <tr>
                                <td class="has-text-muted">{{ $pos + 1 }}</td>
                                <td class="has-text-weight-semibold">{{ $r['label'] }}</td>
                                <td style="width:40%"><div class="is-flex is-align-items-center" style="gap:.6rem"><div class="mini-bar" style="flex:1"><div style="width: {{ $r['avg'] ? (($n - $r['avg'] + 1) / $n * 100) : 0 }}%"></div></div><span class="is-tabular">{{ $r['avg'] ? number_format($r['avg'], 1, ',', '.') : '—' }}</span></div></td>
                                <td class="has-text-right is-tabular">{{ number_format($r['first_pct'], 1, ',', '.') }}%</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="help">Quanto menor a posição média, mais importante para quem respondeu.</p>
                @elseif ($item['grid'])
                    @php $g = $item['grid']; @endphp
                    <div class="table-wrap">
                        <table class="table is-fullwidth is-narrow matrix-table">
                            <thead><tr><th></th>@foreach ($g['cols'] as $c)<th class="has-text-centered">{{ $c }}</th>@endforeach</tr></thead>
                            <tbody>
                            @foreach ($g['rows'] as $row)
                                @php $tot = max(1, array_sum($g['counts'][$row] ?? [])); @endphp
                                <tr>
                                    <td class="has-text-weight-semibold">{{ $row }}</td>
                                    @foreach ($g['cols'] as $c)
                                        @php $pc = round(($g['counts'][$row][$c] ?? 0) / $tot * 100); @endphp
                                        <td class="has-text-centered is-tabular" style="background: color-mix(in srgb, var(--tf-primary) {{ $pc }}%, #fff); color: {{ $pc > 55 ? '#fff' : 'inherit' }}">{{ $pc }}%</td>
                                    @endforeach
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif ($item['chart'])
                    @php $vertical = in_array($f->type, ['nps', 'scale', 'rating', 'time'], true); @endphp
                    <div class="chart-box {{ $vertical ? 'is-small' : '' }}" style="{{ $vertical ? '' : 'height:'.max(120, count($item['chart']) * 34 + 30).'px' }}"><canvas id="q-{{ $i }}"></canvas></div>
                @elseif (count($item['samples']))
                    <p class="is-size-8 has-text-muted has-text-weight-bold mb-2">Respostas recentes</p>
                    @foreach ($item['samples'] as $sm)
                        <div class="py-2" style="border-bottom:1px solid var(--tf-line)">
                            <span class="is-size-7">{{ $sm['text'] }}</span>
                            <span class="is-size-8 has-text-muted ml-2">{{ $sm['at']->locale('pt_BR')->diffForHumans() }}</span>
                        </div>
                    @endforeach
                    @can('responses.view', $form)
                        <a href="{{ route('submissions.index', $form) }}" class="is-size-7 mt-2 is-inline-block">Ver todas as respostas →</a>
                    @endcan
                @else
                    <p class="has-text-muted is-size-7">Sem respostas para esta pergunta.</p>
                @endif
            </div>
        @endforeach
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = @json(collect($summary['items'])->map(fn ($it) => ['type' => $it['field']->type, 'chart' => $it['chart']]));
    items.forEach((it, i) => {
        const el = document.getElementById('q-' + i);
        if (!el || !it.chart) return;
        const labels = it.chart.map(c => c.label), values = it.chart.map(c => c.count);
        if (['nps', 'scale', 'rating', 'time'].includes(it.type)) {
            const colors = it.type === 'nps' ? labels.map(l => l <= 6 ? '#c4320a' : (l <= 8 ? '#e0a526' : '#0e7c66')) : '#0e7c66';
            FP.bars(el, it.type === 'rating' ? labels.map(l => l + '★') : labels, values, { colors, suffix: ' respostas' });
        } else {
            FP.bars(el, labels.map((l, k) => l + '  (' + it.chart[k].pct.toLocaleString('pt-BR') + '%)'), values, { horizontal: true, colors: FP.palette, suffix: ' respostas' });
        }
    });
});
</script>
@endpush
