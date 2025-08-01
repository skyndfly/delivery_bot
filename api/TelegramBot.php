<?php

namespace api;

use components\telegram\KeyBoardBuilder;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

class TelegramBot
{
    private array $firms;
    private array $address;
    private array $images;
    private array $notes;

    private Api $telegram;

    private KeyBoardBuilder $keyboardBuilder;

    public function __construct(
        Api $telegram,
        KeyBoardBuilder $keyboardBuilder,
        array $firms,
        array $address,
        array $images,
        array $notes
    ) {
        $this->telegram = $telegram;
        $this->keyboardBuilder = $keyboardBuilder;
        $this->firms = $firms;
        $this->address = $address;
        $this->images = $images;
        $this->notes = $notes;
    }


    public function actionStart(int $chatId): void
    {

        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create('./img/cover.jpg'),
            'caption' => 'Выберите откуда нужна доставка 🚕',
            'reply_markup' => json_encode([
                'inline_keyboard' => $this->keyboardBuilder->fromFirms($this->firms),
            ])
        ]);
    }

    public function actionSelectedFirm(string $firm, int $chatId): void
    {
        if (!isset($this->address[$firm])) {
            $this->actionSendError($chatId);
        }
        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create($this->images[$firm]),
            'caption' => $this->getMessageCaption($firm),
            'reply_markup' => json_encode([
                'inline_keyboard' => $this->keyboardBuilder->fromAddress($firm, $this->address[$firm]),
            ])
        ]);

    }

    public function actionSendError(int $chatId): void
    {

        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create('./img/error.jpg'),
            'caption' => 'ВНИМАНИЕ ‼️

Последовательность действий нарушена! ❌

1) Нажмите кнопку  СТАРТ

2) Выберите пункт откуда нужно забрать ВАШ заказ

3) Укажите адрес пункта 

4) Прикрепить штрих код!

🅱️ Важно, если эти действия не выполнить штрих код будет не забран ❌',
            'reply_markup' => json_encode([
                'inline_keyboard' => [[[
                    'text' => 'СТАРТ ✅',
                    'callback_data' => 'action_start',
                ]]],
            ])
        ]);

    }

    public function actionSuccessDownload(int $chatId): void
    {
        $this->telegram->sendPhoto([
            'chat_id' => $chatId,
            'photo' => InputFile::create('./img/congrat.jpg'),
            'caption' => "Заказ принят!👍\n\nВыберите следующий шаг👇",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [[
                        'text' => 'Добавить код ✅',
                        'callback_data' => 'action_start',
                    ]],
                    [[
                        'text' => 'Оформить доставку 🚛',
                        'url' => 'https://t.me/kolibridelivery_bot',
                    ]],
                    [[
                        'text' => 'Завершить ❗️',
                        'callback_data' => 'action_end',
                    ]],
                ],
            ])
        ]);
    }

    public function actionEnd(int $chatId): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => "Все заказы приняты и будут доставлены.\n\nЗабрать свой заказ Вы сможете:
г. Антрацит, ул. Петровского 21 , за налоговой 108 кабинет",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [[
                        'text' => 'Добавить код ✅',
                        'callback_data' => 'action_start',
                    ]],
                ],
            ])
        ]);
    }

    public function actionSelectedAddress(int $chatId): void
    {
        $this->sendTextMessage(
            $chatId,
            'Прикрепите штрих код'
        );
    }

    public function actionBadUpload(int $chatId): void
    {
        $this->sendTextMessage(
            chatId: $chatId,
            text: 'При загрузке кода произошла ошибка, попробуйте еще раз или отправьте код по номеру телефона +7 925 230-63-75'
        );
    }

    public function getImagePath(Collection $photos, $botToken): string
    {
        $large = $photos[count($photos) - 2];
        $fileId = $large->getFileId();
        $file = $this->telegram->getFile(['file_id' => $fileId]);
        return "https://api.telegram.org/file/bot{$botToken}/{$file->getFilePath()}";
    }

    private function getMessageCaption(string $firm): string
    {
        if (isset($this->notes[$firm])) {
            return $this->notes[$firm] . "\nВыберите пункт 🏦";
        }
        return "Выберите пункт 🏦";
    }

    private function sendTextMessage(string $chatId, string $text): void
    {
        $this->telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $text
        ]);
    }

    public function answerCallback(int $id)
    {
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $id,
        ]);
    }
}