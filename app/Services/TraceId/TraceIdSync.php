<?php

namespace App\Services\TraceId;

use App\Enums\Role;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Traz para o TraceForms quem é a pessoa, de qual empresa e com qual nível,
 * a partir das claims do Trace ID (id_token ou userinfo).
 *
 * Migração de quem já usava o TraceForms:
 *  - pessoa: procurada pelo identificador do Trace ID (sub); se ainda não foi ligada,
 *    pelo e-mail. No primeiro login ela fica ligada para sempre ao sub.
 *  - empresa: procurada pelo UUID da conta no Trace ID; se ainda não foi ligada e a pessoa
 *    é administradora lá e dona/administradora de exatamente UMA empresa local sem ligação,
 *    essa empresa é ligada. Senão, uma empresa nova é criada.
 */
class TraceIdSync
{
    public static function roleFor(?string $level): Role
    {
        $mapped = config('traceid.levels.'.($level ?? ''), 'viewer');

        return Role::tryFrom($mapped) ?? Role::Viewer;
    }

    /** Nível do Trace ID mais próximo de um papel local (usado ao enviar membros para o Trace ID). */
    public static function levelFor(Role $role): string
    {
        $map = array_flip(config('traceid.levels'));
        if ($role === Role::Owner) {
            $role = Role::Admin;
        }

        return $map[$role->value] ?? match (true) {
            $role->atLeast(Role::Admin) => 'admin',
            $role->atLeast(Role::Editor) => 'gestor',
            $role->atLeast(Role::Analyst) => 'usuario',
            default => 'cliente',
        };
    }

    /** @return array{0: User, 1: Account, 2: Membership} */
    public function fromClaims(array $claims): array
    {
        $trace = $claims['trace'] ?? null;
        if (! is_array($trace) || empty($trace['account']['id'])) {
            throw new TraceIdException('O Trace ID não informou a empresa desta sessão.');
        }

        return DB::transaction(function () use ($claims, $trace) {
            $user = $this->user($claims);
            $account = $this->account($trace, $user);
            $membership = $this->membership($account, $user, $trace['level'] ?? null);

            $user->forceFill(['current_account_id' => $account->id, 'last_login_at' => now()])->save();

            return [$user, $account, $membership];
        });
    }

    /** Atualiza só nível e recursos (revalidação periódica com userinfo). */
    public function refreshAccess(User $user, array $info): ?Membership
    {
        $trace = $info['trace'] ?? null;
        $account = ! empty($trace['account']['id']) ? Account::where('trace_account_id', $trace['account']['id'])->first() : null;
        if (! $account) {
            return null;
        }
        $account->forceFill(['trace_features' => $trace['features'] ?? null, 'trace_synced_at' => now()])->save();

        return $this->membership($account, $user, $trace['level'] ?? null);
    }

    private function user(array $claims): User
    {
        $sub = (string) $claims['sub'];
        $email = isset($claims['email']) ? Str::lower(trim($claims['email'])) : null;
        $name = trim((string) ($claims['name'] ?? $claims['given_name'] ?? ''));

        $user = User::where('trace_id', $sub)->first();

        if (! $user && $email && ($claims['email_verified'] ?? true) !== false) {
            $user = User::whereNull('trace_id')->where('email', $email)->first();
        }

        if (! $user) {
            if (! $email) {
                throw new TraceIdException('O Trace ID não informou o e-mail desta pessoa. Peça para incluir o escopo "email" na plataforma.');
            }
            if (User::where('email', $email)->exists()) {
                throw new TraceIdException('Este e-mail já está ligado a outra identidade do Trace ID. Fale com o suporte.');
            }
            $user = new User(['email' => $email]);
        }

        $user->trace_id = $sub;
        if ($name !== '') {
            $user->name = $name;
        }
        if ($email && $email !== $user->email && ! User::where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            $user->email = $email;
        }
        $user->name = $user->name ?: Str::before((string) $user->email, '@');
        $user->save();

        return $user;
    }

    private function account(array $trace, User $user): Account
    {
        $uuid = (string) $trace['account']['id'];
        $traceName = trim((string) ($trace['account']['name'] ?? ''));

        $account = Account::where('trace_account_id', $uuid)->first();

        if (! $account && ($trace['level'] ?? null) === 'admin') {
            $candidates = $user->accounts()
                ->whereNull('accounts.trace_account_id')
                ->wherePivotIn('role', [Role::Owner->value, Role::Admin->value])
                ->get();
            if ($candidates->count() === 1) {
                $account = $candidates->first();
                AuditLog::create([
                    'account_id' => $account->id,
                    'user_id' => $user->id,
                    'action' => 'account.linked',
                    'subject_type' => 'Account',
                    'subject_id' => $account->id,
                    'description' => 'Empresa ligada à conta "'.$traceName.'" do Trace ID no primeiro login',
                    'meta' => ['trace_account_id' => $uuid],
                    'created_at' => now(),
                ]);
            }
        }

        if (! $account) {
            $name = $traceName !== '' ? $traceName : 'Empresa';
            $account = Account::create([
                'name' => $name,
                'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
                'owner_id' => $user->id,
            ]);
        }

        $account->forceFill([
            'trace_account_id' => $uuid,
            'name' => $traceName !== '' ? $traceName : $account->name,
            'trace_features' => $trace['features'] ?? null,
            'trace_synced_at' => now(),
        ])->save();

        return $account;
    }

    private function membership(Account $account, User $user, ?string $level): Membership
    {
        $role = self::roleFor($level);
        $membership = Membership::where('account_id', $account->id)->where('user_id', $user->id)->first();

        if (! $membership) {
            $account->users()->attach($user->id, ['role' => $role->value]);

            return Membership::where('account_id', $account->id)->where('user_id', $user->id)->firstOrFail();
        }

        if ($membership->role !== $role) {
            $membership->role = $role;
            // Restrição por formulário só vale para papéis abaixo de administrador.
            if (! $role->scopable()) {
                $membership->form_ids = null;
            }
            $membership->save();
        }

        return $membership;
    }
}
