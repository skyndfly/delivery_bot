# Delivery Telegram Bot

## Обзор

Проект представляет собой PHP Telegram-бота для обработки кодов доставки. Бот ведет пользователя по выбору маркетплейса и адреса, принимает фото кода, сохраняет файл на Яндекс Диск и отправляет данные во внешний backend API. Дополнительно есть HTTP endpoints для уведомлений, синхронизации пользователей, поиска пользователей и очистки кэша.

## Основные возможности

- Telegram polling обработка входящих сообщений и callback-запросов через SOCKS/proxy.
- Авторизация пользователей по локальному MySQL-списку, синхронизируемому из Google Таблицы.
- Выбор компании и адреса через inline-клавиатуры Telegram.
- Прием фотографий с кодами, загрузка на Яндекс Диск и передача файла во внешний backend API.
- Уведомление пользователей о статусах кодов через HTTP endpoint `/issued`.
- Кэширование настроек и состояния диалога в Redis.
- Docker Compose окружение с PHP Apache, MySQL и Redis.

## Технологический стек

- **Язык программирования:** PHP 8.4
- **Фреймворк:** без полноценного фреймворка; собственный front controller в `index.php`
- **Автозагрузка:** Composer PSR-4
- **Telegram:** `irazasyed/telegram-bot-sdk`
- **HTTP-клиент:** Guzzle
- **Конфигурация окружения:** `vlucas/phpdotenv`
- **База данных:** MySQL через PDO
- **Кэш и состояние:** Redis через Predis
- **Интеграции:** Telegram Bot API, Яндекс Диск API, Google Таблица CSV, внешний backend API
- **Инфраструктура:** Dockerfile + `docker-compose.yml`, отдельный `telegram-polling` worker

## Наблюдаемые паттерны

- `index.php` обслуживает HTTP API; Telegram update polling запускается отдельным `polling.php` process.
- Пространства имен соответствуют директориям из `composer.json`: `api\`, `db\`, `handler\`, `components\`, `repositories\`, `services\`, `enums\`, `console\`, `bootstrap\`.
- Внешние сервисы инкапсулированы в API-классах: `BackApi`, `YandexDiskApi`, `GoogleTableApi`, `TelegramBotApi`.
- Состояние пользовательского сценария хранится через `StepRepository` и Redis.
- Доступ к пользователям идет через контракт `UserRepositoryContract`; актуальная реализация — `UserMysqlRepository`, старая Redis-реализация помечена deprecated.
- Ошибки внешних вызовов чаще всего оборачиваются в `RuntimeException`, `DomainException` или логируются через `log_dump()`.

## Архитектурные заметки

Проекту подходит архитектура `Structured Modules (Technical Layers)`: текущая кодовая база уже разделена по техническим слоям, но пока не требует полной Clean/Hexagonal структуры. Развитие стоит вести через укрепление границ между HTTP/Telegram handlers, application services, repositories, db connections и external API adapters.

## Нефункциональные требования

- **Логирование:** использовать существующий `log_dump()` для ошибок интеграций и важных операций синхронизации.
- **Обработка ошибок:** HTTP endpoints должны возвращать корректные status codes и JSON-ответы; Telegram-сценарии должны отправлять пользователю понятное сообщение без утечки технических деталей.
- **Безопасность:** секреты должны оставаться в `.env`; не коммитить реальные `BOT_TOKEN`, `DISK_TOKEN`, proxy credentials и backend credentials.
- **Надежность:** внешние HTTP-интеграции должны иметь timeout и безопасную обработку невалидных ответов.
- **Совместимость:** сохранять PSR-4 namespaces из `composer.json` и не ломать Docker Compose сервисные имена `mysql` и `redis`.

## Архитектура

Подробные архитектурные правила описаны в `.ai-factory/ARCHITECTURE.md`.

**Паттерн:** Structured Modules (Technical Layers)
