<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Invitation;
use App\Models\Membership;
use App\Notifications\InvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index()
    {
        $account = $this->account();

        return view('members.index', [
            'members' => $account->memberships()->with('user')->get()->sortByDesc(fn ($m) => $m->role->level())->values(),
            'invitations' => $account->invitations()->with('inviter')->whereNull('accepted_at')->latest()->get(),
            'roles' => Role::cases(),
            'assignable' => $this->membership()->role->assignable(),
            'forms' => $account->forms()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    public function invite(Request $request)
    {
        $account = $this->account();
        $assignable = array_map(fn ($r) => $r->value, $this->membership()->role->assignable());

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in($assignable)],
        ], ['role.in' => 'Você não pode atribuir esse papel.']);
        $email = strtolower($data['email']);

        if ($account->users()->where('email', $email)->exists()) {
            return back()->with('error', 'Essa pessoa já é membro da conta.');
        }

        $invitation = Invitation::updateOrCreate(
            ['account_id' => $account->id, 'email' => $email, 'accepted_at' => null],
            ['role' => $data['role'], 'token' => Str::random(48), 'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(7)]
        );

        try {
            Notification::route('mail', $email)->notify(new InvitationNotification($invitation));
        } catch (\Throwable $e) {
            report($e);
        }
        AuditLog::record('invitation.sent', 'Convidou '.$email.' como '.$invitation->role->label(), $invitation);

        return back()->with('success', 'Convite enviado para '.$email.'.')->with('invite_link', route('invitations.show', $invitation->token));
    }

    public function update(Request $request, Membership $membership)
    {
        $me = $this->membership();
        abort_unless($membership->account_id === $me->account_id, 404);
        $this->guardTarget($membership);

        $assignable = array_map(fn ($r) => $r->value, $me->role->assignable());
        $data = $request->validate([
            'role' => ['required', Rule::in($assignable)],
            'restrict' => ['nullable', 'boolean'],
            'form_ids' => ['nullable', 'array'],
            'form_ids.*' => ['integer'],
        ]);
        $role = Role::from($data['role']);
        $formIds = $role->scopable() && $request->boolean('restrict')
            ? $this->account()->forms()->whereIn('id', $data['form_ids'] ?? [])->pluck('id')->all()
            : null;

        $old = $membership->role;
        $membership->update(['role' => $role->value, 'form_ids' => $formIds ?: null]);
        AuditLog::record('member.updated', "Alterou {$membership->user->name}: {$old->label()} → {$role->label()}".($formIds ? ' (restrito a '.count($formIds).' formulário(s))' : ''), $membership);

        return back()->with('success', 'Acesso de '.$membership->user->name.' atualizado.');
    }

    public function destroy(Request $request, Membership $membership)
    {
        $me = $this->membership();
        abort_unless($membership->account_id === $me->account_id, 404);
        $leaving = $membership->user_id === $request->user()->id;

        if ($leaving) {
            abort_if($membership->role === Role::Owner, 422, 'Transfira a propriedade antes de sair da conta.');
        } else {
            Gate::authorize('members.manage');
            $this->guardTarget($membership);
        }

        $name = $membership->user->name;
        $membership->delete();
        AuditLog::record('member.removed', $leaving ? "{$name} saiu da conta" : "Removeu {$name} da conta");

        return $leaving
            ? redirect()->route('dashboard')->with('success', 'Você saiu da conta.')
            : back()->with('success', $name.' foi removido.');
    }

    public function revoke(Invitation $invitation)
    {
        abort_unless($invitation->account_id === $this->account()->id, 404);
        $invitation->delete();
        AuditLog::record('invitation.revoked', 'Cancelou o convite de '.$invitation->email);

        return back()->with('success', 'Convite cancelado.');
    }

    /** Ninguém altera o proprietário nem alguém de nível igual ou superior (exceto o proprietário). */
    private function guardTarget(Membership $target): void
    {
        $me = $this->membership();
        abort_if($target->role === Role::Owner, 403, 'O proprietário não pode ser alterado aqui.');
        abort_if($me->role !== Role::Owner && $target->role->level() >= $me->role->level(), 403, 'Você não pode alterar alguém do mesmo nível ou superior.');
    }
}
