# EXOPLANET DATA CHALLENGE API

Laravel 13 API (PHP 8.4). Sanctum cookie session. Postgres. MinIO. Mailpit.

## Run (Docker)

```bash
cp .env.example .env
# set APP_KEY after first php container start, or:
docker compose run --rm php php artisan key:generate
docker compose up --build
```

- API: http://localhost:8080
- Health: http://localhost:8080/up and http://localhost:8080/api/v1/health/ready
- OpenAPI (local): http://localhost:8080/docs or http://localhost:8080/docs/api
- Mailpit: http://localhost:8025
- Postgres (host): localhost:5433 (container still 5432)
- MinIO console: http://localhost:9001 (`minio` / `minio12345`)

Seed admin: `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` (min 10 chars). Default `admin@localhost` / `AdminPass1x`.

```bash
docker compose exec php php artisan db:seed
```

SPA: `GET /sanctum/csrf-cookie` then `POST /api/v1/auth/login` with credentials. No Bearer token.

## Local tests (no Docker)

PHP 8.3+ and Composer.

```bash
cp .env.example .env
php artisan key:generate
composer install
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse
```

Pest uses sqlite `:memory:`. Schema stays Postgres-compatible.

Windows: bind-mount hides image `vendor`. Compose uses named volume `php_vendor` and runs `composer install` if autoload is missing. First `docker compose up --build` can take a few minutes. If PHP exits 255: `docker compose logs php` after `postgres ready`. Empty `APP_KEY` is generated in the container.

## Queue / schedule

Compose already runs `queue:work --tries=3 --timeout=120` and `schedule:work`.

Hourly: expire upload sessions. Daily: prune consumed tokens and sent outbox. Weekly: `queue:prune-failed`.

## Env (required)

`APP_KEY`, `FRONTEND_URL`, `AWS_BUCKET`, `DB_*`, `SANCTUM_STATEFUL_DOMAINS`.

Local browser PUTs use `AWS_URL=http://localhost:9000` (not `minio:9000`).
