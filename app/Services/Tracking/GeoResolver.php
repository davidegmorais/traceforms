<?php

namespace App\Services\Tracking;

use Illuminate\Http\Request;

/**
 * Resolve país/região/cidade a partir dos cabeçalhos injetados por CDNs
 * (Cloudflare, CloudFront, Vercel, Fastly, Google LB, Akamai).
 *
 * Para geolocalização sem CDN, estenda resolveByIp() com MaxMind GeoLite2
 * (geoip2/geoip2) ou um serviço externo.
 */
class GeoResolver
{
    public function resolve(Request $request, ?string $timezone = null, ?string $language = null): array
    {
        $h = fn (string ...$names) => collect($names)->map(fn ($n) => $request->header($n))->first(fn ($v) => $v !== null && $v !== '' && $v !== 'XX' && $v !== 'T1');

        $country = $h('CF-IPCountry', 'CloudFront-Viewer-Country', 'X-Vercel-IP-Country', 'Fastly-Client-Country', 'X-Country-Code', 'X-AppEngine-Country', 'X-Client-Geo-Country');
        $region = $h('CF-Region', 'CloudFront-Viewer-Country-Region-Name', 'X-Vercel-IP-Country-Region', 'X-AppEngine-Region', 'X-Client-Geo-Region');
        $city = $h('CF-IPCity', 'CloudFront-Viewer-City', 'X-Vercel-IP-City', 'X-AppEngine-City', 'X-Client-Geo-City');

        $utf8 = fn (?string $v) => $v === null ? null : mb_substr(mb_check_encoding($v = urldecode($v), 'UTF-8') ? $v : mb_convert_encoding($v, 'UTF-8', 'ISO-8859-1'), 0, 120);
        $geo = [
            'country' => $country ? strtoupper(substr($country, 0, 2)) : null,
            'region' => $utf8($region),
            'city' => $utf8($city),
        ];

        if (! $geo['country']) {
            $geo = array_merge($geo, $this->resolveByIp((string) $request->ip()));
        }

        // Último recurso: estimar o país pelo fuso horário do navegador.
        if (! $geo['country'] && $timezone) {
            $geo['country'] = self::TIMEZONE_COUNTRY[$timezone] ?? null;
        }
        if (! $geo['country'] && $language && preg_match('/^[a-z]{2}-([A-Z]{2})$/', $language, $m)) {
            $geo['country'] = $m[1];
        }

        return $geo;
    }

    protected function resolveByIp(string $ip): array
    {
        return [];
    }

    /** Fusos mais comuns → país (fallback quando não há CDN). */
    public const TIMEZONE_COUNTRY = [
        'America/Sao_Paulo' => 'BR', 'America/Fortaleza' => 'BR', 'America/Recife' => 'BR', 'America/Bahia' => 'BR',
        'America/Belem' => 'BR', 'America/Manaus' => 'BR', 'America/Cuiaba' => 'BR', 'America/Campo_Grande' => 'BR',
        'America/Porto_Velho' => 'BR', 'America/Boa_Vista' => 'BR', 'America/Rio_Branco' => 'BR', 'America/Maceio' => 'BR',
        'America/Araguaina' => 'BR', 'America/Noronha' => 'BR', 'America/Santarem' => 'BR',
        'Europe/Lisbon' => 'PT', 'Atlantic/Azores' => 'PT', 'Atlantic/Madeira' => 'PT',
        'America/Argentina/Buenos_Aires' => 'AR', 'America/Santiago' => 'CL', 'America/Bogota' => 'CO',
        'America/Lima' => 'PE', 'America/Montevideo' => 'UY', 'America/Asuncion' => 'PY', 'America/Mexico_City' => 'MX',
        'America/New_York' => 'US', 'America/Chicago' => 'US', 'America/Denver' => 'US', 'America/Los_Angeles' => 'US',
        'America/Phoenix' => 'US', 'America/Toronto' => 'CA', 'America/Vancouver' => 'CA',
        'Europe/London' => 'GB', 'Europe/Madrid' => 'ES', 'Europe/Paris' => 'FR', 'Europe/Berlin' => 'DE',
        'Europe/Rome' => 'IT', 'Europe/Amsterdam' => 'NL', 'Europe/Dublin' => 'IE', 'Europe/Zurich' => 'CH',
        'Africa/Luanda' => 'AO', 'Africa/Maputo' => 'MZ', 'Asia/Tokyo' => 'JP', 'Australia/Sydney' => 'AU',
    ];
}
