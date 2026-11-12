<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Form;
use App\Models\FormSession;
use App\Models\Submission;
use App\Models\Upload;
use App\Models\User;
use App\Support\Sql;

class AdminDashboardController extends Controller
{
    public function __invoke()
    {
        $from = now()->subDays(29)->startOfDay();
        $date = fn (string $col) => Sql::date($col, Sql::offsetMinutes('America/Sao_Paulo'));

        $series = [];
        for ($d = $from->copy(); $d->lte(now()); $d->addDay()) {
            $series[$d->format('Y-m-d')] = ['label' => $d->format('d/m'), 'users' => 0, 'accounts' => 0, 'submissions' => 0];
        }
        foreach ([
            'users' => User::query(), 'accounts' => Account::query(), 'submissions' => Submission::query(),
        ] as $key => $q) {
            $col = $key === 'submissions' ? 'submitted_at' : 'created_at';
            $q->where($col, '>=', $from)->selectRaw($date($col).' as d, count(*) as c')->groupByRaw($date($col))
                ->toBase()->get()->each(function ($r) use (&$series, $key) {
                    if (isset($series[$r->d])) {
                        $series[$r->d][$key] = (int) $r->c;
                    }
                });
        }

        $topAccounts = Account::query()
            ->withCount(['forms', 'memberships'])
            ->addSelect(['responses_30d' => Submission::selectRaw('count(*)')
                ->join('forms', 'forms.id', '=', 'submissions.form_id')
                ->whereColumn('forms.account_id', 'accounts.id')
                ->where('submissions.submitted_at', '>=', $from)])
            ->orderByDesc('responses_30d')->limit(8)->get();

        return view('admin.dashboard', [
            'totals' => [
                'accounts' => Account::count(),
                'suspended' => Account::whereNotNull('suspended_at')->count(),
                'users' => User::count(),
                'forms' => Form::count(),
                'published' => Form::where('status', 'published')->count(),
                'responses' => Submission::where('is_spam', false)->count(),
                'responses_30d' => Submission::where('is_spam', false)->where('submitted_at', '>=', $from)->count(),
                'sessions_30d' => FormSession::where('created_at', '>=', $from)->count(),
                'storage' => (int) Upload::sum('size'),
            ],
            'series' => array_values($series),
            'topAccounts' => $topAccounts,
            'recentUsers' => User::latest()->limit(8)->get(),
        ]);
    }
}
