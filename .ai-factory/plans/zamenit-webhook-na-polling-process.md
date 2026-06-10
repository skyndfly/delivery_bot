# Implementation Plan: Замена Telegram webhook на polling-процесс

Branch: master
Created: 2026-06-10

## Settings
- Testing: no
- Logging: minimal
- Docs: yes

## Scope

Заменить обработку Telegram updates через webhook на постоянный CLI polling-процесс, который сам забирает updates у Telegram через уже настроенный SOCKS/proxy (`TELEGRAM_PROXY`, `TELEGRAM_PROXY_TYPE`). Существующее HTTP API должно продолжить работать как раньше: `/issued`, `/cache/clear`, `/users`, `/message`, `/users/sync` в `index.php` и `api/index.php?action=webhook-add-user` не должны менять контракт ответов и payload.

## Архитектурный контекст

- Следовать `Structured Modules (Technical Layers)` из `.ai-factory/ARCHITECTURE.md`.
- `index.php` сейчас совмещает HTTP API endpoints и Telegram webhook dispatch через `$telegram->getWebhookUpdate()`.
- `polling.php` существует, но фактически пустой.
- Telegram API уже создается через `createTelegramApi()`, который использует `TelegramProxy::buildGuzzleOptions()` и `components\HttpClient`, поэтому polling должен переиспользовать этот proxy-aware путь.
- Handlers `handler/MessageHandler.php` и `handler/CallbackQuery.php` уже принимают `Telegram\Bot\Objects\Update`; их нужно переиспользовать без изменения пользовательского сценария.

## Commit Plan

- **Commit 1** (после задач 1-3): `refactor: extract telegram update bootstrap and dispatcher`
- **Commit 2** (после задач 4-6): `feat: run telegram updates through polling process`
- **Commit 3** (после задачи 7): `docs: document polling operation`

## Tasks

### Phase 1: Разделить HTTP API и Telegram update dispatch

- [x] Task 1: Выделить общий bootstrap Telegram-бота и зависимостей из `index.php` в отдельный слой без изменения HTTP API.
  - Files: создать `services/TelegramBotRuntimeFactory.php` или близкий по смыслу service/factory, изменить `index.php` минимально.
  - Expected behavior: factory загружает `.env`, создает `BackApi`, `BotCacheRepository`, `UserMysqlRepository`, `AuthorizeService`, `Telegram\Bot\Api`, `StepRepository`, `TelegramBotApi`, `YandexDiskApi`, данные фирм/адресов/сообщений и текущую дату так же, как сейчас делает нижняя часть `index.php`.
  - Dependency notes: это основа для `polling.php`; не менять endpoints `/issued`, `/cache/clear`, `/users`, `/message`, `/users/sync`.
  - LOGGING REQUIREMENTS: minimal logging only; логировать через `log_dump()` только bootstrap failures и fallback на локальные `data/firms.php`/`data/address.php`, без токенов и proxy URL.

- [x] Task 2: Выделить единый dispatcher для одного Telegram `Update`.
  - Files: создать `services/TelegramUpdateDispatcher.php` или близкий service; использовать существующие `handler/MessageHandler.php`, `handler/CallbackQuery.php`.
  - Expected behavior: dispatcher принимает `Telegram\Bot\Objects\Update`, выбирает `MessageHandler` для user messages и `CallbackQuery` для callback_query, игнорирует неподдерживаемые updates без падения long-running процесса.
  - Dependency notes: логика выбора handler должна повторять текущую логику `index.php`, но вместо `throw new DomainException('Handle not set')` для неподдерживаемого update лучше безопасно пропускать update.
  - LOGGING REQUIREMENTS: minimal logging; логировать только неподдерживаемый update type и handler exceptions через `log_dump()`, без dump полного payload с персональными данными.

- [x] Task 3: Оставить `index.php` только для HTTP API и, при необходимости временной совместимости, убрать зависимость от webhook dispatch.
  - Files: изменить `index.php`.
  - Expected behavior: HTTP API endpoints продолжают работать как раньше; код после HTTP endpoints больше не вызывает `$telegram->getWebhookUpdate()` как основной путь обработки Telegram updates.
  - Dependency notes: если нужен безопасный переходный период, можно оставить webhook dispatch только за явным env-флагом вроде `ENABLE_TELEGRAM_WEBHOOK=1`, но default должен быть polling/no webhook handling.
  - LOGGING REQUIREMENTS: minimal logging; логировать только попытку использовать отключенный/legacy webhook path или критическую ошибку HTTP endpoint, не логировать секреты.

### Phase 2: Реализовать polling-процесс через SOCKS/proxy

