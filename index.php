<?php

use Telegram\Bot\Api;

require_once "vendor/autoload.php";

$firms = [
    "Ozon",
    "Wildberries"
];
$address = [
    'Ozon' => [
        'Молодогвардейцев 25',
        'Киевская 28Б',
        'Белорусская 10',
    ],
    'Wildberries' => [
        'Молодогвардейцев 25',
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
                    'callback_data' => 'firm_'.$firm
                ]],
                $firms)
        ];

        $telegram->sendMessage([
            'chat_id' => $chat_id,
            'text' => 'Выберите фирму:',
            'reply_markup' => json_encode($keyboard)
        ]);
    }

}




