<?php

namespace enums;

enum UploadedCodeStatusEnum: string
{
    case AWAIT = "await";
    case NOT_PAID = "not paid";
    case OUTDATED = "outdated";
    case PENDING = "pending";
    case ISSUED = "issued";

    public function label(): string
    {
        return match ($this) {
            self::AWAIT => 'Ожидает выдачи',
            self::NOT_PAID => 'Не оплачен',
            self::OUTDATED => 'Код устарел',
            self::ISSUED => 'Выдан',
            self::PENDING => 'Отложенный',
        };
    }
}
