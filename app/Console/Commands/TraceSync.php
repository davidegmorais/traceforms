<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\TraceId\TraceIdClient;
use Illuminate\Console\Command;

/**
 * Confere as licenças no Trace ID. Empresa com licença inativa, vencida ou removida fica
 * suspensa (formulários param de aceitar respostas; dados são mantidos). Quando a licença
 * volta, a suspensão feita por este comando é desfeita.
 */
class TraceSync extends Command
{
    protected $signature = 'traceforms:trace-sync';

    protected $description = 'Atualiza licença, nome e recursos das empresas a partir do Trace ID';

    private const REASON = 'Licença inativa no Trace ID';

    public function handle(TraceIdClient $trace): int
    {
        if (! $trace->configured()) {
            $this->warn('Trace ID não configurado; nada a sincronizar.');

            return self::SUCCESS;
        }

        $r = $trace->api('get', 'accounts');
        if (! $r->successful()) {
            $this->error('O Trace ID recusou a consulta de contas (HTTP '.$r->status().').');

            return self::FAILURE;
        }
        $remote = collect($r->json('data') ?? $r->json())->keyBy('id');

        $suspended = $reactivated = 0;
        Account::whereNotNull('trace_account_id')->each(function (Account $account) use ($remote, &$suspended, &$reactivated) {
            $info = $remote->get($account->trace_account_id);
            $expired = ! empty($info['license_expires_at']) && now()->startOfDay()->gt($info['license_expires_at']);
            $active = $info && ($info['status'] ?? 'active') === 'active' && ! $expired;

            if ($info) {
                $account->forceFill([
                    'name' => $info['name'] ?? $account->name,
                    'trace_features' => $info['features'] ?? $account->trace_features,
                    'trace_synced_at' => now(),
                ]);
            }

            if (! $active && ! $account->isSuspended()) {
                $account->forceFill(['suspended_at' => now(), 'suspension_reason' => self::REASON]);
                $suspended++;
            } elseif ($active && $account->isSuspended() && $account->suspension_reason === self::REASON) {
                $account->forceFill(['suspended_at' => null, 'suspension_reason' => null]);
                $reactivated++;
            }
            $account->save();
        });

        $this->info("Empresas suspensas: {$suspended} · reativadas: {$reactivated}");

        return self::SUCCESS;
    }
}
