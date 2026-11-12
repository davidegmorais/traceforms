@extends('layouts.admin')
@section('title', 'Novo usuário')
@section('crumb')<a href="{{ route('admin.users.index') }}">Usuários</a>@endsection

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" class="columns"
      x-data="{ mode: @js(old('account_mode', 'new')), loading: false }" @submit="loading = true">
    @csrf
    <div class="column is-7">
        <div class="box">
            <div class="box-title">Pessoa</div>
            <div class="field">
                <label class="label" for="name">Nome</label>
                <input class="input @error('name') is-danger @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus>
                @error('name')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="email">E-mail</label>
                <input class="input @error('email') is-danger @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="password">Senha</label>
                <input class="input @error('password') is-danger @enderror" id="password" name="password" type="text" autocomplete="new-password" placeholder="Deixe em branco para gerar uma senha">
                @error('password')<p class="help is-danger">{{ $message }}</p>@else<p class="help">Se ficar em branco, uma senha é gerada e mostrada uma única vez depois de salvar.</p>@enderror
            </div>
            <label class="checkbox is-size-7"><input type="checkbox" name="is_super_admin" value="1" @checked(old('is_super_admin'))> Administrador da plataforma (acessa esta área de Administração)</label>
        </div>
    </div>

    <div class="column is-5">
        <div class="box">
            <div class="box-title">Empresa</div>
            <div class="field">
                <label class="radio is-block mb-2"><input type="radio" name="account_mode" value="new" x-model="mode"> Criar uma empresa nova, com esta pessoa como dona</label>
                <label class="radio is-block ml-0"><input type="radio" name="account_mode" value="existing" x-model="mode" @disabled($accounts->isEmpty())> Adicionar a uma empresa existente</label>
            </div>

            <div class="field" x-show="mode === 'new'">
                <label class="label" for="company">Nome da empresa</label>
                <input class="input @error('company') is-danger @enderror" id="company" name="company" value="{{ old('company') }}" :required="mode === 'new'">
                @error('company')<p class="help is-danger">{{ $message }}</p>@enderror
            </div>

            <div x-show="mode === 'existing'" x-cloak>
                <div class="field">
                    <label class="label" for="account_id">Empresa</label>
                    <div class="select is-fullwidth @error('account_id') is-danger @enderror"><select id="account_id" name="account_id" :required="mode === 'existing'">
                        <option value="">Escolha…</option>
                        @foreach ($accounts as $a)
                            <option value="{{ $a->id }}" @selected(old('account_id') == $a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select></div>
                    @error('account_id')<p class="help is-danger">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label class="label" for="role">Papel</label>
                    <div class="select is-fullwidth"><select id="role" name="role">
                        @foreach (['admin' => 'Administrador', 'editor' => 'Editor', 'analyst' => 'Analista', 'viewer' => 'Leitor'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('role', 'editor') === $v)>{{ $l }}</option>
                        @endforeach
                    </select></div>
                </div>
            </div>
        </div>
        <button class="button is-primary is-fullwidth" :class="{ 'is-loading': loading }">Criar usuário</button>
    </div>
</form>
@endsection
