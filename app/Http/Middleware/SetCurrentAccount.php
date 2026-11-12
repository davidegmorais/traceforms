<?php

namespace App\Http\Middleware;

use App\Models\Membership;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve a conta (workspace) ativa do usuário e o seu vínculo (papel).
 * Disponibiliza app('account') e app('membership') para o resto da requisição.
 */
class SetCurrentAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Usuário suspenso pela administração da plataforma.
        if ($user->isSuspended() && ! $request->session()->has('impersonator_id')) {
            auth()->logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'Seu acesso foi suspenso. Fale com o suporte.']);
        }

        $membership = $user->current_account_id
            ? Membership::with('account')->where('user_id', $user->id)->where('account_id', $user->current_account_id)->first()
            : null;

        if (! $membership) {
            $membership = Membership::with('account')->where('user_id', $user->id)->oldest()->first();
            if (! $membership) {
                return redirect()->route('accounts.create');
            }
            $user->forceFill(['current_account_id' => $membership->account_id])->save();
        }

        // Conta (empresa) suspensa: membros veem um aviso; administradores da plataforma entram normalmente.
        if ($membership->account->isSuspended() && ! $user->is_super_admin && ! $request->session()->has('impersonator_id')) {
            $others = $user->accounts()->whereNull('accounts.suspended_at')->get();

            return response()->view('accounts.suspended', ['suspended' => $membership->account, 'others' => $others], 403);
        }

        app()->instance('account', $membership->account);
        app()->instance('membership', $membership);

        View::share('account', $membership->account);
        View::share('membership', $membership);

        return $next($request);
    }
}
