@extends('layouts.guest')
@section('title', 'Nova conta')
@section('content')
    <h1 class="title is-3 mb-2">Criar uma conta</h1>
    <p class="has-text-muted mb-5">Contas separam formulários, dados e equipes — por exemplo, uma por empresa ou cliente.</p>
    <form method="POST" action="{{ route('accounts.store') }}">
        @csrf
        <div class="field">
            <label class="label">Nome da conta</label>
            <input class="input is-medium @error('name') is-danger @enderror" name="name" value="{{ old('name') }}" placeholder="Ex.: Agência Norte, Cliente XPTO" required autofocus>
            @error('name')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <button class="button is-primary is-fullwidth is-medium mt-4">Criar conta</button>
    </form>
    @if (auth()->user()->current_account_id)
        <p class="has-text-centered mt-5 is-size-7"><a href="{{ route('dashboard') }}"><i class="fa-solid fa-arrow-left mr-1"></i> Voltar ao painel</a></p>
    @endif
@endsection
