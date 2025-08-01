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
            fn($label) => [[
                'text' => $label,
                'callback_data' => 'address|' . $firm . '|' . $label,
            ]],
            $addresses
        );
    }
}