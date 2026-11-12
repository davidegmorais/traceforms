<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invitation;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function show(Request $request, string $token)
    {
        $invitation = Invitation::with(['account', 'inviter'])->where('token', $token)->firstOrFail();

        return view('invitations.show', [
            'invitation' => $invitation,
            'userExists' => \App\Models\User::where('email', $invitation->email)->exists(),
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::with('account')->where('token', $token)->firstOrFail();
        $user = $request->user();

        if (! $invitation->isPending()) {
            return redirect()->route('dashboard')->with('error', 'Este convite expirou ou já foi usado.');
        }
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            return back()->with('error', 'Este convite foi enviado para '.$invitation->email.'. Entre com esse e-mail para aceitar.');
        }

        if (! $user->membershipFor($invitation->account)) {
            $invitation->account->users()->attach($user->id, ['role' => $invitation->role->value]);
        }
        $invitation->update(['accepted_at' => now()]);
        $user->switchAccount($invitation->account);

        app()->instance('account', $invitation->account);
        AuditLog::record('member.joined', $user->name.' entrou na conta como '.$invitation->role->label(), $invitation);

        return redirect()->route('dashboard')->with('success', 'Você entrou em "'.$invitation->account->name.'".');
    }
}
