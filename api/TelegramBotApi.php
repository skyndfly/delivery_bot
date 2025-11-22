<?php

namespace api;

use components\telegram\KeyBoardBuilder;
use components\telegram\MessageSender;
use Illuminate\Support\Collection;
use Telegram\Bot\Api;

class TelegramBotApi
{
    private array $firms;
    private array $address;
    private array $images;
    private array $notes;
    private array $messages;

    private Api $telegram;

    private KeyBoardBuilder $keyboardBuilder;
    private MessageSender $sender;

    public function __construct(
        Api $telegram,
        KeyBoardBuilder $keyboardBuilder,
        array $firms,
        array $address,
        array $images,
        array $notes,
        array $messages,
        MessageSender $sender
    ) {
        $this->telegram = $telegram;
        $this->keyboardBuilder = $keyboardBuilder;
        $this->firms = $firms;
        $this->address = $address;
        $this->images = $images;
        $this->notes = $notes;
        $this->sender = $sender;
        $this->messages = $messages;
    }


    public function actionStart(int $chatId): void
    {
        $this->sender->sendPhoto(
            chatId: $chatId,
            photoPath: $this->messages['start']['img'],
            caption: $this->messages['start']['text'],
            keyboard: $this->keyboardBuilder->fromFirms($this->firms)
        );
    }

    public function actionSelectedFirm(string $firm, int $chatId): void
    {
        if (!isset($this->address[$firm])) {
            $this->actionSendError($chatId);
            return;
        }
        $this->sender->sendPhoto(
            chatId: $chatId,
            photoPath: $this->images[$firm],
            caption: $this->getMessageCaption($firm),
            keyboard: $this->keyboardBuilder->fromAddress($firm, $this->address[$firm])
        );

    }

    public function actionSendError(int $chatId, ?string $customText = null): void
    {
        $this->sender->sendPhoto(
            chatId: $chatId,
            photoPath: $this->messages['error']['img'],
            caption: $customText === null ? $this->messages['error']['text'] : $customText,
            keyboard: $this->keyboardBuilder->fromError(),
        );

    }

    public function actionSuccessDownload(int $chatId): void
    {
        $this->sender->sendPhoto(
            chatId: $chatId,
            photoPath: $this->messages['success']['img'],
            caption: $this->messages['success']['text'],
            keyboard: $this->keyboardBuilder->fromSuccess()
        );
    }

    public function actionEnd(int $chatId): void
    {
        $this->sender->sendText(
            chatId: $chatId,
            text: $this->messages['end']['text'],
            keyboard: $this->keyboardBuilder->fromEnd()
        );
    }

    public function actionSelectedAddress(int $chatId): void
    {
        $this->sender->sendText(
            chatId: $chatId,
            text: $this->messages['checkQR']['text']
        );
    }

    public function actionBadUpload(int $chatId): void
    {
        $this->sender->sendText(
            chatId: $chatId,
            text: $this->messages['badUpload']['text']
        );
    }

    public function actionNoAuthorize(int $chatId): void
    {
        $this->sender->sendPhoto(
            chatId: $chatId,
            photoPath: $this->messages['noAuthorize']['img'],
            caption: $this->messages['noAuthorize']['text'],
            keyboard: []
        );
    }

    public function getImagePath(Collection $photos, $botToken): string
    {
        $large = $photos->last();
        $fileId = $large->getFileId();
        $file = $this->telegram->getFile(['file_id' => $fileId]);
        return "https://api.telegram.org/file/bot{$botToken}/{$file->getFilePath()}";
    }

    public function answerCallback(int $id): void
    {
        $this->telegram->answerCallbackQuery([
            'callback_query_id' => $id,
        ]);
    }

    private function getMessageCaption(string $firm): string
    {
        return isset($this->notes[$firm])
            ? $this->notes[$firm] . $this->messages['point']['text']
            : $this->messages['point']['text'];
    }

}