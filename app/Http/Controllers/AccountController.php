<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /** Trocar de empresa = novo login no Trace ID pedindo aquela empresa. */
    public function switch(Request $request, Account $account)
    {
        abort_unless($request->user()->membershipFor($account) && $account->trace_account_id, 403);

        return redirect()->route('auth.trace.redirect', ['account' => $account->trace_account_id]);
    }

    public function edit()
    {
        $account = $this->account();

        return view('accounts.edit', [
            'timezones' => \DateTimeZone::listIdentifiers(),
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
            'timezone' => ['required', 'timezone'],
            'brand_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_notify' => ['nullable', 'boolean'],
            'data_retention_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);
        $account = $this->account();
        $account->update([
            'timezone' => $data['timezone'],
            'brand_color' => $data['brand_color'],
            'settings' => array_merge($account->settings ?? [], [
                'data_retention_days' => (int) ($data['data_retention_days'] ?? 0),
            ]),
        ]);
        AuditLog::record('account.updated', 'Atualizou as configurações da conta', $account);

        return back()->with('success', 'Configurações salvas.');
    }
}
