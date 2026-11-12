@extends('layouts.guest')
@section('title', 'Entrar')
@section('content')
    <h1 class="title is-3 mb-2" style="letter-spacing:-.02em">Entrar no TraceForms</h1>
    <p class="has-text-muted mb-5">Use o mesmo login das outras plataformas Tracezap.</p>

    @if ($errors->has('trace'))
        <div class="notification is-warning is-light is-size-7">{{ $errors->first('trace') }}</div>
    @endif

    @if ($configured)
        <a href="{{ route('auth.trace.redirect') }}" class="button is-primary is-fullwidth is-medium" x-data="{ loading: false }" @click="loading = true" :class="{ 'is-loading': loading }">
            Entrar com Trace ID
        </a>
        <p class="has-text-centered mt-5 is-size-7 has-text-muted">Não tem acesso? Peça ao administrador da sua empresa para liberar o TraceForms no Trace ID.</p>
    @else
        <div class="notification is-danger is-light is-size-7">
            O login pelo Trace ID ainda não foi configurado. Preencha <span class="mono">TRACE_ID_CLIENT_ID</span> e <span class="mono">TRACE_ID_CLIENT_SECRET</span> no <span class="mono">.env</span> e rode <span class="mono">php artisan config:cache</span>.
        </div>
    @endif
@endsection
