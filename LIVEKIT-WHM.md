# LiveKit on WHM/cPanel (No Docker)

Self-host LiveKit for EventOS breakout rooms, lounge tables, and 1:1 meetings on a VPS with WHM/cPanel.

This guide installs LiveKit as a **native binary + systemd service**, then fronts it with Apache (same pattern as MinIO on `cdn.expouse.com`).

| Piece | Value |
|---|---|
| Public URL | `wss://livekit.expouse.com` |
| Signaling (local) | `127.0.0.1:7880` — Apache reverse-proxies HTTPS/WSS |
| RTC TCP fallback | `7881` — public (optional but recommended) |
| RTC media | UDP `50000–50100` — **public** (Apache cannot proxy UDP) |
| Laravel → LiveKit | `LIVEKIT_HOST=http://127.0.0.1:7880` |

---

## 0. Architecture

```
Browser
  │  wss://livekit.expouse.com  (signaling)
  │  UDP 50000–50100            (audio/video media)
  ▼
Apache (cPanel) ──TLS──► 127.0.0.1:7880  ──► livekit-server (systemd)
                              ▲
Laravel API (same VPS) ───────┘  http://127.0.0.1:7880  (RoomService / tokens)
```

**Why UDP must be public:** WebRTC media is UDP. Apache only terminates TLS and proxies WebSocket signaling. If CSF blocks `50000–50100/udp`, rooms join but video/audio fail.

---

## 1. Prerequisites

- Root SSH on the VPS
- WHM + cPanel with AutoSSL
- Domain zone editable in cPanel (example: `expouse.com`)
- CSF (or equivalent firewall) you can edit
- EasyApache 4 modules: `mod_proxy`, `mod_proxy_http`, `mod_proxy_wstunnel`, `mod_rewrite`

Replace placeholders:

| Placeholder | Example |
|---|---|
| `VPS_IP` | `203.0.113.10` |
| `YOUR_API_KEY` | output of `openssl rand -base64 24` |
| `YOUR_API_SECRET` | output of `openssl rand -base64 32` (32+ chars) |

---

## 2. DNS (cPanel → Zone Editor)

Add an **A record**:

| Name | Type | Value |
|---|---|---|
| `livekit.expouse.com` | A | `VPS_IP` |

Verify:

```bash
dig livekit.expouse.com +short
# → VPS_IP
```

---

## 3. Create subdomain + AutoSSL

1. **WHM/cPanel → Domains → Create A New Domain**
   - Domain: `livekit.expouse.com`
   - Document root can stay default (Apache will reverse-proxy; you won’t serve static files)

2. **cPanel → SSL/TLS Status → Run AutoSSL** for `livekit.expouse.com`

Confirm HTTPS works (even if it still shows a cPanel placeholder page):

```bash
curl -sI https://livekit.expouse.com | head -5
```

---

## 4. Install LiveKit binary

SSH as root:

```bash
curl -sSL https://get.livekit.io | bash
livekit-server --version
```

Binary path: `/usr/local/bin/livekit-server`

---

## 5. Generate API credentials

```bash
openssl rand -base64 24   # LIVEKIT_API_KEY
openssl rand -base64 32   # LIVEKIT_API_SECRET
```

Save both — they must match in:

1. `/etc/livekit/livekit.yaml`
2. Laravel `eventos-api/.env` (`LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET`)

**Never reuse the repo’s dev defaults** (`devkey` / `devsecret_change_me_min_32_chars_`).

---

## 6. Config file

```bash
mkdir -p /etc/livekit
nano /etc/livekit/livekit.yaml
```

```yaml
# /etc/livekit/livekit.yaml
port: 7880
bind_addresses:
  - "127.0.0.1"          # signaling only on loopback — Apache proxies it

rtc:
  tcp_port: 7881
  port_range_start: 50000
  port_range_end: 50100
  use_external_ip: true  # required so browsers get a routable ICE candidate
  # If STUN discovery fails, set your public IP explicitly:
  # node_ip: VPS_IP

keys:
  YOUR_API_KEY: YOUR_API_SECRET

logging:
  level: info
```

