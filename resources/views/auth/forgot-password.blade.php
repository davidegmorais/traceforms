@extends('layouts.guest')
@section('title', 'Recuperar senha')
@section('content')
    <h1 class="title is-3 mb-2">Recuperar senha</h1>
    <p class="has-text-muted mb-5">Informe seu e-mail e enviaremos um link para criar uma nova senha.</p>
    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="field">
            <label class="label" for="email">E-mail</label>
            <input class="input is-medium @error('email') is-danger @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <button class="button is-primary is-fullwidth is-medium mt-4">Enviar link</button>
    </form>
    <p class="has-text-centered mt-5 is-size-7"><a href="{{ route('login') }}"><i class="fa-solid fa-arrow-left mr-1"></i> Voltar ao login</a></p>
@endsection
