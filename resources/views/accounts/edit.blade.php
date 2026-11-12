@extends('layouts.app')
@section('title', 'Configurações da empresa')
@section('crumb'){{ $account->name }}@endsection

@section('content')
<div style="max-width:860px">
    <div class="columns is-variable is-3 mb-2">
        <div class="column"><div class="box kpi"><div class="label">Formulários</div><div class="value">{{ $stats['forms'] }}</div></div></div>
        <div class="column"><div class="box kpi"><div class="label">Respostas</div><div class="value">{{ number_format($stats['responses'], 0, ',', '.') }}</div></div></div>
        <div class="column"><div class="box kpi"><div class="label">Membros</div><div class="value">{{ $stats['members'] }}</div></div></div>
    </div>

    <form method="POST" action="{{ route('account.update') }}" class="box">
        @csrf @method('PUT')
        <div class="box-title">Geral</div>
        <div class="field">
            <label class="label">Nome da empresa</label>
            <p>{{ $account->name }}</p>
            <p class="help">O nome vem do Trace ID e é atualizado a cada login. Para mudar, altere lá.</p>
        </div>
        <div class="columns">
            <div class="column field">
                <label class="label">Fuso horário dos relatórios</label>
                <div class="select is-fullwidth"><select name="timezone">
                    @foreach ($timezones as $tz)<option value="{{ $tz }}" @selected($account->timezone === $tz)>{{ $tz }}</option>@endforeach
                </select></div>
            </div>
            <div class="column is-4 field">
                <label class="label">Cor da marca</label>
                <div class="is-flex" style="gap:.5rem"><input type="color" class="swatch" name="brand_color" value="{{ $account->brand_color }}"><span class="help">Usada como padrão em novos formulários.</span></div>
            </div>
        </div>
        <div class="field">
            <label class="label">Retenção de dados de visitas (dias)</label>
            <input type="number" class="input" name="data_retention_days" min="0" max="3650" value="{{ $account->setting('data_retention_days', 0) }}" style="max-width:160px">
            <p class="help">Sessões sem resposta mais antigas que isso são apagadas pelo comando agendado <span class="mono">traceforms:prune</span>. 0 = manter para sempre.</p>
        </div>
        <button class="button is-primary">Salvar</button>
    </form>

    @if ($account->trace_features)
        <div class="box">
            <div class="box-title">Plano no Trace ID</div>
            @foreach ($account->trace_features as $key => $value)
                <div class="stat-line"><span>{{ \Illuminate\Support\Str::of($key)->replace('_', ' ')->ucfirst() }}</span><strong>{{ is_bool($value) ? ($value ? 'Incluso' : 'Não incluso') : ($value === null ? 'Ilimitado' : $value) }}</strong></div>
            @endforeach
            <p class="help mt-2">Atualizado em {{ $account->trace_synced_at?->timezone($account->timezone)->format('d/m/Y H:i') ?? '—' }}.</p>
        </div>
    @endif
</div>
@endsection
