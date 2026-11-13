<?php

namespace App\Services\Tracking;

/**
 * Extrai UTMs, click IDs e informações de referência das URLs da visita.
 */
class TrafficParser
{
    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];

    /** Identificadores de clique das principais plataformas de mídia. */
    public const CLICK_IDS = [
        'gclid' => 'Google Ads', 'gbraid' => 'Google Ads (iOS)', 'wbraid' => 'Google Ads (web→app)',
        'dclid' => 'Google Display', 'fbclid' => 'Meta', 'msclkid' => 'Microsoft Ads',
        'ttclid' => 'TikTok', 'li_fat_id' => 'LinkedIn', 'twclid' => 'X / Twitter',
        'sccid' => 'Snapchat', 'epik' => 'Pinterest', 'yclid' => 'Yandex', 'irclickid' => 'Impact',
        'rdt_cid' => 'Reddit', 'kwai_clid' => 'Kwai',
    ];

    public function query(?string $url): array
    {
        if (! $url) {
            return [];
        }
        $q = parse_url($url, PHP_URL_QUERY);
        if (! $q) {
            return [];
        }
        parse_str($q, $params);

        return array_filter($params, fn ($v) => is_scalar($v));
    }

    public function host(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /**
     * Combina parâmetros da página que incorporou o formulário (embed) com os da URL do formulário.
     * Os da página-mãe têm prioridade, pois são os que o visitante realmente recebeu.
     */
    public function extract(?string $landingUrl, ?string $parentUrl = null): array
    {
        $params = array_merge($this->query($landingUrl), $this->query($parentUrl));
        $params = array_map(fn ($v) => mb_substr((string) $v, 0, 255), $params);

        $utm = [];
        foreach (self::UTM_KEYS as $k) {
            $utm[$k] = isset($params[$k]) && $params[$k] !== '' ? $params[$k] : null;
        }

        // Aliases comuns (ref, source) quando não há utm_source.
        if (! $utm['utm_source']) {
            $utm['utm_source'] = $params['ref'] ?? $params['source'] ?? null;
        }

        $clickType = $clickId = null;
        foreach (array_keys(self::CLICK_IDS) as $k) {
            if (! empty($params[$k])) {
                $clickType = $k;
                $clickId = $params[$k];
                break;
            }
        }

        return $utm + [
            'click_id_type' => $clickType,
            'click_id' => $clickId,
            'query_params' => $params ?: null,
        ];
    }

    public static function clickLabel(?string $type): ?string
    {
        return $type ? (self::CLICK_IDS[$type] ?? $type) : null;
    }
}
