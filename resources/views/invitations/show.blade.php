@extends('layouts.guest')
@section('title', 'Convite')
@section('content')
    @if (! $invitation->isPending())
        <div class="empty">
            <div class="icon-big"><i class="fa-solid fa-hourglass-end"></i></div>
            <h3>Convite indisponível</h3>
            <p>Este convite expirou ou já foi utilizado. Peça um novo convite ao administrador da conta.</p>
            <a href="{{ route('login') }}" class="button is-primary">Ir para o login</a>
        </div>
    @else
        <div class="avatar is-large mb-4" style="background: {{ $invitation->account->brand_color }}">{{ mb_strtoupper(mb_substr($invitation->account->name, 0, 1)) }}</div>
        <h1 class="title is-3 mb-2">Junte-se a {{ $invitation->account->name }}</h1>
        <p class="has-text-muted mb-4">{{ $invitation->inviter?->name ?? 'Um administrador' }} convidou <strong>{{ $invitation->email }}</strong> para colaborar.</p>
        <div class="box mb-5" style="box-shadow:none">
            <span class="tag {{ $invitation->role->color() }} mb-2">{{ $invitation->role->label() }}</span>
            <p class="is-size-7 has-text-muted">{{ $invitation->role->description() }}</p>
        </div>

        @auth
            @if (strtolower(auth()->user()->email) === strtolower($invitation->email))
                <form method="POST" action="{{ route('invitations.accept', $invitation->token) }}">@csrf
                    <button class="button is-primary is-fullwidth is-medium">Aceitar convite</button>
                </form>
            @else
                <div class="notification is-warning is-light">Você está conectado como <strong>{{ auth()->user()->email }}</strong>. Saia e entre com <strong>{{ $invitation->email }}</strong> para aceitar.</div>
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="button is-fullwidth">Sair</button></form>
            @endif
        @else
            @if ($userExists)
                <a href="{{ route('login') }}" class="button is-primary is-fullwidth is-medium">Entrar para aceitar</a>
                <p class="help has-text-centered mt-3">Depois de entrar, abra este link novamente.</p>
            @else
                <a href="{{ route('register', ['invite' => $invitation->token]) }}" class="button is-primary is-fullwidth is-medium">Criar acesso e entrar</a>
            @endif
        @endauth
    @endif
@endsection
