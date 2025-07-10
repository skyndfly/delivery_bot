<?php

namespace classes;

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class TelegramBot
{
    public string $selectedFirm;
    public string $selectedAddress;
    private array $firms;
    private array $address;
    private array $images;
    private array $notes;

    private Api $telegram;


    public function __construct(
        Api $telegram,
        array $firms,
        array $address,
        array $images,
        array $notes
    ) {
        $this->telegram = $telegram;
        $this->firms = $firms;
        $this->address = $address;
        $this->images = $images;
        $this->notes = $notes;
    }


    public function actionStart(int $chatId): void
    {
        $keyboard = [];

        foreach ($this->firms as $key => $label) {
            $keyboard[] = [[
                'text' => $label . ' ✅',
                'callback_data' => 'firm|' . $key,
            ]];
        }

        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create('./img/cover.jpg'),
            'caption' => 'Выберите откуда нужна доставка 🚕',
            'reply_markup' => json_encode([
                'inline_keyboard' => $keyboard,
            ])
        ]);
    }

    public function actionSelectedFirm(string $firm, int $chatId): void
    {

        if (isset($this->address[$firm])) {
            $keyboard = [];

            foreach ($this->address[$firm] as $label) {
                $keyboard[] = [[
                    'text' => $label,
                    'callback_data' =>  'address|' . $firm .'|'. $label,
                ]];
            }
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => InputFile::create($this->images[$firm]),
                'caption' => $this->getMessageCaption($firm),
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard,
                ])
            ]);
        }
    }

    public function actionSelectedAddress(int $chatId): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => 'Прикрепите штрих код'
        ]);
    }


    private function getMessageCaption(string $firm): string
    {
        if (isset($this->notes[$firm])) {
            return $this->notes[$firm] . "\nВыберите пункт 🏦";
        }
        return "Выберите пункт 🏦";

    }
}