# PetConnect auf Coolify deployen

## Aufbau

- **app** (`Dockerfile`): PHP-Server (Frontend + API über `router.php`, Port 8000) und der
  Python-AI-Service (ResNet50, nur intern auf `127.0.0.1:8002`) in einem Container.
  Beide müssen dieselben Dateien in `backend/uploads` sehen, weil der AI-Service Bilder
  über den Dateipfad liest.
- **mysql** (`mysql:8.0`): Datenbank. Das Schema wird beim ersten Start automatisch
  von `docker/init-db.php` importiert.

## Variante A – Docker Compose (empfohlen)

1. In Coolify: *New Resource → Git Repository → Build Pack „Docker Compose“*.
2. Unter *Environment Variables* setzen:
   - `DB_PASS` – Passwort für den DB-User
   - `MYSQL_ROOT_PASSWORD` – Root-Passwort für MySQL
   - optional `DB_NAME`, `DB_USER` (Default: `petconnect`)
3. Beim Service `app` die Domain eintragen (Port 8000) und deployen.

Volumes `uploads` (Bilder) und `mysql-data` bleiben bei Redeploys erhalten.

## Variante B – nur Dockerfile

Wenn MySQL bereits als eigene Coolify-Ressource läuft:

1. *Build Pack „Dockerfile“*, *Ports Exposes* = `8000`.
2. Umgebungsvariablen: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Persistenten Speicher auf `/app/backend/uploads` anlegen.

## Hinweise

- Der erste Build dauert einige Minuten (PyTorch CPU + Modellgewichte, Image ca. 1,5–2 GB).
- Der Container braucht ca. 1–1,5 GB RAM (ResNet50 im Speicher).
- Lokal testen: in `docker-compose.yml` den `ports`-Block einkommentieren, `.env` aus
  `.env.example` anlegen, `docker compose up --build`, dann http://localhost:8000.
