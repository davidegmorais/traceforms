@php
    /** @var array $k ['value','prev','delta'] */
    $fmt = $format ?? 'int';
    $show = fn ($v) => match ($fmt) {
        'pct' => number_format((float) $v, 1, ',', '.').'%',
        'duration' => \App\Services\Reports\ReportService::duration($v),
        default => number_format((int) $v, 0, ',', '.'),
    };
    $d = $k['delta'];
    $good = $d === null ? null : (($inverse ?? false) ? $d < 0 : $d > 0);
@endphp
<div class="kpi">
    <div class="label">
        {{ $label }}
        @isset($tip)<i class="fa-regular fa-circle-question has-text-muted" title="{{ $tip }}"></i>@endisset
    </div>
    <div class="value">{{ $display ?? ($k['value'] === null ? '—' : $show($k['value'])) }}</div>
    @if ($d !== null)
        <div class="delta {{ $d == 0 ? 'flat' : ($good ? 'up' : 'down') }}">
            <i class="fa-solid {{ $d > 0 ? 'fa-arrow-trend-up' : ($d < 0 ? 'fa-arrow-trend-down' : 'fa-minus') }}"></i>
            {{ $d > 0 ? '+' : '' }}{{ number_format($d, 1, ',', '.') }}%
            <span class="has-text-muted has-text-weight-normal">vs. {{ $k['prev'] === null ? '—' : $show($k['prev']) }}</span>
        </div>
    @else
        <div class="delta flat">&nbsp;</div>
    @endif
</div>
