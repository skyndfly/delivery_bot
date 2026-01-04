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
    private int $timeout = 10;         // сек
    private int $connectTimeout = 5;   // сек

    public function __construct(array $guzzleConfig = [])
    {
        $this->client = new Client(array_merge([
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            ],
        ], $guzzleConfig));
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
            // логируем ошибку
            log_dump('HttpClient send error: ' . $e->getMessage());

            // создаём фиктивный ответ, чтобы SDK не падал
            return new Response(500, [], json_encode([
                'ok' => false,
                'error_code' => 500,
                'description' => $e->getMessage()
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
}