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
     * @return array{firms: array<string,string>, address: array<string, array<int, array{id:int, address:string}>>}
     */
    public function getBotData(): array
    {
        try {
            $response = $this->client->request('GET', "{$this->host}/bot-data");
            $payload = json_decode((string) $response->getBody(), true);
            if (!is_array($payload)) {
                throw new RuntimeException('Invalid bot-data response');
            }
            $firms = $payload['firms'] ?? null;
            $address = $payload['address'] ?? null;
            if (!is_array($firms) || !is_array($address)) {
                throw new RuntimeException('Missing bot-data fields');
            }
            return [
                'firms' => $firms,
                'address' => $address,
            ];
        } catch (GuzzleException $e) {
            throw new RuntimeException("BackAPI HTTP Error: {$e->getMessage()}");
        } catch (Throwable $e) {
            throw new RuntimeException("BackAPI Internal Error: {$e->getMessage()}");
        }
    }

    /**
     * @throws RuntimeException
     */
    public function uploadCode(string $companyKey, string $chatId, string $filePath, ?int $addressId = null, ?string $address = null): bool
    {
        try {
            $multipart = [
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
            ];
            if ($addressId !== null) {
                $multipart[] = [
                    'name' => 'addressId',
                    'contents' => (string) $addressId,
                ];
            }
            if ($address !== null) {
                $multipart[] = [
                    'name' => 'address',
                    'contents' => $address,
                ];
            }
            $response = $this->client->request(
                'POST',
                "{$this->host}/upload/store",
                [
                    'multipart' => $multipart,
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
