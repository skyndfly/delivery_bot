<?php

namespace components;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Telegram\Bot\HttpClients\HttpClientInterface;

class HttpClient implements HttpClientInterface
{
    private Client $client;
    private int $timeout;
    private int $connectTimeout;
    private string $context;

    public function __construct(
        array $guzzleConfig = [],
        int $timeout = 30,
        int $connectTimeout = 10,
        string $context = 'telegram'
    ) {
        $this->timeout = max(1, $timeout);
        $this->connectTimeout = max(1, $connectTimeout);
        $this->context = $context;

        // Guzzle timeout must be greater than Telegram getUpdates long-poll timeout.
        // Short sendMessage/sendPhoto requests can work through the same proxy while
        // getUpdates fails if this transport timeout is too small.
        log_dump(
            '[INFO] HttpClient init: context=' . $this->context
            . ', timeout=' . $this->timeout
            . ', connect_timeout=' . $this->connectTimeout,
            'HttpClient'
        );

        $baseConfig = [
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ];

        if (isset($guzzleConfig['curl']) && is_array($guzzleConfig['curl'])) {
            $baseConfig['curl'] = array_replace($baseConfig['curl'], $guzzleConfig['curl']);
            unset($guzzleConfig['curl']);
        }

        $this->client = new Client(array_merge($baseConfig, $guzzleConfig));
    }

    /**
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param array $options
     * @param bool $isAsyncRequest
     * @return ResponseInterface|null
     */
    public function send(string $url, string $method, array $headers = [], array $options = [], bool $isAsyncRequest = false): ?ResponseInterface
    {
        $guzzleOptions = $options;
        if (!empty($headers)) {
            $guzzleOptions['headers'] = $headers;
        }

        try {
            if ($isAsyncRequest) {
                // асинхронные запросы не обязательны для твоего кейса
                return null;
            }

            return $this->client->request($method, $url, $guzzleOptions);
        } catch (GuzzleException $e) {
            log_dump(
                '[ERROR] HttpClient send error: context=' . $this->context
                . ', timeout=' . $this->timeout
                . ', connect_timeout=' . $this->connectTimeout
                . ', error=' . self::sanitizeErrorMessage($e->getMessage()),
                'HttpClient'
            );

            // создаём фиктивный ответ, чтобы SDK не падал
            return new Response(500, [], json_encode([
                'ok' => false,
                'error_code' => 500,
                'description' => self::sanitizeErrorMessage($e->getMessage())
            ]));
        }
    }

    public function getTimeOut(): int
    {
        return $this->timeout;
    }

    public function setTimeOut(int $timeOut): static
    {
        $this->timeout = $timeOut;
        return $this;
    }

    public function getConnectTimeOut(): int
    {
        return $this->connectTimeout;
    }

    public function setConnectTimeOut(int $connectTimeOut): static
    {
        $this->connectTimeout = $connectTimeOut;
        return $this;
    }

    private static function sanitizeErrorMessage(string $message): string
    {
        return preg_replace(
            '#https://api\.telegram\.org/bot[^/\s]+/#',
            'https://api.telegram.org/bot<redacted>/',
            $message
        ) ?? $message;
    }
}
