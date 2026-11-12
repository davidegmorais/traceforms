<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Administração') · TraceForms Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%2314231f'/><path d='M7 22.5 13 13.5 19 19 25.5 9.5' stroke='white' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/><circle cx='25.5' cy='9.5' r='3.1' fill='%23f2b33d'/></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bulma.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=6">
    @include('partials.sidebar-state')
</head>
<body class="is-admin">
@php
    $user = auth()->user();
    $nav = fn ($pattern) => request()->routeIs($pattern) ? 'is-active' : '';
@endphp
{{-- Área separada da plataforma: não depende da conta ativa e tem identidade própria (barra escura). --}}
<div class="fp-app" x-data="{ open: false }" :class="{ 'is-open': open }">
    <div class="fp-overlay" @click="open = false"></div>

    <aside class="fp-sidebar admin-sidebar" aria-label="Menu da administração">
        <div class="sb-top">
            <a href="{{ route('admin.dashboard') }}" class="fp-brand" title="TraceForms · Administração">@include('partials.logo', ['bg' => '#f2b33d', 'fg' => '#14231f']) <span class="lbl brand-text">TraceForms<small>Administração</small></span></a>
        </div>

        <nav class="fp-nav" aria-label="Administração">
            <a href="{{ route('admin.dashboard') }}" class="{{ $nav('admin.dashboard') }}" data-tip="Visão geral"><i class="fa-solid fa-gauge"></i><span class="lbl">Visão geral</span></a>
            <a href="{{ route('admin.accounts.index') }}" class="{{ $nav('admin.accounts.*') }}" data-tip="Empresas"><i class="fa-solid fa-building"></i><span class="lbl">Empresas</span></a>
            <a href="{{ route('admin.users.index') }}" class="{{ $nav('admin.users.*') }}" data-tip="Usuários"><i class="fa-solid fa-users"></i><span class="lbl">Usuários</span></a>
            <div class="fp-nav-label"><span class="lbl">Atalhos</span></div>
            <a href="{{ route('dashboard') }}" data-tip="Voltar ao painel"><i class="fa-solid fa-arrow-left"></i><span class="lbl">Voltar ao painel</span></a>
        </nav>

        <button type="button" class="sb-collapse" onclick="TFSidebar.toggle()" data-tip="Expandir menu" aria-label="Recolher ou expandir o menu">
            <i class="fa-solid fa-angles-left"></i><span class="lbl">Recolher menu</span>
        </button>

        <div class="fp-user">
            @include('partials.avatar', ['u' => $user])
            <div class="lbl" style="min-width:0">
                <span class="name truncate">{{ $user->name }}</span>
                <div class="role truncate">Admin da plataforma</div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="lbl">@csrf
                <button class="button is-small" title="Sair"><i class="fa-solid fa-arrow-right-from-bracket"></i></button>
            </form>
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
        <main class="fp-content">
            @yield('content')
        </main>
    </div>
</div>

@include('partials.toasts')
<script src="{{ asset('js/app.js') }}?v=2"></script>
<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
@stack('scripts')
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</body>
</html>
