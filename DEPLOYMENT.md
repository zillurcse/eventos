# EventOS — Production Deployment Guide (VPS + cPanel/WHM)

Target: a VPS you administer yourself (root SSH), with cPanel/WHM installed for DNS, mail, and AutoSSL.
Base domain **expouse.com**, admin app on **admin.expouse.com**, every event on **`<subdomain>.expouse.com`**.

This guide mirrors the repo's existing `docker-compose.yml` almost exactly, adapted for production, and uses
cPanel only for what it's good at (DNS, SSL certs, static file hosting) — not for running PHP/Node processes.

---

## 0. Architecture

| Domain | What serves it | How |
|---|---|---|
| `admin.expouse.com` | `eventos-admin` (Nuxt SPA, `ssr:false`) | **Static files**, built once with `nuxt generate`, hosted directly by cPanel as a normal subdomain |
| `*.expouse.com` | `eventos-event` (Nuxt SPA, `ssr:false`) | **Static files**, same build, hosted as a cPanel **wildcard** subdomain (the app reads the hostname client-side to pick the event) |
| `api.expouse.com` | `eventos-api` (Laravel) | Docker container (php-fpm + nginx), bound to `127.0.0.1:8080`; cPanel's Apache reverse-proxies to it and terminates TLS |
| `api.expouse.com` (WebSocket) | Reverb | Docker container on `127.0.0.1:8081`; same Apache vhost proxies the `/app` and `/apps` paths with `mod_proxy_wstunnel` |
| `cdn.expouse.com` | MinIO (S3-compatible object storage) | Docker container on `127.0.0.1:9000`; Apache reverse-proxies plain HTTP, used for uploaded images/documents |
| `livekit.expouse.com` *(optional — breakout-room video)* | LiveKit SFU | Docker container; signaling proxied like Reverb, but the RTC **media** (UDP 50000-50100) is opened directly on the firewall — Apache can't proxy UDP |
| `expouse.com` / `www.expouse.com` | whatever marketing page you want later | not required for the app itself; simplest is a redirect to `admin.expouse.com` for now |

Why static hosting for the two Nuxt apps: both already have `ssr: false` in `nuxt.config.ts`, so
`nuxt generate` prerenders them to plain HTML/CSS/JS. No Node process needs to run in production for
either app — a big simplification over the dev Dockerfiles (which just run `nuxt dev`).

Both apps authenticate against the API with a `Authorization: Bearer <token>` header
(`useApi.ts`), not cookies — so you don't need to fight cross-subdomain cookie/CORS rules, just make
sure the API is reachable from each domain.

---

## 1. Prerequisites

- Root SSH access to the VPS (confirmed).
- cPanel/WHM access for DNS (Zone Editor) and Apache (Include Editor) and AutoSSL.
- Domain `expouse.com` already pointed at this VPS's nameservers / the zone is editable in cPanel.
- Your Windows dev machine has the code; push it to a **git remote** (GitHub/GitLab/etc. — private repo)
  so you can `git clone`/`git pull` on the VPS. Don't hand-copy files over OneDrive-synced paths.

---

## 2. DNS records (cPanel → Zone Editor)

Add these **A records**, all pointing at the VPS's public IP (call it `VPS_IP`):

| Name | Type | Value |
|---|---|---|
| `admin.expouse.com` | A | `VPS_IP` |
| `*.expouse.com` | A | `VPS_IP` |
| `api.expouse.com` | A | `VPS_IP` |
| `cdn.expouse.com` | A | `VPS_IP` |
| `livekit.expouse.com` *(if using video rooms)* | A | `VPS_IP` |
| `cname.expouse.com` | A | `VPS_IP` |
| `expouse.com`, `www.expouse.com` | A / CNAME | `VPS_IP` (or wherever your marketing page will live) |

`cname.expouse.com` is the target the app already tells *organizers* to CNAME their own custom domain
to (see `eventos-api/config/eventos.php` → `domain.cname_target`) — it's part of the existing
custom-domain feature, not something new to build.

DNS can take a few minutes to a few hours to propagate — kick this off first, verify with
`dig admin.expouse.com` before moving on.

---

## 3. Server preparation (SSH)

