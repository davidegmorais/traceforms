<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Membership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function create()
    {
        return view('accounts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        Account::createFor($request->user(), $data['name']);

        return redirect()->route('dashboard')->with('success', 'Conta "'.$data['name'].'" criada.');
    }

    public function switch(Request $request, Account $account)
    {
        abort_unless($request->user()->switchAccount($account), 403);

        return redirect()->route('dashboard')->with('success', 'Você está agora em "'.$account->name.'".');
    }

    public function edit()
    {
        $account = $this->account();
        $admins = $account->memberships()->with('user')->where('user_id', '!=', $account->owner_id)->get();

        return view('accounts.edit', [
            'timezones' => \DateTimeZone::listIdentifiers(),
            'transferTargets' => $admins,
            'stats' => [
                'forms' => $account->forms()->count(),
                'responses' => $account->submissions()->count(),
                'members' => $account->memberships()->count(),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_notify' => ['nullable', 'boolean'],
            'data_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $account = $this->account();
        $account->update([
            'name' => $data['name'],
            'timezone' => $data['timezone'],
            'brand_color' => $data['brand_color'],
            'settings' => array_merge($account->settings ?? [], [
                'data_retention_days' => (int) ($data['data_retention_days'] ?? 0),
            ]),
        ]);
        AuditLog::record('account.updated', 'Atualizou as configurações da conta', $account);

        return back()->with('success', 'Configurações salvas.');
    }

    public function transfer(Request $request)
    {
        $account = $this->account();
        $request->validate(['membership_id' => ['required', 'integer'], 'confirm' => ['required', 'in:'.$account->name]], [
            'confirm.in' => 'Digite o nome exato da conta para confirmar.',
        ]);
        $target = Membership::where('account_id', $account->id)->findOrFail($request->membership_id);

        DB::transaction(function () use ($account, $target) {
            Membership::where('account_id', $account->id)->where('user_id', $account->owner_id)->update(['role' => Role::Admin->value]);
            $target->update(['role' => Role::Owner->value, 'form_ids' => null]);
            $account->update(['owner_id' => $target->user_id]);
        });
        AuditLog::record('account.transferred', 'Transferiu a propriedade para '.$target->user->name, $account);

        return redirect()->route('members.index')->with('success', 'Propriedade transferida para '.$target->user->name.'. Você agora é Administrador.');
    }

    public function destroy(Request $request)
    {
        $account = $this->account();
        $request->validate(['confirm' => ['required', 'in:'.$account->name]], ['confirm.in' => 'Digite o nome exato da conta para confirmar.']);

        $userIds = $account->memberships()->pluck('user_id');
        $account->forms()->withTrashed()->get()->each->forceDelete();
        $account->delete();
        \App\Models\User::whereIn('id', $userIds)->where('current_account_id', $account->id)->update(['current_account_id' => null]);

        return redirect()->route('dashboard')->with('success', 'Conta excluída.');
    }
}
