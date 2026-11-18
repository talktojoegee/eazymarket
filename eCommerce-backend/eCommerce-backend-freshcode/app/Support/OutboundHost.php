<?php

namespace App\Support;

use InvalidArgumentException;

class OutboundHost
{
    public static function assertPublicHost(string $host, string $label = 'Host'): void
    {
        $host = trim($host);

        if ($host === '') {
            throw new InvalidArgumentException($label . ' is required');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : gethostbynamel($host);

        if (!$ips) {
            throw new InvalidArgumentException($label . ' could not be resolved');
        }

        foreach ($ips as $ip) {
            $public = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );

            if ($public === false) {
                throw new InvalidArgumentException($label . ' must resolve to a public address');
            }
        }
    }

    public static function assertPublicHttpUrl(string $url, string $label = 'URL'): void
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');

        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new InvalidArgumentException($label . ' must be an http or https address');
        }

        self::assertPublicHost($parts['host'] ?? '', $label);
    }
}