```bash
ssh root@VPS_IP

# Docker Engine + Compose plugin (Debian/Ubuntu; adjust for your VPS OS)
curl -fsSL https://get.docker.com | sh
systemctl enable --now docker
docker compose version   # sanity check

# Swap helps if the VPS has <4GB RAM (composer install / npm generate are memory-hungry)
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

If the VPS runs **CSF/ConfigServer Firewall** (common alongside cPanel), open the ports you'll need
publicly (see §9 for the full table) via WHM → *ConfigServer Security & Firewall* → Firewall
Configuration, or edit `/etc/csf/csf.conf` `TCP_IN`/`UDP_IN` directly and `csf -r`.

---

## 4. Get the code onto the VPS

```bash
mkdir -p /opt/eventos && cd /opt/eventos
git clone <your-private-repo-url> .
```

Everything below assumes `/opt/eventos` as the deploy path.

---

## 5. Production secrets

Generate strong values for everything below — **never reuse the dev defaults**
(`postgres`, `migrator_secret`, `app_secret`, `devkey`/`devsecret_change_me_min_32_chars_`,
`eventos_secret`, `masterKey123456` are all fine for `docker-compose.yml` on your laptop and
must never touch a public server).

```bash
# generate random secrets as you go, e.g.:
openssl rand -base64 32   # run once per secret you need below
```

### 5a. Root `.env` (feeds `docker-compose.yml` — the compose file itself doesn't change)

```bash
cp .env.example .env
```

Edit `/opt/eventos/.env`:

```dotenv
COMPOSE_PROJECT_NAME=eventos

POSTGRES_DB=eventos
POSTGRES_USER=postgres
POSTGRES_PASSWORD=<random>
EVENTOS_MIGRATOR_PASSWORD=<random>
EVENTOS_APP_PASSWORD=<random>
PG_PORT=5433                 # fine to leave — only reachable via 127.0.0.1 anyway (see §8)

REDIS_PORT=6380

API_HTTP_PORT=8080            # nginx container — Apache will proxy to 127.0.0.1:8080
REVERB_PORT=8081              # Apache proxies WS traffic to 127.0.0.1:8081

LIVEKIT_PORT=7880
LIVEKIT_RTC_TCP_PORT=7881
LIVEKIT_NODE_IP=<VPS_IP>                 # public IP — LiveKit stamps this into ICE candidates
LIVEKIT_URL=wss://livekit.expouse.com    # what browsers connect to (see §8)
LIVEKIT_HOST=http://livekit:7880         # server-to-server, stays internal
LIVEKIT_API_KEY=<random>
LIVEKIT_API_SECRET=<random 32+ chars>

MINIO_ROOT_USER=<random>
MINIO_ROOT_PASSWORD=<random>
MINIO_BUCKET=eventos

ADMIN_PORT=3000    # unused in prod — the admin container isn't run (see §7)
EVENT_PORT=3001    # unused in prod — the event container isn't run (see §7)
```

Also edit `docker/livekit/livekit.yaml`:

```yaml
port: 7880
rtc:
  tcp_port: 7881
  port_range_start: 50000
  port_range_end: 50100
  use_external_ip: true        # ← change from false; required for real internet clients
keys:
  <LIVEKIT_API_KEY>: <LIVEKIT_API_SECRET>   # must match .env exactly
```

### 5b. `eventos-api/.env` (the Laravel app's own env — separate from the root one)

```bash
cp eventos-api/.env.example eventos-api/.env
```

Key production values to set in `eventos-api/.env`:

```dotenv
APP_NAME=EventOS
APP_ENV=production
APP_KEY=                      # leave blank, generate in §6
APP_DEBUG=false
APP_URL=https://api.expouse.com

PLATFORM_APEX=expouse.com
PLATFORM_CNAME_TARGET=cname.expouse.com
PLATFORM_INGRESS_IP=<VPS_IP>

LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=eventos
DB_USERNAME=eventos_app
DB_PASSWORD=<same as EVENTOS_APP_PASSWORD above>
DB_ADMIN_USERNAME=eventos_migrator
DB_ADMIN_PASSWORD=<same as EVENTOS_MIGRATOR_PASSWORD above>

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
BROADCAST_CONNECTION=reverb

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null

