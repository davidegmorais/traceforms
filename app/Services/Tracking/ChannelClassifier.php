<?php

namespace App\Services\Tracking;

/**
 * Agrupamento de canais inspirado no "Default Channel Grouping" do GA4.
 */
class ChannelClassifier
{
    public const LABELS = [
        'paid_search' => 'Pesquisa paga',
        'organic_search' => 'Pesquisa orgânica',
        'paid_social' => 'Social pago',
        'organic_social' => 'Social orgânico',
        'email' => 'E-mail',
        'display' => 'Display',
        'affiliate' => 'Afiliados',
        'referral' => 'Referência',
        'sms' => 'SMS / Mensagens',
        'qr' => 'QR Code / Offline',
        'direct' => 'Direto',
        'other' => 'Outros',
    ];

    public const COLORS = [
        'paid_search' => '#1d5fa8', 'organic_search' => '#0e7c66', 'paid_social' => '#b4472b',
        'organic_social' => '#e0a526', 'email' => '#6d3fc0', 'display' => '#c1688d',
        'affiliate' => '#2c4a43', 'referral' => '#5aa889', 'sms' => '#8a6d1f',
        'qr' => '#4f6fa8', 'direct' => '#7a8a85', 'other' => '#b7c4bf',
    ];

    private const SEARCH = ['google.', 'bing.com', 'yahoo.', 'duckduckgo.com', 'yandex.', 'baidu.com', 'ecosia.org', 'search.brave.com', 'ask.com', 'naver.com', 'perplexity.ai', 'chatgpt.com', 'you.com'];

    private const SOCIAL = ['facebook.com', 'fb.com', 'fb.me', 'm.facebook.com', 'l.facebook.com', 'lm.facebook.com', 'instagram.com', 'l.instagram.com', 't.co', 'twitter.com', 'x.com', 'linkedin.com', 'lnkd.in', 'youtube.com', 'youtu.be', 'tiktok.com', 'pinterest.', 'reddit.com', 'threads.net', 'whatsapp.com', 'wa.me', 'web.whatsapp.com', 't.me', 'telegram.org', 'snapchat.com', 'kwai.com', 'discord.com', 'bsky.app', 'quora.com', 'tumblr.com'];

    private const EMAIL = ['mail.google.com', 'outlook.live.com', 'outlook.office.com', 'mail.yahoo.com', 'webmail.', 'mail.', 'email.', 'mailchimp', 'rdstation', 'sendgrid', 'list-manage.com', 'hubspot'];

    private const SOCIAL_SOURCES = ['facebook', 'fb', 'instagram', 'ig', 'meta', 'twitter', 'x', 'linkedin', 'youtube', 'tiktok', 'pinterest', 'reddit', 'whatsapp', 'telegram', 'snapchat', 'kwai', 'threads'];

    private const SEARCH_SOURCES = ['google', 'bing', 'yahoo', 'duckduckgo', 'yandex', 'baidu', 'ecosia'];

    public function classify(?string $referrerDomain, ?string $source, ?string $medium, ?string $clickIdType, ?string $selfHost = null): string
    {
        $source = strtolower(trim((string) $source));
        $medium = strtolower(trim((string) $medium));
        $ref = strtolower((string) $referrerDomain);

        if ($ref && $selfHost && $ref === strtolower($selfHost)) {
            $ref = '';
        }

        $isPaidMedium = (bool) preg_match('/^(.*cp.*|ppc|retargeting|paid.*|ads?)$/', $medium);
        $sourceIsSocial = in_array($source, self::SOCIAL_SOURCES, true) || $this->matches($ref, self::SOCIAL);
        $sourceIsSearch = in_array($source, self::SEARCH_SOURCES, true) || $this->matches($ref, self::SEARCH);

        // Click IDs revelam mídia paga mesmo sem UTM.
        if (in_array($clickIdType, ['gclid', 'gbraid', 'wbraid', 'msclkid'], true) && ! $medium) {
            return 'paid_search';
        }
        // fbclid aparece também em links orgânicos do Facebook, por isso não entra aqui.
        if (in_array($clickIdType, ['ttclid', 'li_fat_id', 'twclid', 'sccid', 'epik', 'rdt_cid', 'kwai_clid'], true) && ! $medium) {
            return 'paid_social';
        }

        if ($medium) {
            if (in_array($medium, ['email', 'e-mail', 'e_mail', 'newsletter'], true)) {
                return 'email';
            }
            if (in_array($medium, ['sms', 'whatsapp', 'push', 'mobile-notification'], true)) {
                return 'sms';
            }
            if (in_array($medium, ['affiliate', 'afiliado', 'partner'], true)) {
                return 'affiliate';
            }
            if (in_array($medium, ['display', 'banner', 'cpm', 'expandable', 'interstitial'], true)) {
                return 'display';
            }
            if (in_array($medium, ['qr', 'qrcode', 'offline', 'print'], true)) {
                return 'qr';
            }
            if ($isPaidMedium) {
                return $sourceIsSocial ? 'paid_social' : 'paid_search';
            }
            if (in_array($medium, ['social', 'social-network', 'social-media', 'sm', 'social network', 'organic_social'], true) || $sourceIsSocial) {
                return 'organic_social';
            }
            if ($medium === 'organic') {
                return 'organic_search';
            }
            if ($medium === 'referral') {
                return 'referral';
            }
        }

        if ($source && ! $ref) {
            if ($sourceIsSocial) {
                return 'organic_social';
            }
            if ($sourceIsSearch) {
                return 'organic_search';
            }
            if (str_contains($source, 'mail') || str_contains($source, 'newsletter')) {
                return 'email';
            }
        }

        if ($ref) {
            if ($this->matches($ref, self::SEARCH)) {
                return 'organic_search';
            }
            if ($this->matches($ref, self::SOCIAL)) {
                return 'organic_social';
            }
            if ($this->matches($ref, self::EMAIL)) {
                return 'email';
            }

            return 'referral';
        }

        return ($source || $medium) ? 'other' : 'direct';
    }

    private function matches(string $host, array $needles): bool
    {
        if ($host === '') {
            return false;
        }
        foreach ($needles as $n) {
            if (str_ends_with($n, '.') ? str_contains($host, $n) : ($host === $n || str_ends_with($host, '.'.$n) || str_starts_with($host, $n))) {
                return true;
            }
        }

        return false;
    }

    public static function label(?string $channel): string
    {
        return self::LABELS[$channel] ?? 'Desconhecido';
    }
}
