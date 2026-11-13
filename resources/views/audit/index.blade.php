@extends('layouts.app')
@section('title', 'Auditoria')
@section('crumb'){{ $account->name }}@endsection

@section('content')
<form method="GET" class="filter-bar">
    <div class="select is-small"><select name="user" onchange="this.form.submit()"><option value="">Todas as pessoas</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
    <div class="select is-small"><select name="action" onchange="this.form.submit()">
        <option value="">Todas as ações</option>
        @foreach (['form' => 'Formulários', 'member' => 'Membros', 'invitation' => 'Convites', 'submission' => 'Respostas', 'export' => 'Exportações', 'link' => 'Links', 'account' => 'Conta'] as $k => $l)
            <option value="{{ $k }}" @selected(request('action') === $k)>{{ $l }}</option>
        @endforeach
    </select></div>
</form>

<div class="box">
    @forelse ($logs as $log)
        <div class="timeline-item">
            <span class="timeline-dot"><i class="fa-solid {{ $log->icon() }}"></i></span>
            <div class="is-size-7" style="flex:1">
                <strong>{{ $log->user?->name ?? 'Sistema' }}</strong> {{ lcfirst($log->description) }}
                <br><span class="has-text-muted">{{ $log->created_at->setTimezone($account->timezone)->format('d/m/Y H:i:s') }} · <span class="mono">{{ $log->action }}</span>{{ $log->ip ? ' · '.$log->ip : '' }}</span>
            </div>
        </div>
    @empty
        <p class="has-text-muted has-text-centered py-5">Nenhum evento registrado.</p>
    @endforelse
</div>
{{ $logs->links() }}
@endsection
