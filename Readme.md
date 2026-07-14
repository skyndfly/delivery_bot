# Delivery Telegram Bot

## Режим работы

HTTP API обслуживается Apache через `index.php`. Telegram updates больше не должны приходить webhook-запросами в web endpoint: их забирает отдельный long-running polling-процесс `polling.php` через Telegram Bot API и настроенный SOCKS/proxy.

Одновременно должен работать только один polling-процесс на один `BOT_TOKEN`, иначе возможна гонка обработки updates.

## Переменные окружения

```dotenv
BOT_TOKEN=
DISK_TOKEN=
TABLE_URL=
API_BACK=http://hummingbird-nginx-1:80
TELEGRAM_PROXY=http://user:pass@host:port
TELEGRAM_PROXY_TYPE=socks5h
ENABLE_TELEGRAM_WEBHOOK=0
TELEGRAM_POLLING_TIMEOUT=50
TELEGRAM_HTTP_TIMEOUT=55
TELEGRAM_HTTP_CONNECT_TIMEOUT=10
TELEGRAM_POLLING_LIMIT=20
TELEGRAM_POLLING_RETRY_SLEEP=5
```

`TELEGRAM_PROXY` и `TELEGRAM_PROXY_TYPE` используются всеми обращениями к Telegram: отправкой сообщений, загрузкой файлов и polling-запросами `getUpdates`.

`TELEGRAM_POLLING_TIMEOUT` — timeout long polling для `getUpdates`. `TELEGRAM_HTTP_TIMEOUT` — общий transport timeout Guzzle для Telegram Bot API; он должен быть больше `TELEGRAM_POLLING_TIMEOUT`, иначе пустой long poll может обрываться как `cURL error 28`. `TELEGRAM_HTTP_CONNECT_TIMEOUT` отвечает только за установку соединения и должен оставаться коротким.

Если `TELEGRAM_HTTP_TIMEOUT` не задан или задан меньше/равным `TELEGRAM_POLLING_TIMEOUT`, приложение использует безопасное значение `TELEGRAM_POLLING_TIMEOUT + 5`. Логи не должны содержать `BOT_TOKEN`, пароль proxy или другие секреты.

Проверить timeout-конфигурацию без обращения к Telegram API:

```bash
composer telegram-timeout-check
```

## Запуск polling

Локально или внутри контейнера:

```bash
composer polling
```

Через Docker Compose:

```bash
docker compose up -d php-apache telegram-polling mysql redis
```

Смотреть события старта, остановки и ошибок polling можно в `logs/log.txt` по заголовку `TelegramPolling`.

## HTTP API

Добавить пользователя в базу:

```bash
curl -X GET "http://127.0.0.1:8003/api/index.php?action=webhook-add-user&id=1111"
```

Примеры web endpoints, которые остаются HTTP API:

```text
POST /issued
POST /cache/clear
GET /users
POST /message
POST /users/sync
```

## Docker сеть

Создать общую сеть:

```bash
docker network create shared-network
```
