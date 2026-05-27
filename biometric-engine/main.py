from __future__ import annotations

import base64
import binascii
import io
from abc import ABC, abstractmethod

from fastapi import FastAPI, HTTPException
from PIL import Image, ImageChops, ImageOps
from pydantic import BaseModel, Field, field_validator


class CompareRequest(BaseModel):
    stored_image: str = Field(..., min_length=100, max_length=2_097_152)
    candidate_image: str = Field(..., min_length=100, max_length=2_097_152)
    threshold: float = Field(40, ge=0, le=100)

    @field_validator("stored_image", "candidate_image")
    @classmethod
    def strip_data_url(cls, value: str) -> str:
        if value.startswith("data:image/png;base64,"):
            return value.replace("data:image/png;base64,", "", 1)

        return value


class CompareResponse(BaseModel):
    match: bool
    score: float
    threshold: float


class FingerprintMatcher(ABC):
    @abstractmethod
    def compare(self, stored_image: str, candidate_image: str, threshold: float) -> CompareResponse:
        raise NotImplementedError


class ExperimentalImageMatcher(FingerprintMatcher):
    """
    Connectivity/demo matcher.

    TODO: Replace with a real biometric matcher such as SourceAFIS or a vendor SDK.
    This does not compare fingerprint minutiae; it only compares normalized images
    so Laravel can exercise the FastAPI integration end to end.
    """

    size = (128, 128)

    def compare(self, stored_image: str, candidate_image: str, threshold: float) -> CompareResponse:
        stored = self._decode_image(stored_image)
        candidate = self._decode_image(candidate_image)

        diff = ImageChops.difference(stored, candidate)
        histogram = diff.histogram()
        total = sum(value * count for value, count in enumerate(histogram))
        max_total = 255 * sum(histogram)
        score = 100.0 if max_total == 0 else max(0.0, 100.0 - ((total / max_total) * 100.0))

        return CompareResponse(
            match=score >= threshold,
            score=round(score, 2),
            threshold=threshold,
        )

    def _decode_image(self, value: str) -> Image.Image:
        try:
            raw = base64.b64decode(value, validate=True)
        except (binascii.Error, ValueError) as exc:
            raise HTTPException(status_code=422, detail="Invalid base64 image.") from exc

        try:
            image = Image.open(io.BytesIO(raw))
            image.load()
        except OSError as exc:
            raise HTTPException(status_code=422, detail="Invalid PNG image.") from exc

        return ImageOps.grayscale(image).resize(self.size)


app = FastAPI(title="Nexgol Biometric Engine", version="0.1.0")
matcher: FingerprintMatcher = ExperimentalImageMatcher()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/compare", response_model=CompareResponse)
def compare(payload: CompareRequest) -> CompareResponse:
    return matcher.compare(
        stored_image=payload.stored_image,
        candidate_image=payload.candidate_image,
        threshold=payload.threshold,
    )
