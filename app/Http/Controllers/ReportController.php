<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportService;
use App\Services\Tracking\TrafficParser;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function show(Request $request, Form $form, string $tab = 'overview')
    {
        $this->authorizeForm($form, 'reports.view');
        $form->load('fields');

        $tz = $this->account()->timezone;
        $probe = new ReportService([$form->id], new ReportFilters($tz));
        $filters = ReportFilters::fromRequest($request, $tz, $probe->earliest());
        $r = ReportService::forForm($form, $filters);

        $data = match ($tab) {
            'acquisition' => [
                'channels' => $r->channels(),
                'sourceMedium' => $r->sourceMedium(),
                'sources' => $r->breakdown('utm_source', 15),
                'mediums' => $r->breakdown('utm_medium', 10),
                'campaigns' => $r->breakdown('utm_campaign', 20),
                'contents' => $r->breakdown('utm_content', 15),
                'terms' => $r->breakdown('utm_term', 15),
                'referrers' => $r->breakdown('referrer_domain', 15),
                'landings' => $r->landingPages(12),
                'clickIds' => $r->breakdown('click_id_type', 10, fn ($v) => $v ? TrafficParser::clickLabel($v)." ($v)" : 'Sem click ID'),
                'embed' => $r->breakdown('embed_mode', 5, fn ($v) => ['direct' => 'Link direto', 'iframe' => 'Incorporado (iframe)', 'link' => 'Link rastreado'][$v] ?? $v),
                'links' => $r->breakdown('tracking_link_id', 15, fn ($v) => $v ? ($form->trackingLinks()->find($v)?->name ?? 'Link #'.$v) : 'Sem link rastreado'),
                'attribution' => $r->attribution(),
            ],
            'audience' => [
                'devices' => $r->breakdown('device_type', 8, fn ($v) => self::deviceLabel($v)),
                'os' => $r->breakdown('os_name', 10),
                'browsers' => $r->breakdown('browser_name', 10),
                'brands' => $r->breakdown('device_brand', 12),
                'models' => $r->breakdown('device_model', 12),
                'screens' => $r->breakdown(self::screenExpr(), 12),
                'countries' => $r->breakdown('country', 15, fn ($v) => self::countryLabel($v)),
                'regions' => $r->breakdown('region', 12),
                'cities' => $r->breakdown('city', 12),
                'languages' => $r->breakdown('language', 10),
                'timezones' => $r->breakdown('timezone', 10),
                'connections' => $r->breakdown('connection_type', 6),
                'returning' => $r->breakdown('is_returning', 2, fn ($v) => $v ? 'Recorrente' : 'Novo visitante'),
                'darkmode' => $r->breakdown('dark_mode', 3, fn ($v) => $v === null ? 'Desconhecido' : ($v ? 'Modo escuro' : 'Modo claro')),
            ],
            'behavior' => [
                'funnel' => $r->fieldFunnel($form),
                'heatmap' => $r->heatmap('submits'),
                'heatmapViews' => $r->heatmap('views'),
                'engagement' => $r->engagement(),
            ],
            'questions' => [
                'summary' => $r->questionSummaries($form),
            ],
            default => [
                'kpis' => $r->kpisWithDelta(),
                'series' => $r->timeseries(),
                'channels' => $r->channels(),
                'devices' => $r->breakdown('device_type', 6, fn ($v) => self::deviceLabel($v)),
                'campaigns' => $r->breakdown('utm_campaign', 6),
                'countries' => $r->breakdown('country', 6, fn ($v) => self::countryLabel($v)),
            ],
        };

        return view('reports.'.$tab, $data + [
            'form' => $form,
            'tab' => $tab,
            'filters' => $filters,
            'segmentOptions' => [
                'channel' => $probe->segmentValues('channel'),
                'utm_source' => $probe->segmentValues('utm_source'),
                'utm_medium' => $probe->segmentValues('utm_medium'),
                'utm_campaign' => $probe->segmentValues('utm_campaign'),
                'device_type' => $probe->segmentValues('device_type'),
                'country' => $probe->segmentValues('country'),
            ],
        ]);
    }

    public static function screenExpr(): string
    {
        return match (\App\Support\Sql::driver()) {
            'mysql', 'mariadb' => "CONCAT(form_sessions.screen_width, '×', form_sessions.screen_height)",
            default => "(form_sessions.screen_width || '×' || form_sessions.screen_height)",
        };
    }

    public static function deviceLabel(?string $v): string
    {
        return [
            'desktop' => 'Desktop', 'smartphone' => 'Smartphone', 'tablet' => 'Tablet', 'phablet' => 'Phablet',
            'tv' => 'TV', 'console' => 'Console', 'car browser' => 'Carro', 'smart display' => 'Smart display',
            'feature phone' => 'Celular básico', 'wearable' => 'Wearable', 'portable media player' => 'Player portátil',
        ][$v] ?? ($v ?: 'Desconhecido');
    }

    public static function countryLabel(?string $code): string
    {
        if (! $code) {
            return 'Desconhecido';
        }
        $name = class_exists(\Locale::class) ? \Locale::getDisplayRegion('-'.$code, 'pt_BR') : $code;
        $flag = mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);

        return $flag.' '.($name ?: $code);
    }
}
