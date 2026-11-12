<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Administração dos usuários da plataforma. */
class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->withCount('accounts');
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
        }
        match ($request->query('filter')) {
            'admins' => $q->where('is_super_admin', true),
            'suspended' => $q->whereNotNull('suspended_at'),
            'inactive' => $q->where(fn ($w) => $w->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(60))),
            default => null,
        };
        $request->query('sort') === 'login' ? $q->orderByDesc('last_login_at') : $q->latest();

        return view('admin.users.index', ['users' => $q->paginate(30)->withQueryString()]);
    }

    public function show(User $user)
    {
        return view('admin.users.show', [
            'u' => $user,
            'memberships' => $user->accounts()->with('owner')->get(),
            'logs' => AuditLog::with('account')->where('user_id', $user->id)->latest('created_at')->limit(15)->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::min(8)->letters()->numbers()],
            'is_super_admin' => ['nullable', 'boolean'],
        ]);
        $isSuper = $request->boolean('is_super_admin');
        abort_if($user->id === auth()->id() && ! $isSuper, 422, 'Você não pode remover seu próprio acesso de administrador.');

        $user->forceFill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'is_super_admin' => $isSuper,
        ] + (! empty($data['password']) ? ['password' => $data['password']] : []))->save();

        if (! empty($data['password']) && config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete(); // força novo login
        }

        return back()->with('success', 'Usuário atualizado.'.(! empty($data['password']) ? ' A nova senha já vale e as sessões abertas foram encerradas.' : ''));
    }

    public function suspend(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Você não pode suspender a si mesmo.');
        $user->forceFill(['suspended_at' => now()])->save();
        if (config('session.driver') === 'database') {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return back()->with('success', $user->name.' foi suspenso e desconectado.');
    }

    public function reactivate(User $user)
    {
        $user->forceFill(['suspended_at' => null])->save();

        return back()->with('success', $user->name.' foi reativado.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Você não pode excluir a si mesmo.');
        $owned = $user->accounts()->wherePivot('role', Role::Owner->value)->get();
        if ($owned->count()) {
            return back()->with('error', 'Esta pessoa é proprietária de: '.$owned->pluck('name')->implode(', ').'. Troque o proprietário ou exclua essas contas antes.');
        }
        $request->validate(['confirm' => ['required', 'in:'.$user->email]], ['confirm.in' => 'Digite o e-mail exato para confirmar.']);
        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'Usuário excluído.');
    }

    /** Entra no painel como este usuário, para dar suporte. Um aviso fica visível o tempo todo. */
    public function impersonate(Request $request, User $user)
    {
        abort_if($user->id === auth()->id(), 422);
        abort_if($user->isSuspended(), 422, 'Reative o usuário antes de acessar como ele.');
        $adminId = auth()->id();

        if ($user->current_account_id) {
            AuditLog::create(['account_id' => $user->current_account_id, 'user_id' => $adminId, 'action' => 'admin.impersonate',
                'description' => 'Administração da plataforma acessou como '.$user->name.' (suporte)', 'ip' => $request->ip(), 'created_at' => now()]);
        }
        Auth::login($user);
        $request->session()->put('impersonator_id', $adminId);

        return redirect()->route('dashboard')->with('success', 'Você está acessando como '.$user->name.'.');
    }

    public function stopImpersonating(Request $request)
    {
        $adminId = $request->session()->pull('impersonator_id');
        abort_unless($adminId, 403);
        $previous = auth()->id();
        Auth::loginUsingId($adminId);

        return redirect()->route('admin.users.show', $previous)->with('success', 'Você voltou para a sua conta.');
    }
}
