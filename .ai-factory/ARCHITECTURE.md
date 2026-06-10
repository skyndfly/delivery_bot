# Architecture: Structured Modules (Technical Layers)

## Обзор

Проект использует прагматичную модульную архитектуру с организацией по техническим слоям. Это соответствует текущему состоянию кода: HTTP-входные точки находятся в `index.php` и `api/index.php`, Telegram updates забирает `polling.php` и передает их в `handler/`, внешние интеграции — в `api/`, orchestration — в `services/`, доступ к данным — в `repositories/` и `db/`.

Цель архитектуры — укреплять существующие границы без большого переписывания. Новые изменения должны уменьшать связанность `index.php`, не смешивать Telegram/UI-логику с доступом к данным и постепенно переводить бизнес-сценарии в services/use cases.

## Обоснование решения

- **Тип проекта:** небольшой/средний Telegram-бот с HTTP endpoints и несколькими внешними интеграциями.
- **Технологический стек:** PHP 8.4, Composer PSR-4, MySQL, Redis, Guzzle, Telegram SDK, Docker Compose.
- **Ключевой фактор:** структура уже разделена по техническим слоям, а доменная сложность пока не требует полной Clean/Hexagonal архитектуры.
- **Ограничение:** `index.php` остается HTTP router, а Telegram composition root вынесен в `services/TelegramBotRuntimeFactory.php`; дальнейший рефакторинг routing не требует перехода на framework.

## Структура каталогов

```text
.
├── index.php                         # HTTP API router; legacy webhook only behind ENABLE_TELEGRAM_WEBHOOK=1
├── polling.php                       # Long-running Telegram getUpdates worker
├── api/                              # External API adapters и Telegram facade
│   ├── BackApi.php                   # Внешний backend API
│   ├── GoogleTableApi.php            # Чтение пользователей из Google Таблицы CSV
│   ├── TelegramBotApi.php            # Высокоуровневые действия бота
│   └── YandexDiskApi.php             # Интеграция с Яндекс Диском
├── handler/                          # Presentation layer для Telegram Update
│   ├── MessageHandler.php            # Обработка сообщений
│   └── CallbackQuery.php             # Обработка inline callback data
├── services/                         # Application services/use cases
│   ├── AuthorizeService.php          # Авторизация пользователя и окно доступности сервиса
│   └── UserSyncService.php           # Синхронизация пользователей
├── repositories/                     # Data access и state repositories
│   ├── contracts/                    # Repository interfaces
│   ├── UserMysqlRepository.php       # MySQL users repository
│   └── StepRepository.php            # Состояние сценария в Redis
├── db/                               # Infrastructure connections
│   ├── MysqlConnection.php
│   └── RedisConnection.php
├── components/telegram/              # Telegram keyboard/message helpers
├── enums/                            # Состояния и статусы
├── data/                             # Статические справочники
├── messages/                         # Тексты сообщений
└── bootstrap/                        # Env bootstrap
```

## Правила зависимостей

- Разрешено: `handler/` зависит от `services/`, `api/`, `repositories/` и `enums/` для orchestration Telegram-сценария.
- Разрешено: `services/` зависит от repository contracts, API adapters и cache repositories.
- Разрешено: `repositories/` зависит от `db/` и contracts.
- Разрешено: `api/` зависит от Guzzle, Telegram SDK и компонентов отправки сообщений.
- Запрещено: `db/` импортирует `handler/`, `services/` или `api/`.
- Запрещено: repositories отправляют Telegram-сообщения или вызывают внешний backend API.
- Запрещено: handlers напрямую создают PDO/Redis/Guzzle низкого уровня, если уже есть repository/API adapter.
- Запрещено: domain/application решения зависят от runtime-секретов напрямую; `.env` читается на уровне bootstrap/composition root.

## Коммуникация слоев

- `index.php` обслуживает HTTP API; `polling.php` создает Telegram runtime и передает updates в dispatcher.
- `handler/` валидирует Telegram-сценарий и вызывает application services или API facade; сложная бизнес-логика должна выноситься из handler в `services/`.
- `services/` оркестрируют use cases: читают данные через repositories/API adapters, принимают решение, возвращают результат или бросают понятное исключение.
- `repositories/` скрывают конкретное хранилище: MySQL, Redis или будущие реализации.
- `api/` скрывает внешние HTTP protocols, форматы payload и ошибки интеграций.

## Ключевые принципы

1. `index.php` — HTTP API router, а не место для роста бизнес-логики или long-running процессов.
2. Новые use cases добавлять в `services/`, если логика выходит за простую маршрутизацию endpoint.
3. Для доступа к данным использовать repository contracts, когда нужна заменяемость или тестируемость.
4. Внешние HTTP-вызовы держать в `api/`, не размазывать Guzzle по handlers/services.
5. Состояния Telegram-сценария описывать через `StateEnum` и `StepRepository`, не строками в разных местах.
6. Секреты и runtime endpoints брать из `.env` только на уровне bootstrap/composition root.

## Примеры кода

### Service получает зависимости через контракт

```php
<?php

namespace services;

use repositories\contracts\UserRepositoryContract;

final class CheckUserAccessService
{
    public function __construct(
        private readonly UserRepositoryContract $users,
    ) {
    }

    public function handle(int $chatId): bool
    {
        return $this->users->exists($chatId);
    }
}
```

### Handler делегирует сценарий service/API adapter

```php
<?php

namespace handler;

use services\CheckUserAccessService;
use Telegram\Bot\Objects\Update;

final class MessageHandler implements HandlerInterface
{
    public function __construct(
        private readonly CheckUserAccessService $access,
    ) {
    }

    public function handle(Update $update): void
    {
        $chatId = $update->getMessage()->getChat()->getId();

        if (!$this->access->handle($chatId)) {
            // Handler отвечает за Telegram response, service — за use case decision.
            return;
        }
    }
}
```

## Антипаттерны

- Не добавлять новые крупные `if ($_SERVER['REQUEST_URI'] ...)` блоки в `index.php`, если endpoint содержит бизнес-сценарий; выносить сценарий в service.
- Не создавать `new PDO`, `new Predis\Client` или `new GuzzleHttp\Client` внутри handlers при наличии adapter/repository слоя.
- Не хранить бизнес-состояние в произвольных Redis keys вне repository.
- Не использовать реальные токены или proxy credentials в коде, документации и тестовых данных.
- Не смешивать отправку Telegram-сообщений, загрузку файлов и сохранение пользователей в одном service без явного use-case смысла.
