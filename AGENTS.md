# AGENTS.md

> Карта проекта для AI-агентов и разработчиков. Обновляйте файл при существенном изменении структуры, входных точек или AI Factory артефактов.

## Обзор проекта

PHP Telegram-бот для обработки кодов доставки: HTTP API обслуживается Apache, а Telegram updates забираются отдельным polling worker через SOCKS/proxy. Подробное описание находится в `.ai-factory/DESCRIPTION.md`.

## Технологический стек

- **Язык программирования:** PHP 8.4
- **Фреймворк:** без полноценного фреймворка; собственный `index.php`
- **База данных:** MySQL через PDO
- **Кэш:** Redis через Predis
- **Интеграции:** Telegram Bot API, Яндекс Диск API, Google Таблица CSV, внешний backend API
- **Инфраструктура:** Dockerfile, Docker Compose, Apache, `telegram-polling` worker

## Структура проекта

```text
.
├── index.php                 # HTTP API front controller
├── polling.php               # Long-running Telegram polling worker
├── api/                      # Адаптеры внешних API и Telegram facade
├── bootstrap/                # Загрузка окружения
├── components/               # HTTP/proxy компоненты и Telegram UI helpers
├── console/                  # CLI-команды Composer scripts
├── data/                     # Статические данные компаний, адресов, изображений и заметок
├── db/                       # MySQL, PostgreSQL и Redis connection factories
├── enums/                    # Enum-состояния и статусы
├── handler/                  # Обработчики Telegram сообщений и callback-запросов
├── helpers/                  # Глобальные helper-функции
├── img/                      # Изображения для Telegram-сообщений
├── logs/                     # Runtime-логи
├── messages/                 # Тексты Telegram-сообщений
├── repositories/             # Доступ к данным и contracts
├── services/                 # Application services/use cases
├── Dockerfile                # PHP Apache image для приложения
├── docker-compose.yml        # PHP Apache, MySQL, Redis
└── composer.json             # Composer dependencies и PSR-4 autoload
```

## Ключевые входные точки

| Файл | Назначение |
|------|------------|
| `index.php` | Основная HTTP-точка входа для API endpoints; Telegram polling по умолчанию не запускает. |
| `polling.php` | CLI long-running worker для `getUpdates` через SOCKS/proxy. |
| `api/index.php` | Дополнительный API entrypoint. |
| `console/UsersUpdateCommand.php` | CLI-синхронизация пользователей через Composer script `user-update`. |
| `composer.json` | Зависимости, PSR-4 namespaces и scripts. |
| `docker-compose.yml` | Локальное окружение с `php-apache`, `mysql`, `redis`. |
| `.env-example` | Список переменных окружения без секретов. |

## Документация

| Документ | Путь | Описание |
|----------|------|----------|
| README | `Readme.md` | Краткие команды для добавления пользователя и создания Docker-сети. |

## AI Context Files

| Файл | Назначение |
|------|------------|
| `AGENTS.md` | Карта проекта и правила для AI-агентов. |
| `.ai-factory/DESCRIPTION.md` | Описание проекта, стек, паттерны и нефункциональные требования. |
| `.ai-factory/ARCHITECTURE.md` | Архитектурные правила и рекомендуемый паттерн. |
| `.ai-factory/rules/base.md` | Базовые соглашения, автоматически определенные по коду. |
| `.ai-factory/config.yaml` | Конфигурация AI Factory: языки, пути, workflow и git-настройки. |

## Правила для агентов

- Не объединять команды, если одна команда зависит от результата другой и может требовать отдельного разрешения.
- Неправильно: `git checkout master && git pull`.
- Правильно: сначала `git checkout master`, затем `git pull origin master`.
- Не реализовывать функциональность в рамках `$aif`; этот запуск только настраивает контекст, правила, навыки и архитектурные артефакты.
- Не коммитить `.env`, runtime-логи и реальные токены.
