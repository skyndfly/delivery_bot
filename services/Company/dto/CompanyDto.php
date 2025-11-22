<?php

namespace services\Company\dto;

class CompanyDto
{
    public function __construct(
        public string $name,
        public string $key
    ) {
    }

    /**
     * @param array{
     *     name: string,
     *     key: string,
     * } $record
     */
    public static function fromDbRecord(array $record): self
    {
        return new self(name: $record['name'], key: $record['key']);
    }
}