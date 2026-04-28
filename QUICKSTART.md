# 🚀 PetConnect - Schnellstart Guide

Folge dieser Anleitung, um PetConnect schnell zu starten!

## ⏱️ Geschätzter Setup-Zeit: 10 Minuten

---

## 📋 Voraussetzungen

Installiere folgende Software:

- **PHP 8.0+** → https://www.php.net/downloads
- **MySQL 5.7+** → https://www.mysql.com/downloads/mysql/
- **Python 3.9+** → https://www.python.org/downloads/
- **Git** (optional) → https://git-scm.com/

### Verifiziere Installation

```bash
# Im Terminal/PowerShell prüfen:
php --version
mysql --version
python --version
```

---

## 🗄️ Schritt 1: Datenbank initialisieren

### Windows (PowerShell)

```powershell
# Starte MySQL
mysql -u root

# Paste in MySQL:
SOURCE C:\Users\fabia\Desktop\PetConnect_beta_02\database\schema.sql;
EXIT;
```

### macOS/Linux

```bash
mysql -u root -p < ~/Desktop/PetConnect_beta_02/database/schema.sql
```

**Ergebnis**: Datenbank `petconnect` sollte jetzt mit allen Tabellen existieren.

---

## 🔧 Schritt 2: PHP Backend starten

```powershell
# Terminal/PowerShell

# Navigate zu Backend
cd C:\Users\fabia\Desktop\PetConnect_beta_02\backend

# Erstelle /uploads Verzeichnisse
mkdir uploads/pets
mkdir uploads/finder

# Starte PHP Server
php -S localhost:8001

# ✅ Output: 
# Development Server (http://localhost:8001) started
```

**Teste** den Server: http://localhost:8001/

---

## 🤖 Schritt 3: Python AI-Service starten

**Offne ein NEUES Terminal/PowerShell fenster!**

```powershell
# Terminal 2 öffnen

cd C:\Users\fabia\Desktop\PetConnect_beta_02\ai-service

# Dependencies installieren
pip install -r requirements.txt

# Starte FastAPI Server
python main.py

# ✅ Output sollte sein:
# 🚀 PetConnect - KI-Service für Bildanalyse
# 💻 Device: cpu (oder cuda)
# ℹ️  Uvicorn running on http://0.0.0.0:8000
```

**Teste** den Service: http://localhost:8000/health

---

## 🌐 Schritt 4: Frontend öffnen

**Öffne einen Browser** (neues Fenster):

```
http://localhost:8001/frontend/pages/index.html
```

**Oder** öffne direkt die Datei:
```
C:\Users\fabia\Desktop\PetConnect_beta_02\frontend\pages\index.html
```

Du solltest die PetConnect Homepage sehen! 🎉

---

## 🔄 Schritt 5 (optional): Alle Embeddings neu aufbauen

Wenn die Wiederfindung schwach ist oder alte Embeddings inkonsistent sind, kannst du alle Tier-Embeddings neu erzeugen.

```powershell
# Im Projekt-Root ausführen
php backend/scripts/rebuild_all_embeddings.php --dry-run

# Danach real ausführen
php backend/scripts/rebuild_all_embeddings.php

# Optional: nur ein bestimmtes Tier
php backend/scripts/rebuild_all_embeddings.php --pet-id=4
```

Voraussetzung: Der KI-Service muss laufen (Port 8002 in der aktuellen Backend-Konfiguration).

---

## 🧪 Test Workflow (5 Minuten Demo)

### 1️⃣ Registriere einen Test-Benutzer

→ Klick auf **Login** im Frontend

→ Wechsel zu **Registrierung**

→ Fülle aus:
- Username: `testuser`
- Email: `test@example.com`
- Password: `TestPass123`

→ Klick **Registrieren**

### 2️⃣ Erstelle ein Test-Tier

→ Gehe zu **Dashboard** (nach Login)

→ Klick **Neues Tier hinzufügen**

→ Fülle aus:
- Name: `Fluffy`
- Art: `Katze`
- Rasse: `Siamese`
- Alter: `3`

