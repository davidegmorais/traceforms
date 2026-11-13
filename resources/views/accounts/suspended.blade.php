@extends('layouts.guest')
@section('title', 'Conta suspensa')
@section('content')
    <h1 class="title is-3 mb-2">“{{ $suspended->name }}” está com a licença inativa</h1>
    <p class="has-text-muted mb-5">A licença desta empresa para o TraceForms está inativa no Trace ID. Os formulários pararam de receber respostas, mas os dados continuam guardados. Fale com o suporte para reativar.</p>
    @if ($suspended->trace_account_id)
        <a href="{{ route('auth.trace.redirect', ['account' => $suspended->trace_account_id]) }}" class="button is-primary is-fullwidth mb-4">Já foi reativada? Conferir de novo</a>
    @endif
    @if ($others->count())
        <p class="label">Entrar em outra empresa</p>
        @foreach ($others as $acc)
            <a href="{{ route('auth.trace.redirect', ['account' => $acc->uuid]) }}" class="button is-fullwidth is-justify-content-flex-start mb-2">{{ $acc->name }}</a>
        @endforeach
    @endif
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="button is-white">Sair</button></form>
@endsection