REVERB_APP_ID=<random>
REVERB_APP_KEY=<random>
REVERB_APP_SECRET=<random>
REVERB_HOST=api.expouse.com
REVERB_PORT=443
REVERB_SCHEME=https

LIVEKIT_URL=wss://livekit.expouse.com
LIVEKIT_HOST=http://livekit:7880
LIVEKIT_API_KEY=<same as root .env>
LIVEKIT_API_SECRET=<same as root .env>

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<same as MINIO_ROOT_USER>
AWS_SECRET_ACCESS_KEY=<same as MINIO_ROOT_PASSWORD>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=eventos
AWS_ENDPOINT=http://minio:9000        # internal, container-to-container
AWS_URL=https://cdn.expouse.com       # public URL used in generated links (see §8)
AWS_USE_PATH_STYLE_ENDPOINT=true

MAIL_MAILER=smtp
MAIL_HOST=<your SMTP host — see note below>
MAIL_PORT=587
MAIL_USERNAME=<mailbox>
MAIL_PASSWORD=<mailbox password>
MAIL_FROM_ADDRESS="noreply@expouse.com"
MAIL_FROM_NAME="EventOS"

SANCTUM_STATEFUL_DOMAINS=admin.expouse.com,api.expouse.com,expouse.com,*.expouse.com
```

> **Mail:** since cPanel is already on this VPS, the simplest option is to create a mailbox
> (e.g. `noreply@expouse.com`) in cPanel → Email Accounts and point `MAIL_HOST` at `localhost` or the
> server's mail hostname with that mailbox's credentials. A transactional provider (SES, Postmark,
> Mailgun) is more reliable at scale if you outgrow it later.

---

## 6. Production Docker Compose

The repo's `docker-compose.yml` is dev-oriented (bind-mounted source, `npm run dev`, `composer install`
happening at container start, ports open on all interfaces). Add a `docker-compose.prod.yml` next to it
— compose merges the two with `-f docker-compose.yml -f docker-compose.prod.yml` — rather than editing
the original, so your local dev setup keeps working unchanged.

Create `/opt/eventos/eventos-api/Dockerfile.prod`:

```dockerfile
# ── EventOS API — production image (deps baked in, no runtime composer install) ──
FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip \
        libzip-dev libpq-dev libpng-dev libjpeg-dev libfreetype6-dev \
        libicu-dev libonig-dev \
        postgresql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql pgsql gd intl bcmath zip pcntl opcache exif sockets

RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-eventos.ini

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.prod.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
```

Create `/opt/eventos/eventos-api/docker/entrypoint.prod.sh`:

```bash
#!/bin/sh
set -e
cd /var/www/html

DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
echo "[entrypoint] waiting for postgres..."
until pg_isready -h "${DB_HOST}" -p "${DB_PORT}" >/dev/null 2>&1; do sleep 1; done

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  php artisan migrate --force --database=pgsql_admin
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache
fi

exec "$@"
```

Create `/opt/eventos/docker-compose.prod.yml`:

```yaml
services:
  api:
    build:
      dockerfile: Dockerfile.prod
    environment:
      INSTALL_DEPS: "false"
      RUN_MIGRATIONS: "true"
    volumes: []          # drop the dev bind-mount + named vendor volume — code is baked into the image
    ports: []
  horizon:
    build:
      dockerfile: Dockerfile.prod
    volumes: []
  reverb:
    build:
      dockerfile: Dockerfile.prod
    volumes: []
    ports:
      - "127.0.0.1:${REVERB_PORT:-8081}:8080"   # localhost-only; Apache proxies to it
  scheduler:
    build:
      dockerfile: Dockerfile.prod
    volumes: []

  nginx:
    ports:
      - "127.0.0.1:${API_HTTP_PORT:-8080}:80"   # localhost-only; Apache proxies to it
    volumes:
      - ./eventos-api/public:/var/www/html/public:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro

  postgres:
    ports:
      - "127.0.0.1:${PG_PORT:-5433}:5432"       # never expose the DB publicly

  redis:
    ports:
      - "127.0.0.1:${REDIS_PORT:-6380}:6379"

  minio:
    ports:
      - "127.0.0.1:${MINIO_API_PORT:-9000}:9000"
      - "127.0.0.1:${MINIO_CONSOLE_PORT:-9001}:9001"

  livekit:
    command: ["--config", "/etc/livekit.yaml", "--node-ip", "${LIVEKIT_NODE_IP}"]
    ports:
      - "127.0.0.1:${LIVEKIT_PORT:-7880}:7880"          # signaling — proxied by Apache
      - "50000-50100:50000-50100/udp"                    # RTC media — public, can't be proxied

  # admin / event: not started in production — they're static builds, see §7
  admin:
    profiles: ["dev-only"]
  event:
    profiles: ["dev-only"]
