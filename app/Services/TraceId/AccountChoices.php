<?php

namespace App\Services\TraceId;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Empresas para o seletor de empresa: as que o Trace ID diz que a pessoa pode abrir no TraceForms,
 * inclusive as em que ela ainda não entrou. Se o Trace ID não responder, ficam as já conhecidas aqui.
 */
class AccountChoices
{
    public function __construct(private TraceIdClient $trace) {}

    /** @return Collection<int, object{uuid: string, name: string, color: string, local: ?Account}> */
    public function for(User $user): Collection
    {
        $remote = $user->trace_id && $this->trace->configured()
            ? Cache::remember("traceforms.choices.{$user->id}", 300, function () use ($user) {
                try {
                    return $this->trace->userAccounts($user->trace_id);
                } catch (\Throwable $e) {
                    Log::warning('Trace ID: empresas da pessoa indisponíveis', ['erro' => $e->getMessage()]);

                    return null;
                }
            })
            : null;

        $local = $user->accounts()->whereNotNull('accounts.trace_account_id')->get()->keyBy('trace_account_id');

        $list = $remote === null
            ? $local->map(fn (Account $a) => ['id' => $a->trace_account_id, 'name' => $a->name])
            : collect($remote)->where('status', 'active');

        return $list->map(function ($r) use ($local) {
            $acc = $local->get($r['id']) ?? Account::where('trace_account_id', $r['id'])->first();

            return (object) [
                'uuid' => $r['id'],
                'name' => $acc?->name ?? $r['name'],
                'color' => $acc?->brand_color ?? '#0e7c66',
                'local' => $acc,
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function forget(User $user): void
    {
        Cache::forget("traceforms.choices.{$user->id}");
    }
}
