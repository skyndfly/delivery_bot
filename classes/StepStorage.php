<?php

namespace classes;

use Predis\Client;

class StepStorage
{
    private Client $redis;

    public function __construct()
    {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host' => 'redis',
            'port' => 6379,
        ]);
    }

    public function getStep(int $chatId): ?string
    {
        return $this->redis->get('step:' . $chatId);
    }

    public function setStep(int $chatId, string $step): void
    {
        $this->redis->set('step:' . $chatId, $step);
    }
    public function getPath(int $path): ?string
    {
        return $this->redis->get('path:' . $path);
    }
    public function setPath(int $chatId, string $path): void
    {
        $this->redis->set('path:' . $chatId, $path);
    }
}