```

Bring it up:

```bash
cd /opt/eventos
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f api
```

The `api` container's entrypoint runs migrations (as `eventos_migrator`, via `pgsql_admin`) and caches
config/routes/views on every start — that's intentional and safe to re-run.

To avoid retyping the `-f ... -f ...` every time, alias it for this deploy:

```bash
echo "alias dc='docker compose -f /opt/eventos/docker-compose.yml -f /opt/eventos/docker-compose.prod.yml'" >> ~/.bashrc
source ~/.bashrc
```

---

## 7. Build and publish the two static SPAs

Do this from `/opt/eventos` on the VPS (Node isn't otherwise needed on the host, only for this
one-off build step — use a throwaway Docker container so you don't have to install Node system-wide):

```bash
cd /opt/eventos

# ── Admin ──
cat > eventos-admin/.env <<'EOF'
NUXT_PUBLIC_API_BASE=https://api.expouse.com/api/v1
EOF
docker run --rm -v "$PWD/eventos-admin:/app" -w /app node:20-alpine \
  sh -c "npm ci && npm run generate"
# static site is now in eventos-admin/.output/public

# ── Event ──
cat > eventos-event/.env <<'EOF'
NUXT_PUBLIC_API_BASE=https://api.expouse.com/api/v1
NUXT_PUBLIC_EVENT_BASE_DOMAIN=expouse.com
NUXT_PUBLIC_JITSI_DOMAIN=meet.jit.si
NUXT_PUBLIC_REVERB_KEY=<same as REVERB_APP_KEY>
NUXT_PUBLIC_REVERB_HOST=api.expouse.com
NUXT_PUBLIC_REVERB_PORT=443
NUXT_PUBLIC_REVERB_SCHEME=https
EOF
docker run --rm -v "$PWD/eventos-event:/app" -w /app node:20-alpine \
  sh -c "npm ci && npm run generate"
# static site is now in eventos-event/.output/public
```

Now create the cPanel subdomains and copy the builds in:

1. **WHM/cPanel → Domains → Create A New Domain**
   - Domain: `admin.expouse.com` → note the document root it creates (e.g. `/home/<user>/admin.expouse.com`)
   - Domain: `expouse.com` with subdomain `*` (wildcard) → note its document root
     (cPanel supports wildcard subdomains; if the UI doesn't offer `*` directly, WHM → *Domains* →
     *Create a New Domain* lets you type `*.expouse.com` as the subdomain name)

2. Copy the builds into those document roots:

```bash
rsync -a --delete eventos-admin/.output/public/ /home/<user>/admin.expouse.com/
rsync -a --delete eventos-event/.output/public/  /home/<user>/expouse.com/  # or wherever the wildcard's doc root is
```

3. **cPanel → SSL/TLS Status → Run AutoSSL** (or wait for the next automatic run) to issue Let's Encrypt
   certs for `admin.expouse.com` and the wildcard `*.expouse.com`. Wildcard certs via AutoSSL require
   the `*.expouse.com` DNS record to already resolve (§2) — AutoSSL uses DNS or HTTP validation
   depending on your cPanel version's ACME setup.

Re-running §7 (rebuild + rsync) is your redeploy step for the frontends — see §13.

---

## 8. Apache reverse proxy for the backend (WHM → Apache Configuration → Include Editor)

`api.expouse.com`, `cdn.expouse.com`, and (if used) `livekit.expouse.com` are **not** created as normal
cPanel-hosted subdomains — instead, create them the same way (§7 step 1, so cPanel/AutoSSL manages a
cert and a vhost for the domain), but replace their served content with a reverse proxy to the Docker
containers listening on `127.0.0.1`.

WHM → *Apache Configuration* → *Include Editor* → *Pre VirtualHost Include* (or *Post VirtualHost*,
per-domain), select each domain, and add:

**`api.expouse.com`** — proxies HTTP to nginx (8080) and WebSocket traffic to Reverb (8081):

```apache
ProxyPreserveHost On
ProxyRequests Off

