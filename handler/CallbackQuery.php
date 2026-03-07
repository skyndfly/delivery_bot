<?php

namespace handler;

use api\YandexDiskApi;
use api\TelegramBotApi;
use DateTimeInterface;
use DomainException;
use enums\StateEnum;
use repositories\StepRepository;
use services\AuthorizeService;
use Telegram\Bot\Objects\Update;

class CallbackQuery implements HandlerInterface
{
    public function __construct(
        private readonly TelegramBotApi $bot,
        private readonly StepRepository $redis,
        private readonly YandexDiskApi $apiDisk,
        private readonly DateTimeInterface $currentDate,
        private readonly AuthorizeService $authorize,
        private readonly array $firms
    ) {
    }

    public function handle(Update $update): void
    {
        $callbackQuery = $update->get('callback_query');
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();
        try {
            if (!$this->authorize->handle($chatId, $chatId)) {
                $this->bot->actionNoAuthorize($chatId);
                return;
            }
        } catch (DomainException $e) {
            $this->bot->actionSendError($chatId, $e->getMessage());
            return;
        }
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
            $this->redis->setFirm($chatId, $firm);
            $this->bot->answerCallback($callbackQuery->getId());
        } elseif (str_starts_with($data, 'address|')) {
            $parts = explode('|', $data, 4);
            $firm = $parts[1] ?? '';
            $addressId = null;
            $address = $parts[2] ?? '';
            if (isset($parts[3])) {
                $addressId = ctype_digit($parts[2]) ? (int) $parts[2] : null;
                $address = $parts[3];
            }
            $path = $this->currentDate->format('d-m-Y') . '/' . $this->firms[$firm] . '/' . $address;
            $this->apiDisk->createFolder($path);
            $this->bot->actionSelectedAddress($chatId);
            $this->redis->setPath($chatId, $path);
            if ($addressId !== null) {
                $this->redis->setAddressId($chatId, $addressId);
            }
            if ($address !== '') {
                $this->redis->setAddressLabel($chatId, $address);
            }
            $this->redis->setStep($chatId, StateEnum::AWAITING_PHOTO->value);
            $this->bot->answerCallback($callbackQuery->getId());
        }
    }
}
