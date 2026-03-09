# PetConnect - System Architecture

## 🏗️ High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         WEB BROWSER / CLIENT                         │
│     (HTML5 + CSS3 + Vanilla JavaScript)                              │
└──────────────┬──────────────────────────────────────────────────────┘
               │ HTTP(S) REST AJAX
               │
    ┌──────────▼──────────────────────────┐
    │                                      │
    ├─ Login / Registration ──────────────┤
    │                                      │
    │  POST /api/auth/register            │
    │  POST /api/auth/login               │
    │  POST /api/auth/logout              │
    │                                      │
    ├─ Pet Management ──────────────────┤
    │                                      │
    │  POST /api/pets/pet/create          │
    │  POST /api/pets/pet/upload          │  ← Calls AI Service
    │  POST /api/pets/missing             │      for Embeddings
    │  GET  /api/pets/list                │
    │  GET  /api/pets/missing             │
    │                                      │
    ├─ Finder Reports (PUBLIC) ──────────┤
    │                                      │
    │  POST /api/finder/submit            │  ← No Auth Required
    │  GET  /api/finder/report            │      Calls AI for Matching
    │  GET  /api/finder/reports           │
    │                                      │
    └──────┬──────────────────────────────┘
           │ PDO/MySQL Queries
           │
    ┌──────▼───────────────────────────────────────┐
    │   PHP 8.x Backend Server (OOP)                │
    │   Port: 8001                                  │
    ├──────────────────────────────────────────────┤
    │                                               │
    │ Models:                                       │
    │ • User.php    - Authentication & Sessions    │
    │ • Pet.php     - Pet CRUD & Missing Logic     │
    │ • Finder.php  - Finder Reports & Matching   │
    │                                               │
    │ API Handlers:                                 │
    │ • auth.php    - Auth Endpoints               │
    │ • pets.php    - Pet Endpoints                │
    │ • finder.php  - Finder Endpoints             │
    │                                               │
    └──────┬────────────┬────────────────────────┘
           │            │
    cURL   │            │ PDO
    HTTP   │            │ MySQL
           │            │
    ┌──────▼──────┐  ┌──▼──────────────────────┐
    │   AI SERVICE │  │  MYSQL DATABASE        │
    │   (Python)   │  │  (InnoDB)              │
    │   Port: 8000 │  ├────────────────────────┤
    │              │  │                        │
    │ FastAPI +    │  │ Tables:                │
    │ PyTorch      │  │ • users                │
    │              │  │ • pets                 │
    │ Models:      │  │ • pet_images (binary)  │
    │              │  │ • missing_reports      │
    │ Endpoints:   │  │ • finder_reports       │
    │ • generate   │  │ • match_results        │
    │   _embedding │  │ • sessions             │
    │ • compare    │  │ • activity_logs        │
    │   _embeddings│  │                        │
    │ • upload_    │  │ Indexes:               │
    │   and_embed  │  │ • pet_id (FK)          │
    │ • health     │  │ • is_missing           │
    │ • model_info │  │ • similarity_score     │
    │              │  │ • created_at           │
    └──────────────┘  └────────────────────────┘
