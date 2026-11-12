@extends('layouts.admin')
@section('title', 'Usuários')


@section('content')
<form method="GET" class="filter-bar">
    <div class="control has-icons-left"><input class="input is-small" name="q" value="{{ request('q') }}" placeholder="Nome ou e-mail" style="width:280px"><span class="icon is-left is-small"><i class="fa-solid fa-magnifying-glass"></i></span></div>
    <div class="select is-small"><select name="filter" onchange="this.form.submit()">
        <option value="">Todos</option>
        <option value="admins" @selected(request('filter') === 'admins')>Administradores da plataforma</option>
        <option value="suspended" @selected(request('filter') === 'suspended')>Suspensos</option>
        <option value="inactive" @selected(request('filter') === 'inactive')>Sem acesso há 60 dias</option>
    </select></div>
    <div class="select is-small"><select name="sort" onchange="this.form.submit()"><option value="">Cadastro mais recente</option><option value="login" @selected(request('sort') === 'login')>Último acesso</option></select></div>
    <button class="button is-small is-primary">Buscar</button>
    <span class="is-size-7 has-text-muted ml-auto">{{ number_format($users->total(), 0, ',', '.') }} usuários</span>
</form>

<div class="box p-0">
    <div class="table-wrap">
        <table class="table is-fullwidth is-hoverable mb-0">
            <thead><tr><th>Usuário</th><th class="has-text-right">Empresas</th><th>Cadastro</th><th>Último acesso</th><th>Situação</th></tr></thead>
            <tbody>
            @forelse ($users as $u)
                <tr class="is-clickable" onclick="location='{{ route('admin.users.show', $u) }}'">
                    <td><div class="is-flex is-align-items-center" style="gap:.65rem">@include('partials.avatar', ['u' => $u])<span><strong>{{ $u->name }}</strong> @if ($u->is_super_admin)<span class="tag is-dark ml-1">Admin</span>@endif<br><span class="is-size-7 has-text-muted">{{ $u->email }}</span></span></div></td>
                    <td class="has-text-right">{{ $u->accounts_count }}</td>
                    <td class="has-text-muted nowrap">{{ $u->created_at->format('d/m/Y') }}</td>
                    <td class="has-text-muted nowrap">{{ $u->last_login_at ? $u->last_login_at->locale('pt_BR')->diffForHumans() : 'nunca' }}</td>
                    <td>@if ($u->isSuspended())<span class="tag is-danger is-light">Suspenso</span>@else<span class="tag is-success is-light">Ativo</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="has-text-centered has-text-muted py-5">Nenhum usuário encontrado.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $users->links() }}
@endsection
