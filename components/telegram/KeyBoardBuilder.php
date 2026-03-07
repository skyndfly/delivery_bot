<?php

namespace components\telegram;

use enums\CallbackDataEnum;

class KeyBoardBuilder
{
    public function fromFirms(array $firms): array
    {
        return array_map(
            fn($key, $label) => [[
                'text' => $label . ' ✅',
                'callback_data' => CallbackDataEnum::FIRM->value . $key
            ]],
            array_keys($firms),
            $firms
        );
    }

    public function fromAddress(string $firm, array $addresses): array
    {

        return array_map(
            function ($item) use ($firm) {
                if (is_array($item)) {
                    $label = $item['address'] ?? '';
                    $id = $item['id'] ?? null;
                } else {
                    $label = $item;
                    $id = null;
                }
                $callback = $id === null
                    ? 'address|' . $firm . '|' . $label
                    : 'address|' . $firm . '|' . $id . '|' . $label;

                return [[
                    'text' => $label,
                    'callback_data' => $callback,
                ]];
            },
            $addresses
        );
    }

    public function fromSuccess(): array
    {
        return [
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
        ];
    }

    public function fromEnd(): array
    {
        return [
            [[
                'text' => 'Добавить код ✅',
                'callback_data' => 'action_start',
            ]],
        ];
    }

    public function fromError(): array
    {
        return [[[
            'text' => 'СТАРТ ✅',
            'callback_data' => 'action_start',
        ]]];
    }
}
