<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\TraceId\TraceIdClient;
use App\Services\TraceId\TraceIdSync;
use Illuminate\Console\Command;

/**
 * Cria no Trace ID o acesso ao TraceForms de cada membro atual de uma empresa,
 * com o nível equivalente ao papel que a pessoa tem hoje.
 * Quem já tem conta no Trace ID mantém a senha; quem não tem recebe uma senha gerada.
 */
class TracePush extends Command
{
    protected $signature = 'traceforms:trace-push {account : ID da empresa no TraceForms} {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Cria no Trace ID os acessos dos membros atuais de uma empresa';

    public function handle(TraceIdClient $trace): int
    {
        $account = Account::with('memberships.user')->find($this->argument('account'));
        if (! $account?->trace_account_id) {
            $this->error('A empresa não existe ou ainda não está ligada ao Trace ID. Rode antes traceforms:trace-link.');

            return self::FAILURE;
        }
        if (! $trace->configured()) {
            $this->error('Preencha TRACEID_CLIENT_ID e TRACEID_CLIENT_SECRET no .env.');

            return self::FAILURE;
        }

        $existing = collect($trace->members($account->trace_account_id))->keyBy(fn ($m) => strtolower($m['email'] ?? ''));
        $dry = $this->option('dry-run');
        $rows = [];
        $passwords = [];
        $failures = 0;

        foreach ($account->memberships as $m) {
            $user = $m->user;
            $level = TraceIdSync::levelFor($m->role);

            if ($current = $existing->get(strtolower($user->email))) {
                $rows[] = [$user->name, $user->email, $m->role->label(), $level, 'Já tem acesso ('.($current['level'] ?? '?').')'];

                continue;
            }
            if ($dry) {
                $rows[] = [$user->name, $user->email, $m->role->label(), $level, 'Seria criado'];

                continue;
            }

            $r = $trace->api('post', "accounts/{$account->trace_account_id}/members", [
                'email' => $user->email,
                'name' => $user->name,
                'level' => $level,
                'external_id' => (string) $user->id,
            ]);

            if ($r->status() === 201) {
                $rows[] = [$user->name, $user->email, $m->role->label(), $level, 'Criado'];
                if ($pw = $r->json('generated_password') ?? $r->json('data.generated_password')) {
                    $passwords[] = [$user->email, $pw];
                }
            } else {
                $failures++;
                $msg = collect($r->json('errors') ?? [])->flatten()->first() ?? $r->json('message') ?? 'HTTP '.$r->status();
                $rows[] = [$user->name, $user->email, $m->role->label(), $level, 'Falhou: '.$msg];
            }
        }

        $this->info(($dry ? 'Simulação para ' : '')."\"{$account->name}\"");
        $this->table(['Nome', 'E-mail', 'Papel no TraceForms', 'Nível no Trace ID', 'Resultado'], $rows);

        if ($passwords) {
            $this->newLine();
            $this->warn('Senhas geradas pelo Trace ID para quem ainda não tinha conta. Elas aparecem só agora: envie a cada pessoa.');
            $this->table(['E-mail', 'Senha'], $passwords);
        }
        if ($dry) {
            $this->line('Nada foi gravado. Rode sem --dry-run para criar os acessos.');
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
