<?php

namespace components\telegram;

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class MessageSender
{
    private Api $telegram;

    public function __construct(Api $telegram)
    {
        $this->telegram = $telegram;
    }

    public function sendPhoto(
        int $chatId,
        string $photoPath,
        string $caption,
        array $keyboard
    ): void {
        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create($photoPath),
            'caption' => $caption,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ])
        ]);
    }

    public function sendText(int $chatId, string $text, array $keyboard = []): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ])
        ]);
    }
}