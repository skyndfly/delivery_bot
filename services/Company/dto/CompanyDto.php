<?php

namespace services\Company\dto;

class CompanyDto
{
    public function __construct(
        public string $name,
        public string $botKey
    ) {
    }

    /**
     * @param array{
     *     name: string,
     *     bot_key: string,
     * } $record
     */
    public static function fromDbRecord(array $record): self
    {
        return new self(name: $record['name'], botKey: $record['bot_key']);
    }
}