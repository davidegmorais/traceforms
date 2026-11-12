@extends('layouts.guest')
@section('title', 'Conta suspensa')
@section('content')
    <h1 class="title is-3 mb-2">“{{ $suspended->name }}” está suspensa</h1>
    <p class="has-text-muted mb-5">O acesso a esta conta foi pausado pela administração da plataforma. Os dados continuam guardados. Fale com o suporte para reativar.</p>
    @if ($others->count())
        <p class="label">Entrar em outra conta</p>
        @foreach ($others as $acc)
            <form method="POST" action="{{ route('accounts.switch', $acc) }}" class="mb-2">@csrf
                <button class="button is-fullwidth is-justify-content-flex-start">{{ $acc->name }}</button>
            </form>
        @endforeach
    @endif
    <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="button is-white">Sair</button></form>
@endsection
