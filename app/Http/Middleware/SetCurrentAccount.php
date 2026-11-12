<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a empresa ativa e o papel da pessoa nela.
 * A empresa é a da sessão do Trace ID (cada login vale para uma empresa); trocar de empresa
 * passa por um novo login no Trace ID.
 * Disponibiliza app('account') e app('membership') para o resto da requisição.
 */
class SetCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $accountId = $request->session()->get('traceid.account_id') ?? $user->current_account_id;

        $membership = $accountId
            ? Membership::with('account')->where('user_id', $user->id)->where('account_id', $accountId)->first()
            : null;

        if (! $membership) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['trace' => 'Não encontramos seu acesso a esta empresa. Entre de novo pelo Trace ID.']);
        }

        // Empresa com licença inativa no Trace ID (marcada pelo traceforms:trace-sync).
        if ($membership->account->isSuspended()) {
            $others = $user->accounts()->whereNull('accounts.suspended_at')->whereNotNull('accounts.trace_account_id')->get();

            return response()->view('accounts.suspended', ['suspended' => $membership->account, 'others' => $others], 403);
        }

        app()->instance('account', $membership->account);
        app()->instance('membership', $membership);

        View::share('account', $membership->account);
        View::share('membership', $membership);

        return $next($request);
    }
}
