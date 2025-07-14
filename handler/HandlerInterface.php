<?php

namespace handler;

use Telegram\Bot\Objects\Update;

interface HandlerInterface
{
    public function handle(Update $update): void;
}