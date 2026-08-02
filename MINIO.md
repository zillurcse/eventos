# MinIO — Production Ops Guide (VPS + WHM/cPanel, no Docker)

Reference for EventOS object storage on the production VPS. MinIO runs as a native systemd service; Apache (cPanel) reverse-proxies `cdn.expouse.com` to it.

---

## Architecture

| Piece | Value |
|---|---|
| Public URL | `https://cdn.expouse.com` |
| Internal API | `http://127.0.0.1:9000` |
| Console (localhost only) | `http://127.0.0.1:9001` |
| Data dir | `/data/minio` |
| Bucket | `eventos` |
| Service | `minio.service` (systemd) |
| Binary | `/usr/local/bin/minio` |
| Client (`mc`) | `/usr/local/bin/mc` |
| Env file | `/etc/default/minio` |
| Unit file | `/etc/systemd/system/minio.service` |
| System user | `minio-user` |

Laravel talks to MinIO over loopback (`AWS_ENDPOINT=http://127.0.0.1:9000`). Browsers load files via `AWS_URL=https://cdn.expouse.com`.

---

## Install (already done on this VPS — keep for rebuilds)

```bash
useradd -r -s /sbin/nologin minio-user 2>/dev/null || true
mkdir -p /data/minio
chown -R minio-user:minio-user /data/minio

curl -fsSL https://dl.min.io/server/minio/release/linux-amd64/minio -o /usr/local/bin/minio
chmod +x /usr/local/bin/minio

# Generate secrets:
#   openssl rand -base64 18   → MINIO_ROOT_USER
#   openssl rand -base64 24   → MINIO_ROOT_PASSWORD

cat > /etc/default/minio <<'EOF'
MINIO_ROOT_USER=<random>
MINIO_ROOT_PASSWORD=<random>
MINIO_VOLUMES="/data/minio"
MINIO_OPTS="--address 127.0.0.1:9000 --console-address 127.0.0.1:9001"
EOF

cat > /etc/systemd/system/minio.service <<'EOF'
[Unit]
Description=MinIO
Wants=network-online.target
After=network-online.target
AssertFileIsExecutable=/usr/local/bin/minio

[Service]
User=minio-user
Group=minio-user
EnvironmentFile=-/etc/default/minio
ExecStartPre=/bin/bash -c "if [ -z \"${MINIO_VOLUMES}\" ]; then echo \"MINIO_VOLUMES not set\"; exit 1; fi"
ExecStart=/usr/local/bin/minio server $MINIO_OPTS $MINIO_VOLUMES
Restart=always
LimitNOFILE=65536
TimeoutStopSec=infinity
SendSIGKILL=no

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now minio
```

### Bucket bootstrap

```bash
curl -fsSL https://dl.min.io/client/mc/release/linux-amd64/mc -o /usr/local/bin/mc
chmod +x /usr/local/bin/mc

source /etc/default/minio
mc alias set local http://127.0.0.1:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
mc mb --ignore-existing local/eventos
mc anonymous set download local/eventos   # public read for uploaded media URLs
```

---

## WHM / cPanel (public HTTPS)

1. **DNS** — Zone Editor: A record `cdn` → VPS public IP  
2. **Domain** — Create domain `cdn.expouse.com` (so AutoSSL can issue a cert)  
3. **EasyApache 4** — ensure **ProxyHTTP** is installed  
4. **Include Editor** — for `cdn.expouse.com`:

```apache
ProxyPreserveHost On
ProxyRequests Off
ProxyPass / http://127.0.0.1:9000/
ProxyPassReverse / http://127.0.0.1:9000/
```

5. Rebuild Apache config / restart `httpd`  
6. **SSL/TLS Status** → Run AutoSSL for `cdn.expouse.com`

Do **not** open ports `9000` / `9001` in CSF. Keep them on `127.0.0.1` only.

### MinIO Console (admin UI)

SSH tunnel from your laptop:

```bash
ssh -L 9001:127.0.0.1:9001 root@VPS_IP
```

Then open `http://localhost:9001` and log in with `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD`.

---

## Laravel (`eventos-api/.env`)