Notes:

- `use_external_ip: true` is required in production (local dev often uses `false`).
- Keep `7880` on `127.0.0.1` only. Do **not** bind signaling to `0.0.0.0` unless you intentionally expose it (not recommended behind Apache).

---

## 7. systemd service

```bash
nano /etc/systemd/system/livekit.service
```

```ini
[Unit]
Description=LiveKit SFU Server
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/local/bin/livekit-server --config /etc/livekit/livekit.yaml
Restart=always
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
systemctl daemon-reload
systemctl enable --now livekit
systemctl status livekit
journalctl -u livekit -f
```

Confirm signaling is local-only:

```bash
ss -lntp | grep 7880
# expect: 127.0.0.1:7880
```

Useful commands:

```bash
systemctl restart livekit
systemctl stop livekit
journalctl -u livekit -n 100 --no-pager
```

---

## 8. Apache reverse proxy (WHM Include Editor)

### 8a. Enable modules

**WHM → EasyApache 4** — ensure these are installed:

- ProxyHTTP (`mod_proxy` / `mod_proxy_http`)
- ProxyWsTunnel (`mod_proxy_wstunnel`)
- Rewrite (`mod_rewrite`)

### 8b. Add Include for `livekit.expouse.com`

**WHM → Apache Configuration → Include Editor → Pre VirtualHost Include**  
Select **`livekit.expouse.com`** and paste:

```apache
ProxyPreserveHost On
ProxyRequests Off

RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/(.*) ws://127.0.0.1:7880/$1 [P,L]

ProxyPass / http://127.0.0.1:7880/
ProxyPassReverse / http://127.0.0.1:7880/
```

This mirrors MinIO (`cdn`) but adds WebSocket upgrade (like Reverb):

```apache
# MinIO comparison — plain HTTP only
# ProxyPreserveHost On
# ProxyPass / http://127.0.0.1:9000/
# ProxyPassReverse / http://127.0.0.1:9000/
```

### 8c. Rebuild Apache

```bash
/scripts/rebuildhttpdconf
systemctl restart httpd
```

Or use WHM’s **Rebuild Configuration** / restart HTTP Server.

---

## 9. Firewall (CSF)

| Port | Protocol | Exposure | Purpose |
|---|---|---|---|
| 80, 443 | TCP | Public | Apache / AutoSSL (already open) |
| 7880 | TCP | **127.0.0.1 only** | Signaling — do **not** open in CSF |
| 7881 | TCP | Public | RTC TCP fallback (corporate networks that block UDP) |
| 50000–50100 | UDP | **Public** | WebRTC media — required for A/V |

### CSF UI

**WHM → ConfigServer Security & Firewall → Firewall Configuration**

- `UDP_IN` / `UDP_OUT`: add `50000:50100`
- `TCP_IN` / `TCP_OUT`: add `7881`

### Or edit config

```bash
nano /etc/csf/csf.conf
# edit TCP_IN, TCP_OUT, UDP_IN, UDP_OUT
csf -r
```

Without the UDP range, `wss://` may connect but camera/mic will not.

---

## 10. Laravel / EventOS env

Edit `eventos-api/.env` on the same server:

```dotenv
LIVEKIT_URL=wss://livekit.expouse.com
LIVEKIT_HOST=http://127.0.0.1:7880
LIVEKIT_API_KEY=YOUR_API_KEY
LIVEKIT_API_SECRET=YOUR_API_SECRET
LIVEKIT_TOKEN_TTL=3600
```

| Variable | Meaning |
|---|---|
| `LIVEKIT_URL` | What browsers / `livekit-client` connect to (WSS via Apache) |
| `LIVEKIT_HOST` | What Laravel uses for RoomService Twirp (kick, lock, occupancy) |
| `LIVEKIT_API_KEY` / `SECRET` | Must match `/etc/livekit/livekit.yaml` `keys:` |

