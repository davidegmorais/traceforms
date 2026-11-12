<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Membership;
use Illuminate\Http\Request;

/**
 * Equipe: quem tem acesso a esta empresa. Os acessos (entrada, nível, remoção) são
 * gerenciados no Trace ID; aqui só dá para restringir alguém a formulários específicos.
 */
class MemberController extends Controller
{
    public function index()
    {
        $account = $this->account();

        return view('members.index', [
            'members' => $account->memberships()->with('user')->get()->sortByDesc(fn ($m) => $m->role->level())->values(),
            'roles' => array_values(array_filter(Role::cases(), fn ($r) => $r !== Role::Owner)),
            'forms' => $account->forms()->orderBy('title')->get(['id', 'title']),
        ]);
    }

    /** Restringe (ou libera) um membro abaixo de administrador a formulários específicos. */
    public function scope(Request $request, Membership $membership)
    {
        abort_unless($membership->account_id === $this->account()->id, 404);
        abort_unless($membership->role->scopable(), 422, 'Administradores sempre veem todos os formulários.');

        $data = $request->validate([
            'restrict' => ['nullable', 'boolean'],
            'form_ids' => ['nullable', 'array'],
            'form_ids.*' => ['integer'],
        ]);
        $formIds = $request->boolean('restrict')
            ? $this->account()->forms()->whereIn('id', $data['form_ids'] ?? [])->pluck('id')->all()
            : null;

        $membership->update(['form_ids' => $formIds ?: null]);
        AuditLog::record('member.updated', "Alterou os formulários de {$membership->user->name}: ".($formIds ? 'restrito a '.count($formIds).' formulário(s)' : 'todos'), $membership);

        return back()->with('success', 'Acesso de '.$membership->user->name.' atualizado.');
    }
}
