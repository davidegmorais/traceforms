<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AuditLog;
use App\Services\TraceId\TraceIdClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Liga uma empresa do TraceForms a uma conta do Trace ID (ou desfaz com --unlink). */
class TraceLink extends Command
{
    protected $signature = 'traceforms:trace-link {account : ID da empresa no TraceForms} {uuid? : UUID da conta no Trace ID} {--unlink : Desfaz a ligação} {--force : Liga mesmo sem conseguir conferir no Trace ID}';

    protected $description = 'Liga uma empresa do TraceForms a uma conta do Trace ID';

    public function handle(TraceIdClient $trace): int
    {
        $account = Account::find($this->argument('account'));
        if (! $account) {
            $this->error('Empresa '.$this->argument('account').' não encontrada. Veja os IDs com traceforms:trace-status.');

            return self::FAILURE;
        }

        if ($this->option('unlink')) {
            $account->forceFill(['trace_account_id' => null])->save();
            $this->info("\"{$account->name}\" não está mais ligada ao Trace ID.");

            return self::SUCCESS;
        }

        $uuid = Str::lower((string) $this->argument('uuid'));
        if (! Str::isUuid($uuid)) {
            $this->error('Informe o UUID da conta no Trace ID (veja com traceforms:trace-status).');

            return self::FAILURE;
        }
        if ($other = Account::where('trace_account_id', $uuid)->whereKeyNot($account->id)->first()) {
            $this->error("Essa conta do Trace ID já está ligada à empresa {$other->id} (\"{$other->name}\"). Desfaça antes com --unlink.");

            return self::FAILURE;
        }

        $traceName = null;
        if ($trace->configured()) {
            $r = $trace->api('get', "accounts/{$uuid}");
            if ($r->successful()) {
                $traceName = $r->json('name') ?? $r->json('data.name');
            } elseif (! $this->option('force')) {
                $this->error('O Trace ID não encontrou essa conta com licença do TraceForms (HTTP '.$r->status().'). Use --force para ligar mesmo assim.');

                return self::FAILURE;
            }
        }

        $account->forceFill(['trace_account_id' => $uuid])->save();
        AuditLog::create([
            'account_id' => $account->id,
            'action' => 'account.linked',
            'subject_type' => 'Account',
            'subject_id' => $account->id,
            'description' => 'Empresa ligada à conta '.($traceName ? "\"{$traceName}\" " : '').'do Trace ID',
            'meta' => ['trace_account_id' => $uuid],
            'created_at' => now(),
        ]);

        $this->info("\"{$account->name}\" ligada à conta {$uuid}".($traceName ? " (\"{$traceName}\")" : '').' do Trace ID.');
        $this->line('Próximo passo: php artisan traceforms:trace-push '.$account->id.' --dry-run');

        return self::SUCCESS;
    }
}