```

---

## 📊 Data Flow: Image Similarity Matching

```
┌─────────────────────────────────────────────────────────────┐
│                    Finder Upload (Public)                   │
│                       found.html                             │
└──────────────────┬──────────────────┬──────────────────────┘
                   │                  │
          Drag Drop│                  │Form Data
                   ▼                  ▼
        ┌──────────────────────────────────────┐
        │  POST /api/finder/submit             │
        │  • image (multipart)                 │
        │  • location                          │
        │  • description                       │
        │  • finder_contact                    │
        └──────────────┬───────────────────────┘
                       │
                       │ Finder.php
                       │ • Save image to disk
                       │ • Call AI Service
                       ▼
        ┌──────────────────────────────────────┐
        │  POST http://localhost:8000/api/     │
        │  generate_embedding                  │
        │  • image_path                        │
        └──────────────┬───────────────────────┘
                       │
                       │ Python (main.py)
                       │ • Load PIL Image
                       │ • Pass through ResNet50
                       │ • Extract 2048-dim vector
                       │ • L2 Normalization
                       │ • Base64 encode
                       ▼
        ┌──────────────────────────────────────┐
        │ Response: {embedding: "..."}         │
        └──────────────┬───────────────────────┘
                       │
                       │ Finder.php
                       │ • Store embedding in DB
                       │ • Fetch all missing pets
                       ▼                        Row 1: pet_id=1, embedding=binary
        ┌──────────────────────────────────────┐ Row 2: pet_id=2, embedding=binary
        │ Batch Loop:                          │ Row 3: pet_id=3, embedding=binary
        │ For each missing pet {               │
        │   POST .../compare_embeddings        │
        │   • embedding1 (finder)              │
        │   • embedding2 (pet_i)               │
        │ }                                    │
        └──────────────┬───────────────────────┘
                       │
                       │ cURL requests
                       │ (parallel possible)
                       ▼
        ┌──────────────────────────────────────┐
        │  POST http://localhost:8000/api/     │
        │  compare_embeddings                  │
        │  • embedding1: "base64..."           │
        │  • embedding2: "base64..."           │
        └──────────────┬───────────────────────┘
                       │
                       │ Python (main.py)
                       │ • Decode base64
                       │ • np.dot(emb1, emb2)
                       │ • Cosine Similarity
                       │ Return: 0.0 - 1.0
                       ▼
        ┌──────────────────────────────────────┐
        │ Response: {                          │
        │   similarity: 0.8743                 │
        │ }                                    │
        └──────────────┬───────────────────────┘
                       │
                       │ Finder.php
                       │ • If score > 0.65:
                       │   - Store in match_results
                       │   - Add to results array
                       │ • Sort by similarity_score DESC
                       │ • Take top 10
                       ▼
        ┌──────────────────────────────────────┐
        │ Response: {                          │
        │   finder_report_id: 789,             │
        │   matches: [                         │
        │     {                                │
        │       pet_id: 1,                     │
        │       name: "Max",                   │
        │       similarity: 0.8743,            │
        │       owner_email: "john@...",       │
        │       owner_phone: "+49..."          │
        │     },                               │
        │     ...                              │
        │   ]                                  │
        │ }                                    │
        └──────────────┬───────────────────────┘
                       │
                       │ Frontend JS
                       │ • Display results
                       │ • Show modal
                       ▼
        ┌──────────────────────────────────────┐
        │  Finder sees:                        │
        │  🏆 Match #1: Max - 87.43%          │
        │     Owner: john@example.com          │
        │     Phone: +49 123 456789            │
        │  🏆 Match #2: Bella - 76.12%        │
        │     Owner: ...                       │
        └──────────────────────────────────────┘
```

---

## 🔐 Authentication Flow

```
┌────────────────────────────────────┐
│     User Registriert / Loggt ein   │
│         login.html                 │
└────────────┬──────────────────────┘
             │
    ┌────────▼──────────┐
    │ POST /api/auth/   │
    │ register or login │
    └────────┬──────────┘
             │
      ┌──────▼─────────────────────────┐
      │ Backend: auth.php              │
      │ • Validate input               │
      │ • Check credentials            │
      │ • Generate session_token       │
      │   (32 bytes random)            │
      └──────┬──────────────────────────┘
             │
      ┌──────▼──────────────────────────┐
      │ Insert into sessions table      │
      │ • user_id                       │
      │ • session_token (UNIQUE)        │
      │ • ip_address                    │
      │ • expires_at (NOW + 7 days)     │
      └──────┬──────────────────────────┘
             │
      ┌──────▼──────────────────────────┐
      │ Response: {                     │
      │   session_token: "a7f3...",     │
      │   user_id: 42                   │
      │ }                               │
      └──────┬──────────────────────────┘
             │
      ┌──────▼──────────────────────────┐
      │ Frontend: localStorage.setItem  │
      │ • sessionToken                  │
      │ • userId                        │
      └──────┬──────────────────────────┘
             │
      ┌──────▼──────────────────────────┐
      │ Alle künftigen API calls:       │
      │ • Schicke session_token         │
      │ • Backend validiert Token       │
      │ • Check: Token exists &         │
      │   expires_at > NOW()            │
      │ • Führe Aktion für User aus     │
      └──────────────────────────────────┘
```

---

## 🗄️ Database Relationships

```
┌──────────────────┐
│     users        │
│  ────────────    │
│  id (PK)         │
│  username        │
│  email           │
│  password_hash   │  ◄──────────┐
│  phone           │             │
│  location        │             │
│  created_at      │             │
└────┬─────────────┘             │
     │ 1 : N                      │
     │                            │
