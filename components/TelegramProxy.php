<?php

namespace components;

final class TelegramProxy
{
    public static function buildGuzzleOptions(string $proxy, ?string $proxyType = null): array
    {
        $proxy = trim($proxy);
        $proxyType = $proxyType !== null ? strtolower(trim($proxyType)) : null;

        $proxyUrl = $proxy;
        if ($proxyType && !str_contains($proxyUrl, '://')) {
            if ($proxyType === 'socks5' || $proxyType === 'socks5h') {
                $proxyUrl = $proxyType . '://' . $proxyUrl;
            } elseif ($proxyType === 'http' || $proxyType === 'https') {
                $proxyUrl = $proxyType . '://' . $proxyUrl;
            }
        }

        $curl = [];
        if (defined('CURLOPT_PROXYTYPE')) {
            if ($proxyType === 'socks5' && defined('CURLPROXY_SOCKS5')) {
                $curl[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            } elseif ($proxyType === 'socks5h' && defined('CURLPROXY_SOCKS5_HOSTNAME')) {
                $curl[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
            }
        }

        $options = [
            'proxy' => $proxyUrl,
        ];
        if (!empty($curl)) {
            $options['curl'] = $curl;
        }

        return $options;
    }
}
