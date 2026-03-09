# 🐾 PetConnect - Wiederfindung vermisster Haustiere mit KI

Eine **vollständige, produktionsreife Web-Applikation** zur Wiederfindung vermisster Hunde und Katzen mithilfe von **PyTorch-basierter Bildanalyse** und modernen Web-Technologien.

## 🎯 Projektübersicht

**PetConnect** verbindet Tierbesitzer mit Findern durch intelligente Bildverarbeitung:

- **Besitzer** registrieren ihre Haustiere und markieren sie als vermisst
- **Finder** laden Fotos gefundener Tiere hoch (keine Anmeldung erforderlich)
- **Unsere KI** vergleicht die Finder-Fotos mit vermissten Tieren via **ResNet50 + Cosine Similarity**
- **Automatische Benachrichtigungen** an Besitzer bei möglichen Treffern

---

## 🏗️ Technische Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                        FRONTEND (HTTP)                      │
│  HTML5 + CSS3 + Vanilla JavaScript (AJAX)                  │
│  • Responsive Design (Tailwind CSS)                        │
│  • Drag & Drop Image Upload                                │
│  • Real-time Notifications                                 │
└──────────┬──────────────────────────┬────────────────────────┘
           │                          │
      BACKEND (PHP 8.x)        KI-SERVICE (FastAPI)
      REST API                 PyTorch Server
           │                          │
    ┌──────▼─────────┐        ┌──────▼────────┐
    │  PHP/OOP       │        │  ResNet50     │
    │  • Auth        │        │  • Embeddings │
    │  • Pet Mgmt    │        │  • Similarity │
    │  • Finder API  │        │  • Batch Ops  │
    └──────┬─────────┘        └──────┬────────┘
           │                         │
    ┌──────▼─────────────────────────▼──────┐
    │      MySQL Database (InnoDB)          │
    │  • users, pets, missing_reports       │
    │  • finder_reports, match_results      │
    │  • Binary embeddings storage          │
    └───────────────────────────────────────┘
```

### 📁 Verzeichnisstruktur

```
PetConnect_beta_02/
├── backend/                 # PHP 8.x Backend
│   ├── config/
│   │   └── Database.php     # PDO Connection + Singleton
│   ├── models/
│   │   ├── User.php         # Auth, Sessions, Password Management
│   │   ├── Pet.php          # Pet CRUD + Missing Status
│   │   └── Finder.php       # Finder Reports + KI Matching
│   ├── api/
│   │   ├── auth.php         # /api/auth/* endpoints
│   │   ├── pets.php         # /api/pets/* endpoints
│   │   └── finder.php       # /api/finder/* endpoints (public)
│   └── uploads/            # User-uploaded images
│       └── pets/
│       └── finder/
│
├── frontend/                # HTML5 + CSS + JavaScript
│   ├── pages/
│   │   ├── index.html       # Homepage
│   │   ├── found.html       # "Tier gefunden?" (drag & drop)
│   │   ├── missing-pets.html # Public missing board
│   │   └── login.html       # Auth page
│   ├── js/
│   │   └── api.js           # API Client + Session Management
│   └── css/
│       └── styles.css       # Tailwind + Custom (Pet-themed)
│
├── ai-service/              # Python FastAPI Service
│   ├── main.py              # FastAPI Server mit PyTorch
│   ├── requirements.txt      # Python Dependencies
│   ├── models/              # Pretrained models cache
│   └── uploads/             # Temporary uploads
│
├── database/
│   └── schema.sql           # MySQL Schema + Indexes
│
└── docs/                    # Documentation

```

---

## 🚀 Installation & Setup

### 1. **Voraussetzungen**

- PHP 8.0+
- MySQL 5.7+
- Python 3.9+
- Node.js (optional, for build tools)
- CUDA 11.8+ (optional, für GPU acceleration)

### 2. **Datenbank einrichten**

```bash
# Importiere das Schema
mysql -u root -p < database/schema.sql

# Oder manuell:
mysql -u root -p
> CREATE DATABASE petconnect;
> USE petconnect;
> SOURCE database/schema.sql;
```

### 3. **PHP Backend konfigurieren**

```bash
# Backend-Verzeichnis navigieren
cd backend

