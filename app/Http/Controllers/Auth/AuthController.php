<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $cred = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], ['required' => 'Campo obrigatório.', 'email' => 'E-mail inválido.']);

        if (! Auth::attempt($cred, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])->onlyInput('email');
        }

        if ($request->user()->isSuspended()) {
            Auth::logout();

            return back()->withErrors(['email' => 'Seu acesso foi suspenso. Fale com o suporte.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(Request $request)
    {
        $invitation = $request->query('invite') ? Invitation::with('account')->where('token', $request->query('invite'))->first() : null;
        $invitation = $invitation?->isPending() ? $invitation : null;

        if (! $invitation && ! config('traceforms.registration_open')) {
            return redirect()->route('login')->with('status', 'O cadastro está fechado. Peça acesso ao administrador ou use o link do convite que você recebeu.');
        }

        return view('auth.register', ['invitation' => $invitation]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'company' => ['nullable', 'string', 'max:120'],
            'invite' => ['nullable', 'string'],
        ], [
            'required' => 'Campo obrigatório.',
            'email.unique' => 'Já existe uma conta com este e-mail.',
            'password.confirmed' => 'As senhas não conferem.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
            'password.letters' => 'A senha precisa conter letras.',
            'password.numbers' => 'A senha precisa conter números.',
        ]);

        // Com o cadastro fechado, só passa quem tem um convite válido para este e-mail.
        if (! config('traceforms.registration_open')) {
            $invite = ! empty($data['invite'])
                ? Invitation::where('token', $data['invite'])->whereNull('accepted_at')->where('expires_at', '>', now())->first()
                : null;
            if (! $invite || strtolower($invite->email) !== strtolower($data['email'])) {
                return redirect()->route('login')->with('status', 'O cadastro está fechado. Peça acesso ao administrador ou use o link do convite que você recebeu.');
            }
        }

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
            ]);

            $invitation = ! empty($data['invite'])
                ? Invitation::where('token', $data['invite'])->whereNull('accepted_at')->where('expires_at', '>', now())->first()
                : null;

            if ($invitation && strtolower($invitation->email) === $user->email) {
                $invitation->account->users()->attach($user->id, ['role' => $invitation->role->value]);
                $invitation->update(['accepted_at' => now()]);
                $user->forceFill(['current_account_id' => $invitation->account_id])->save();
            } else {
                Account::createFor($user, $data['company'] ?: 'Conta de '.explode(' ', $user->name)[0]);
            }

            return $user;
        });

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Bem-vindo ao TraceForms! Crie seu primeiro formulário.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
