# Biometric Engine

Microservicio FastAPI para conectar Laravel con SourceAFIS.

SourceAFIS esta integrado mediante su libreria oficial Java `com.machinezoo.sourceafis:sourceafis`.
FastAPI recibe imagenes PNG/base64, genera templates SourceAFIS reutilizables y compara templates
preprocesados para verificacion 1:1 o identificacion 1:N.

## Ejecutar

```bash
cd biometric-engine
python -m venv venv
source venv/bin/activate
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8001
```

En Windows PowerShell:

```powershell
cd biometric-engine
python -m venv venv
.\venv\Scripts\Activate.ps1
pip install -r requirements.txt
uvicorn main:app --host 0.0.0.0 --port 8001
```

Laravel en Docker debe usar:

```env
BIOMETRIC_ENGINE_URL=http://biometric-engine:8001
```

Tambien puede ejecutarse con Docker Compose desde la raiz del proyecto:

```bash
docker compose up -d --build biometric-engine
```

## Endpoints

Generar template:

```bash
curl -X POST http://127.0.0.1:8001/templates \
  -H 'Content-Type: application/json' \
  -d '{"image":"<png-base64>"}'
```

Comparar templates:

```bash
curl -X POST http://127.0.0.1:8001/compare-templates \
  -H 'Content-Type: application/json' \
  -d '{
    "stored_template": "<sourceafis-template-base64>",
    "candidate_template": "<sourceafis-template-base64>",
    "threshold": 40
  }'
```

Identificar contra varios templates:

```bash
curl -X POST http://127.0.0.1:8001/identify-templates \
  -H 'Content-Type: application/json' \
  -d '{
    "candidate_template": "<sourceafis-template-base64>",
    "templates": [
      {"id": 1, "finger_position": "right_thumb", "template": "<sourceafis-template-base64>"}
    ],
    "threshold": 40
  }'
```

## Nota Tecnica

SourceAFIS recomienda indicar DPI porque no es scale-invariant. El CLI usa `dpi(500)`, que es
el valor comun para lectores U.are.U/DigitalPersona 4500.
