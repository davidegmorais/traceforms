<?php

namespace App\Services\Tracking;

use App\Models\Form;
use App\Models\FormSession;
use App\Models\TrackingLink;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Registra e atualiza a sessão de visita a partir dos sinais enviados pelo tracker.
 */
class SessionRecorder
{
    public function __construct(
        private DeviceParser $devices,
        private GeoResolver $geo,
        private TrafficParser $traffic,
        private ChannelClassifier $channels,
    ) {}

    public function start(Form $form, Request $request, array $p): FormSession
    {
        $c = (array) ($p['client'] ?? []);
        $landing = $this->url($p['url'] ?? null);
        $parent = $this->url($p['parent_url'] ?? null);
        $referrer = $this->url($p['referrer'] ?? null);

        $traffic = $this->traffic->extract($landing, $parent);

        // Link rastreado (/l/{code}) completa UTMs ausentes.
        $link = null;
        if (! empty($p['link'])) {
            $link = TrackingLink::where('code', $p['link'])->where('form_id', $form->id)->first();
            if ($link) {
                foreach ($link->utmParams() as $k => $v) {
                    if (array_key_exists($k, $traffic) && empty($traffic[$k])) {
                        $traffic[$k] = $v;
                    }
                }
            }
        }

        $refDomain = $this->traffic->host($referrer);
        $selfHost = $this->traffic->host($parent ?: $landing);
        // Quando incorporado, o referrer do iframe é a própria página-mãe: usa o referrer dela.
        if (($p['embed_mode'] ?? 'direct') === 'iframe' && ! empty($p['parent_referrer'])) {
            $referrer = $this->url($p['parent_referrer']);
            $refDomain = $this->traffic->host($referrer);
        }

        $device = $this->devices->parse($request);
        $timezone = $this->str($c['timezone'] ?? null, 60);
        $language = $this->str($c['language'] ?? null, 20);
        $geo = $this->geo->resolve($request, $timezone, $language);

        $firstTouch = is_array($p['first_touch'] ?? null) ? Arr::only($p['first_touch'], [
            'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'referrer', 'landing', 'ts', 'click_id_type',
        ]) : null;

        // Campos ocultos: parâmetros da URL com o mesmo nome configurado no campo.
        $hidden = [];
        foreach ($form->fields->where('type', 'hidden') as $field) {
            $param = $field->setting('param') ?: $field->key;
            $hidden[$field->key] = $traffic['query_params'][$param] ?? $field->setting('default');
        }

        $visitorId = $this->str($p['visitor_id'] ?? null, 64) ?: (string) Str::uuid();
        $previous = FormSession::where('form_id', $form->id)->where('visitor_id', $visitorId)->count();

        $session = FormSession::create([
            'uuid' => (string) Str::uuid(),
            'form_id' => $form->id,
            'tracking_link_id' => $link?->id,
            'visitor_id' => $visitorId,
            'is_returning' => $previous > 0 || ! empty($p['returning']),
            'visit_number' => $previous + 1,
            'status' => 'viewed',
            'ip' => IpPrivacy::apply($form, $request->ip()),
            ...$geo,
            ...$device,
            'screen_width' => $this->int($c['screen_w'] ?? null),
            'screen_height' => $this->int($c['screen_h'] ?? null),
            'viewport_width' => $this->int($c['viewport_w'] ?? null),
            'viewport_height' => $this->int($c['viewport_h'] ?? null),
            'pixel_ratio' => isset($c['dpr']) ? min(9.99, (float) $c['dpr']) : null,
            'touch' => isset($c['touch']) ? (bool) $c['touch'] : null,
            'language' => $language,
            'languages' => $this->str(is_array($c['languages'] ?? null) ? implode(',', $c['languages']) : null, 250),
            'timezone' => $timezone,
            'timezone_offset' => isset($c['tz_offset']) ? (int) $c['tz_offset'] : null,
            'connection_type' => $this->str($c['connection'] ?? null, 20),
            'connection_downlink' => isset($c['downlink']) ? min(9999, (float) $c['downlink']) : null,
            'dark_mode' => isset($c['dark']) ? (bool) $c['dark'] : null,
            'cookies_enabled' => isset($c['cookies']) ? (bool) $c['cookies'] : null,
            'do_not_track' => isset($c['dnt']) ? (bool) $c['dnt'] : null,
            'hardware_concurrency' => $this->int($c['cores'] ?? null),
            'device_memory' => isset($c['memory']) ? (float) $c['memory'] : null,
            'is_bot' => ($device['is_bot'] ?? false) || ! empty($c['webdriver']),
            'referrer' => $referrer,
            'referrer_domain' => $refDomain === $selfHost ? null : $refDomain,
            'channel' => $this->channels->classify($refDomain, $traffic['utm_source'], $traffic['utm_medium'], $traffic['click_id_type'], $selfHost),
            'landing_url' => $landing,
            'parent_url' => $parent,
            'embed_mode' => in_array($p['embed_mode'] ?? null, ['iframe', 'link', 'direct'], true) ? $p['embed_mode'] : ($link ? 'link' : 'direct'),
            ...Arr::only($traffic, ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id', 'click_id_type', 'click_id', 'query_params']),
            'first_touch' => $firstTouch,
            'hidden_fields' => $hidden ?: null,
            'last_activity_at' => now(),
        ]);

        return $session;
    }

    /** Atualiza engajamento: início, passo atual, tempo por campo e respostas parciais. */
    public function progress(FormSession $session, array $p): void
    {
        if ($session->status === 'submitted') {
            return;
        }
        $form = $session->form;
        $update = ['last_activity_at' => now()];

        if (($p['type'] ?? null) === 'start' || ! empty($p['started'])) {
            if (! $session->started_at) {
                $update['started_at'] = now();
                $update['status'] = 'started';
                $update['time_to_start_ms'] = $this->int($p['t'] ?? null, 3_600_000);
            }
        }

        if (isset($p['step'])) {
            $update['max_step'] = max($session->max_step, min(500, (int) $p['step']));
        }
        if (! empty($p['field']) && $form->fields->firstWhere('key', $p['field'])) {
            $update['last_field_key'] = $p['field'];
        }
        foreach (['duration_ms' => 86_400_000, 'active_ms' => 86_400_000] as $k => $max) {
            if (isset($p[$k])) {
                $update[$k] = $this->int($p[$k], $max);
            }
        }
        foreach (['tab_switches', 'paste_count'] as $k) {
            if (isset($p[$k])) {
                $update[$k] = min(65000, max(0, (int) $p[$k]));
            }
        }
        if (isset($p['stats']) && is_array($p['stats'])) {
            $update['field_stats'] = $this->sanitizeStats($form, $p['stats']);
        }
        if ($form->setting('save_partials', true) && isset($p['partial']) && is_array($p['partial'])) {
            $update['partial_data'] = $this->sanitizeAnswers($form, $p['partial']);
        }

        $session->update($update);
    }

    public function sanitizeStats($form, array $stats): array
    {
        $keys = $form->fields->pluck('key')->all();
        $out = [];
        foreach ($stats as $key => $s) {
            if (! in_array($key, $keys, true) || ! is_array($s)) {
                continue;
            }
            $out[$key] = [
                'ms' => $this->int($s['ms'] ?? 0, 3_600_000) ?? 0,
                'focus' => min(9999, (int) ($s['focus'] ?? 0)),
                'changes' => min(9999, (int) ($s['changes'] ?? 0)),
                'errors' => min(9999, (int) ($s['errors'] ?? 0)),
            ];
        }

        return $out;
    }

    public function sanitizeAnswers($form, array $data): array
    {
        $out = [];
        foreach ($form->fields as $field) {
            if (! array_key_exists($field->key, $data) || in_array($field->type, ['statement', 'hidden', 'file', 'signature'], true)) {
                continue;
            }
            $v = $data[$field->key];
            $out[$field->key] = is_array($v)
                ? array_slice(array_map(fn ($x) => mb_substr(is_scalar($x) ? (string) $x : '', 0, 500), $v), 0, 50, true)
                : mb_substr((string) $v, 0, 5000);
        }

        return $out;
    }

    private function url(mixed $v): ?string
    {
        if (! is_string($v) || $v === '' || ! preg_match('#^https?://#i', $v)) {
            return null;
        }

        return mb_substr($v, 0, 2000);
    }

    private function str(mixed $v, int $max): ?string
    {
        return is_scalar($v) && $v !== '' ? mb_substr((string) $v, 0, $max) : null;
    }

    private function int(mixed $v, int $max = 65535): ?int
    {
        return is_numeric($v) ? (int) max(0, min($max, (int) $v)) : null;
    }
}
