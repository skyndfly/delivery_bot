<?php

use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

require_once "vendor/autoload.php";
function log_dump($var, $title = '')
{
    ob_start();
    echo "\n--- $title ---\n";
    var_dump($var);
    echo "\n";
    file_put_contents("log.txt", ob_get_clean(), FILE_APPEND);
}

try {

    $firms = [
        "Wildberries ✅",
        'СДЭК ✅',
        "Ozon ✅",
        'Аптека.ру ✅',
        'DNS ✅',
        'SUNLIGHT ✅',
        'Детский мир ✅',
        'Авито ✅',
        '5post ✅',
        'Яндекс Маркет ✅',
        'ПОЧТА РОССИИ ✅',
        'Все инструменты ✅',
        'Яндекс доставка ✅',
    ];
    $address = [
        'Ozon' => [
            'Молодогвардейцев 25',
            'Киевская 28Б',
            'Белорусская 10',
            'Белорусская 19',
        ],
        'Wildberries' => [
            'Молодогвардейцев 25',
            'Молодогвардейцев 19',
            'Киевская 28Б',
            'Белорусская 10',
        ]
    ];
    $botToken = "8000230460:AAFU0ivU-a2PVr69iWUf5K3JLc7d791Xknw";

    $telegram = new Api($botToken);

    $update = $telegram->getWebhookUpdate();


    $message = $update->getMessage();

    if ($message) {
        $chat_id = $message->getChat()->getId();
        $text = $message->getText();

        if ($text == "/start") {
            $keyboard = [
                'inline_keyboard' => array_map(
                    fn($firm) => [[
                        'text' => $firm,
                        'callback_data' => 'firm_' . $firm
                    ]],
                    $firms)
            ];
            file_put_contents("log.txt", "text: $text\n", FILE_APPEND);
            $telegram->sendPhoto([
                'chat_id' => $chat_id,
                'photo' => InputFile::create('./img/cover.jpg'),
                'caption' => 'Выберите откуда нужна доставка 🚕',
                'reply_markup' => json_encode($keyboard)
            ]);
        }
    }
    /** @var \Telegram\Bot\Objects\CallbackQuery|null $callbackQuery */
    $callbackQuery = $update->get('callback_query');
    log_dump($callbackQuery);
    if ($callbackQuery) {
        $chatId = $callbackQuery->getMessage()->getChat()->getId();
        $data = $callbackQuery->getData();

        if (str_starts_with($data, 'firm_')) {
            $firm = substr($data, strlen('firm_'));

            if (isset($address[$firm])) {
                $keyboard = [
                    'inline_keyboard' => array_map(
                        fn($addr) => [[
                            'text' => $addr,
                            'callback_data' => 'address_' . $addr
                        ]],
                        $address[$firm]
                    )
                ];
                file_put_contents("log.txt", "Callback: $data\n", FILE_APPEND);
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => "Выберите пункт 🏦",
                    'reply_markup' => json_encode($keyboard)
                ]);
            }
        }
        if (str_starts_with($data, 'address_')) {
            $address = substr($data, strlen('address_'));
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Прикрепите штрих код'
            ]);
        }
    }
} catch (Exception|Error $e) {
    file_put_contents("log.txt", "Exception: {$e->getMessage()}\n", FILE_APPEND);
}