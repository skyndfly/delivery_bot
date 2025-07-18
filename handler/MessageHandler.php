<?php

namespace handler;

use api\ApiYandexDisk;
use api\TelegramBot;
use db\StepStorage;
use enums\StateEnum;
use Telegram\Bot\Objects\Update;

class MessageHandler implements HandlerInterface
{

    public function __construct(
        private TelegramBot $bot,
        private StepStorage $redis,
        private ApiYandexDisk $apiDisk,
        private string $botToken
    ) {
    }

    public function handle(Update $update): void
    {
        $message = $update->getMessage();
        $chatId = $message->getChat()->getId();
        $text = $message->getText();
        $step = $this->redis->getStep($chatId);

        if ($text === "/start" || $text === "/restart") {
            $this->bot->actionStart($chatId);
            $this->redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
        } elseif (!$message->has('photo') && $step === StateEnum::AWAITING_PHOTO->value) {
            $this->bot->actionSendError($chatId);
        } elseif ($message->has('photo')) {
            if ($step === StateEnum::AWAITING_PHOTO->value) {
                $photos = $message->getPhoto();
                $url = $this->bot->getImagePath($photos, $this->botToken);
                if (!$this->apiDisk->uploadFile($url, $this->redis->getPath($chatId))) {
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
}