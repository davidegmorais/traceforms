<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') · TraceForms</title>
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bulma.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body style="min-height:100vh;display:grid;place-items:center">
    <div class="empty">
        <div class="icon-big"><i class="fa-solid @yield('icon')"></i></div>
        <p class="has-text-muted has-text-weight-bold mb-1">Erro @yield('code')</p>
        <h3 class="is-size-4">@yield('title')</h3>
        <p>@yield('message')</p>
        <a href="{{ url()->previous() !== url()->current() ? url()->previous() : url('/') }}" class="button">Voltar</a>
        <a href="{{ url('/') }}" class="button is-primary">Ir para o início</a>
    </div>
</body>
</html>
