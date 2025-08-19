<?php

namespace api;

class GoogleTableApi
{
    private string $tableUrl;
    public function __construct(string $tableUrl)
    {
        $this->tableUrl = $tableUrl;
    }

    /**
     * @return int[]
     */
    public function getUserData(): array
    {
        $csvData = file_get_contents($this->tableUrl);
        if ($csvData === false) {
            log_dump('Не удалось загрузить данные таблицы');
        }
        $rows = array_map(
            fn ($line) => str_getcsv($line, ',', '"', '\\'),
            explode("\n", $csvData)
        );
        array_shift($rows);

        $users = [];
        foreach ($rows as $row) {
            if (!empty($row[0])) {
                $users[] = (int)$row[0];
            }
        }
        return $users;
    }
}