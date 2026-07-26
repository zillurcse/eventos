from __future__ import annotations

import os
from contextlib import asynccontextmanager
from typing import Annotated

import cv2
import numpy as np
from fastapi import FastAPI, File, Header, HTTPException, UploadFile, status
from insightface.app import FaceAnalysis
from pydantic import BaseModel

MODEL_NAME = os.getenv("FACE_MODEL_NAME", "buffalo_l")
DETECTION_SIZE = int(os.getenv("FACE_DETECTION_SIZE", "640"))
MAX_UPLOAD_BYTES = int(os.getenv("FACE_MAX_UPLOAD_BYTES", str(25 * 1024 * 1024)))
SERVICE_TOKEN = os.getenv("FACE_SERVICE_TOKEN", "")

analyzer: FaceAnalysis | None = None


class FaceResult(BaseModel):
    bbox: list[float]
    embedding: list[float]
    detection_score: float
    quality_score: float


class DetectionResponse(BaseModel):
    faces: list[FaceResult]
    model: str
    dimensions: int


class EmbeddingResponse(BaseModel):
    face: FaceResult
    model: str
    dimensions: int


def _load_analyzer() -> FaceAnalysis:
    providers = ["CPUExecutionProvider"]
    face_analyzer = FaceAnalysis(name=MODEL_NAME, providers=providers)
    face_analyzer.prepare(ctx_id=-1, det_size=(DETECTION_SIZE, DETECTION_SIZE))
    return face_analyzer


@asynccontextmanager
async def lifespan(_: FastAPI):
    global analyzer
    analyzer = _load_analyzer()
    yield
    analyzer = None


app = FastAPI(
    title="ExpoLens Face Service",
    version="1.0.0",
    docs_url=None,
    redoc_url=None,
    lifespan=lifespan,
)


def _authorize(token: str | None) -> None:
    if SERVICE_TOKEN and token != SERVICE_TOKEN:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid service token")


async def _decode_image(file: UploadFile) -> np.ndarray:
    if file.content_type not in {"image/jpeg", "image/png", "image/webp"}:
        raise HTTPException(status_code=415, detail="JPEG, PNG, or WebP image required")

    payload = await file.read(MAX_UPLOAD_BYTES + 1)
    if len(payload) > MAX_UPLOAD_BYTES:
        raise HTTPException(status_code=413, detail="Image exceeds the configured size limit")

    image = cv2.imdecode(np.frombuffer(payload, dtype=np.uint8), cv2.IMREAD_COLOR)
    if image is None:
        raise HTTPException(status_code=422, detail="Image could not be decoded")
    return image


def _quality(face: object, image: np.ndarray) -> float:
    x1, y1, x2, y2 = [int(value) for value in face.bbox]
    height, width = image.shape[:2]
    crop = image[max(0, y1):min(height, y2), max(0, x1):min(width, x2)]
    if crop.size == 0:
        return 0.0
    sharpness = float(cv2.Laplacian(crop, cv2.CV_64F).var())
    size_ratio = min(1.0, float(crop.shape[0] * crop.shape[1]) / float(160 * 160))
    sharpness_score = min(1.0, sharpness / 250.0)
    return round((0.65 * sharpness_score) + (0.35 * size_ratio), 4)


def _analyze(image: np.ndarray) -> list[FaceResult]:
    if analyzer is None:
        raise HTTPException(status_code=503, detail="Face model is not ready")

    results: list[FaceResult] = []
    for face in analyzer.get(image):
        embedding = np.asarray(face.normed_embedding, dtype=np.float32)
        results.append(
            FaceResult(
                bbox=[round(float(value), 2) for value in face.bbox],
                embedding=embedding.tolist(),
                detection_score=round(float(face.det_score), 6),
                quality_score=_quality(face, image),
            )
        )
    return results


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok" if analyzer is not None else "starting", "model": MODEL_NAME}


@app.post("/detect-and-embed", response_model=DetectionResponse)
async def detect_and_embed(
    file: Annotated[UploadFile, File()],
    x_service_token: Annotated[str | None, Header()] = None,
) -> DetectionResponse:
    _authorize(x_service_token)
    faces = _analyze(await _decode_image(file))
    dimensions = len(faces[0].embedding) if faces else 512
    return DetectionResponse(faces=faces, model=MODEL_NAME, dimensions=dimensions)


@app.post("/embed", response_model=EmbeddingResponse)
async def embed(
    file: Annotated[UploadFile, File()],
    x_service_token: Annotated[str | None, Header()] = None,
) -> EmbeddingResponse:
    _authorize(x_service_token)
    faces = _analyze(await _decode_image(file))
    if len(faces) != 1:
        raise HTTPException(
            status_code=422,
            detail=f"Enrollment image must contain exactly one face; detected {len(faces)}",
        )
    return EmbeddingResponse(face=faces[0], model=MODEL_NAME, dimensions=len(faces[0].embedding))
