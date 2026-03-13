# Telegram Tour Bot — Design Spec

## Overview

Telegram-бот для турагентства Pegas Touristik (Алматы). Менеджер отправляет ссылку с qui-quo.com в бот, система парсит туры, генерирует красивые продающие посты через Claude API, отправляет на одобрение, и публикует в Telegram-канал по расписанию.

## User Story

1. Менеджер кидает ссылку `qui-quo.com/XXXX-XXXX` в бот
2. Бот парсит страницу, извлекает все туры (отели, цены, даты, перелёты)
3. Каждый тур отправляется в Claude API для генерации продающего поста
4. Бот отправляет посты менеджеру один за другим с кнопками: Одобрить / Отклонить / Перегенерировать
5. При одобрении — менеджер выбирает дату и время публикации
6. В назначенное время бот публикует пост в Telegram-канал
7. Менеджер получает уведомление об успешной публикации

## Tech Stack

- **Backend:** Laravel 11, PHP 8.3
- **Telegram SDK:** nutgram/nutgram (webhook mode)
- **HTML Parser:** symfony/dom-crawler
- **HTTP Client:** guzzlehttp/guzzle
- **AI:** Claude API (claude-sonnet-4-6) для генерации текстов
- **Database:** MySQL
- **Queue:** Laravel Queue + Redis driver
- **Scheduler:** Laravel Scheduler (резервная проверка публикаций)
- **Hosting:** VPS с ISP Manager (существующий сервер с Laravel-проектами)

## Database Schema

### users
| Column | Type | Description |
|--------|------|-------------|
| id | bigint, PK | Auto-increment |
| telegram_id | bigint, unique | Telegram user ID |
| name | string | Имя менеджера |
| is_admin | boolean, default false | Администратор (может добавлять менеджеров) |
| is_active | boolean, default true | Доступ к боту |
| created_at | timestamp | |
| updated_at | timestamp | |

### tour_batches
| Column | Type | Description |
|--------|------|-------------|
| id | bigint, PK | Auto-increment |
| user_id | bigint, FK → users.id | Кто отправил ссылку |
| source_url | string | Ссылка qui-quo |
| created_at | timestamp | |
| updated_at | timestamp | |

### tours
| Column | Type | Description |
|--------|------|-------------|
| id | bigint, PK | Auto-increment |
| batch_id | bigint, FK → tour_batches.id | Группа из одной ссылки |
| hotel_name | string | Название отеля |
| stars | tinyint | Звёздность |
| country | string | Страна |
| location | string | Курорт / город |
| departure_city | string | Город вылета |
| airline | string | Авиакомпания |
| flight_out | datetime | Дата/время вылета |
| flight_back | datetime | Дата/время обратного рейса |
| nights | string | Количество ночей (напр. "5+1") |
| room_type | string | Тип номера |
| meal_plan | string | Тип питания (BB, AI, HB и т.д.) |
| guests | string | Размещение (напр. "2 взрослых") |
| price | bigint | Цена в тенге |
| amenities | json | Удобства отеля |
| raw_data | json | Полные сырые данные |
| created_at | timestamp | |
| updated_at | timestamp | |

### posts
| Column | Type | Description |
|--------|------|-------------|
| id | bigint, PK | Auto-increment |
| tour_id | bigint, FK → tours.id | Связь с туром |
| user_id | bigint, FK → users.id | Кто создал |
| generated_text | text | Текст поста от Claude |
| status | enum: draft, approved, scheduled, published, rejected | Статус поста |
| regeneration_count | tinyint, default 0 | Счётчик перегенераций (макс 3) |
| publish_at | datetime, nullable | Запланированное время публикации |
| published_at | datetime, nullable | Фактическое время публикации |
| telegram_message_id | bigint, nullable | ID сообщения в канале после публикации |
| created_at | timestamp | |
| updated_at | timestamp | |

## Architecture

```
Менеджер → Telegram Bot → Webhook (POST /api/telegram/webhook)
                                    ↓
                            TelegramController
                                    ↓
                          LinkHandler (парсинг ссылки)
                                    ↓
                          QuiQuoParser → загрузка и парсинг HTML
                                    ↓
                          ClaudeService → генерация поста
                                    ↓
                          Отправка превью в бот (inline-кнопки)
                                    ↓
                    ┌───────────────┼────────────────┐
                    ↓               ↓                ↓
              ApproveHandler   RejectHandler   RegenerateHandler
                    ↓                                ↓
            ScheduleHandler                   ClaudeService (заново)
                    ↓
            PublishPostJob (delayed dispatch в очередь)
                    ↓
            TelegramPublisher → отправка в канал
                    ↓
            Уведомление менеджеру
```

## Project Structure

```
app/
├── Http/
│   └── Controllers/
│       └── TelegramWebhookController.php
├── Services/
│   ├── QuiQuoParser.php           — парсинг HTML страниц qui-quo.com
│   ├── ClaudeService.php          — генерация текстов через Claude API
│   └── TelegramPublisher.php      — публикация постов в канал
├── Telegram/
│   └── Handlers/
│       ├── LinkHandler.php        — обработка входящих ссылок
│       ├── ApproveHandler.php     — одобрение поста
│       ├── RejectHandler.php      — отклонение поста
│       ├── RegenerateHandler.php  — перегенерация текста
│       └── ScheduleHandler.php    — выбор времени публикации
├── Jobs/
│   └── PublishPostJob.php         — отложенная публикация
├── Models/
│   ├── User.php
│   ├── TourBatch.php
│   ├── Tour.php
│   └── Post.php
├── Console/
│   └── Commands/
│       └── CheckStuckPostsCommand.php  — проверка застрявших постов
```

