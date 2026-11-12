<?php

namespace App\Services\Tracking;

use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;

/**
 * Identifica dispositivo, sistema, navegador e bots a partir do User-Agent
 * e dos Client Hints (Sec-CH-UA-*), usando matomo/device-detector.
 */
class DeviceParser
{
    public function parse(Request $request): array
    {
        $ua = (string) $request->userAgent();
        $dd = new DeviceDetector($ua, ClientHints::factory($request->server->all()));
        $dd->discardBotInformation();
        $dd->parse();

        if ($dd->isBot()) {
            return ['is_bot' => true, 'device_type' => 'bot', 'user_agent' => $ua];
        }

        $os = $dd->getOs() ?: [];
        $client = $dd->getClient() ?: [];

        return [
            'user_agent' => $ua,
            'is_bot' => false,
            'device_type' => $dd->getDeviceName() ?: 'desktop',
            'device_brand' => $dd->getBrandName() ?: null,
            'device_model' => $dd->getModel() ?: null,
            'os_name' => $this->clean($os['name'] ?? null),
            'os_version' => $this->clean($os['version'] ?? null),
            'browser_name' => $this->clean($client['name'] ?? null),
            'browser_version' => $this->clean($client['version'] ?? null),
            'browser_engine' => $this->clean($client['engine'] ?? null),
        ];
    }

    private function clean(?string $v): ?string
    {
        return ($v === null || $v === '' || $v === 'UNK') ? null : $v;
    }
}
