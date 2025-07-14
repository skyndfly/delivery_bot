<?php

namespace classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ApiYandexDisk
{
    private const string ACCESS_TOKEN = 'y0__xC9rdSSBhji7TggrZne4hN0SVH1fRYfV1j0NK1YzbqHMdK_mQ';
    private const string BASE_FOLDER = 'delivery_bot';
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'headers' => [
                'Authorization' => 'OAuth ' . self::ACCESS_TOKEN,
            ]
        ]);
        $this->createBaseFolder();
    }


    public function createFolder(string $folderName): true
    {
        try {
            $this->client->request(
                'PUT',
                'https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode(self::BASE_FOLDER . '/' . $folderName)
            );
            return true;
        } catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            if ($statusCode == 409) {
                return true;
            }
            throw $e;
        }
    }

    public function uploadFile(string $url, string $filePath):bool
    {
        try {
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $this->client->request(
                'POST',
                "https://cloud-api.yandex.net/v1/disk/resources/upload?url={$url}&path=" . self::BASE_FOLDER . "/" .
                urlencode($filePath). '/' . uniqid() .'.'. $extension
            );
            return true;
        }catch (ClientException $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            if ($statusCode == 409) {
                return false;
            }
        }
        return false;
    }

    private function createBaseFolder()
    {
        try {
            $this->client->request(
                'GET',
                'https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode(self::BASE_FOLDER)
            );
        } catch (ClientException $e) {
            $status = $e->getResponse()->getStatusCode();
            if ($status == 404) {
                $this->client->request(
                    'PUT',
                    'https://cloud-api.yandex.net/v1/disk/resources?path=' . urlencode(self::BASE_FOLDER)
                );
            } else {
                throw $e;
            }
        }
    }
}