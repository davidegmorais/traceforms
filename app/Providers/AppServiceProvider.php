<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\Form;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        // Cada habilidade da matriz de papéis vira um Gate.
        // Quando um formulário é informado, também verifica restrição por formulário.
        foreach (array_keys(Role::ABILITIES) as $ability) {
            Gate::define($ability, function (User $user, ?Form $form = null) use ($ability) {
                $membership = app()->bound('membership') ? app('membership') : null;
                if (! $membership || $membership->user_id !== $user->id) {
                    return false;
                }
                if (! $membership->can($ability)) {
                    return false;
                }

                return $form === null || $membership->canAccessForm($form);
            });
        }

        // Proteção dos endpoints públicos de coleta.
        RateLimiter::for('collect', fn (Request $r) => Limit::perMinute(120)->by($r->ip()));
        RateLimiter::for('submit', fn (Request $r) => Limit::perMinute(12)->by($r->ip()));
        RateLimiter::for('upload', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(30)->by($r->ip()));
    }
}