- [x] Task 4: Реализовать постоянный polling loop в `polling.php`.
  - Files: изменить `polling.php`.
  - Expected behavior: CLI-процесс загружает autoload/helpers, создает runtime через общий factory, вызывает Telegram `getUpdates` с `offset`, `timeout` и `allowed_updates`, передает каждый update в dispatcher, после успешной обработки повышает offset на `update_id + 1`.
  - Dependency notes: polling должен использовать тот же `createTelegramApi()`/proxy-aware HTTP client path, чтобы Telegram API ходил через SOCKS в РФ; не создавать отдельный Guzzle client без proxy.
  - LOGGING REQUIREMENTS: minimal logging; логировать start/stop процесса, ошибку polling-запроса, ошибку обработки конкретного update id и восстановление после паузы; не логировать полный текст сообщений и фото URL.

- [x] Task 5: Добавить устойчивость long-running процесса.
  - Files: `polling.php`, при необходимости `services/TelegramUpdateDispatcher.php`.
  - Expected behavior: процесс не завершается от единичной ошибки Telegram API, backend API, Redis, MySQL или Yandex Disk; ошибки логируются, после короткой паузы loop продолжается. Добавить обработку SIGTERM/SIGINT, если расширение `pcntl` доступно, чтобы Docker/CLI мог корректно остановить процесс.
  - Dependency notes: offset повышать только после попытки обработки update; для необработанных/unsupported updates offset тоже должен продвигаться, чтобы не зациклиться.
  - LOGGING REQUIREMENTS: minimal logging; логировать только exception class/message, update id и факт sleep/backoff; не включать токены, proxy credentials, raw update payload.

- [x] Task 6: Добавить удобный способ запуска polling.
  - Files: `composer.json`, возможно `docker-compose.yml` если нужен отдельный service, `.env-example`.
  - Expected behavior: добавить Composer script вроде `polling`: `php polling.php`; при необходимости задокументировать команду `docker compose exec php-apache composer polling` или отдельный long-running контейнер/command без нарушения текущего web service.
  - Dependency notes: не ломать существующий script `user-update`; HTTP API должен продолжить обслуживаться Apache отдельно от polling-процесса.
  - LOGGING REQUIREMENTS: minimal logging; startup должен записать, что polling запущен, и режим proxy type без значения proxy URL.

### Phase 3: Документация и ручная проверка

- [x] Task 7: Обновить документацию и выполнить ручную проверку без автоматических тестов.
  - Files: `Readme.md`, `.env-example`, возможно `AGENTS.md` если структура запуска изменилась.
  - Expected behavior: документация объясняет, что Telegram updates теперь обрабатываются polling-процессом, HTTP API endpoints остаются web endpoints, какие env vars нужны (`BOT_TOKEN`, `TELEGRAM_PROXY`, `TELEGRAM_PROXY_TYPE`) и как запускать polling.
  - Manual verification: проверить `php -l polling.php`, `php -l index.php`, `composer dump-autoload` при добавлении новых classes, запуск polling в dev-окружении, POST `/message`, POST `/issued`, GET `/users`, `api/index.php?action=webhook-add-user`.
  - Dependency notes: автотесты не планируются по выбору пользователя; ручная проверка обязательна перед deploy.
  - LOGGING REQUIREMENTS: minimal logging; в docs указать, где смотреть `logs/log.txt` и какие события должны появляться при старте/ошибке polling.

## Edge Cases

- Telegram недоступен через прямое соединение: все методы Telegram SDK, включая `getUpdates`, `sendMessage`, `sendPhoto`, `getFile`, должны идти через текущий SOCKS/proxy configuration.
- Proxy переменные отсутствуют или неверны: polling должен завершиться с понятной ошибкой на старте или логировать ошибку и retry, но не раскрывать credentials.
- Unsupported update types: процесс должен продвигать offset и не зависать на одном update.
- Ошибка внутри handler на одном update: процесс логирует ошибку, продвигает offset согласно выбранной политике и продолжает loop.
- HTTP API параллельно с polling: web endpoints не должны ждать polling loop и не должны запускать бесконечный процесс внутри Apache request.
- Повторный запуск нескольких polling-процессов: в документации предупредить, что одновременно должен работать один polling worker на bot token, иначе возможны гонки обработки updates.

## Non-Goals

- Не переписывать проект на framework.
- Не менять бизнес-сценарии Telegram-бота, тексты сообщений, Redis state model и backend upload flow без отдельной задачи.
- Не менять контракты существующих HTTP API endpoints.
- Не добавлять автоматические тесты в рамках этого плана.
