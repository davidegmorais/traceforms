@extends('layouts.app')
@section('title', 'Configurações da conta')
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
        <div class="box-title"><i class="fa-solid fa-building has-text-primary"></i> Geral</div>
        <div class="field"><label class="label">Nome da conta</label><input class="input" name="name" value="{{ old('name', $account->name) }}" required></div>
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

    @can('account.transfer')
        <form method="POST" action="{{ route('account.transfer') }}" class="box" x-data="{ open: false }">
            @csrf
            <div class="box-title"><i class="fa-solid fa-crown has-text-warning"></i> Transferir propriedade</div>
            <p class="is-size-7 has-text-muted mb-3">O novo proprietário terá controle total. Você passará a ser Administrador.</p>
            @if ($transferTargets->isEmpty())
                <p class="is-size-7">Convide alguém para a equipe primeiro.</p>
            @else
                <button type="button" class="button is-small" @click="open = !open" x-show="!open">Transferir…</button>
                <div x-show="open" x-cloak>
                    <div class="field"><div class="select"><select name="membership_id">@foreach ($transferTargets as $t)<option value="{{ $t->id }}">{{ $t->user->name }} ({{ $t->role->label() }})</option>@endforeach</select></div></div>
                    <div class="field"><label class="label is-small">Digite <strong>{{ $account->name }}</strong> para confirmar</label><input class="input" name="confirm" autocomplete="off"></div>
                    <button class="button is-warning">Transferir propriedade</button>
                </div>
            @endif
        </form>
    @endcan

    @can('account.delete')
        <form method="POST" action="{{ route('account.destroy') }}" class="box" style="border-color:#f5c2c4" x-data="{ open: false }">
            @csrf @method('DELETE')
            <div class="box-title has-text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Zona de perigo</div>
            <p class="is-size-7 mb-3">Excluir a conta remove permanentemente todos os formulários, respostas, dados de visitas e acessos da equipe.</p>
            <button type="button" class="button is-danger is-light is-small" @click="open = true" x-show="!open">Excluir conta…</button>
            <div x-show="open" x-cloak>
                <div class="field"><label class="label is-small">Digite <strong>{{ $account->name }}</strong> para confirmar</label><input class="input" name="confirm" autocomplete="off"></div>
                <button class="button is-danger">Excluir definitivamente</button>
            </div>
        </form>
    @endcan
</div>
@endsection
