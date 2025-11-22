<?php

namespace services\Company;

use db\RedisConnection;
use repositories\CompanyRepository;
use Symfony\Contracts\Cache\ItemInterface;

class GetCachedCompanyService
{
    private const string CACHE_KEY = 'companies_assoc_array';
    public function __construct(
        private CompanyRepository $companyRepository,
    )
    {
    }

    /**
     * @return array<string, string>
     */
    public function execute(): array
    {
        $cache =  RedisConnection::getInstance();
        return $cache->get(self::CACHE_KEY, function (ItemInterface $item) {
            // Устанавливаем TTL 24 часа
            $item->expiresAfter(86400);

            return $this->companyRepository->getAllAsAssocArray();
        });
    }
    public function clearCache(): void
    {
        $cache = RedisConnection::getInstance();
        $cache->delete(self::CACHE_KEY);
    }
}