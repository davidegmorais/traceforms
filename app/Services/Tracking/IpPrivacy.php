<?php

namespace App\Services\Tracking;

use App\Models\Form;

class IpPrivacy
{
    /** Aplica a política de IP do formulário: não coletar, anonimizar ou guardar. */
    public static function apply(Form $form, ?string $ip): ?string
    {
        if (! $ip || ! $form->setting('collect_ip', true)) {
            return null;
        }

        return $form->setting('anonymize_ip', false) ? self::anonymize($ip) : $ip;
    }

    public static function anonymize(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            return inet_ntop(substr($packed, 0, 6).str_repeat("\0", 10));
        }

        return $ip;
    }
}
