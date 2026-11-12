<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\TraceId\TraceIdClient;
use Illuminate\Console\Command;

/** Mostra quais empresas e pessoas já estão ligadas ao Trace ID e lista as contas licenciadas lá. */
class TraceStatus extends Command
{
    protected $signature = 'traceforms:trace-status';

    protected $description = 'Mostra a situação da migração para o Trace ID';

    public function handle(TraceIdClient $trace): int
    {
        $this->info('Empresas no TraceForms');
        $rows = Account::withCount('forms')->with('memberships.user')->orderBy('id')->get()->map(fn (Account $a) => [
            $a->id,
            $a->name,
            $a->trace_account_id ?? '— não ligada',
            $a->forms_count,
            $a->memberships->count(),
            $a->memberships->filter(fn ($m) => $m->user?->trace_id)->count(),
            $a->isSuspended() ? 'licença inativa' : 'ativa',
        ]);
        $this->table(['ID', 'Nome', 'Conta no Trace ID', 'Formulários', 'Membros', 'Já entraram pelo Trace ID', 'Situação'], $rows);

        if (! $trace->configured()) {
            $this->warn('TRACE_ID_CLIENT_ID e TRACE_ID_CLIENT_SECRET não estão no .env: não dá para consultar o Trace ID.');

            return self::SUCCESS;
        }

        $r = $trace->api('get', 'accounts');
        if (! $r->successful()) {
            $this->error('O Trace ID recusou a consulta de contas (HTTP '.$r->status().'). A plataforma precisa do escopo members:read.');

            return self::FAILURE;
        }

        $linked = Account::whereNotNull('trace_account_id')->pluck('id', 'trace_account_id');
        $this->newLine();
        $this->info('Contas com licença do TraceForms no Trace ID');
        $this->table(['UUID', 'Nome', 'Situação', 'Plano', 'Licença até', 'Ligada à empresa local'], collect($r->json('data') ?? $r->json())->map(fn ($a) => [
            $a['id'], $a['name'] ?? '', $a['status'] ?? '', $a['plan'] ?? '—', $a['license_expires_at'] ?? 'sem prazo', $linked[$a['id']] ?? '—',
        ]));

        $this->line('Para ligar: php artisan traceforms:trace-link {ID local} {UUID do Trace ID}');

        return self::SUCCESS;
    }
}
