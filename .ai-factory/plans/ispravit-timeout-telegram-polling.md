# План реализации: исправить timeout Telegram polling

Branch: polling
Created: 2026-07-14

## Settings
- Testing: no
- Logging: verbose
- Docs: yes

## Roadmap Linkage
Milestone: "none"
Rationale: "Связь с roadmap пропущена: файл `.ai-factory/ROADMAP.md` отсутствует или не содержит активных milestone."

## Context

В логах polling worker повторяется `cURL error 28: Operation timed out after 30002 milliseconds with 0 bytes received` при запросе к `https://api.telegram.org/...`.

Наиболее вероятная причина в текущем коде:
- `polling.php` передает в `getUpdates` параметр `timeout` из `TELEGRAM_POLLING_TIMEOUT`, по умолчанию `50`.
- `components/HttpClient.php` создает Guzzle client с общим `timeout = 30`.
- При long polling Telegram может держать соединение до 50 секунд без ответа, но локальный HTTP transport обрывает запрос через 30 секунд.

Важное ограничение диагностики:
- HTTP-сценарии уведомления пользователя с сайта работают: `index.php` для `/issued` создает Telegram API через `TelegramBotRuntimeFactory::createTelegramApi()` и выполняет короткий `sendMessage`.
- Значит proxy route и базовая авторизация Telegram Bot API, скорее всего, рабочие.
- Исправление не должно менять proxy как первопричину. Фокус — различие между короткими Telegram send-запросами и долгим `getUpdates` long polling, где transport timeout должен быть больше polling timeout.

Цель плана: сделать transport timeout Telegram API управляемым и гарантированно больше long polling timeout, чтобы пустой long poll не считался ошибкой каждые 30 секунд.

## Commit Plan
- **Commit 1** (после задач 1-3): `fix: align telegram polling transport timeout`
- **Commit 2** (после задач 4-5): `test: cover telegram polling timeout configuration`

## Tasks

### Phase 1: Диагностика и конфигурация
- [x] Task 1: Зафиксировать текущую модель timeout для Telegram HTTP transport.
  - Deliverable: краткий кодовый комментарий или self-documenting имена в `components/HttpClient.php` / `services/TelegramBotRuntimeFactory.php`, которые показывают различие между `TELEGRAM_POLLING_TIMEOUT`, Guzzle `timeout`, `connect_timeout` и короткими `sendMessage` / `sendPhoto` запросами.
  - Expected behavior: разработчик видит, почему proxy может работать для уведомлений с сайта, но `getUpdates` все равно падает при слишком коротком transport timeout.
  - Files: `components/HttpClient.php`, `services/TelegramBotRuntimeFactory.php`, `index.php` только для проверки integration point без рефакторинга.
  - Logging requirements: добавить DEBUG/INFO лог при создании Telegram HTTP client с итоговыми значениями `timeout`, `connect_timeout`, proxy type и caller context (`polling` или short request), если это можно сделать без большого рефакторинга; не логировать proxy credentials и `BOT_TOKEN`.

- [x] Task 2: Добавить env-driven настройки Telegram HTTP timeout.
  - Deliverable: поддержать переменные вроде `TELEGRAM_HTTP_TIMEOUT` и `TELEGRAM_HTTP_CONNECT_TIMEOUT` на уровне composition root.
  - Expected behavior: если переменные не заданы, transport timeout автоматически выбирается не меньше `TELEGRAM_POLLING_TIMEOUT + safety_margin`; `connect_timeout` остается коротким и управляемым.
  - Files: `services/TelegramBotRuntimeFactory.php`, `components/HttpClient.php`, `.env-example`.
  - Dependency notes: зависит от понимания Task 1, чтобы не смешать long polling timeout с connect timeout.
  - Logging requirements: INFO логировать рассчитанные значения timeout; WARN логировать исправление некорректных env значений, например `TELEGRAM_HTTP_TIMEOUT <= TELEGRAM_POLLING_TIMEOUT`.

