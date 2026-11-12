@extends('layouts.admin')
@section('title', $acc->name)
@section('crumb')<a href="{{ route('admin.accounts.index') }}">Empresas</a>@endsection
@section('actions')
    @if ($acc->isSuspended())
        <form method="POST" action="{{ route('admin.accounts.reactivate', $acc) }}">@csrf<button class="button is-small is-primary">Reativar empresa</button></form>
    @endif
    @if ($acc->owner && $acc->owner->id !== auth()->id())
        <form method="POST" action="{{ route('admin.users.impersonate', $acc->owner) }}">@csrf<button class="button is-small"><i class="fa-solid fa-user-secret mr-2"></i> Acessar como proprietário</button></form>
    @endif
@endsection

@section('content')
@php $n = fn ($v) => number_format($v, 0, ',', '.'); @endphp
@if ($acc->isSuspended())
    <div class="notification is-danger is-light">Suspensa em {{ $acc->suspended_at->format('d/m/Y H:i') }}{{ $acc->suspension_reason ? ' — '.$acc->suspension_reason : '' }}. Os membros não conseguem entrar e os formulários não aceitam respostas.</div>
@endif

<div class="columns is-multiline">
    <div class="column">@include('partials.kpi', ['label' => 'Formulários', 'icon' => '', 'k' => ['value' => 0, 'delta' => null, 'prev' => null], 'display' => $forms->count()])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Respostas', 'icon' => '', 'k' => ['value' => 0, 'delta' => null, 'prev' => null], 'display' => $n($stats['responses'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Respostas (30 dias)', 'icon' => '', 'k' => ['value' => 0, 'delta' => null, 'prev' => null], 'display' => $n($stats['responses_30d'])])</div>
    <div class="column">@include('partials.kpi', ['label' => 'Arquivos', 'icon' => '', 'k' => ['value' => 0, 'delta' => null, 'prev' => null], 'display' => \App\Models\Upload::humanSize($stats['storage'])])</div>
</div>

