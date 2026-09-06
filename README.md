# EXOPLANET DATA CHALLENGE API

Laravel 13 API (PHP 8.4). Sanctum cookie session. Postgres. MinIO. Mailpit.

## Run (Docker)

```bash
docker compose up --build
```

No `.env` required for Compose. Missing `APP_KEY` is generated on first PHP start and written to `.env`.

Wait until `php` is **healthy**, then:

- API: http://localhost:8080
- Health: http://localhost:8080/up and http://localhost:8080/api/v1/health/ready
- OpenAPI: http://localhost:8080/docs (redirects to `/docs/api`)
- Mailpit: http://localhost:8025
- Postgres (host tools): localhost:5433 (container is still 5432)
- MinIO console: http://localhost:9001 (`minio` / `minio12345`)

Seed admin: `SEED_ADMIN_EMAIL` / `SEED_ADMIN_PASSWORD` (min 10 chars). Default `admin@localhost` / `AdminPass1x`.

```bash
docker compose exec php php artisan db:seed
```

SPA: `GET /sanctum/csrf-cookie` then `POST /api/v1/auth/login` with credentials. No Bearer token.

OpenAPI Try It uses the same cookie session. Paths in the spec are `/api/v1/...` (not `/v1/...`). Call `GET /sanctum/csrf-cookie` then login before authenticated Try It requests.

### Port already allocated

```bash
HTTP_PORT=8081 POSTGRES_PORT=5434 docker compose up --build
```

Also set `APP_URL=http://localhost:8081` if you change `HTTP_PORT`.

| Variable | Default | Host bind |
| --- | --- | --- |
| `HTTP_PORT` | 8080 | API |
| `POSTGRES_PORT` | 5433 | Postgres |
| `MINIO_API_PORT` | 9000 | MinIO S3 |
| `MINIO_CONSOLE_PORT` | 9001 | MinIO UI |
| `MAILPIT_UI_PORT` | 8025 | Mailpit UI |
| `MAILPIT_SMTP_PORT` | 1025 | Mailpit SMTP |

### Docs shows `NOT_FOUND` / `correlationId: null`

That was a stale container or volume. From the repo root:

```bash
docker compose down
docker compose up --build
```

If it still 404s after a healthy `php` container:

```bash
docker compose down -v
docker compose up --build
```

`-v` deletes the `php_vendor` volume (safe). Recreates Postgres/MinIO data too.

### PHP exits 255

```bash
docker compose logs php --tail 80
```

Look after `postgres ready`. Windows: do not bind-mount `docker/entrypoint.sh`. Image already strips CR.

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

Host artisan against Docker Postgres: `.env` uses `DB_HOST=127.0.0.1` `DB_PORT=5433`. Compose **always** points app containers at `postgres:5432` and does not read host `DB_HOST`.

## Queue / schedule

Compose already runs `queue:work --tries=3 --timeout=120` and `schedule:work`.

Hourly: expire upload sessions. Daily: prune consumed tokens and sent outbox. Weekly: `queue:prune-failed`.

## Env (required)

`APP_KEY`, `FRONTEND_URL`, `AWS_BUCKET`, `DB_*`, `SANCTUM_STATEFUL_DOMAINS`.

Local browser PUTs use `AWS_URL=http://localhost:9000` (not `minio:9000`).
