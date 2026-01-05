<?php

namespace handler;

use api\BackApi;
use api\YandexDiskApi;
use api\TelegramBotApi;
use DomainException;
use enums\StateEnum;
use Exception;
use repositories\StepRepository;
use services\AuthorizeService;
use Telegram\Bot\Objects\Update;
use Throwable;

class MessageHandler implements HandlerInterface
{

    public function __construct(
        private TelegramBotApi $bot,
        private StepRepository $redis,
        private YandexDiskApi $apiDisk,
        private readonly AuthorizeService $authorize,
        private string $botToken,
        private BackApi $backApi,
    ) {
    }

    public function handle(Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $step = $this->redis->getStep($chatId);
        try {
            if (!$this->authorize->handle($chatId, $chatId)) {
                $this->bot->actionNoAuthorize($chatId);
                return;
            }
        } catch (DomainException $e) {
            $this->bot->actionSendError($chatId, $e->getMessage());
            return;
        }
        if ($text === "/start" || $text === "/restart") {
            $this->bot->actionStart($chatId);
            $this->redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
        } elseif (!$message->has('photo') && $step === StateEnum::AWAITING_PHOTO->value) {
            $this->bot->actionSendError($chatId);
        } elseif ($message->has('photo')) {
            if ($step === StateEnum::AWAITING_PHOTO->value) {
                $photos = $message->getPhoto();
                $url = $this->bot->getImagePath($photos, $this->botToken);

                $path = $this->redis->getPath($chatId);

                //Загружаем фото на яндекс диск
                $yandexSuccess = $this->apiDisk->uploadFile(url: $url, filePath: $path);

                // Загружаем на бэкенд API
                //TODO на время убираем загрузку на сайт
                try {
                    $backendSuccess = $this->uploadToBackend(chatId: $chatId, imageUrl: $url, path: $path);
                } catch (Throwable) {
                    $backendSuccess = true;
                }
                if (!$yandexSuccess || !$backendSuccess) {
                    $this->bot->actionBadUpload($chatId);
                } else {
                    $this->bot->actionSuccessDownload($chatId);
                    $this->redis->setStep($chatId, StateEnum::PAUSE->value);
                }
            } else {
                $this->bot->actionSendError($chatId);
            }
        } elseif (!in_array($text, ['/start', '/restart']) && $step === StateEnum::FIRM_SELECT->value) {
            $this->bot->actionSendError($chatId);
        } elseif (!in_array($text, ['/start', '/restart']) && $step === StateEnum::FIRM_SELECTED->value) {
            $this->bot->actionSendError($chatId);
            // Повторно показывать адреса не получится без выбранной фирмы, поэтому лучше не повторять вывод клавиатуры здесь
        }

    }


    private function uploadToBackend(int $chatId, string $imageUrl, string $path): bool
    {
        $tempFile = null;
        // Если не wb и не озон значит загружать на сайт не надо и считаем типа загрузили
        try {
            if (mb_stripos($path, 'Wildberries/Молодогвардейцев 25') !== false) {
                $companyKey = 'wb';

            } elseif (mb_stripos($path, 'Ozon/Молодогвардейцев 25') !== false) {
                $companyKey = 'ozon';
            } else {
                return true;
            }

            // Скачиваем файл из Telegram
            $fileContent = file_get_contents($imageUrl);
            if (!$fileContent) {
                return false;
            }

            // Создаем временный файл на диске
            $tempDir = sys_get_temp_dir();
            $filename = 'tg_photo_' . $chatId . '_' . uniqid() . '.jpg';
            $tempFile = $tempDir . '/' . $filename;

            // Сохраняем содержимое во временный файл
            if (file_put_contents($tempFile, $fileContent) === false) {
                return false;
            }

            // Проверяем, что файл создан
            if (!file_exists($tempFile)) {
                return false;
            }

            // Отправляем на бэкенд

            return $this->backApi->uploadCode(
                companyKey: $companyKey,
                chatId: (string) $chatId,
                filePath: $tempFile
            );

        } catch (Exception $e) {
            log_dump('Backend upload error: ' . $e->getMessage());
            return false;
        } finally {
            // Удаляем временный файл в любом случае
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

}