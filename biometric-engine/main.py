from __future__ import annotations

import base64
import binascii
import io
import json
import os
import subprocess
import tempfile
from abc import ABC, abstractmethod
from pathlib import Path
from typing import Any

from fastapi import FastAPI, HTTPException
from PIL import Image
from pydantic import BaseModel, Field, field_validator


TEMPLATE_FORMAT = "sourceafis_3.18.1_base64"


class TemplateRequest(BaseModel):
    image: str = Field(..., min_length=100, max_length=2_097_152)

    @field_validator("image")
    @classmethod
    def strip_data_url(cls, value: str) -> str:
        return strip_png_data_url(value)


class CompareRequest(BaseModel):
    stored_image: str = Field(..., min_length=100, max_length=2_097_152)
    candidate_image: str = Field(..., min_length=100, max_length=2_097_152)
    threshold: float = Field(40, ge=0, le=100)

    @field_validator("stored_image", "candidate_image")
    @classmethod
    def strip_data_url(cls, value: str) -> str:
        return strip_png_data_url(value)


class CompareTemplatesRequest(BaseModel):
    stored_template: str = Field(..., min_length=20, max_length=2_097_152)
    candidate_template: str = Field(..., min_length=20, max_length=2_097_152)
    threshold: float = Field(40, ge=0, le=100)


class IdentifyTemplateItem(BaseModel):
    id: int
    finger_position: str | None = None
    template: str = Field(..., min_length=20, max_length=2_097_152)


class IdentifyTemplatesRequest(BaseModel):
    candidate_template: str = Field(..., min_length=20, max_length=2_097_152)
    templates: list[IdentifyTemplateItem] = Field(..., min_length=1, max_length=10_000)
    threshold: float = Field(40, ge=0, le=100)


class TemplateResponse(BaseModel):
    template: str
    format: str = TEMPLATE_FORMAT


class CompareResponse(BaseModel):
    match: bool
    score: float
    threshold: float


class IdentifyResponse(BaseModel):
    match: bool
    threshold: float
    best: dict[str, Any]
    candidates: list[dict[str, Any]]


def strip_png_data_url(value: str) -> str:
    if value.startswith("data:image/png;base64,"):
        return value.replace("data:image/png;base64,", "", 1)

    return value


class FingerprintMatcher(ABC):
    @abstractmethod
    def create_template(self, image: str) -> TemplateResponse:
        raise NotImplementedError

    @abstractmethod
    def compare_images(self, stored_image: str, candidate_image: str, threshold: float) -> CompareResponse:
        raise NotImplementedError

    @abstractmethod
    def compare_templates(self, stored_template: str, candidate_template: str, threshold: float) -> CompareResponse:
        raise NotImplementedError

    @abstractmethod
    def identify_templates(
        self,
        candidate_template: str,
        templates: list[IdentifyTemplateItem],
        threshold: float,
    ) -> IdentifyResponse:
        raise NotImplementedError


