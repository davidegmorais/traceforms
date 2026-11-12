<?php

/*
| Login pelo Trace ID (OpenID Connect).
| Cadastre o TraceForms como plataforma no Trace ID com:
|   URL de retorno:      {APP_URL}/auth/trace/callback
|   URL após sair:       {APP_URL}/
| e preencha TRACEID_CLIENT_ID e TRACEID_CLIENT_SECRET no .env.
*/
return [
    'issuer' => rtrim(env('TRACEID_ISSUER', 'https://id.tracezap.com.br'), '/'),
    'client_id' => env('TRACEID_CLIENT_ID'),
    'client_secret' => env('TRACEID_CLIENT_SECRET'),

    // Se vazio, usa {APP_URL}/auth/trace/callback.
    'redirect_uri' => env('TRACEID_REDIRECT_URI'),

    'scopes' => 'openid profile email offline_access',

    // De quanto em quanto tempo (segundos) o acesso da pessoa é conferido de novo no Trace ID.
    // Suspensões e mudanças de nível feitas lá valem no TraceForms dentro deste intervalo.
    'recheck_seconds' => (int) env('TRACEID_RECHECK_SECONDS', 300),

    // Nível no Trace ID => papel no TraceForms (admin, editor, analyst, viewer).
    'levels' => [
        'admin' => env('TRACEID_LEVEL_ADMIN', 'admin'),
        'gestor' => env('TRACEID_LEVEL_GESTOR', 'editor'),
        'usuario' => env('TRACEID_LEVEL_USUARIO', 'analyst'),
        'cliente' => env('TRACEID_LEVEL_CLIENTE', 'viewer'),
    ],

    // Página onde a pessoa altera nome, e-mail e senha.
    'profile_url' => env('TRACEID_PROFILE_URL', 'https://id.tracezap.com.br'),

    'http_timeout' => 10,
];