Reload Laravel config:

```bash
cd /path/to/eventos-api
php artisan config:clear
# production:
# php artisan config:cache
```

These map to `config/services.php → livekit` and are used by `LiveKitProvider`.

---

## 11. Verify

### Service + local signaling

```bash
systemctl is-active livekit
curl -s http://127.0.0.1:7880
curl -sI https://livekit.expouse.com | head -10
```

### From Laravel (same host)

```bash
curl -s http://127.0.0.1:7880
# or from wherever the API runs, if not localhost, use that host's reachability to 127.0.0.1:7880
```

### End-to-end video

1. Open the event app, join a breakout room or lounge table.
2. Allow camera/mic.
3. Test from **mobile data** (not only the same LAN as the VPS) so UDP paths are real.

---

## 12. Upgrade LiveKit

```bash
systemctl stop livekit
curl -sSL https://get.livekit.io | bash
systemctl start livekit
livekit-server --version
systemctl status livekit
```

Config at `/etc/livekit/livekit.yaml` is unchanged by the install script.

---

## 13. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `502` on `https://livekit.expouse.com` | LiveKit down or wrong proxy port | `systemctl status livekit`; confirm `127.0.0.1:7880` |
| WebSocket fails / constant reconnect | Missing `mod_proxy_wstunnel` or Rewrite rules | Enable ProxyWsTunnel; re-check Include |
| Room joins, no video/audio | UDP `50000–50100` blocked | Open in CSF; retest on mobile data |
| ICE / connection stuck | Wrong public IP advertised | Set `use_external_ip: true` or `node_ip: VPS_IP` |
| Laravel kick/lock/occupancy fails | Wrong `LIVEKIT_HOST` or key mismatch | Use `http://127.0.0.1:7880`; match yaml keys |
| AutoSSL fails | DNS not pointing yet | Fix A record; re-run AutoSSL |
| Works on Wi‑Fi, fails on mobile | Carrier / path issues; TCP fallback | Ensure `7881/tcp` open |

Logs:

```bash
journalctl -u livekit -f
tail -f /usr/local/apache/logs/error_log
# or:
tail -f /var/log/apache2/error_log
```

---

## 14. Security checklist

- [ ] Fresh random `LIVEKIT_API_KEY` / `LIVEKIT_API_SECRET` (not dev defaults)
- [ ] Keys identical in `livekit.yaml` and `eventos-api/.env`
- [ ] Signaling bound to `127.0.0.1:7880` only
- [ ] `7880` **not** in CSF public allow list
- [ ] UDP `50000–50100` open only as needed
- [ ] AutoSSL valid for `livekit.expouse.com` (browsers need real TLS for WSS)
- [ ] Back up `/etc/livekit/livekit.yaml` and Laravel `.env` off-server

---

## 15. Quick reference

```bash
# install
curl -sSL https://get.livekit.io | bash

# config
nano /etc/livekit/livekit.yaml

# service
systemctl enable --now livekit
systemctl restart livekit
journalctl -u livekit -f

# apache
/scripts/rebuildhttpdconf && systemctl restart httpd

# firewall
csf -r

# smoke
curl -sI https://livekit.expouse.com
ss -lntp | grep -E '7880|7881'
ss -lunp | grep 5000
```

Laravel:

```dotenv
LIVEKIT_URL=wss://livekit.expouse.com
LIVEKIT_HOST=http://127.0.0.1:7880
LIVEKIT_API_KEY=...
LIVEKIT_API_SECRET=...
```

---

## Related

- EventOS Docker-based deploy notes: [`DEPLOYMENT.md`](./DEPLOYMENT.md) (this WHM guide is the **non-Docker** path)
- Breakout architecture: [`eventos-api/docs/breakout-rooms-architecture.md`](./eventos-api/docs/breakout-rooms-architecture.md)
- Upstream: [LiveKit self-hosting](https://docs.livekit.io/transport/self-hosting/deployment/)