# Reverb (Laravel Echo / Pusher-protocol WebSocket + its HTTP API)
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/app/(.*) ws://127.0.0.1:8081/app/$1 [P,L]
ProxyPass /app ws://127.0.0.1:8081/app
ProxyPassReverse /app ws://127.0.0.1:8081/app
ProxyPass /apps http://127.0.0.1:8081/apps
ProxyPassReverse /apps http://127.0.0.1:8081/apps

# Everything else → the Laravel API
ProxyPass / http://127.0.0.1:8080/
ProxyPassReverse / http://127.0.0.1:8080/
```

**`cdn.expouse.com`** — proxies to MinIO:

```apache
ProxyPreserveHost On
ProxyPass / http://127.0.0.1:9000/
ProxyPassReverse / http://127.0.0.1:9000/
```

**`livekit.expouse.com`** *(optional, only if you're using breakout rooms / video tables)*:

```apache
ProxyPreserveHost On
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/(.*) ws://127.0.0.1:7880/$1 [P,L]
ProxyPass / http://127.0.0.1:7880/
ProxyPassReverse / http://127.0.0.1:7880/
```

`mod_proxy_wstunnel` needs to be enabled in EA4 — WHM → *EasyApache 4* → check
**ProxyHTTP** and **ProxyWsTunnel** modules are installed (they're on by default in most cPanel
installs; if `Upgrade` fails with a 502, this is the first thing to check).

After saving, run AutoSSL for these three domains too, then rebuild Apache config
(`Rebuild Configuration` button, or `service httpd restart` over SSH).

---

## 9. Firewall — what's actually public

| Port | Who | Exposure |
|---|---|---|
| 80, 443 | Apache (cPanel) | Public — already open |
| 8080 (nginx→api), 8081 (Reverb), 9000 (MinIO), 7880 (LiveKit signaling) | Docker | **127.0.0.1 only** (see `docker-compose.prod.yml` §6) — never opened on the firewall |
| 5433 (Postgres), 6380 (Redis) | Docker | **127.0.0.1 only** — never opened on the firewall |
| 50000–50100/udp | LiveKit RTC media | **Public** — required for video/audio to actually flow; can't be proxied by Apache |
| 7881/tcp | LiveKit RTC TCP fallback | Public, only needed if UDP is blocked for some clients (corporate networks) |
| 22 | SSH | Public, restrict to your IP if possible |

Everything Docker-side binding to `127.0.0.1:<port>` in §6 means CSF/the firewall doesn't need rules for
those ports at all — they're unreachable from outside the box regardless of firewall config, Apache is
the only thing that can reach them (via loopback). Only add firewall rules for the UDP range and 7881.

---

## 10. First boot — migrate, seed, verify

```bash
dc exec api php artisan db:seed --force   # only if you have a production-safe seeder; skip otherwise
dc exec api php artisan storage:link
dc exec api php artisan tinker            # optional: create your first super-admin user by hand
```

Smoke test:

```bash
curl -s https://api.expouse.com/api/v1/health
# → should return a healthy JSON response (see eventos-api/app/Http/Controllers/HealthController.php)

curl -sI https://admin.expouse.com | head -5
curl -sI https://cdn.expouse.com

# create a test event with subdomain "demo" via the admin app, then:
curl -sI https://demo.expouse.com
```

Also open `https://admin.expouse.com` in a browser, log in, and confirm:
- API calls succeed (check the Network tab — 401s mean auth is fine but check for CORS/mixed-content errors)
- Uploading an image (e.g. event logo) works and the resulting URL is `https://cdn.expouse.com/...`
- If you set up LiveKit: join a breakout room / lounge table and confirm video connects (this is the
  one piece that depends on the UDP port range actually being open — test from a network you don't
  control too, e.g. mobile data, since some networks block outbound UDP)

---

