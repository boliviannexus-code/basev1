# Biometric Engine

Microservicio experimental FastAPI para conectar Laravel con un endpoint `/compare`.

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

## Nota Tecnica

`ExperimentalImageMatcher` solo valida la conexion end-to-end y compara imagenes normalizadas.
No es un matcher biometrico de minucias. Debe reemplazarse por un motor real como SourceAFIS
u otro SDK biometrico compatible.
