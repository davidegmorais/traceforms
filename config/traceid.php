<?php

/*
| Login pelo Trace ID (OpenID Connect).
| Mesmos nomes de variáveis do TraceChat: TRACE_ID_URL, TRACE_ID_CLIENT_ID,
| TRACE_ID_CLIENT_SECRET e TRACE_ID_REDIRECT_URI. (Os nomes TRACEID_* também são aceitos.)
| URL de retorno aceita: {APP_URL}/auth/callback (padrão) ou {APP_URL}/auth/trace/callback.
| Ela precisa ser idêntica à cadastrada no Trace ID, inclusive a barra no fim.
*/
return [
    'issuer' => rtrim(env('TRACE_ID_URL', env('TRACEID_ISSUER', 'https://id.tracezap.com.br')), '/'),
    'client_id' => env('TRACE_ID_CLIENT_ID', env('TRACEID_CLIENT_ID')),
    'client_secret' => env('TRACE_ID_CLIENT_SECRET', env('TRACEID_CLIENT_SECRET')),

    // Se vazio, usa {APP_URL}/auth/callback.
    'redirect_uri' => env('TRACE_ID_REDIRECT_URI', env('TRACEID_REDIRECT_URI')),

    'scopes' => 'openid profile email offline_access',

    // De quanto em quanto tempo (segundos) o acesso da pessoa é conferido de novo no Trace ID.
    // Suspensões e mudanças de nível feitas lá valem no TraceForms dentro deste intervalo.
    'recheck_seconds' => (int) env('TRACE_ID_RECHECK_SECONDS', env('TRACEID_RECHECK_SECONDS', 300)),

    // Nível no Trace ID => papel no TraceForms (admin, editor, analyst, viewer).
    'levels' => [
        'admin' => env('TRACE_ID_LEVEL_ADMIN', env('TRACEID_LEVEL_ADMIN', 'admin')),
        'gestor' => env('TRACE_ID_LEVEL_GESTOR', env('TRACEID_LEVEL_GESTOR', 'editor')),
        'usuario' => env('TRACE_ID_LEVEL_USUARIO', env('TRACEID_LEVEL_USUARIO', 'analyst')),
        'cliente' => env('TRACE_ID_LEVEL_CLIENTE', env('TRACEID_LEVEL_CLIENTE', 'viewer')),
    ],

    // Página onde a pessoa altera nome, e-mail e senha.
    'profile_url' => env('TRACE_ID_PROFILE_URL', env('TRACEID_PROFILE_URL', env('TRACE_ID_URL', 'https://id.tracezap.com.br'))),

    'http_timeout' => 10,
];
