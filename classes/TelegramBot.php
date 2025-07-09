<?php

namespace classes;

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class TelegramBot
{
    public string $selectedFirm;
    public string $selectedAddress;
    private array $keys;
    private array $firms;
    private array $address;
    private array $images;
    private array $notes;

    private Api $telegram;


    public function __construct(
        Api $telegram,
        array $keys,
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
        $this->keys = $keys;
    }


    public function actionStart(int $chatId): void
    {

        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create('./img/cover.jpg'),
            'caption' => 'Выберите откуда нужна доставка 🚕',
            'reply_markup' => json_encode($this->createKeyboard(
                key: 'firm',
                data: $this->keys,
                suffix: '✅'
            ))
        ]);
    }

    public function actionSelectedFirm(string $firm, int $chatId): void
    {
        //TODO закончить код првоерить что приходит в $firm
        if (isset($this->address[$firm])) {
            $this->telegram->sendPhoto([
                'chat_id' => $chatId,
                'photo' => InputFile::create($this->images[$firm]),
                'caption' => $this->getMessageCaption($firm),
                'reply_markup' => json_encode($this->createKeyboard(
                    key: 'address|' . $firm,
                    data: $this->address[$firm],
                ))
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

    private function createKeyboard(string $key, array $data, ?string $suffix = null): array
    {
        $r =  [
            'inline_keyboard' => array_map(
                fn($item) => [[
                    'text' => $suffix !== null ? $this->firms[$item] . ' ' . $suffix : $this->firms[$item],
                    'callback_data' => $key . '|' . $item
                ]],
                $data
            )
        ];
       return $r;
    }

    private function getMessageCaption(string $firm): string
    {
        if (isset($this->notes[$firm])) {
            return $this->notes[$firm] . "\nВыберите пункт 🏦";
        }
        return "Выберите пункт 🏦";

    }
}