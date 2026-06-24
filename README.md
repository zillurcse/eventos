# EventOS — Dockerized Development Environment

Multi-tenant SaaS event-management platform. This repo is the **orchestration root**:
one `docker-compose.yml` runs the whole stack locally.

```
eventos-api/     Laravel 13 API (PHP 8.3) — domain logic, builders, billing, queues, real-time
eventos-admin/   Nuxt 4 SPA — Super-Admin control plane
eventos-event/   Nuxt 4 SPA — Organizer + attendee experience
docker/          Shared container configs (postgres init, nginx vhost)
```

## Prerequisites

- **Docker Desktop** running (Linux engine). Everything else (PHP 8.3, Node 20, Postgres 16,
  Redis) runs in containers — you do **not** need them on the host.

> ⚠️ **OneDrive warning.** This project currently lives under `OneDrive\…`. Dependencies
> (`vendor/`, `node_modules/`) are deliberately kept in **Docker named volumes**, never on the
> host, so OneDrive won't try to sync tens of thousands of files. For best performance and to
> avoid sync/file-lock issues, consider moving this folder **out of OneDrive**, or exclude
> `vendor/`, `node_modules/`, and `eventos-api/storage/` from OneDrive sync.

## Quick start

```bash
cp .env.example .env                 # one time (already present in this checkout)
docker compose up -d                 # build + start the core stack
docker compose ps                    # watch services become healthy
curl http://localhost:8080/api/v1/health
```

First boot pulls images and runs `composer install` into a volume, so it takes a few minutes.
The `api` service installs dependencies and runs migrations automatically; the other PHP
services (`horizon`, `reverb`, `scheduler`) wait for that to finish.

## Services & URLs

| URL                              | What                          |
|----------------------------------|-------------------------------|
| http://localhost:8080/api/v1     | Laravel API (via Nginx)       |
| http://localhost:3000            | Admin SPA (Nuxt)              |
| http://localhost:3001            | Event SPA (Nuxt)             |
| http://localhost:8025            | Mailpit (sent mail)           |
| http://localhost:9001            | MinIO console (S3)            |
| ws://localhost:8081              | Reverb (WebSockets)           |
| http://localhost:8090            | Adminer (`--profile tools`)   |
| http://localhost:7700            | Meilisearch (`--profile search`) |

Postgres → `localhost:5433`, Redis → `localhost:6380` (host ports shifted to avoid clashing
with a local Laragon install).

## Common commands

With `make` (optional): `make help`, `make up`, `make logs`, `make shell`, `make migrate`,
`make fresh`, `make tinker`, `make psql-app`.

Without `make`:

```bash
docker compose up -d
docker compose logs -f api nginx
docker compose exec api bash
docker compose exec api php artisan migrate --database=pgsql_admin
docker compose exec api php artisan tinker
```

## Multi-tenancy & Row-Level Security

Two Postgres roles enforce tenant isolation (architecture §4):

- **`eventos_migrator`** — owns tables, `BYPASSRLS`. Runs migrations & seeders
  (Laravel connection `pgsql_admin`).
- **`eventos_app`** — the runtime role, **subject to RLS**. Every request sets
  `app.current_organization` and the `tenant_isolation` policy filters every tenant table.

Superusers bypass RLS, which is why the app never connects as one. Test it:

```sql
-- make psql-app
SET app.current_organization = '1';
SELECT id, organization_id, name FROM events;   -- only org 1's rows
```

## Profiles

```bash
docker compose --profile tools up -d     # + Adminer
docker compose --profile search up -d    # + Meilisearch
```

## Reset everything

```bash
docker compose down -v    # ⚠️ removes volumes (DB, MinIO, deps) — full clean slate
```
