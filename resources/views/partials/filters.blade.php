{{-- Barra de filtros global: período + segmentos. Params: $filters, $segmentOptions?, $extra? --}}
@php
    use App\Services\Reports\ReportFilters;
    use App\Services\Tracking\ChannelClassifier;
    $labels = ReportFilters::SEGMENTS;
@endphp
<form method="GET" class="filter-bar" x-data="{ range: @js($filters->range) }">
    @foreach (request()->except(['range', 'from', 'to', 'page', ...array_keys($segmentOptions ?? [])]) as $k => $v)
        @if (is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    {{ $extra ?? '' }}

    <div class="select is-small">
        <select name="range" x-model="range" @change="range !== 'custom' && $el.form.submit()">
            @foreach (ReportFilters::RANGES as $k => $l)
                <option value="{{ $k }}" @selected($filters->range === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <template x-if="range === 'custom'">
        <div class="is-flex" style="gap:.35rem">
            <input type="date" class="input is-small" name="from" value="{{ $filters->from->format('Y-m-d') }}" style="width:140px">
            <input type="date" class="input is-small" name="to" value="{{ $filters->to->format('Y-m-d') }}" style="width:140px">
            <button class="button is-small is-primary">Aplicar</button>
        </div>
    </template>

    @foreach ($segmentOptions ?? [] as $seg => $values)
        @if (count($values))
            <div class="select is-small">
                <select name="{{ $seg }}" onchange="this.form.submit()">
                    <option value="">{{ $labels[$seg] ?? $seg }}: todos</option>
                    @foreach ($values as $v)
                        <option value="{{ $v }}" @selected(($filters->segments[$seg] ?? null) === $v)>{{ $seg === 'channel' ? ChannelClassifier::label($v) : ($seg === 'device_type' ? \App\Http\Controllers\ReportController::deviceLabel($v) : $v) }}</option>
                    @endforeach
                </select>
            </div>
        @endif
    @endforeach

    <span class="has-text-muted is-size-7 ml-1"><i class="fa-regular fa-calendar mr-1"></i>{{ $filters->from->format('d/m/Y') }} – {{ $filters->to->format('d/m/Y') }}</span>
</form>

@if ($filters->segments)
    <div class="is-flex is-flex-wrap-wrap mb-4" style="gap:.4rem;margin-top:-.5rem">
        <span class="is-size-7 has-text-muted mr-1" style="align-self:center">Segmento:</span>
        @foreach ($filters->segments as $k => $v)
            <span class="chip">{{ $labels[$k] ?? $k }}: {{ $k === 'channel' ? ChannelClassifier::label($v) : $v }} <a href="{{ request()->fullUrlWithQuery([$k => null]) }}" aria-label="Remover"><i class="fa-solid fa-xmark"></i></a></span>
        @endforeach
        <a href="{{ request()->fullUrlWithQuery(array_fill_keys(array_keys($filters->segments), null)) }}" class="is-size-7 ml-2" style="align-self:center">Limpar</a>
    </div>
@endif
