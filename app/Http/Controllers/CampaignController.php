<?php

namespace App\Http\Controllers;

use App\Models\FormSession;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

/** Painel de campanhas: UTMs consolidadas de todos os formulários da conta. */
class CampaignController extends Controller
{
    public function __invoke(Request $request)
    {
        $forms = $this->visibleForms()->orderBy('title')->get(['id', 'title']);
        $ids = $forms->pluck('id')->all() ?: [0];
        if ($request->filled('form') && in_array((int) $request->query('form'), $ids, true)) {
            $ids = [(int) $request->query('form')];
        }

        $tz = $this->account()->timezone;
        $filters = ReportFilters::fromRequest($request, $tz);
        $r = new ReportService($ids, $filters);

        // Matriz campanha × formulário
        $matrix = FormSession::whereIn('form_id', $ids)->where('is_bot', false)
            ->whereBetween('created_at', [$filters->from->utc(), $filters->to->utc()])
            ->whereNotNull('utm_campaign')
            ->when($filters->segments, fn ($q) => $filters->apply($q))
            ->selectRaw("utm_campaign, form_id, count(*) as views, sum(case when status = 'submitted' then 1 else 0 end) as submits")
            ->groupBy('utm_campaign', 'form_id')->get();

        return view('campaigns.index', [
            'filters' => $filters,
            'forms' => $forms,
            'kpis' => $r->kpisWithDelta(),
            'channels' => $r->channels(),
            'campaigns' => $r->breakdown('utm_campaign', 30),
            'sourceMedium' => $r->sourceMedium(20),
            'sources' => $r->breakdown('utm_source', 12),
            'contents' => $r->breakdown('utm_content', 12),
            'terms' => $r->breakdown('utm_term', 12),
            'attribution' => $r->attribution(),
            'matrix' => $matrix,
            'segmentOptions' => [
                'channel' => $r->segmentValues('channel'),
                'utm_source' => $r->segmentValues('utm_source'),
                'utm_medium' => $r->segmentValues('utm_medium'),
                'utm_campaign' => $r->segmentValues('utm_campaign'),
                'device_type' => $r->segmentValues('device_type'),
                'country' => $r->segmentValues('country'),
            ],
        ]);
    }
}