<div class="columns">
    <div class="column is-7">
        <div class="box">
            <div class="box-title">Membros <span class="right has-text-muted">{{ $acc->memberships->count() }}</span></div>
            <table class="table is-fullwidth">
                <tbody>
                @foreach ($acc->memberships->sortByDesc(fn ($m) => $m->role->level()) as $m)
                    <tr>
                        <td style="width:44px">@include('partials.avatar', ['u' => $m->user])</td>
                        <td><a href="{{ route('admin.users.show', $m->user) }}" class="has-text-weight-semibold" style="color:inherit">{{ $m->user->name }}</a><br><span class="is-size-7 has-text-muted">{{ $m->user->email }}</span></td>
                        <td class="has-text-right nowrap">
                            @if ($m->role === \App\Enums\Role::Owner)
                                <span class="tag {{ $m->role->color() }}">{{ $m->role->label() }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.accounts.members.update', [$acc, $m]) }}" class="is-inline">@csrf @method('PUT')
                                    <div class="select is-small"><select name="role" onchange="this.form.submit()">
                                        @foreach ($roles as $r) @if ($r !== \App\Enums\Role::Owner)<option value="{{ $r->value }}" @selected($m->role === $r)>{{ $r->label() }}</option>@endif @endforeach
                                    </select></div>
                                </form>
                                <form method="POST" action="{{ route('admin.accounts.members.update', [$acc, $m]) }}" class="is-inline" onsubmit="return confirm('Remover {{ addslashes($m->user->name) }} desta empresa?')">@csrf @method('DELETE')
                                    <button class="button is-small is-white" title="Remover"><i class="fa-solid fa-user-minus has-text-danger"></i></button></form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('admin.accounts.members.add', $acc) }}" class="is-flex is-flex-wrap-wrap mt-2" style="gap:.5rem">
                @csrf
                <input class="input is-small" name="email" type="email" placeholder="E-mail de um usuário existente" required style="flex:1;min-width:200px">
                <div class="select is-small"><select name="role"><option value="admin">Administrador</option><option value="editor" selected>Editor</option><option value="analyst">Analista</option><option value="viewer">Leitor</option></select></div>
                <button class="button is-small">Adicionar</button>
            </form>
        </div>

        <div class="box">
            <div class="box-title">Formulários</div>
            @forelse ($forms as $f)
                <div class="stat-line">
                    <span><span class="dot mr-2" style="background: {{ $f->status === 'published' ? '#0e7c66' : ($f->status === 'closed' ? '#b42318' : '#e0a526') }}"></span>{{ $f->title }} <span class="is-size-7 has-text-muted">· {{ $f->statusLabel() }}</span></span>
                    <span><strong>{{ $n($f->submissions_count) }}</strong> <span class="is-size-7 has-text-muted">respostas</span> · <a href="{{ $f->publicUrl($f->isPublished() ? [] : ['preview' => 1]) }}" target="_blank" class="is-size-7">abrir</a></span>
                </div>
            @empty
                <p class="has-text-muted is-size-7">Nenhum formulário.</p>
            @endforelse
        </div>
    </div>

    <div class="column is-5">
        <form method="POST" action="{{ route('admin.accounts.update', $acc) }}" class="box">
            @csrf @method('PUT')
            <div class="box-title">Dados da empresa</div>
            <div class="field"><label class="label is-small">Nome</label><input class="input" name="name" value="{{ $acc->name }}" required></div>
            <div class="field"><label class="label is-small">Fuso horário</label>
                <div class="select is-fullwidth"><select name="timezone">@foreach (\DateTimeZone::listIdentifiers() as $tz)<option @selected($acc->timezone === $tz)>{{ $tz }}</option>@endforeach</select></div></div>
            <div class="field"><label class="label is-small">Proprietário</label>
                <div class="select is-fullwidth"><select name="owner_id">@foreach ($acc->memberships as $m)<option value="{{ $m->user_id }}" @selected($acc->owner_id === $m->user_id)>{{ $m->user->name }} ({{ $m->user->email }})</option>@endforeach</select></div>
                <p class="help">O proprietário anterior passa a ser Administrador.</p></div>
            <p class="is-size-7 has-text-muted mb-3">Criada em {{ $acc->created_at->format('d/m/Y') }} · última resposta {{ $stats['last_response'] ? \Carbon\Carbon::parse($stats['last_response'])->locale('pt_BR')->diffForHumans() : 'nunca' }}</p>
            <button class="button is-primary is-small">Salvar</button>
        </form>

        <div class="box">
            <div class="box-title">Atividade recente</div>
            @forelse ($logs as $log)
                <div class="is-size-7 py-1" style="border-bottom:1px solid #eef2f0"><strong>{{ $log->user?->name ?? 'Sistema' }}</strong> {{ lcfirst($log->description) }}<br><span class="has-text-muted">{{ $log->created_at->format('d/m/Y H:i') }}</span></div>
            @empty
                <p class="has-text-muted is-size-7">Sem registros.</p>
            @endforelse
        </div>

        <div class="box danger-zone" x-data="{ s: false, d: false }">
            <div class="box-title has-text-danger">Ações sensíveis</div>
            @unless ($acc->isSuspended())
                <button class="button is-small is-warning is-light mb-2" @click="s = !s">Suspender empresa…</button>
                <form method="POST" action="{{ route('admin.accounts.suspend', $acc) }}" x-show="s" x-cloak class="mb-4">@csrf
                    <div class="field"><input class="input is-small" name="reason" placeholder="Motivo (visível aos administradores)"></div>
                    <button class="button is-small is-warning">Suspender agora</button>
                    <p class="help">Membros perdem o acesso e os formulários deixam de aceitar respostas. Nada é apagado.</p>
                </form>
            @endunless
            <button class="button is-small is-danger is-light" @click="d = !d">Excluir empresa…</button>
            <form method="POST" action="{{ route('admin.accounts.destroy', $acc) }}" x-show="d" x-cloak class="mt-2">@csrf @method('DELETE')
                <div class="field"><label class="label is-small">Digite <strong>{{ $acc->name }}</strong> para confirmar</label><input class="input is-small" name="confirm" autocomplete="off"></div>
                <button class="button is-small is-danger">Excluir com todos os formulários, respostas e arquivos</button>
            </form>
        </div>
    </div>
</div>
@endsection
