<?php

use App\Models\Account;
use App\Models\FormSession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
 * Apaga sessões de visita sem resposta mais antigas que a retenção configurada em cada conta.
 * Agendado diariamente — rode `php artisan schedule:work` (ou o cron do schedule:run) em produção.
 */
Artisan::command('traceforms:prune', function () {
    $total = 0;
    Account::query()->each(function (Account $account) use (&$total) {
        $days = (int) $account->setting('data_retention_days', 0);
        if ($days <= 0) {
            return;
        }
        $formIds = $account->forms()->withTrashed()->pluck('id');
        $total += FormSession::whereIn('form_id', $formIds)
            ->where('status', '!=', 'submitted')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    });
    // Arquivos enviados por quem desistiu de responder (sem resposta vinculada há mais de 24h).
    $orphans = 0;
    App\Models\Upload::whereNull('submission_id')->where('created_at', '<', now()->subDay())->each(function ($u) use (&$orphans) {
        $u->delete();
        $orphans++;
    });
    $this->info("Sessões removidas: {$total} · arquivos órfãos removidos: {$orphans}");
})->purpose('Aplica a política de retenção de dados de visitas de cada conta');

Schedule::command('traceforms:prune')->dailyAt('03:30');

// Licenças do Trace ID: suspende empresas com licença inativa e reativa as que voltaram.
Schedule::command('traceforms:trace-sync')->hourly()->withoutOverlapping();
