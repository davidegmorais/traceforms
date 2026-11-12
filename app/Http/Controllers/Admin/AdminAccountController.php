<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Submission;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Administração das empresas (contas) da plataforma. */
class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        $q = Account::query()->with('owner')->withCount(['forms', 'memberships']);
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")
                ->orWhereHas('owner', fn ($o) => $o->where('email', 'like', "%{$s}%")->orWhere('name', 'like', "%{$s}%")));
        }
        match ($request->query('status')) {
            'active' => $q->whereNull('suspended_at'),
            'suspended' => $q->whereNotNull('suspended_at'),
            default => null,
        };
        match ($request->query('sort', 'recent')) {
            'name' => $q->orderBy('name'),
            'forms' => $q->orderByDesc('forms_count'),
            default => $q->latest(),
        };

        return view('admin.accounts.index', ['accounts' => $q->paginate(30)->withQueryString()]);
    }

    public function show(Account $account)
    {
        $account->load(['owner', 'memberships.user']);
        $formIds = $account->forms()->withTrashed()->pluck('id');

        return view('admin.accounts.show', [
            'acc' => $account,
            'forms' => $account->forms()->withCount(['submissions' => fn ($q) => $q->where('is_spam', false)])->latest()->get(),
            'stats' => [
                'responses' => Submission::whereIn('form_id', $formIds)->where('is_spam', false)->count(),
                'responses_30d' => Submission::whereIn('form_id', $formIds)->where('is_spam', false)->where('submitted_at', '>=', now()->subDays(30))->count(),
                'storage' => (int) Upload::whereIn('form_id', $formIds)->sum('size'),
                'last_response' => Submission::whereIn('form_id', $formIds)->max('submitted_at'),
            ],
            'logs' => $account->auditLogs()->with('user')->latest('created_at')->limit(15)->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function update(Request $request, Account $account)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'timezone'],
            'owner_id' => ['required', Rule::exists('account_user', 'user_id')->where('account_id', $account->id)],
        ], ['owner_id.exists' => 'O novo proprietário precisa ser membro da conta.']);

        DB::transaction(function () use ($account, $data) {
            if ((int) $data['owner_id'] !== $account->owner_id) {
                Membership::where('account_id', $account->id)->where('user_id', $account->owner_id)->update(['role' => Role::Admin->value]);
                Membership::where('account_id', $account->id)->where('user_id', $data['owner_id'])->update(['role' => Role::Owner->value, 'form_ids' => null]);
            }
            $account->update($data);
        });
        $this->log($account, 'account.admin_updated', 'Administração da plataforma alterou os dados da conta');

        return back()->with('success', 'Conta atualizada.');
    }

    public function suspend(Request $request, Account $account)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $account->update(['suspended_at' => now(), 'suspension_reason' => $data['reason'] ?? null]);
        $this->log($account, 'account.suspended', 'Administração da plataforma suspendeu a conta'.($data['reason'] ?? null ? ': '.$data['reason'] : ''));

        return back()->with('success', 'Conta suspensa. Os membros perderam o acesso e os formulários pararam de aceitar respostas.');
    }

    public function reactivate(Account $account)
    {
        $account->update(['suspended_at' => null, 'suspension_reason' => null]);
        $this->log($account, 'account.reactivated', 'Administração da plataforma reativou a conta');

        return back()->with('success', 'Conta reativada.');
    }

    public function member(Request $request, Account $account, Membership $membership)
    {
        abort_unless($membership->account_id === $account->id, 404);
        if ($request->isMethod('delete')) {
            abort_if($membership->role === Role::Owner, 422, 'Troque o proprietário antes de remover esta pessoa.');
            $membership->delete();
            $this->log($account, 'member.removed', 'Administração da plataforma removeu '.$membership->user->name);

            return back()->with('success', 'Membro removido.');
        }
        $role = $request->validate(['role' => ['required', Rule::in(array_map(fn ($r) => $r->value, array_filter(Role::cases(), fn ($r) => $r !== Role::Owner)))]])['role'];
        abort_if($membership->role === Role::Owner, 422, 'Use “trocar proprietário” para alterar o proprietário.');
        $membership->update(['role' => $role]);
        $this->log($account, 'member.updated', 'Administração da plataforma alterou o papel de '.$membership->user->name.' para '.Role::from($role)->label());

        return back()->with('success', 'Papel atualizado.');
    }

    public function addMember(Request $request, Account $account)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'role' => ['required', Rule::in(['admin', 'editor', 'analyst', 'viewer'])],
        ], ['email.exists' => 'Não há usuário com este e-mail. Peça para a pessoa criar a conta ou envie um convite pela conta.']);
        $user = User::where('email', strtolower($data['email']))->first();
        if ($user->membershipFor($account)) {
            return back()->with('error', 'Essa pessoa já é membro.');
        }
        $account->users()->attach($user->id, ['role' => $data['role']]);
        $this->log($account, 'member.added', 'Administração da plataforma adicionou '.$user->name.' como '.Role::from($data['role'])->label());

        return back()->with('success', $user->name.' adicionado.');
    }

    public function destroy(Request $request, Account $account)
    {
        $request->validate(['confirm' => ['required', 'in:'.$account->name]], ['confirm.in' => 'Digite o nome exato da conta para confirmar.']);
        $userIds = $account->memberships()->pluck('user_id');
        $account->forms()->withTrashed()->get()->each->forceDelete();
        $account->delete();
        User::whereIn('id', $userIds)->where('current_account_id', $account->id)->update(['current_account_id' => null]);

        return redirect()->route('admin.accounts.index')->with('success', 'Conta “'.$account->name.'” excluída com todos os dados.');
    }

    private function log(Account $account, string $action, string $description): void
    {
        AuditLog::create([
            'account_id' => $account->id, 'user_id' => auth()->id(), 'action' => $action,
            'subject_type' => 'Account', 'subject_id' => $account->id, 'description' => $description,
            'meta' => ['by_platform_admin' => true], 'ip' => request()->ip(), 'created_at' => now(),
        ]);
    }
}
