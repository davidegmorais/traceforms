@extends('layouts.guest')
@section('title', 'Nova senha')
@section('content')
    <h1 class="title is-3 mb-5">Criar nova senha</h1>
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label class="label">E-mail</label>
            <input class="input @error('email') is-danger @enderror" type="email" name="email" value="{{ old('email', $email) }}" required>
            @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label">Nova senha</label>
            <input class="input @error('password') is-danger @enderror" type="password" name="password" required autocomplete="new-password">
            @error('password')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="label">Confirmar nova senha</label>
            <input class="input" type="password" name="password_confirmation" required autocomplete="new-password">
        </div>
        <button class="button is-primary is-fullwidth is-medium mt-4">Salvar nova senha</button>
    </form>
@endsection