## Key Components

### QuiQuoParser
- Загружает HTML по ссылке через Guzzle
- Парсит DOM через symfony/dom-crawler
- Извлекает: отель, звёзды, страна, даты, перелёт, цена, питание, номер, удобства
- Возвращает массив структурированных данных
- Сохраняет каждый тур в таблицу `tours`

### ClaudeService
- Принимает данные тура из модели Tour
- Отправляет в Claude API (claude-sonnet-4-6) с system prompt
- System prompt содержит стиль постов (эмодзи, структура, продающий тон)
- Пример стиля зашит в конфиг (на основе шаблона от пользователя)
- Возвращает готовый текст поста
- Сохраняет в таблицу `posts` со статусом `draft`

### Claude System Prompt (конфиг)
```
Ты — копирайтер турагентства. Генерируй продающие посты для Telegram-канала.
Стиль: эмодзи, структурированный, с ценой и деталями перелёта.
Формат поста:
- Заголовок с огоньком и страной
- Отель и звёзды
- Детали перелёта (город, даты, авиакомпания)
- Ночи, номер, питание
- Удобства отеля
- Цена (общая и на человека)
- Призыв к действию с контактом
```

### Telegram Handlers

**LinkHandler:**
- Проверяет что отправитель есть в `users` и `is_active`
- Валидирует ссылку (qui-quo.com/*)
- Запускает парсинг и генерацию
- Отправляет превью постов с inline-кнопками

**ApproveHandler:**
- Callback query: `approve:{post_id}`
- Идемпотентность: проверяет что статус = `draft` перед переходом (lockForUpdate)
- Меняет статус поста на `approved`
- Показывает кнопки выбора времени

**ScheduleHandler:**
- Быстрый выбор: "Через 1 час", "Сегодня 18:00", "Завтра 10:00"
- Ручной ввод: формат `ДД.ММ ЧЧ:ММ` (интерпретируется как Asia/Almaty, хранится в UTC)
- Меняет статус на `scheduled`
- Создаёт `PublishPostJob::dispatch()->delay($publishAt)`

**RejectHandler:**
- Callback query: `reject:{post_id}`
- Меняет статус на `rejected`
- Удаляет превью из чата

**RegenerateHandler:**
- Callback query: `regenerate:{post_id}`
- Проверяет `regeneration_count < 3`, иначе сообщает что лимит исчерпан
- Вызывает ClaudeService заново
- Обновляет текст в `posts`, инкрементирует `regeneration_count`
- Отправляет новое превью

### PublishPostJob
- Проверяет что статус = `scheduled` (защита от дублей)
- Отправляет `generated_text` в канал через Telegram Bot API
- Обновляет `published_at` и `telegram_message_id`
- Меняет статус на `published`
- Отправляет уведомление менеджеру в бот

### CheckStuckPostsCommand
- Artisan command в Laravel Scheduler (каждые 5 минут)
- Находит посты со статусом `scheduled` и `publish_at` в прошлом
- Перезапускает PublishPostJob для застрявших

## Error Handling

- **qui-quo.com недоступен / 5xx:** бот отвечает "Не удалось загрузить страницу, попробуйте позже"
- **Невалидная ссылка / 0 туров:** бот отвечает "Туры не найдены по этой ссылке"
- **Claude API ошибка:** бот отвечает "Не удалось сгенерировать пост, попробуйте /retry"
- **Публикация не удалась:** статус остаётся `scheduled`, CheckStuckPostsCommand подхватит
- **Telegram message > 4096 символов:** ClaudeService промпт ограничивает длину; TelegramPublisher валидирует перед отправкой

## Timezone

- Все datetime в БД хранятся в UTC
- Пользовательский ввод времени интерпретируется как Asia/Almaty (UTC+6)
- Laravel config `app.timezone = 'UTC'`

## Webhook Security

- Nutgram secret token verification (X-Telegram-Bot-Api-Secret-Token)
- Настраивается через `TELEGRAM_WEBHOOK_SECRET` в .env

## Auth & Access Control

- Первый пользователь (ты) — `is_admin = true`, захардкожен по Telegram ID в .env
- Админ может добавлять менеджеров командой `/add {telegram_id} {name}`
- Админ может деактивировать: `/remove {telegram_id}`
- Все handler'ы проверяют `is_active` перед обработкой

## Config (.env)

```
TELEGRAM_BOT_TOKEN=xxx
TELEGRAM_CHANNEL_ID=@channel_username
TELEGRAM_ADMIN_ID=123456789
CLAUDE_API_KEY=xxx
CLAUDE_MODEL=claude-sonnet-4-6
TELEGRAM_WEBHOOK_SECRET=xxx
APP_TIMEZONE=UTC
```

## Deployment

- Git push на VPS
- `composer install --no-dev`
- `php artisan migrate`
- `php artisan queue:work redis` (supervisor для перезапуска)
- Webhook: `php artisan nutgram:hook:set https://domain.com/api/telegram/webhook`
- Cron: `* * * * * php artisan schedule:run`
