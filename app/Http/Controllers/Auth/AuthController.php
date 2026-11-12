<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TraceId\TraceIdClient;
use App\Services\TraceId\TraceIdException;
use App\Services\TraceId\TraceIdSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Login exclusivamente pelo Trace ID (OpenID Connect, código de autorização + PKCE).
 */
class AuthController extends Controller
{
    public function __construct(private TraceIdClient $trace) {}

    /** Tela de entrar: um botão. Mostra mensagens de erro vindas do retorno. */
    public function showLogin()
    {
        return view('auth.login', ['configured' => $this->trace->configured()]);
    }

    /** Envia a pessoa ao Trace ID. ?account=UUID pede uma empresa específica (troca de empresa). */
    public function redirect(Request $request)
    {
        if (! $this->trace->configured()) {
            return redirect()->route('login');
        }

        $state = Str::random(40);
        $nonce = Str::random(40);
        $verifier = Str::random(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $request->session()->put('traceid.flow', [
            'state' => $state,
            'nonce' => $nonce,
            'verifier' => $verifier,
            'intended' => $request->session()->get('url.intended'),
            'at' => time(),
        ]);

        $account = $request->query('account');
        $extra = [
            'account_hint' => is_string($account) && Str::isUuid($account) ? $account : null,
            'prompt' => in_array($request->query('prompt'), ['login', 'select_account'], true) ? $request->query('prompt') : null,
        ];

        return redirect()->away($this->trace->authorizeUrl($state, $nonce, $challenge, $extra));
    }

    public function callback(Request $request, TraceIdSync $sync)
    {
        $flow = $request->session()->pull('traceid.flow');

        if ($request->filled('error')) {
            $msg = match ($request->query('error')) {
                'access_denied' => 'O acesso foi negado no Trace ID.',
                'login_required', 'interaction_required' => 'Entre no Trace ID para continuar.',
                default => 'O Trace ID não concluiu o login: '.Str::limit((string) ($request->query('error_description') ?: $request->query('error')), 160),
            };

            return redirect()->route('login')->withErrors(['trace' => $msg]);
        }

        if (! $flow || ! hash_equals($flow['state'], (string) $request->query('state')) || time() - $flow['at'] > 900) {
            return redirect()->route('login')->withErrors(['trace' => 'O login expirou ou veio de outra janela. Tente de novo.']);
        }
        if ($request->filled('iss') && rtrim($request->query('iss'), '/') !== $this->trace->issuer()) {
            return redirect()->route('login')->withErrors(['trace' => 'Resposta de um emissor desconhecido. Tente de novo.']);
        }

        try {
            $tokens = $this->trace->exchangeCode((string) $request->query('code'), $flow['verifier']);
            if (empty($tokens['id_token'])) {
                throw new TraceIdException('O Trace ID não devolveu a identificação da pessoa (escopo openid).');
            }
            $claims = $this->trace->verifyIdToken($tokens['id_token'], $flow['nonce']);
            [$user, $account] = $sync->fromClaims($claims);
        } catch (TraceIdException $e) {
            Log::warning('Trace ID: login recusado', ['motivo' => $e->getMessage(), 'codigo' => $e->reason]);

            return redirect()->route('login')->withErrors(['trace' => $e->getMessage()]);
        }

        // Ao trocar de empresa, a sessão anterior no Trace ID deixa de ser usada.
        if ($old = $request->session()->get('traceid.refresh_token')) {
            $this->trace->revoke($old);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('traceid', [
            'sub' => $claims['sub'],
            'account_id' => $account->id,
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'id_token' => $tokens['id_token'],
            'expires_at' => time() + (int) ($tokens['expires_in'] ?? 3600),
            'checked_at' => time(),
        ]);

        return redirect()->to($flow['intended'] ?: route('dashboard'));
    }

    /** Sai do TraceForms e encerra a sessão no Trace ID. */
    public function logout(Request $request)
    {
        $s = $request->session()->get('traceid', []);
        if (! empty($s['refresh_token'])) {
            $this->trace->revoke($s['refresh_token']);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if (! $this->trace->configured()) {
            return redirect()->route('home');
        }

        return redirect()->away($this->trace->logoutUrl($s['id_token'] ?? null, route('home')));
    }
}
