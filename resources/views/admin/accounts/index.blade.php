@extends('layouts.admin')
@section('title', 'Empresas')


@section('content')
<form method="GET" class="filter-bar">
    <div class="control has-icons-left"><input class="input is-small" name="q" value="{{ request('q') }}" placeholder="Nome da empresa ou e-mail do proprietário" style="width:300px"><span class="icon is-left is-small"><i class="fa-solid fa-magnifying-glass"></i></span></div>
    <div class="select is-small"><select name="status" onchange="this.form.submit()">
        <option value="">Todas</option><option value="active" @selected(request('status') === 'active')>Ativas</option><option value="suspended" @selected(request('status') === 'suspended')>Suspensas</option>
    </select></div>
    <div class="select is-small"><select name="sort" onchange="this.form.submit()">
        <option value="recent">Mais recentes</option><option value="name" @selected(request('sort') === 'name')>Nome (A–Z)</option><option value="forms" @selected(request('sort') === 'forms')>Mais formulários</option>
    </select></div>
    <button class="button is-small is-primary">Buscar</button>
    <span class="is-size-7 has-text-muted ml-auto">{{ number_format($accounts->total(), 0, ',', '.') }} empresas</span>
</form>

<div class="box p-0">
    <div class="table-wrap">
        <table class="table is-fullwidth is-hoverable mb-0">
            <thead><tr><th>Empresa</th><th>Proprietário</th><th class="has-text-right">Formulários</th><th class="has-text-right">Membros</th><th>Criada em</th><th>Situação</th></tr></thead>
            <tbody>
            @forelse ($accounts as $a)
                <tr class="is-clickable" onclick="location='{{ route('admin.accounts.show', $a) }}'">
                    <td><div class="is-flex is-align-items-center" style="gap:.6rem"><span class="avatar is-small" style="background: {{ $a->brand_color }}">{{ mb_strtoupper(mb_substr($a->name, 0, 1)) }}</span><strong>{{ $a->name }}</strong></div></td>
                    <td>{{ $a->owner?->name }}<br><span class="is-size-7 has-text-muted">{{ $a->owner?->email }}</span></td>
                    <td class="has-text-right">{{ $a->forms_count }}</td>
                    <td class="has-text-right">{{ $a->memberships_count }}</td>
                    <td class="has-text-muted nowrap">{{ $a->created_at->format('d/m/Y') }}</td>
                    <td>@if ($a->isSuspended())<span class="tag is-danger is-light">Suspensa</span>@else<span class="tag is-success is-light">Ativa</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="has-text-centered has-text-muted py-5">Nenhuma empresa encontrada.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
{{ $accounts->links() }}
@endsection