┌────▼──────────────────────┐   │
│     pets                  │   │
│  ────────────────────     │   │
│  id (PK)                  │   │
│  user_id (FK) ────────────┼───┤
│  name                     │   │
│  species (enum)           │   │
│  breed                    │   │
│  is_missing               │   │
│  profile_image            │   │
│  created_at               │   │
└────┬──────────────────────┘   │
     │ 1 : N                      │
     │                            │
┌────▼──────────────────────┐   │
│     pet_images            │   │
│  ────────────────────     │   │
│  id (PK)                  │   │
│  pet_id (FK) ─────┐       │   │
│  embedding        │       │   │    ┌──────────────────────┐
│  (LONGBLOB)       │       │   │    │  missing_reports     │
│  is_primary       │       │   │    │  ──────────────────  │
│  uploaded_at      │       │   │    │  id (PK)             │
└───────────────────┘       │   │    │  pet_id (FK) ────────┼───┤
                            │   │    │  user_id (FK) ───────┘
                            │   │    │  location_last_seen  │
                            │   │    │  date_missing        │
                            │   │    │  status (enum)       │
                            │   │    │  created_at          │
                            │   │    └──────────────────────┘
                            │   │
                            │   └──────────────────────┐
                            │                         │
┌───────────────────────────▼─────────────────────────▼───┐
│          match_results                                    │
│  ──────────────────────────────────────────────────────  │
│  id (PK)                                                  │
│  finder_report_id (FK)                                   │
│  pet_id (FK)                                             │
│  similarity_score (DECIMAL 0-1)                          │
│  user_notified (BOOLEAN)                                 │
│  matched_at (TIMESTAMP)                                  │
│  UNIQUE(finder_report_id, pet_id)                        │
└─────────────────────────────────────────────────────────┘
```

---

## 🔄 Technology Stack Summary

| Layer | Technology | Purpose |
|-------|-----------|---------|
| **Frontend** | HTML5 + CSS3 | Structure & Styling |
| | Vanilla JS + AJAX | Interactivity (no jQuery/frameworks) |
| | Tailwind CSS | Responsive Design |
| **Backend** | PHP 8.x | REST API Server |
| | OOP/Classes | Clean Code Architecture |
| | PDO | Secure Database Queries |
| | cURL | API-to-API Communication |
| **AI Service** | Python 3.9+ | ML Backend |
| | FastAPI | REST Framework |
| | PyTorch | Deep Learning |
| | ResNet50 | Pre-trained Vision Model |
| | NumPy/SciPy | Numerical Computing |
| **Database** | MySQL 5.7+ | Data Persistence |
| | InnoDB | ACID Transactions |
| | LONGBLOB | Binary Embeddings |

---

## 📈 Performance Considerations

### Optimization Strategies

1. **Database Indexing**
   - `idx_is_missing` for fast missing pet queries
   - `idx_similarity_score DESC` for ranking matches

2. **Embedding Storage**
   - ResNet50: 2048 dimensions × 4 bytes = 8KB per embedding
   - L2 normalized for fast Cosine Similarity

3. **Batch Processing**
   - Option for batch_compare endpoint
   - Reduces cURL overhead

4. **Caching** (future enhancement)
   - Redis for session caching
   - Memcached for frequent queries

5. **GPU Acceleration** (optional)
   - PyTorch supports CUDA
   - Significantly faster inference

---

## 🚢 Deployment Architecture

```
Production Setup (recommended):

┌─────────────────────────────────────────────────────┐
│           Internet / Client Browsers                 │
└──────────────────┬──────────────────────────────────┘
                   │ HTTPS
        ┌──────────▼──────────────┐
        │  Nginx Reverse Proxy    │
        │  • Load Balancing       │
        │  • SSL/TLS              │
        │  • Static Assets        │
        └──────────┬───────────────┘
                   │
        ┌──────────┴──────────────┬──────────────────┐
        │                         │                  │
        ▼                         ▼                  ▼
    [PHP-FPM]              [Python-Gunicorn]    [MySQL]
    on Port 9000           on Port 8000          on 3306
    • Backend              • AI Service          • Data
    • API Handlers         • PyTorch Models      • Backups
    • Session Mgmt         • GPU (optional)      • Replication
```

