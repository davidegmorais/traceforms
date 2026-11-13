<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Painel') · TraceForms</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%230e7c66'/><path d='M7 22.5 13 13.5 19 19 25.5 9.5' stroke='white' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/><circle cx='25.5' cy='9.5' r='3.1' fill='%23f2b33d'/></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bulma.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=7">
    @include('partials.sidebar-state')
    @stack('head')
</head>
<body>
@php
    $user = auth()->user();
    $role = $membership->role;
    $nav = fn ($pattern) => request()->routeIs($pattern) ? 'is-active' : '';
    $myAccounts = app(\App\Services\TraceId\AccountChoices::class)->for($user);
@endphp
<div class="fp-app" x-data="{ open: false }" :class="{ 'is-open': open }">
    <div class="fp-overlay" @click="open = false"></div>

    <aside class="fp-sidebar" aria-label="Menu principal">
        <div class="sb-top">
            <a href="{{ route('dashboard') }}" class="fp-brand" title="TraceForms">@include('partials.logo') <span class="lbl">TraceForms</span></a>
        </div>

        {{-- Seletor de conta (empresa) --}}
        <div class="fp-account" x-data="{ menu: false }" @click.outside="menu = false">
            <div class="dropdown" :class="{ 'is-active': menu }">
                <div class="dropdown-trigger">
                    <button class="fp-account-btn" @click="menu = !menu" aria-haspopup="true" title="{{ $account->name }}: trocar de empresa">
                        <span class="avatar is-small" style="background: {{ $account->brand_color }}">{{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}</span>
                        <span class="lbl" style="flex:1;min-width:0"><span class="truncate has-text-weight-semibold">{{ $account->name }}</span><small>{{ $role->label() }}</small></span>
                        <i class="fa-solid fa-chevron-down is-size-8 lbl"></i>
                    </button>
                </div>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-content">
                        <p class="dropdown-item has-text-muted is-size-8 has-text-weight-bold">Suas empresas</p>
                        @foreach ($myAccounts as $acc)
                            @if ($acc->uuid === $account->trace_account_id)
                                <div class="dropdown-item is-flex is-align-items-center" style="gap:.5rem">
                                    <span class="avatar is-small" style="background: {{ $acc->color }}">{{ mb_strtoupper(mb_substr($acc->name, 0, 1)) }}</span>
                                    <span class="truncate" style="flex:1">{{ $acc->name }}</span>
                                    <i class="fa-solid fa-check has-text-primary"></i>
                                </div>
                            @else
                                <a href="{{ route('auth.trace.redirect', ['account' => $acc->uuid]) }}" class="dropdown-item is-flex is-align-items-center" style="gap:.5rem">
                                    <span class="avatar is-small" style="background: {{ $acc->color }}">{{ mb_strtoupper(mb_substr($acc->name, 0, 1)) }}</span>
                                    <span class="truncate" style="flex:1">{{ $acc->name }}</span>
                                </a>
                            @endif
                        @endforeach
                        <hr class="dropdown-divider">
                        <a href="{{ route('auth.trace.redirect', ['prompt' => 'select_account']) }}" class="dropdown-item"><i class="fa-solid fa-right-left mr-2"></i> Escolher no Trace ID</a>
                    </div>
                </div>
            </div>
        </div>

        <nav class="fp-nav" aria-label="Principal">
            <a href="{{ route('dashboard') }}" class="{{ $nav('dashboard') }}" data-tip="Visão geral"><i class="fa-solid fa-chart-simple"></i><span class="lbl">Visão geral</span></a>
            <a href="{{ route('forms.index') }}" class="{{ $nav('forms.*') || request()->routeIs('reports.*', 'submissions.*') ? 'is-active' : '' }}" data-tip="Formulários"><i class="fa-regular fa-file-lines"></i><span class="lbl">Formulários</span></a>
            @can('reports.view')
                <a href="{{ route('campaigns') }}" class="{{ $nav('campaigns') }}" data-tip="Campanhas e UTMs"><i class="fa-solid fa-bullhorn"></i><span class="lbl">Campanhas e UTMs</span></a>
            @endcan

            <div class="fp-nav-label"><span class="lbl">Empresa</span></div>
            <a href="{{ route('members.index') }}" class="{{ $nav('members.*') }}" data-tip="Equipe"><i class="fa-solid fa-user-group"></i><span class="lbl">Equipe</span></a>
            @can('audit.view')
                <a href="{{ route('audit') }}" class="{{ $nav('audit') }}" data-tip="Auditoria"><i class="fa-solid fa-clock-rotate-left"></i><span class="lbl">Auditoria</span></a>
            @endcan
            @can('account.settings')
                <a href="{{ route('account.edit') }}" class="{{ $nav('account.*') }}" data-tip="Configurações"><i class="fa-solid fa-gear"></i><span class="lbl">Configurações</span></a>
            @endcan

        </nav>

        <button type="button" class="sb-collapse" onclick="TFSidebar.toggle()" data-tip="Expandir menu" aria-label="Recolher ou expandir o menu">
            <i class="fa-solid fa-angles-left"></i><span class="lbl">Recolher menu</span>
        </button>

        {{-- Menu da conta: Meu perfil, Trocar empresa, Trace ID e Sair (como nas outras plataformas) --}}
        <div class="fp-user" x-data="{ menu: false }" @click.outside="menu = false" @keydown.escape="menu = false">
            <div class="dropdown is-up" :class="{ 'is-active': menu }">
                <div class="dropdown-trigger">
                    <button type="button" class="fp-user-btn" @click="menu = !menu" aria-haspopup="true" :aria-expanded="menu" title="{{ $user->name }} · {{ $user->email }}">
                        @include('partials.avatar', ['u' => $user])
                        <span class="lbl" style="min-width:0;flex:1">
                            <span class="name truncate">{{ $user->name }}</span>
                            <span class="role truncate">{{ $user->email }}</span>
                        </span>
                        <i class="fa-solid fa-chevron-up is-size-8 lbl fp-user-chev"></i>
                    </button>
                </div>
                <div class="dropdown-menu" role="menu">
                    <div class="dropdown-content">
                        <a href="{{ route('profile.edit') }}" class="dropdown-item"><i class="fa-regular fa-user mr-2"></i> Meu perfil</a>
                        <a href="{{ route('auth.trace.redirect', ['prompt' => 'select_account']) }}" class="dropdown-item"><i class="fa-solid fa-right-left mr-2"></i> Trocar empresa</a>
                        <a href="{{ rtrim(config('traceid.issuer'), '/') }}/" class="dropdown-item"><i class="fa-regular fa-id-badge mr-2"></i> Trace ID</a>
                        <hr class="dropdown-divider">
                        <form method="POST" action="{{ route('logout') }}">@csrf
                            <button type="submit" class="dropdown-item fp-user-out"><i class="fa-solid fa-arrow-right-from-bracket mr-2"></i> Sair</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <div class="fp-main">
        <header class="fp-topbar">
            <button class="fp-burger" @click="open = !open" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
            <div style="min-width:0">
                @hasSection('crumb')<div class="crumb">@yield('crumb')</div>@endif
                <h1 class="truncate">@yield('heading', $__env->yieldContent('title'))</h1>
            </div>
            <div class="actions">@yield('actions')</div>
        </header>

        @hasSection('fullwidth')
            @yield('fullwidth')
        @else
            <main class="fp-content">
                @yield('content')
            </main>
        @endif
    </div>
</div>

@include('partials.toasts')

<script src="{{ asset('js/app.js') }}?v=1"></script>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
@stack('scripts')
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</body>
</html>
