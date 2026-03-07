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
     * @return array<int, array{id:int, username:string|null, phone:string|null, name:string|null}>
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
            $id = isset($row[0]) ? trim((string) $row[0]) : '';
            if ($id === '') {
                continue;
            }
            $username = isset($row[1]) ? trim((string) $row[1]) : null;
            $phone = isset($row[2]) ? trim((string) $row[2]) : null;
            $name = isset($row[4]) ? trim((string) $row[4]) : null;
            $users[] = [
                'id' => (int) $id,
                'username' => $username !== '' ? $username : null,
                'phone' => $phone !== '' ? $phone : null,
                'name' => $name !== '' ? $name : null,
            ];
        }
        return $users;
    }
}
