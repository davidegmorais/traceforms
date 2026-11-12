@extends('layouts.guest')
@section('title', 'Criar conta')
@section('content')
    @if ($invitation)
        <div class="notification is-primary is-light mb-5">
            <i class="fa-solid fa-envelope-open-text mr-2"></i>
            Você foi convidado para <strong>{{ $invitation->account->name }}</strong> como <strong>{{ $invitation->role->label() }}</strong>.
        </div>
    @endif
    <h1 class="title is-3 mb-2" style="letter-spacing:-.02em">{{ $invitation ? 'Crie seu acesso' : 'Crie sua conta' }}</h1>
    <p class="has-text-muted mb-5">Leva menos de um minuto.</p>

    <form method="POST" action="{{ route('register') }}" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        @if ($invitation)<input type="hidden" name="invite" value="{{ $invitation->token }}">@endif
        <div class="field">
            <label class="label" for="name">Seu nome</label>
            <input class="input @error('name') is-danger @enderror" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            @error('name')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label" for="email">E-mail de trabalho</label>
            <input class="input @error('email') is-danger @enderror" id="email" type="email" name="email" value="{{ old('email', $invitation?->email) }}" required autocomplete="email" @if($invitation) readonly @endif>
            @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        @unless ($invitation)
            <div class="field">
                <label class="label" for="company">Nome da empresa ou equipe <span class="has-text-muted has-text-weight-normal">(opcional)</span></label>
                <input class="input" id="company" name="company" value="{{ old('company') }}" autocomplete="organization">
            </div>
        @endunless
        <div class="columns is-variable is-2 mb-0">
            <div class="column field mb-0">
                <label class="label" for="password">Senha</label>
                <input class="input @error('password') is-danger @enderror" id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="column field">
                <label class="label" for="password_confirmation">Confirmar</label>
                <input class="input" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
        </div>
        @error('password')<p class="help is-danger mt-0 mb-3">{{ $message }}</p>@enderror
        <p class="help mb-4">Mínimo de 8 caracteres, com letras e números.</p>
        <button class="button is-primary is-fullwidth is-medium" :class="{ 'is-loading': loading }">Criar conta</button>
    </form>
    <p class="has-text-centered mt-5 is-size-7">Já tem conta? <a href="{{ route('login') }}" class="has-text-weight-semibold">Entrar</a></p>
@endsection
