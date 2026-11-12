@extends('layouts.admin')
@section('title', $u->name)
@section('crumb')<a href="{{ route('admin.users.index') }}">Usuários</a>@endsection
@section('actions')
    @if ($u->id !== auth()->id())
        @if ($u->isSuspended())
            <form method="POST" action="{{ route('admin.users.reactivate', $u) }}">@csrf<button class="button is-small is-primary">Reativar</button></form>
        @else
            <form method="POST" action="{{ route('admin.users.impersonate', $u) }}">@csrf<button class="button is-small"><i class="fa-solid fa-user-secret mr-2"></i> Acessar como</button></form>
            <form method="POST" action="{{ route('admin.users.suspend', $u) }}" onsubmit="return confirm('Suspender {{ addslashes($u->name) }}? A pessoa será desconectada e não conseguirá entrar.')">@csrf<button class="button is-small is-warning is-light">Suspender</button></form>
        @endif
    @endif
@endsection

@section('content')
@if (session('new_password'))
    <div class="notification is-success is-light" x-data="{ copied: false }">
        <p class="mb-2"><strong>Senha gerada para {{ $u->email }}</strong>. Ela aparece só agora: copie e envie para a pessoa. Ela pode trocar depois em Meu perfil.</p>
        <div class="field has-addons mb-0" style="max-width:420px">
            <div class="control is-expanded"><input class="input is-family-monospace" readonly value="{{ session('new_password') }}" x-ref="pw" @focus="$el.select()"></div>
            <div class="control"><button type="button" class="button" @click="navigator.clipboard.writeText($refs.pw.value); copied = true" x-text="copied ? 'Copiada' : 'Copiar'">Copiar</button></div>
        </div>
    </div>
@endif
<div class="columns">
    <div class="column is-7">
        <div class="box">
            <div class="is-flex is-align-items-center mb-4" style="gap:1rem">
                @include('partials.avatar', ['u' => $u, 'class' => 'is-large'])
                <div>
                    <p class="has-text-weight-semibold is-size-5" style="color:var(--tf-ink)">{{ $u->name }} @if ($u->is_super_admin)<span class="tag is-dark ml-1">Admin da plataforma</span>@endif @if ($u->isSuspended())<span class="tag is-danger is-light ml-1">Suspenso</span>@endif</p>
                    <p class="is-size-7 has-text-muted">Cadastro em {{ $u->created_at->format('d/m/Y') }} · último acesso {{ $u->last_login_at ? $u->last_login_at->locale('pt_BR')->diffForHumans() : 'nunca' }}</p>
                </div>
            </div>
            <div class="box-title">Empresas</div>
            @forelse ($memberships as $acc)
                <div class="stat-line">
                    <a href="{{ route('admin.accounts.show', $acc) }}" style="color:inherit"><strong>{{ $acc->name }}</strong></a>
                    <span class="tag {{ $acc->pivot->role->color() }}">{{ $acc->pivot->role->label() }}</span>
                </div>
            @empty
                <p class="has-text-muted is-size-7">Não participa de nenhuma empresa.</p>
            @endforelse
        </div>
        <div class="box">
            <div class="box-title">Ações recentes</div>
            @forelse ($logs as $log)
                <div class="is-size-7 py-1" style="border-bottom:1px solid #eef2f0">{{ $log->description }} <span class="has-text-muted">· {{ $log->account?->name }} · {{ $log->created_at->format('d/m/Y H:i') }}</span></div>
            @empty
                <p class="has-text-muted is-size-7">Sem registros.</p>
            @endforelse
        </div>
    </div>

    <div class="column is-5">
        <form method="POST" action="{{ route('admin.users.update', $u) }}" class="box">
            @csrf @method('PUT')
            <div class="box-title">Dados de acesso</div>
            <div class="field"><label class="label is-small">Nome</label><input class="input" name="name" value="{{ old('name', $u->name) }}" required></div>
            <div class="field"><label class="label is-small">E-mail</label><input class="input @error('email') is-danger @enderror" type="email" name="email" value="{{ old('email', $u->email) }}" required>@error('email')<p class="help is-danger">{{ $message }}</p>@enderror</div>
            <div class="field"><label class="label is-small">Definir nova senha</label><input class="input @error('password') is-danger @enderror" type="text" name="password" autocomplete="off" placeholder="Deixe em branco para manter">
                @error('password')<p class="help is-danger">{{ $message }}</p>@enderror<p class="help">Ao definir, as sessões abertas dessa pessoa são encerradas.</p></div>
            <label class="checkbox is-size-7 mb-3"><input type="checkbox" name="is_super_admin" value="1" @checked($u->is_super_admin) @disabled($u->id === auth()->id())> Administrador da plataforma (acesso a /admin)</label>
            @if ($u->id === auth()->id())<input type="hidden" name="is_super_admin" value="1">@endif
            <div><button class="button is-primary is-small">Salvar</button></div>
        </form>

        @if ($u->id !== auth()->id())
            <form method="POST" action="{{ route('admin.users.destroy', $u) }}" class="box danger-zone" x-data="{ o: false }">@csrf @method('DELETE')
                <div class="box-title has-text-danger">Excluir usuário</div>
                <p class="is-size-7 mb-3">Remove a pessoa de todas as empresas. Formulários e respostas das empresas são mantidos. Proprietários precisam ser trocados antes.</p>
                <button type="button" class="button is-small is-danger is-light" @click="o = true" x-show="!o">Excluir…</button>
                <div x-show="o" x-cloak>
                    <div class="field"><label class="label is-small">Digite <strong>{{ $u->email }}</strong> para confirmar</label><input class="input is-small" name="confirm" autocomplete="off"></div>
                    <button class="button is-small is-danger">Excluir definitivamente</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