→ **Speichern**

### 3️⃣ Markiere als vermisst

→ Gehe zu Deinem Tier

→ Klick **Als vermisst markieren**

→ Fülle aus:
- Ort: `Potsdamer Platz, Berlin`
- Datum: `2026-02-20`

→ **Speichern**

### 4️⃣ Test Finder Feature

→ Gehe zu **Tier gefunden?** (Public page)

→ **Lade ein Katzenbild hoch** (Drag & Drop)

→ Fülle Fundort aus

→ **Report einreichen**

→ Sieh die KI-Ergebnisse! 🎯

---

## 📁 File Locations

```
Datenbank Schema
└─ database/schema.sql

Backend API Files
├─ backend/api/auth.php         (http://localhost:8001/api/auth/*)
├─ backend/api/pets.php         (http://localhost:8001/api/pets/*)
└─ backend/api/finder.php       (http://localhost:8001/api/finder/*)

Frontend Seiten (Öffne im Browser)
├─ frontend/pages/index.html           (Startseite)
├─ frontend/pages/login.html           (Anmeldung)
├─ frontend/pages/found.html           (Tier gefunden → mit Drag&Drop!)
└─ frontend/pages/missing-pets.html    (Vermisste Tiere Board)

AI Service
└─ ai-service/main.py           (http://localhost:8000)
```

---

## ✅ Verifikations-Checklist

Nach dem Setup sollte folgendes funktionieren:

- [ ] PHP Server läuft auf Port 8001
- [ ] Python AI Service läuft auf Port 8000
- [ ] Datenbank ist erreichbar
- [ ] Frontend Pages laden ohne Fehler
- [ ] **Registrierung** funktioniert
- [ ] **Login** funktioniert
- [ ] **Image Upload** (Drag & Drop) funktioniert
- [ ] **KI-Matching** zeigt Results

---

## 🐛 Troubleshooting

### Problem: "Connection refused: localhost:8001"

**Lösung**: 
```powershell
# Stelle sicher, dass PHP Server läuft:
cd backend
php -S localhost:8001
```

### Problem: "Cannot connect to MySQL"

**Lösung**:
```powershell
# Verifiziere MySQL läuft:
mysql -u root

# Oder in config/Database.php überprüfen:
# - Username: root
# - Password: (leer oder anpassen)
# - Database: petconnect
```

### Problem: "No module named 'torch'"

**Lösung**:
```powershell
cd ai-service
pip install -r requirements.txt
```

### Problem: Images werden nicht hochgeladen

**Lösung**:
```powershell
# Überprüfe Upload-Verzeichnis Permissions:
chmod 755 backend/uploads/pets
chmod 755 backend/uploads/finder
```

---

## 📚 Nächste Schritte

Nach erfolgreicher Installation:

1. **Lese** `README.md` für technische Details
2. **Erkunde** die API in `docs/`
3. **Schreibe** Tests für die API
4. **Deploye** auf einen Production Server
5. **Optimiere** die KI-Performance mit GPU

---

## 🎯 Wichtige URLs

| URL | Beschreibung |
|-----|-------------|
| http://localhost:8001/ | Backend Root |
| http://localhost:8001/frontend/pages/index.html | **🏠 Startseite** |
| http://localhost:8001/frontend/pages/found.html | **📸 Tier gefunden** (Drag & Drop!) |
| http://localhost:8001/frontend/pages/missing-pets.html | **🔍 Vermisste Tiere** |
| http://localhost:8001/frontend/pages/login.html | **🔑 Login/Registrierung** |
| http://localhost:8000/docs | **🤖 AI Service Dokumentation** |
| http://localhost:8000/health | **✅ AI Service Health Check** |

---

## 🎊 Glückwunsch!

Du hast PetConnect erfolgreich installiert! 🎉

Starte sofort Haustiere zu retten. Happy Matching! 🐾

---

**Support**: Falls Probleme auftreten, überprüfe die Logs in Terminal/PowerShell
