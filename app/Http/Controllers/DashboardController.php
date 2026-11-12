<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $forms = $this->visibleForms()->get(['id', 'title', 'status', 'public_id', 'updated_at']);
        $ids = $forms->pluck('id')->all();
        $tz = $this->account()->timezone;

        $filters = ReportFilters::fromRequest($request, $tz);
        $r = new ReportService($ids ?: [0], $filters);

        // Desempenho por formulário no período.
        $perForm = \App\Models\FormSession::whereIn('form_id', $ids ?: [0])->where('is_bot', false)
            ->whereBetween('created_at', [$filters->from->utc(), $filters->to->utc()])
            ->selectRaw("form_id, count(*) as views, sum(case when status = 'submitted' then 1 else 0 end) as submits, sum(case when started_at is not null then 1 else 0 end) as starts")
            ->groupBy('form_id')->get()->keyBy('form_id');

        $topForms = $forms->map(function ($f) use ($perForm, $r) {
            $p = $perForm->get($f->id);
            $f->views = (int) ($p->views ?? 0);
            $f->submits = (int) ($p->submits ?? 0);
            $f->conversion = $r->pct($f->submits, $f->views);

            return $f;
        })->sortByDesc('submits')->take(6)->values();

        $canSeeResponses = $this->membership()->can('responses.view');

        return view('dashboard', [
            'filters' => $filters,
            'kpis' => $r->kpisWithDelta(),
            'series' => $r->timeseries(),
            'channels' => $r->channels(),
            'campaigns' => $r->breakdown('utm_campaign', 6),
            'devices' => $r->breakdown('device_type', 5, fn ($v) => ReportController::deviceLabel($v)),
            'topForms' => $topForms,
            'formCount' => $forms->count(),
            'publishedCount' => $forms->where('status', 'published')->count(),
            'recent' => $canSeeResponses
                ? Submission::with(['form.fields', 'session'])->whereIn('form_id', $ids ?: [0])->where('is_spam', false)->latest('submitted_at')->limit(8)->get()
                : collect(),
        ]);
    }
}
