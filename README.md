# Log Service

Symfony микросервис для приёма логов через HTTP API и публикации в RabbitMQ.

## Требования

- Docker 24+
- Docker Compose v2

## Запуск

```bash
cp .env.example .env
docker compose build
docker compose up -d
```

Дождитесь инициализации RabbitMQ (~20 секунд), затем проверьте:

```bash
docker compose ps
docker compose logs app
```

## Запуск тестов

```bash
# Все тесты
docker compose exec app php vendor/bin/phpunit

# Только unit тесты
docker compose exec app php vendor/bin/phpunit tests/Unit/

# Только интеграционные тесты
docker compose exec app php vendor/bin/phpunit tests/Integration/
```

Или локально (требуется PHP 8.4+ и Composer):

```bash
composer install --ignore-platform-req=ext-amqp
APP_ENV=test php vendor/bin/phpunit
```

## API

### POST /api/logs/ingest

Принимает batch логов в формате JSON. Максимум 1000 логов в одном запросе.

**Запрос:**

```bash
curl -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "2026-03-04T12:00:00+00:00",
        "level": "info",
        "service": "auth-service",
        "message": "User logged in successfully",
        "context": {"user_id": 42, "ip": "192.168.1.1"}
      },
      {
        "timestamp": "2026-03-04T12:00:01+00:00",
        "level": "error",
        "service": "payment-service",
        "message": "Payment gateway timeout",
        "context": {"order_id": "ORD-9981", "gateway": "stripe"}
      }
    ]
  }'
```

**Ответ 202 Accepted:**

```json
{
  "status": "accepted",
  "batch_id": "batch_550e8400e29b41d4a716446655440000",
  "logs_count": 2
}
```

**Ответ 400 Bad Request (ошибка валидации):**

```bash
curl -X POST http://localhost:8080/api/logs/ingest \
  -H "Content-Type: application/json" \
  -d '{
    "logs": [
      {
        "timestamp": "not-a-date",
        "level": "INVALID",
        "service": "",
        "message": "test"
      }
    ]
  }'
```

```json
{
  "status": "error",
  "message": "Validation failed for one or more log entries.",
  "errors": [
    {
      "index": 0,
      "violations": [
        {"field": "timestamp", "message": "Field \"timestamp\" must be a valid ISO 8601 datetime."},
        {"field": "level", "message": "Field \"level\" must be a valid PSR-3 log level."},
        {"field": "service", "message": "Field \"service\" is required."}
      ]
    }
  ]
}
```

### Допустимые уровни логирования

`debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`

### Обязательные поля

| Поле | Тип | Описание |
|------|-----|----------|
| timestamp | string | ISO 8601 datetime (например, `2026-03-04T12:00:00+00:00`) |
| level | string | PSR-3 log level |
| service | string | Имя сервиса (max 255 символов) |
| message | string | Текст сообщения |

### Опциональные поля

| Поле | Тип | Описание |
|------|-----|----------|
| context | object | Произвольный контекст лога |
| trace_id | string | ID трейса для связывания логов |

## RabbitMQ Management UI

http://localhost:15672

Логин: `guest` / Пароль: `guest`

## Инфраструктура

- **App**: PHP 8.4-FPM + Nginx, порт 8080
- **RabbitMQ**: 3.12 с Management UI, порты 5672 (AMQP) + 15672 (Management)
- **Очередь**: `logs.ingest` (direct exchange, durable)

## Окружения

| Переменная | dev | test | prod |
|-----------|-----|------|------|
| APP_ENV | dev | test | prod |
| MESSENGER_TRANSPORT_DSN | amqp://... | in-memory:// | amqp://... |

## Структура проекта

```
log-service/
├── src/
│   ├── Controller/LogIngestionController.php
│   ├── DTO/LogEntry.php, LogIngestionRequest.php, LogIngestionResult.php
│   ├── Enum/LogLevel.php
│   ├── EventListener/ApiExceptionListener.php
│   ├── Exception/LogIngestionException.php, ValidationException.php
│   ├── Message/ProcessLogBatchMessage.php
│   ├── MessageHandler/ProcessLogBatchMessageHandler.php
│   └── Service/LogValidator.php, LogIngestionService.php
├── tests/
│   ├── Unit/Service/LogValidatorTest.php, LogIngestionServiceTest.php
│   ├── Unit/Enum/LogLevelTest.php
│   ├── Unit/EventListener/ApiExceptionListenerTest.php
│   └── Integration/Controller/LogIngestionControllerTest.php
├── config/packages/messenger.yaml
├── docker/
│   ├── php/Dockerfile
│   ├── nginx/default.conf
│   ├── supervisor/supervisord.conf
│   └── rabbitmq/
├── docker-compose.yml
└── .env.example
```