- [x] Task 3: Обновить `components\HttpClient`, чтобы timeout можно было передавать до создания Guzzle client.
  - Deliverable: конструктор или фабричный путь принимает явные `timeout` / `connect_timeout` без позднего `setTimeOut()`, который сейчас не меняет уже созданный Guzzle client.
  - Expected behavior: итоговый Guzzle client реально использует новые значения; существующие proxy `curl` options сохраняются.
  - Files: `components/HttpClient.php`, `services/TelegramBotRuntimeFactory.php`.
  - Dependency notes: зависит от Task 2.
  - Logging requirements: ERROR лог при невозможности создать client должен включать класс исключения и sanitized контекст timeout/proxy type.

### Phase 2: Поведение polling worker
- [x] Task 4: Уточнить обработку timeout ошибок в `polling.php`.
  - Deliverable: разделить ожидаемые transport timeouts от других Telegram failures там, где это возможно без привязки к конкретной реализации SDK.
  - Expected behavior: после исправления timeout mismatch обычный пустой long poll не пишет ошибку каждые 30 секунд; при реальной сетевой проблеме worker продолжает retry с `TELEGRAM_POLLING_RETRY_SLEEP`; успешная работа `/issued` не рассматривается как повод менять proxy-настройки.
  - Files: `polling.php`, при необходимости `components/HttpClient.php`.
  - Dependency notes: зависит от Task 3.
  - Logging requirements: WARN логировать retry с sanitized причиной, длительностью sleep и текущими timeout settings; DEBUG логировать успешный пустой poll без update только если включен verbose режим.

### Phase 3: Проверка и документация
- [x] Task 5: Добавить проверку конфигурации timeout без обращения к реальному Telegram API.
  - Deliverable: тест или lightweight CLI/manual verification script, который создает Telegram HTTP client с env значениями и проверяет итоговые Guzzle options через доступный в проекте подход.
  - Expected behavior: проверка падает, если transport timeout меньше или равен polling timeout; проверка проходит с дефолтами `.env-example`.
  - Files: новый файл в подходящем месте проекта, либо Composer script в `composer.json`, если выбран CLI-подход.
  - Dependency notes: зависит от Task 2 и Task 3.
  - Logging requirements: INFO логировать результат проверки и рассчитанные значения; ERROR логировать конкретную причину fail без секретов.

- [x] Task 6: Обновить документацию по переменным окружения polling worker.
  - Deliverable: описать `TELEGRAM_POLLING_TIMEOUT`, `TELEGRAM_HTTP_TIMEOUT`, `TELEGRAM_HTTP_CONNECT_TIMEOUT`, `TELEGRAM_POLLING_RETRY_SLEEP` и правило `TELEGRAM_HTTP_TIMEOUT > TELEGRAM_POLLING_TIMEOUT`.
  - Expected behavior: локальный запуск и production настройки можно восстановить без чтения кода.
  - Files: `Readme.md`, `.env-example`.
  - Dependency notes: зависит от Task 2.
  - Logging requirements: документация должна явно указать, что логи не должны содержать `BOT_TOKEN`, proxy password и другие секреты.

## Acceptance Criteria
- При дефолтном `TELEGRAM_POLLING_TIMEOUT=50` Guzzle `timeout` для Telegram API больше 50 секунд.
- `connect_timeout` остается отдельной настройкой и не становится равным long polling timeout.
- Proxy options из `TelegramProxy::buildGuzzleOptions()` сохраняются, включая `socks5h`.
- Исправление не ломает рабочий сценарий уведомлений с сайта через `/issued` / короткие `sendMessage` запросы.
- Диагностика явно различает "proxy не работает" и "long polling transport timeout короче Telegram polling timeout"; рабочие уведомления с сайта считаются evidence в пользу второго варианта.
- Логи polling worker содержат достаточно контекста для диагностики timeout, но не раскрывают токены и proxy credentials.
- `.env-example` и документация отражают новые переменные и безопасные значения.
- Проверка timeout конфигурации выполняется без реального запроса к Telegram.
