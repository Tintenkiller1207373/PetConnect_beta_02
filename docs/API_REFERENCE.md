# 🔌 PetConnect - API Quick Reference

Alle REST-Endpoints im Überblick.

---

## 🔐 Authentication

### Register
```http
POST /api/auth/register
{
  "username": "johndoe",
  "email": "john@example.com",
  "password": "SecurePass123",
  "first_name": "John",
  "last_name": "Doe"
}
→ 200 {"success": true, "user_id": 42}
```

### Login
```http
POST /api/auth/login
{
  "email": "john@example.com",
  "password": "SecurePass123"
}
→ 200 {"success": true, "session_token": "...", "user_id": 42}
```

### Validate Session
```http
POST /api/auth/validate_session
{
  "session_token": "a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a"
}
→ 200 {"success": true, "user": {...}}
```

### Logout
```http
POST /api/auth/logout
{
  "session_token": "..."
}
→ 200 {"success": true}
```

### Get Profile
```http
GET /api/auth/profile?session_token=...
→ 200 {"success": true, "user": {...}}
```

### Update Profile
```http
POST /api/auth/profile
{
  "session_token": "...",
  "first_name": "John",
  "last_name": "Doe",
  "phone": "+49 123 456789",
  "location": "Berlin"
}
→ 200 {"success": true}
```

### Change Password
```http
POST /api/auth/change_password
{
  "session_token": "...",
  "old_password": "OldPass123",
  "new_password": "NewPass456"
}
→ 200 {"success": true, "message": "Passwort erfolgreich geändert"}
```

---

## 🐾 Pet Management

### Create Pet
```http
POST /api/pets/pet/create
{
  "session_token": "...",
  "name": "Max",
  "species": "dog",
  "breed": "German Shepherd",
  "color": "Brown",
  "age": 3,
  "weight": 30.5,
  "features": "White paw",
  "microchip_id": "901000111223344"
}
→ 200 {"success": true, "pet_id": 123}
```

### Upload Pet Image
```http
POST /api/pets/pet/upload
Content-Type: multipart/form-data

session_token: ...
pet_id: 123
image: [binary file]

→ 200 {"success": true, "image": "pet_123_1708234567.jpg", "embedding": true}
```

### Get User Pets
```http
GET /api/pets/list?session_token=...
→ 200 {"success": true, "pets": [{id: 123, name: "Max", ...}]}
```

### Mark As Missing
```http
POST /api/pets/missing
{
  "session_token": "...",
  "pet_id": 123,
  "location": "Potsdamer Platz, Berlin",
  "date_missing": "2026-02-20",
  "description": "Escaped from garden",
  "latitude": 52.5151,
  "longitude": 13.3768,
  "reward": 500.00
}
→ 200 {"success": true, "report_id": 456}
```

### Get Missing Pets (Public)
```http
GET /api/pets/missing?limit=50&offset=0
→ 200 {
    "success": true,
    "pets": [
      {
        "id": 123,
        "name": "Max",
        "location_last_seen": "...",
        "reward_amount": 500.00,
        ...
      }
    ],
    "count": 1
  }
```

### Update Pet
```http
POST /api/pets/update
{
  "session_token": "...",
  "pet_id": 123,
  "data": {
    "name": "Max",
    "age": 4,
    "distinguishing_features": "White spot on chest"
  }
}
→ 200 {"success": true, "message": "Tier aktualisiert"}
```

### Resolve Missing
```http
POST /api/pets/resolve
{
  "session_token": "...",
  "pet_id": 123
}
→ 200 {"success": true, "message": "Vermisstenfall aufgelöst"}
```

---

## 🔍 Finder Reports (PUBLIC - No Auth Required)

### Submit Finder Report
```http
POST /api/finder/submit
Content-Type: multipart/form-data

image: [binary file]
location: "Unter den Linden, Berlin"
latitude: 52.5169
longitude: 13.3886
description: "Large brown dog"
finder_contact: "Anna Schmidt"
finder_phone: "+49 987 654321"

→ 200 {
    "success": true,
    "finder_report_id": 789,
    "matches": [
      {
        "pet_id": 123,
        "name": "Max",
        "similarity": 0.8743,
        "owner_email": "john@example.com"
      }
    ]
  }
```

### Get Finder Report
```http
GET /api/finder/report?id=789
→ 200 {
    "success": true,
    "report": {
      "id": 789,
      "location_found": "...",
      "matches": [...]
    }
  }
```

