@extends('layouts.guest')
@section('title', 'Entrar')
@section('content')
    <h1 class="title is-3 mb-2" style="letter-spacing:-.02em">Bem-vindo de volta</h1>
    <p class="has-text-muted mb-5">Entre para ver seus formulários e relatórios.</p>

    <form method="POST" action="{{ route('login') }}" x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <div class="field">
            <label class="label" for="email">E-mail</label>
            <div class="control has-icons-left">
                <input class="input is-medium @error('email') is-danger @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                <span class="icon is-left"><i class="fa-regular fa-envelope"></i></span>
            </div>
            @error('email')<p class="help is-danger">{{ $message }}</p>@enderror
        </div>
        <div class="field" x-data="{ show: false }">
            <div class="is-flex is-justify-content-space-between">
                <label class="label" for="password">Senha</label>
                <a href="{{ route('password.request') }}" class="is-size-7">Esqueceu a senha?</a>
            </div>
            <div class="control has-icons-left has-icons-right">
                <input class="input is-medium" id="password" :type="show ? 'text' : 'password'" name="password" required autocomplete="current-password">
                <span class="icon is-left"><i class="fa-solid fa-lock"></i></span>
                <span class="icon is-right is-clickable" style="pointer-events:all" @click="show = !show"><i class="fa-regular" :class="show ? 'fa-eye-slash' : 'fa-eye'"></i></span>
            </div>
        </div>
        <label class="checkbox is-size-7 mb-4"><input type="checkbox" name="remember" checked> Manter conectado</label>
        <button class="button is-primary is-fullwidth is-medium mt-3" :class="{ 'is-loading': loading }">Entrar</button>
    </form>
    <p class="has-text-centered mt-5 is-size-7">Ainda não tem conta? <a href="{{ route('register') }}" class="has-text-weight-semibold">Criar conta grátis</a></p>
@endsection
