@extends('layouts.admin')
@section('title', 'Visão geral')


@section('content')
@php $n = fn ($v) => number_format($v, 0, ',', '.'); $k = fn ($v) => ['value' => $v, 'delta' => null, 'prev' => null]; @endphp
<div class="columns is-multiline">
    <div class="column">@include('partials.kpi', ['label' => 'Empresas', 'icon' => '', 'k' => $k($totals['accounts']), 'display' => $n($totals['accounts'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Usuários', 'icon' => '', 'k' => $k($totals['users']), 'display' => $n($totals['users'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Formulários publicados', 'icon' => '', 'k' => $k($totals['published']), 'display' => $n($totals['published']).' / '.$n($totals['forms'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Respostas (30 dias)', 'icon' => '', 'k' => $k($totals['responses_30d']), 'display' => $n($totals['responses_30d'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Arquivos armazenados', 'icon' => '', 'k' => $k(0), 'display' => \App\Models\Upload::humanSize($totals['storage'])])</div>
</div>

<div class="columns">
    <div class="column is-8">
        <div class="box">
            <div class="box-title">Últimos 30 dias</div>
            <div class="chart-box"><canvas id="c-platform"></canvas></div>
        </div>
    </div>
    <div class="column is-4">
        <div class="box" style="height:100%">
            <div class="box-title">Situação</div>
            <div class="stat-line"><span>Respostas no total</span><strong>{{ $n($totals['responses']) }}</strong></div>
            <div class="stat-line"><span>Visitas a formulários (30 dias)</span><strong>{{ $n($totals['sessions_30d']) }}</strong></div>
            <div class="stat-line"><span>Empresas suspensas</span><strong>{{ $n($totals['suspended']) }}</strong></div>
            <div class="stat-line"><span>Formulários em rascunho/encerrados</span><strong>{{ $n($totals['forms'] - $totals['published']) }}</strong></div>
            <div class="buttons mt-4">
                <a href="{{ route('admin.accounts.index') }}" class="button is-small">Ver empresas</a>
                <a href="{{ route('admin.users.index') }}" class="button is-small">Ver usuários</a>
            </div>
        </div>
    </div>
</div>

<div class="columns">
    <div class="column is-7">
        <div class="box" style="height:100%">
            <div class="box-title">Empresas mais ativas <span class="right has-text-muted">respostas nos últimos 30 dias</span></div>
            <table class="table is-fullwidth is-hoverable">
                <thead><tr><th>Empresa</th><th>Proprietário</th><th class="has-text-right">Formulários</th><th class="has-text-right">Membros</th><th class="has-text-right">Respostas</th></tr></thead>
                <tbody>
                @foreach ($topAccounts as $a)
                    <tr class="is-clickable" onclick="location='{{ route('admin.accounts.show', $a) }}'">
                        <td><strong>{{ $a->name }}</strong> @if ($a->isSuspended())<span class="tag is-danger is-light ml-1">Suspensa</span>@endif</td>
                        <td class="has-text-muted">{{ $a->owner?->email }}</td>
                        <td class="has-text-right">{{ $a->forms_count }}</td>
                        <td class="has-text-right">{{ $a->memberships_count }}</td>
                        <td class="has-text-right has-text-weight-semibold">{{ $n($a->responses_30d) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="column is-5">
        <div class="box" style="height:100%">
            <div class="box-title">Cadastros recentes</div>
            @foreach ($recentUsers as $u)
                <a href="{{ route('admin.users.show', $u) }}" class="is-flex is-align-items-center py-2" style="gap:.65rem;border-bottom:1px solid #eef2f0;color:inherit">
                    @include('partials.avatar', ['u' => $u])
                    <span style="flex:1;min-width:0"><strong class="truncate">{{ $u->name }}</strong><span class="is-size-7 has-text-muted truncate">{{ $u->email }}</span></span>
                    <span class="is-size-7 has-text-muted nowrap">{{ $u->created_at->locale('pt_BR')->diffForHumans() }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const s = @json($series);
    FP.line(document.getElementById('c-platform'), s.map(x => x.label), [
        { label: 'Respostas', data: s.map(x => x.submissions), color: '#0e7c66', width: 2.6, fill: true },
        { label: 'Novos usuários', data: s.map(x => x.users), color: '#e0a526' },
        { label: 'Novas empresas', data: s.map(x => x.accounts), color: '#1d5fa8' },
    ]);
});
</script>
@endpush
