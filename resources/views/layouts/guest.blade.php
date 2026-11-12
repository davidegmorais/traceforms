<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · TraceForms</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='9' fill='%230e7c66'/><path d='M7 22.5 13 13.5 19 19 25.5 9.5' stroke='white' stroke-width='2.6' fill='none' stroke-linecap='round' stroke-linejoin='round'/><circle cx='25.5' cy='9.5' r='3.1' fill='%23f2b33d'/></svg>">
    <link rel="stylesheet" href="{{ asset('vendor/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bulma.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=1">
</head>
<body>
<div class="auth-wrap">
    <section class="auth-side">
        <svg class="auth-trace" viewBox="0 0 440 520" aria-hidden="true">
            <path d="M20 500 C 90 420, 60 360, 150 330 S 260 300, 240 220 S 330 110, 420 40" fill="none" stroke="#fff" stroke-width="3" stroke-dasharray="2 12" stroke-linecap="round"/>
            <circle cx="20" cy="500" r="9" fill="#fff"/><circle cx="150" cy="330" r="7" fill="none" stroke="#fff" stroke-width="3"/>
            <circle cx="240" cy="220" r="7" fill="none" stroke="#fff" stroke-width="3"/><circle cx="420" cy="40" r="13" fill="#f2b33d"/>
        </svg>
        <a href="{{ url('/') }}" class="fp-brand">@include('partials.logo', ['bg' => '#fff', 'fg' => '#0e7c66']) TraceForms</a>
        <div>
            <h2>Saiba de onde veio cada resposta.</h2>
            <ul>
                <li>Campanha, anúncio e página de origem de cada pessoa, inclusive em formulários incorporados no seu site</li>
                <li>Dispositivo, navegador, localização e o caminho até responder</li>
                <li>Em qual pergunta as pessoas desistem e quanto tempo levam em cada uma</li>
                <li>Acesso da equipe pelo Trace ID, com níveis e histórico de alterações</li>
            </ul>
        </div>
        <p class="is-size-7" style="opacity:.7">© {{ date('Y') }} TraceForms</p>
    </section>
    <section class="auth-form">
        <div>
            @yield('content')
        </div>
    </section>
</div>
@include('partials.toasts', ['hideErrorToast' => true])
<script src="{{ asset('js/app.js') }}?v=1"></script>
<script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
</body>
</html>
