<?php

namespace repositories;

use db\PostgresConnection;
use PDO;
use services\Company\dto\CompanyDto;

class CompanyRepository
{
    private PDO $connection;
    private const string TABLE = 'company';

    public function __construct()
    {
        $this->connection = PostgresConnection::getInstance();
    }

    /**
     * @return CompanyDto[]
     */
    public function getAll(): array
    {
        $stmt = $this->connection->query("SELECT name, key FROM " . self::TABLE);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(
            callback: fn(array $row) => CompanyDto::fromDbRecord($row),
            array: $results
        );
    }

    public function getAllAsAssocArray(): array
    {
        $companies = $this->getAll();
        $result = [];
        foreach ($companies as $dto) {
            $result[$dto->key] = $dto->name;
        }
        return $result;
    }


}