# Uploads-Verzeichnis erstellen
mkdir -p uploads/pets uploads/finder
chmod 755 uploads/*

# Database-Konfiguration anpassen (config/Database.php)
# - DB Host, Name, Username, Password
```

Starte einen PHP-Server:

```bash
# Vom Backend-Verzeichnis
php -S localhost:8001
```

### 4. **Python KI-Service starten**

```bash
# Dependencies installieren
cd ai-service
pip install -r requirements.txt

# FastAPI Server starten
python main.py
```

Server läuft dann unter **http://localhost:8000**

**Swagger-Dokumentation**: http://localhost:8000/docs

### 5. **Frontend bereitstellen**

```bash
# Einfach in einen Web-Server kopieren oder lokal öffnen
# Die Frontend-Dateienhaben relative Pfade zu /api/
```

---

## 📚 API Dokumentation

Die Kommunikation erfolgt über **REST APIs** zwischen Frontend/Backend und Backend/KI-Service.

### **1. Authentifizierungs-API** (`/backend/api/auth.php`)

#### Registrierung

```http
POST /api/auth/register
Content-Type: application/json

{
    "username": "johndoe",
    "email": "john@example.com",
    "password": "SecurePass123",
    "first_name": "John",
    "last_name": "Doe"
}

Response:
{
    "success": true,
    "message": "Benutzerkonto erfolgreich erstellt",
    "user_id": 42
}
```

#### Login

```http
POST /api/auth/login
Content-Type: application/json

{
    "email": "john@example.com",
    "password": "SecurePass123"
}

Response:
{
    "success": true,
    "message": "Login erfolgreich",
    "user_id": 42,
    "session_token": "a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a"
}
```

#### Session Validierung

```http
POST /api/auth/validate_session
Content-Type: application/json

{
    "session_token": "a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a"
}

Response:
{
    "success": true,
    "message": "Session valid",
    "user": {
        "id": 42,
        "username": "johndoe",
        "email": "john@example.com",
        "first_name": "John",
        "last_name": "Doe"
    }
}
```

### **2. Pet Management API** (`/backend/api/pets.php`)

#### Tier hinzufügen

```http
POST /api/pets/pet/create
Content-Type: application/json

{
    "session_token": "...",
    "name": "Max",
    "species": "dog",
    "breed": "German Shepherd",
    "color": "Brown & Blonde",
    "age": 3,
    "weight": 30.5,
    "features": "Weiße Pfote rechts vorne",
    "microchip_id": "901000111223344"
}

Response:
{
    "success": true,
    "message": "Tier erfolgreich registriert",
    "pet_id": 123
}
```

#### Bild hochladen

```http
POST /api/pets/pet/upload
Content-Type: multipart/form-data

- pet_id: 123
- image: [binary file]
- session_token: ...

Response:
{
    "success": true,
    "message": "Bild erfolgreich hochgeladen",
    "image": "pet_123_1708234567.jpg",
    "embedding": true
}
```

#### Als vermisst markieren

```http
POST /api/pets/missing
Content-Type: application/json

{
    "session_token": "...",
    "pet_id": 123,
    "location": "Potsdamer Platz, Berlin",
    "date_missing": "2026-02-20",
    "description": "Max ist aus dem Garten entkommen",
    "latitude": 52.5151,
    "longitude": 13.3768,
    "reward": 500.00
}

Response:
{
    "success": true,
    "message": "Tier als vermisst markiert",
    "report_id": 456
}
```

#### Alle vermissten Tiere abrufen

```http
GET /api/pets/missing?limit=50&offset=0

Response:
{
    "success": true,
    "pets": [
        {
            "id": 123,
            "name": "Max",
            "species": "dog",
            "breed": "German Shepherd",
            "location_last_seen": "Potsdamer Platz, Berlin",
            "date_missing": "2026-02-20",
            "reward_amount": 500.00,
            "first_name": "John",
            "phone": "+49 123 456789"
        }
    ],
    "count": 1,
    "limit": 50,
    "offset": 0
}
```

### **3. Finder / KI-Matching API** (`/backend/api/finder.php`)

#### Finder Report einreichen (PUBLIC - keine Auth)

```http
POST /api/finder/submit
Content-Type: multipart/form-data

- image: [binary file]
- location: "Unter den Linden, Berlin"
- latitude: 52.5169
- longitude: 13.3886
- description: "Großer brauner Hund, scheint verloren"
- finder_contact: "Anna Schmidt"
- finder_phone: "+49 987 654321"

Response:
{
    "success": true,
    "message": "Bericht erfolgreich eingereicht",
    "finder_report_id": 789,
    "matches": [
        {
            "pet_id": 123,
            "name": "Max",
            "species": "dog",
            "breed": "German Shepherd",
            "similarity": 0.8743,
            "owner_email": "john@example.com",
            "owner_phone": "+49 123 456789",
            "location_last_seen": "Potsdamer Platz, Berlin"
        }
    ]
}
```

---

## 🤖 Python KI-Service API

Der Python-Service läuft auf **Port 8000** und bietet folgende Endpoints:

### **POST /api/generate_embedding**

Generiert Feature-Vektor für ein Tierbild.

```json
Request:
{
    "image_path": "/path/to/pet_123_1708234567.jpg",
    "pet_id": 123
}

Response:
{
    "success": true,
    "embedding": "SGVsbG8gV29ybGQhIFRoaXMgaXMgYSBiYXNlNjQgZW5jb2RlZCBl...",
    "shape": [2048],
    "message": "Embedding erfolgreich generiert"
}
```

### **POST /api/compare_embeddings**

Vergleicht zwei Embeddings mit Cosine Similarity.

```json
Request:
{
    "embedding1": "SGVsbG8gV29ybGQh...",
    "embedding2": "V29ybGQgSGVsbG8h..."
}

Response:
{
    "success": true,
    "similarity": 0.8743,
    "message": "Vergleich erfolgreich"
}
```

### **POST /api/upload_and_embed**

Lädt Datei hoch und generiert Embedding.

```http
POST /api/upload_and_embed?pet_id=123
Content-Type: multipart/form-data

- file: [binary image]

Response:
{
    "success": true,
    "embedding": "...",
    "shape": [2048],
    "filename": "image.jpg"
}
```

### **GET /api/model_info**

Gibt Informationen über das verwendete Modell zurück.

```json
Response:
{
    "model": "ResNet50 (pretrained on ImageNet)",
    "embedding_dimension": 2048,
    "framework": "PyTorch",
    "device": "cuda",
    "normalization": "L2",
    "similarity_metric": "Cosine Similarity"
}
```

---

## 🔄 Kommunikationsflow

### **Workflow: Tier als vermisst melden**

```
1. Frontend (login.html)
   ↓
2. API: POST /api/auth/login
   ↓ (Backend: User.php -> validateSession)
3. Frontend erhält session_token
   ↓
4. Frontend (my-pets.html): Tier markieren
   ↓
5. API: POST /api/pets/missing
   ↓ (Backend: Pet.php -> markAsMissing)
6. DB: UPDATE pets SET is_missing=TRUE
   ↓
7. DB: INSERT INTO missing_reports
   ↓
8. Erfolgreiche Response an Frontend
```

### **Workflow: Tier gefunden → KI-Matching**

```
1. Finder: found.html (keine Anmeldung nötig)
   ↓ (Drag & Drop Upload)
2. API: POST /api/finder/submit (multipart/form-data)
   ↓ (Backend: Finder.php)
3. Image speichern unter /uploads/finder/
   ↓
4. PHP: cURL → Python AI Service
   ↓ (POST /api/generate_embedding)
5. Python (main.py):
   - Bild laden (PIL)
   - ResNet50 Feature Extraction
   - L2 Normalisierung
   - Base64 encode
   ↓
6. PHP speichert Finder-Embedding in DB
   ↓
7. PHP: Holt alle vermissten Tier-Embeddings
   ↓
8. PHP: Loop für jeden vermissten Tier:
   - cURL → Python AI Service
   - POST /api/compare_embeddings
   - Cosine Similarity berechnet
   ↓
9. Python gibt Ähnlichkeit zurück (0.0-1.0)
   ↓
10. PHP: Filters Matches (> threshold 0.65)
    ↓
11. PHP: Speichert in match_results Tabelle
    ↓
12. PHP: Sendet Benachrichtigungen an Besitzer
    ↓
13. Frontend: Matches anzeigen (ranked by similarity)
```

---

## 🔐 Sicherheitsfeatures

### **Authentifizierung & Authorization**

- ✅ **Bcrypt Passwort-Hashing** (Cost: 12)
- ✅ **Session Token Management** (32 bytes random)
- ✅ **HTTPS-ready** (production deployment)
- ✅ **SQL Injection Prevention** (Prepared Statements + PDO)
- ✅ **CSRF Protection ready** (Token validation)

### **Datenschutz**

- ✅ **Sensitive Data**: Emails, Telefone nur für Authentifizierte Benutzer
- ✅ **File Upload Validierung**: Dateityp, Größe, MIME-Type
- ✅ **Rate Limiting ready** (kann in Production hinzugefügt werden)
- ✅ **GDPR-ready** (User-Daten Löschung möglich)

### **Best Practices**

```php
// ✅ KORREKT - Prepared Statements
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);

// ❌ FALSCH - SQL Injection möglich
$query = "SELECT * FROM users WHERE email = '$email'";
```

---

## 📊 Datenbank Schema Highlights

### **Embeddings Storage**

```sql
-- pet_images Tabelle speichert Feature-Vektoren
CREATE TABLE pet_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pet_id INT NOT NULL,
    embedding LONGBLOB,          -- Binary (2048 * 4 bytes = 8KB pro Embedding)
    embedding_model VARCHAR(50), -- Modell Version (resnet50, etc)
    is_primary BOOLEAN
);

-- Indexes für schnelle Abfragen
INDEX idx_pet_id (pet_id);
INDEX idx_is_primary (is_primary);
```

### **Ähnlichkeitsergebnisse**

```sql
-- match_results speichert KI-Vergleiche
CREATE TABLE match_results (
    finder_report_id INT,
    pet_id INT,
    similarity_score DECIMAL(5,4),  -- z.B. 0.8743
    user_notified BOOLEAN,
    UNIQUE KEY unique_match (finder_report_id, pet_id)
);
```

---

## 🧪 Testing

### **API Test mit cURL**

```bash
# Register User
curl -X POST http://localhost:8001/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "max_finder",
    "email": "max@example.com",
    "password": "SuperSecure123",
    "first_name": "Max",
    "last_name": "Finder"
  }'

# Create Pet
curl -X POST http://localhost:8001/api/pets/pet/create \
  -H "Content-Type: application/json" \
  -d '{
    "session_token": "...",
    "name": "Bella",
    "species": "dog",
    "breed": "Golden Retriever",
    "age": 2,
    "weight": 25
  }'

# Check AI Service Health
curl http://localhost:8000/health
```

---

## 🎨 Frontend Features

### **Responsive Design**

- ✅ Mobile-first approach
- ✅ Tailwind CSS Framework
- ✅ Animated transitions
- ✅ Dark mode ready

### **Interaktivität**

- ✅ Drag & Drop Image Upload
- ✅ Real-time Form Validation
- ✅ AJAX API Calls (no page refresh)
- ✅ Modal Dialogs
- ✅ Loading Spinners

### **Accessibility**

- ✅ Semantic HTML5
- ✅ ARIA Labels
- ✅ Keyboard Navigation
- ✅ High Contrast Colors

---

## 🚀 Production Deployment

### **Checkliste**

- [ ] Database Backups einrichten
- [ ] PHP Security hardening (`php.ini`)
- [ ] HTTPS/TLS aktivieren
- [ ] Environment Variables für Secrets
- [ ] AI Service GPU Optimization
- [ ] Reverse Proxy (Nginx/Apache)
- [ ] Rate Limiting implementieren
- [ ] Logging & Monitoring setup
- [ ] CDN für Static Assets
- [ ] Caching Strategy (Redis)

---

## 📝 Lizenz

MIT License - Frei verwendbar für Open Source und private Projekte

---

## 👨‍💻 Entwicklungs-Team

Entwickelt als vollständiges Beispiel-Projekt für:
- **PHP/OOP Backend Development**
- **PyTorch Machine Learning Integration**
- **Modern Frontend Technologien**
- **Secure Database Design**

---

## 📞 Support & Feedback

Fragen? Erstelle einen Issue oder kontaktiere: **developers@petconnect.local**

---

**Machen Sie das Web besser für vermisste Haustiere! 🐾**
