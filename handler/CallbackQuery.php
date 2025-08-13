<?php

namespace handler;

use api\ApiYandexDisk;
use api\TelegramBot;
use DateTimeInterface;
use db\StepStorage;
use enums\StateEnum;
use Telegram\Bot\Objects\Update;

class CallbackQuery implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBot $bot,
        private readonly StepStorage $redis,
        private readonly ApiYandexDisk $apiDisk,
        private readonly DateTimeInterface $currentDate,
        private readonly array $firms
    ) {
    }

    public function handle(Update $update): void
    {
        $callbackQuery = $update->get('callback_query');
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if ($data === 'action_start') {
            $this->bot->actionStart($chatId);
            $this->redis->setStep($chatId, StateEnum::FIRM_SELECT->value);
            $this->bot->answerCallback($callbackQuery->getId());
        } elseif ($data === 'action_end') {
            $this->bot->actionEnd($chatId);
            $this->redis->setStep($chatId, StateEnum::PAUSE->value);
            $this->bot->answerCallback($callbackQuery->getId());
        } elseif (str_starts_with($data, 'firm|')) {
            $firm = substr($data, strlen('firm|'));
            $this->apiDisk->createFolder($this->currentDate->format('d-m-Y') . '/' . $this->firms[$firm]);
            $this->bot->actionSelectedFirm($firm, $chatId);
            $this->redis->setStep($chatId, StateEnum::FIRM_SELECTED->value);
            $this->bot->answerCallback($callbackQuery->getId());
        } elseif (str_starts_with($data, 'address|')) {
            [, $firm, $address] = explode('|', $data, 3);
            $path = $this->currentDate->format('d-m-Y') . '/' . $this->firms[$firm] . '/' . $address;
            $this->apiDisk->createFolder($path);
            $this->bot->actionSelectedAddress($chatId);
            $this->redis->setPath($chatId, $path);
            $this->redis->setStep($chatId, StateEnum::AWAITING_PHOTO->value);
            $this->bot->answerCallback($callbackQuery->getId());
        }
    }
}