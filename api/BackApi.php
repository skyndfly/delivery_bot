<?php

namespace api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;
use Throwable;

class BackApi
{
    private Client $client;

    public function __construct(public string $host)
    {
        $this->client = new Client([
            'timeout' => 30,
        ]);
    }

    /**
     * @throws RuntimeException
     */
    public function uploadCode(string $companyKey, string $chatId, string $filePath): bool
    {
        try {
            $response = $this->client->request(
                'POST',
                "{$this->host}/upload/store",
                [
                    'multipart' => [
                        [
                            'name' => 'companyKey',
                            'contents' => $companyKey,
                        ],
                        [
                            'name' => 'chatId',
                            'contents' => $chatId,
                        ],
                        [
                            'name' => 'code',
                            'contents' => fopen($filePath, 'rb'),
                            'filename' => basename($filePath),
                        ],
                    ],
                ]
            );

            // Проверяем статус-код
            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;

        } catch (GuzzleException $e) {
            log_dump("BackAPI HTTP Error: {$e->getMessage()}");
            return false;

        } catch (Throwable $e) {
            log_dump("BackAPI Internal Error: {$e->getMessage()}");
            return false;
        }
    }
}