## 11. Keep it running across reboots

`docker compose`'s `restart: unless-stopped` (already in the base compose file) means containers come
back up automatically after a VPS reboot, as long as the Docker daemon itself starts — which
`systemctl enable docker` (§3) already ensures. No extra systemd unit needed.

---

## 12. Backups

```bash
mkdir -p /opt/eventos-backups
crontab -e
```

Add (adjust paths/retention to taste):

```cron
# nightly Postgres dump, kept 14 days
0 2 * * * cd /opt/eventos && docker compose -f docker-compose.yml -f docker-compose.prod.yml exec -T postgres pg_dump -U postgres eventos | gzip > /opt/eventos-backups/db-$(date +\%F).sql.gz && find /opt/eventos-backups -name 'db-*.sql.gz' -mtime +14 -delete

# nightly MinIO data mirror
0 3 * * * cd /opt/eventos && docker run --rm -v eventos_miniodata:/data -v /opt/eventos-backups/minio:/backup alpine sh -c "cp -au /data/. /backup/"
```

Also back up, off-server, in a password manager or encrypted vault (losing these means losing access
to encrypted data / breaking every issued token):
- `eventos-api/.env` — especially `APP_KEY`
- The root `.env` — Postgres/MinIO/LiveKit/Reverb credentials
- `docker/livekit/livekit.yaml`

---

## 13. Deploying updates

```bash
cd /opt/eventos
git pull

# Backend
dc build api horizon reverb scheduler
dc up -d api horizon reverb scheduler   # entrypoint re-runs migrate + cache on start

# Frontends — repeat §7's build + rsync steps whenever eventos-admin or eventos-event changes
```

Horizon/Reverb/scheduler restart briefly during a backend deploy (queued jobs resume once Horizon is
back; Reverb WebSocket clients auto-reconnect via Laravel Echo). For zero-downtime you'd need a second
API container behind Apache's load balancing — not necessary until traffic actually requires it.

---

## 14. Security hardening checklist

- [ ] Every secret in §5 is a fresh random value, not a dev default
- [ ] `APP_DEBUG=false` in `eventos-api/.env`
- [ ] Postgres and Redis ports are `127.0.0.1`-bound only (§6), never on the public firewall
- [ ] `docker compose --profile tools` (Adminer) and `--profile search` (Meilisearch, if unused) are
      never started in production — they're dev conveniences with weak default credentials
- [ ] MinIO console (`9001`) stays `127.0.0.1`-only — if you need it, tunnel via
      `ssh -L 9001:127.0.0.1:9001 root@VPS_IP` rather than exposing it
- [ ] SSH: key-based auth only, consider fail2ban (WHM ships *cPHulk* — enable it)
- [ ] AutoSSL is actually issuing/renewing certs for all five domains (check WHM → SSL/TLS Status)
- [ ] `LIVEKIT_API_SECRET` is 32+ random characters, matches in both `.env` and `livekit.yaml`

---

## 15. Troubleshooting

| Symptom | Likely cause |
|---|---|
| `502 Bad Gateway` on `api.expouse.com` | `nginx`/`api` container not up — `dc ps`, `dc logs api nginx` |
| WebSocket connects then immediately drops | `mod_proxy_wstunnel` not enabled in EA4, or `REVERB_HOST`/`REVERB_SCHEME` mismatch between backend and frontend build env |
| Images/uploads 403 or broken links | `AWS_URL` in `eventos-api/.env` doesn't match the real `cdn.expouse.com` proxy, or `AWS_USE_PATH_STYLE_ENDPOINT` isn't `true` |
| `demo.expouse.com` doesn't resolve to the event app | Wildcard DNS record missing/not propagated, or the wildcard cPanel subdomain's document root doesn't contain the `eventos-event` build |
| LiveKit connects but no video/audio | UDP 50000–50100 blocked by firewall/CSF, or `use_external_ip: true` / `LIVEKIT_NODE_IP` not set to the real public IP |
| Migrations fail with a permissions error | `DB_ADMIN_USERNAME`/`DB_ADMIN_PASSWORD` don't match what `docker/postgres/init/10-roles.sh` actually created — check `EVENTOS_MIGRATOR_PASSWORD` in the root `.env` matches |
