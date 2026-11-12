<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'account' => \App\Http\Middleware\SetCurrentAccount::class,
            'trace' => \App\Http\Middleware\EnsureTraceSession::class,
        ]);
        // Endpoints de coleta recebem sendBeacon/fetch de iframes e páginas externas.
        $middleware->validateCsrfTokens(except: ['f/*/collect', 'f/*/submit', 'f/*/upload']);
        // Cookie de visitante é gravado pelo tracker em JS, sem criptografia do Laravel.
        $middleware->encryptCookies(except: ['tf_vid']);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        // Confia em proxies (Cloudflare / load balancer) para IP e protocolo corretos.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
