# PetConnect - Single-Container-Image für Coolify
# PHP (Frontend + API via router.php) und Python AI-Service (FastAPI/ResNet50)
# laufen im selben Container, weil der AI-Service Bilder über den Dateipfad
# aus backend/uploads liest. MySQL läuft als separater Service.

FROM python:3.12-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PIP_NO_CACHE_DIR=1 \
    TORCH_HOME=/opt/torch \
    PORT=8000 \
    AI_HOST=127.0.0.1 \
    AI_PORT=8002 \
    AI_SERVICE_URL=http://127.0.0.1:8002 \
    PHP_CLI_SERVER_WORKERS=4 \
    APP_ENV=production \
    APP_DEBUG=false

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        php-cli php-mysql php-curl php-mbstring curl ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# CPU-Build von PyTorch (deutlich kleiner als die CUDA-Variante)
COPY ai-service/requirements.txt ai-service/requirements.txt
RUN pip install --index-url https://download.pytorch.org/whl/cpu torch torchvision \
    && pip install -r ai-service/requirements.txt

# ResNet50-Gewichte beim Build laden, nicht bei jedem Container-Start
RUN python -c "from torchvision.models import resnet50, ResNet50_Weights; resnet50(weights=ResNet50_Weights.IMAGENET1K_V1)"

COPY . .

RUN chmod +x docker/entrypoint.sh \
    && mkdir -p backend/uploads/pets backend/uploads/finder backend/logs

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=90s --retries=3 \
    CMD curl -fsS "http://127.0.0.1:${PORT}/pages/index.html" > /dev/null \
        && curl -fsS "http://127.0.0.1:${AI_PORT}/health" > /dev/null || exit 1

ENTRYPOINT ["/app/docker/entrypoint.sh"]