```dotenv
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=<same as MINIO_ROOT_USER>
AWS_SECRET_ACCESS_KEY=<same as MINIO_ROOT_PASSWORD>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=eventos
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_URL=https://cdn.expouse.com
AWS_USE_PATH_STYLE_ENDPOINT=true
```

After any change:

```bash
php artisan config:clear
php artisan config:cache
```

Config lives in `eventos-api/config/filesystems.php` → disk `s3`. Uploads go through Laravel controllers (e.g. `FileUploadController`); public object URLs use `AWS_URL`.

---

## Day-to-day commands

```bash
# status / logs
systemctl status minio --no-pager
journalctl -u minio -f

# restart after config edit
systemctl restart minio

# list buckets / objects
source /etc/default/minio
mc alias set local http://127.0.0.1:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD"
mc ls local/
mc ls local/eventos --recursive

# smoke tests
curl -sI http://127.0.0.1:9000          # expect Server: MinIO (400 on bare / is OK)
curl -sI https://cdn.expouse.com
```

### Upgrade MinIO

```bash
systemctl stop minio
curl -fsSL https://dl.min.io/server/minio/release/linux-amd64/minio -o /usr/local/bin/minio
chmod +x /usr/local/bin/minio
systemctl start minio
minio --version
```

### Backup data dir

```bash
# simple filesystem copy (stop or use consistent snapshot if needed)
rsync -a /data/minio/ /opt/eventos-backups/minio/
```

Nightly example (cron as root):

```cron
0 3 * * * rsync -a /data/minio/ /opt/eventos-backups/minio/
```

---

## Security checklist

- [ ] Strong unique `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` (not Docker/dev defaults)
- [ ] Bound to `127.0.0.1` only — never public firewall rules for 9000/9001
- [ ] Console only via SSH tunnel
- [ ] `AWS_URL` matches real public CDN host (`https://cdn.expouse.com`)
- [ ] `AWS_USE_PATH_STYLE_ENDPOINT=true` (required for MinIO path-style URLs)
- [ ] AutoSSL renewing for `cdn.expouse.com`
- [ ] Bucket anonymous policy: `download` only (read); writes stay authenticated via Laravel/S3 keys

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| `502` on `cdn.expouse.com` | `systemctl status minio`; Apache Include wrong; ProxyHTTP missing |
| Images 403 / broken URLs | `AWS_URL` mismatch; bucket not public-read; wrong object path |
| Laravel upload fails | `AWS_ENDPOINT` not `http://127.0.0.1:9000`; credentials ≠ `/etc/default/minio`; `config:cache` stale |
| Console unreachable from browser | Expected — use SSH tunnel to `9001` |
| `curl` localhost returns `400` | Normal for `/` without an S3 object path; check `Server: MinIO` header |

---

## Official MinIO docs (bookmark these)

| Topic | URL |
|---|---|
| MinIO Object Store (home) | https://min.io/docs/minio/linux/index.html |
| Deploy on Linux (bare metal) | https://min.io/docs/minio/linux/operations/install-deploy-migrate/deploy-minio-single-node-single-drive.html |
| `mc` client | https://min.io/docs/minio/linux/reference/minio-mc.html |
| Bucket policies / anonymous | https://min.io/docs/minio/linux/administration/identity-access-management/policy-based-access-control.html |
| systemd service management | https://min.io/docs/minio/linux/operations/install-deploy-migrate/install-minio.html |
| S3 API compatibility | https://min.io/docs/minio/linux/developers/minio-drivers.html |
| Downloads (server + mc) | https://min.io/download |

Community / AGPLv3 docs mirror (if min.io redirects):  
https://minio.community/community/minio-object-store/

---

## Related project files

- `DEPLOYMENT.md` — full production guide (Docker-oriented compose; CDN Apache section still applies)
- `eventos-api/config/filesystems.php` — Laravel `s3` disk
- `eventos-api/app/Http/Controllers/Api/V1/FileUploadController.php` — upload → MinIO
- Local Docker MinIO (dev only): `docker-compose.yml` services `minio` + `createbuckets`
