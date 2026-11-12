<?php

namespace App\Http\Middleware;

use App\Services\TraceId\TraceIdClient;
use App\Services\TraceId\TraceIdException;
use App\Services\TraceId\TraceIdSync;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mantém a sessão alinhada ao Trace ID:
 *  - sessões sem tokens do Trace ID (ex.: login antigo por senha) são encerradas;
 *  - a cada TRACEID_RECHECK_SECONDS, renova o token se venceu e confere o acesso (userinfo),
 *    atualizando nível e recursos. Acesso suspenso ou removido encerra a sessão.
 *  - se o Trace ID estiver fora do ar, a sessão segue e a conferência é tentada de novo em 1 minuto.
 */
class EnsureTraceSession
{
    public function __construct(private TraceIdClient $trace, private TraceIdSync $sync) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $s = $request->session()->get('traceid');
        if (! $s || empty($s['access_token'])) {
            return $this->end($request, 'Agora o TraceForms usa o Trace ID para entrar. Entre de novo.');
        }

        if (time() - ($s['checked_at'] ?? 0) < config('traceid.recheck_seconds', 300)) {
            return $next($request);
        }

        try {
            if (($s['expires_at'] ?? 0) - 60 <= time()) {
                if (empty($s['refresh_token'])) {
                    return $this->end($request, 'Sua sessão terminou. Entre de novo.');
                }
                $t = $this->trace->refresh($s['refresh_token']);
                $s['access_token'] = $t['access_token'];
                $s['refresh_token'] = $t['refresh_token'] ?? $s['refresh_token'];
                $s['id_token'] = $t['id_token'] ?? $s['id_token'];
                $s['expires_at'] = time() + (int) ($t['expires_in'] ?? 3600);
            }

            $info = $this->trace->userinfo($s['access_token']);
            if ($info === null) {
                return $this->end($request, 'Seu acesso ao TraceForms foi suspenso ou removido no Trace ID.');
            }
            if (($info['sub'] ?? null) !== ($s['sub'] ?? null)) {
                return $this->end($request, 'A sessão não corresponde mais à mesma pessoa. Entre de novo.');
            }

            $this->sync->refreshAccess($request->user(), $info);
            $s['checked_at'] = time();
        } catch (TraceIdException $e) {
            if ($e->reason === 'invalid_grant') {
                return $this->end($request, $e->getMessage());
            }
            Log::warning('Trace ID: conferência adiada', ['motivo' => $e->getMessage()]);
            $s['checked_at'] = time() - config('traceid.recheck_seconds', 300) + 60;
        } catch (\Throwable $e) {
            Log::warning('Trace ID indisponível', ['erro' => $e->getMessage()]);
            $s['checked_at'] = time() - config('traceid.recheck_seconds', 300) + 60;
        }

        $request->session()->put('traceid', $s);

        return $next($request);
    }

    private function end(Request $request, string $message): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()->route('login')->withErrors(['trace' => $message]);
    }
}
