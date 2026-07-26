# ExpoLens Face Service

Internal FastAPI worker used by Laravel Horizon jobs to detect faces and produce InsightFace (`buffalo_l`) embeddings.

## Endpoints

- `GET /health`
- `POST /embed` — enrollment image must contain exactly one face
- `POST /detect-and-embed` — event photo; returns every detected face + embedding

Authenticate with header `X-Service-Token` when `FACE_SERVICE_TOKEN` is set.

## Local run

```bash
docker compose up -d --build face-service
```

Model weights download into the `face_models` volume on first boot.
