# eventos-event — per-event public microsite

A Nuxt 4 SPA (`ssr: false`) that serves **one public microsite per event**. Which
event it shows is derived from the **subdomain** in the browser URL:

```
edu.expouse.test     → the event whose event_settings.domain.subdomain = "edu"
expo1234.expouse.test → the event with subdomain "expo1234"
```

On boot (`app/plugins/site.client.ts`) the app resolves the subdomain
(`app/composables/useEventSubdomain.ts`), calls `GET /api/v1/public/site` with an
`X-Event-Subdomain` header, and loads that event's public branding into the site
store. The first screen is the branded **email-first login / signup** page
(`app/pages/index.vue`): enter email → CONTINUE → known account asks for a
password (login), unknown email goes to the event's registration form
(`app/pages/register.vue`). Every API call carries `X-Event-Subdomain` so it's
scoped to the event ("API call under the subdomain").

Only **published** events are exposed; unknown/draft subdomains render an
"event not found" state.

## Running it locally

The stack runs under Docker (`docker compose up -d` from the repo root); the dev
server is published on host port **3001**. The API is on **8088**.

`.test` subdomains don't resolve on their own, so pick one:

### Option A — no DNS (fastest)
Use the built-in dev fallback — pass the subdomain as a query param:

```
http://localhost:3001/?subdomain=expo1234
```

The value sticks for the browser tab (sessionStorage).

### Option B — hosts file (real subdomains, explicit)
`.test` wildcards can't be expressed in the Windows hosts file, so add one line
per event to `C:\Windows\System32\drivers\etc\hosts` (needs admin):

```
127.0.0.1  edu.expouse.test  expo1234.expouse.test
```

Then visit `http://edu.expouse.test:3001`.

### Option C — wildcard DNS
Map `*.expouse.test → 127.0.0.1` with Acrylic DNS Proxy (or Laragon's dnsmasq).
Any subdomain then resolves without editing hosts.

The Vite dev server already allows `.expouse.test` hosts
(`nuxt.config.ts → vite.server.allowedHosts`).

## Configuration

| Setting | Where | Default |
| --- | --- | --- |
| API base | `NUXT_PUBLIC_API_BASE` | `http://localhost:8088/api/v1` |
| Platform apex | `NUXT_PUBLIC_EVENT_BASE_DOMAIN` (`runtimeConfig.public.eventBaseDomain`) | `expouse.test` |
| API-side apex | `PLATFORM_APEX` in `eventos-api/.env` | `expouse.test` |

Set an event's subdomain from the organizer console → **Event Settings → Domain**
(persists to `event_settings.domain.subdomain`).