class SourceAfisMatcher(FingerprintMatcher):
    classpath = os.environ.get(
        "SOURCEAFIS_CLASSPATH",
        "/app/java/classes:/app/java/dependency/*",
    )
    main_class = "com.nexgol.biometric.SourceAfisCli"

    def create_template(self, image: str) -> TemplateResponse:
        raw = self._decode_png(image)

        with tempfile.TemporaryDirectory() as directory:
            image_path = Path(directory) / "fingerprint.png"
            image_path.write_bytes(raw)
            template = self._run(["create-template", str(image_path)])

        return TemplateResponse(template=template)

    def compare_images(self, stored_image: str, candidate_image: str, threshold: float) -> CompareResponse:
        stored = self._decode_png(stored_image)
        candidate = self._decode_png(candidate_image)

        with tempfile.TemporaryDirectory() as directory:
            stored_path = Path(directory) / "stored.png"
            candidate_path = Path(directory) / "candidate.png"
            stored_path.write_bytes(stored)
            candidate_path.write_bytes(candidate)
            score = self._score(["compare-images", str(stored_path), str(candidate_path)])

        return self._compare_response(score, threshold)

    def compare_templates(self, stored_template: str, candidate_template: str, threshold: float) -> CompareResponse:
        self._decode_template(stored_template)
        self._decode_template(candidate_template)
        score = self._score(["compare-templates", stored_template, candidate_template])

        return self._compare_response(score, threshold)

    def identify_templates(
        self,
        candidate_template: str,
        templates: list[IdentifyTemplateItem],
        threshold: float,
    ) -> IdentifyResponse:
        self._decode_template(candidate_template)

        for template in templates:
            self._decode_template(template.template)

        with tempfile.TemporaryDirectory() as directory:
            templates_path = Path(directory) / "templates.json"
            templates_path.write_text(
                json.dumps([template.model_dump() for template in templates]),
                encoding="utf-8",
            )
            raw_results = self._run(["identify-templates", candidate_template, str(templates_path)])

        try:
            candidates = json.loads(raw_results)
        except json.JSONDecodeError as exc:
            raise HTTPException(status_code=500, detail="Invalid SourceAFIS identify response.") from exc

        for candidate in candidates:
            candidate["score"] = round(float(candidate["score"]), 2)
            candidate["match"] = candidate["score"] >= threshold

        best = candidates[0]

        return IdentifyResponse(
            match=best["match"],
            threshold=threshold,
            best=best,
            candidates=candidates[:5],
        )

    def _decode_png(self, value: str) -> bytes:
        try:
            raw = base64.b64decode(value, validate=True)
        except (binascii.Error, ValueError) as exc:
            raise HTTPException(status_code=422, detail="Invalid base64 image.") from exc

        try:
            image = Image.open(io.BytesIO(raw))
            image.load()
        except OSError as exc:
            raise HTTPException(status_code=422, detail="Invalid PNG image.") from exc

        if image.format != "PNG":
            raise HTTPException(status_code=422, detail="Image must be PNG.")

        return raw

    def _decode_template(self, value: str) -> bytes:
        try:
            return base64.b64decode(value, validate=True)
        except (binascii.Error, ValueError) as exc:
            raise HTTPException(status_code=422, detail="Invalid SourceAFIS template.") from exc

    def _score(self, arguments: list[str]) -> float:
        output = self._run(arguments)

        try:
            return float(output)
        except ValueError as exc:
            raise HTTPException(status_code=500, detail="Invalid SourceAFIS score.") from exc

    def _run(self, arguments: list[str]) -> str:
        try:
            process = subprocess.run(
                [
                    "java",
                    "-cp",
                    self.classpath,
                    self.main_class,
                    *arguments,
                ],
                check=True,
                capture_output=True,
                text=True,
                timeout=30,
            )
        except subprocess.TimeoutExpired as exc:
            raise HTTPException(status_code=504, detail="SourceAFIS timed out.") from exc
        except subprocess.CalledProcessError as exc:
            raise HTTPException(
                status_code=422,
                detail=f"SourceAFIS failed: {exc.stderr.strip() or exc.stdout.strip()}",
            ) from exc

        return process.stdout.strip()

    def _compare_response(self, score: float, threshold: float) -> CompareResponse:
        return CompareResponse(
            match=score >= threshold,
            score=round(score, 2),
            threshold=threshold,
        )


app = FastAPI(title="Nexgol Biometric Engine", version="0.2.0")
matcher: FingerprintMatcher = SourceAfisMatcher()


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/templates", response_model=TemplateResponse)
def create_template(payload: TemplateRequest) -> TemplateResponse:
    return matcher.create_template(payload.image)


@app.post("/compare", response_model=CompareResponse)
def compare(payload: CompareRequest) -> CompareResponse:
    return matcher.compare_images(
        stored_image=payload.stored_image,
        candidate_image=payload.candidate_image,
        threshold=payload.threshold,
    )


@app.post("/compare-templates", response_model=CompareResponse)
def compare_templates(payload: CompareTemplatesRequest) -> CompareResponse:
    return matcher.compare_templates(
        stored_template=payload.stored_template,
        candidate_template=payload.candidate_template,
        threshold=payload.threshold,
    )


@app.post("/identify-templates", response_model=IdentifyResponse)
def identify_templates(payload: IdentifyTemplatesRequest) -> IdentifyResponse:
    return matcher.identify_templates(
        candidate_template=payload.candidate_template,
        templates=payload.templates,
        threshold=payload.threshold,
    )
