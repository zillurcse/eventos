# ── EventOS Admin SPA — Nuxt 4 (dev) ──────────────────────────────
# Source is bind-mounted and node_modules lives in a named volume,
# so this image only needs the Node runtime. Deps install at start.
FROM node:20-alpine
WORKDIR /app
EXPOSE 3000
ENV HOST=0.0.0.0
CMD ["sh", "-c", "[ -d node_modules/.bin ] || npm install; exec npm run dev -- --host 0.0.0.0 --port 3000"]
