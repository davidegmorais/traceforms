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

Artisan::command('traceforms:make-admin {email} {--remove : Remove o acesso de administrador}', function (string $email) {
    $user = App\Models\User::where('email', strtolower($email))->first();
    if (! $user) {
        $this->error("Nenhum usuário com o e-mail {$email}. Crie a conta em /register primeiro.");

        return 1;
    }
    $user->forceFill(['is_super_admin' => ! $this->option('remove')])->save();
    $this->info($this->option('remove') ? "{$user->name} não é mais administrador da plataforma." : "{$user->name} agora é administrador da plataforma. Acesse /admin.");
})->purpose('Dá (ou remove) acesso ao painel de administração da plataforma');

Schedule::command('traceforms:prune')->dailyAt('03:30');