### Get All Finder Reports (Admin)
```http
GET /api/finder/reports?limit=50&offset=0
→ 200 {"success": true, "reports": [...], "count": N}
```

### Notify Owner
```http
POST /api/finder/notify
{
  "match_id": 999
}
→ 200 {"success": true, "message": "Besitzer benachrichtigt"}
```

---

## 🤖 AI Service (Python FastAPI)

### Health Check
```http
GET /health
→ 200 {
    "status": "healthy",
    "model": "ResNet50",
    "device": "cuda"
  }
```

### Generate Embedding
```http
POST /api/generate_embedding
{
  "image_path": "/path/to/image.jpg",
  "pet_id": 123
}
→ 200 {
    "success": true,
    "embedding": "[base64 encoded 2048-dim vector]",
    "shape": [2048],
    "message": "Embedding erfolgreich generiert"
  }
```

### Compare Embeddings
```http
POST /api/compare_embeddings
{
  "embedding1": "[base64...]",
  "embedding2": "[base64...]"
}
→ 200 {
    "success": true,
    "similarity": 0.8743
  }
```

### Upload and Embed
```http
POST /api/upload_and_embed?pet_id=123
Content-Type: multipart/form-data

file: [binary image]

→ 200 {
    "success": true,
    "embedding": "[base64...]",
    "shape": [2048],
    "filename": "image.jpg"
  }
```

### Model Info
```http
GET /api/model_info
→ 200 {
    "model": "ResNet50 (pretrained on ImageNet)",
    "embedding_dimension": 2048,
    "device": "cuda"
  }
```

### Batch Compare
```http
POST /api/batch_compare
{
  "finder_embedding": "[base64...]",
  "pet_embeddings": ["[base64...]", "[base64...]", ...]
}
→ 200 {
    "success": true,
    "results": [
      {
        "pet_index": 0,
        "similarity": 0.8743
      }
    ],
    "count": 3
  }
```

---

## 📊 Response Formats

### Erfolg
```json
{
  "success": true,
  "message": "Operation erfolgreich",
  "data": {...}
}
```

### Fehler
```json
{
  "success": false,
  "message": "Fehlerbeschreibung"
}
```

### Paginated Results
```json
{
  "success": true,
  "items": [...],
  "count": 50,
  "limit": 50,
  "offset": 0
}
```

---

## 🔑 Session Token Format

Tokens sind 64-Zeichen lange hexadezimale Strings:
```
a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a5c7d9e1f3a5c7d9e1f3a5c7
```

**Verwendung**: Füge in jeden Request ein:
```json
{
  "session_token": "a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7..."
}
```

---

## 🐛 Common Status Codes

| Code | Meaning |
|------|---------|
| 200  | ✅ OK - Anfrage erfolgreich |
| 400  | ❌ Bad Request - Ungültige Eingabe |
| 401  | 🔐 Unauthorized - Session ungültig |
| 404  | 🔍 Not Found - Ressource existiert nicht |
| 500  | ⚠️ Server Error - Backend Error |

---

## 💡 Tips

1. **Alle POST Requests erfordern `session_token`** außer öffentliche Finder-Endpoints
2. **File Uploads müssen `multipart/form-data` sein**
3. **Embeddings sind immer Base64 encoded**
4. **Similarity Score ist zwischen 0.0 und 1.0**
5. **Session Tokens verfallen nach 7 Tagen**

---

## 📌 Example cURL Commands

### Register
```bash
curl -X POST http://localhost:8001/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "testuser",
    "email": "test@example.com",
    "password": "TestPass123"
  }'
```

### Create Pet
```bash
curl -X POST http://localhost:8001/api/pets/pet/create \
  -H "Content-Type: application/json" \
  -d '{
    "session_token": "a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a",
    "name": "Max",
    "species": "dog"
  }'
```

### Upload Image
```bash
curl -X POST http://localhost:8001/api/pets/pet/upload \
  -F "session_token=a7f3c9e2d8b4e1f9c3a5d7e9b1f3a5c7d9e1f3a" \
  -F "pet_id=123" \
  -F "image=@/path/to/photo.jpg"
```

### Submit Finder Report
```bash
curl -X POST http://localhost:8001/api/finder/submit \
  -F "image=@/path/to/found_dog.jpg" \
  -F "location=Berlin" \
  -F "finder_contact=Anna Schmidt"
```

---

**API Version**: 1.0.0 | **Last Updated**: Feb 2026
