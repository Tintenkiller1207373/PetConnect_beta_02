"""
PetConnect - KI-Service für Bildanalyse
FastAPI Microservice für PyTorch-basierte Bildähnlichkeitsberechnung

Endpoints:
- POST /api/generate_embedding: Generiert Embedding für ein Bild
- POST /api/compare_embeddings: Vergleicht zwei Embeddings (Cosine Similarity)
- GET /health: Health Check
"""

from fastapi import FastAPI, File, UploadFile, HTTPException, BackgroundTasks
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import torch
import torchvision.transforms as transforms
from torchvision.models import resnet50, ResNet50_Weights
from PIL import Image
import numpy as np
import base64
import io
import logging
import os
from pathlib import Path
from typing import Optional

# Logging konfigurieren
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# FastAPI App Initialisierung
app = FastAPI(title="PetConnect AI Service", version="1.0.0")

# CORS aktivieren
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ============================================================================
# Model Initialization (ResNet50 - pretrained)
# ============================================================================

class EmbeddingModel:
    """ResNet50 Feature Extractor for Pet Images"""
    
    def __init__(self):
        self.device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
        logger.info(f"🖥️ Using device: {self.device}")
        
        # ResNet50 laden (pretrained auf ImageNet)
        # IMAGENET1K_V1 == altes pretrained=True, damit gespeicherte Embeddings kompatibel bleiben
        self.model = resnet50(weights=ResNet50_Weights.IMAGENET1K_V1)
        
        # Letzten Layer entfernen (um Features statt Klassifikation zu bekommen)
        self.model = torch.nn.Sequential(*list(self.model.children())[:-1])
        self.model = self.model.to(self.device)
        self.model.eval()
        
        # Standard Preprocessing Pipeline
        self.transform = transforms.Compose([
            transforms.Resize((256, 256)),
            transforms.CenterCrop(224),
            transforms.ToTensor(),
            transforms.Normalize(
                mean=[0.485, 0.456, 0.406],
                std=[0.229, 0.224, 0.225]
            )
        ])
        
        # Test-Time Augmentation (TTA) für robustere Features
        self.transform_tta = [
            # Original
            transforms.Compose([
                transforms.Resize((224, 224)),
                transforms.ToTensor(),
                transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
            ]),
            # Leicht gezoomt
            transforms.Compose([
                transforms.Resize((256, 256)),
                transforms.CenterCrop(224),
                transforms.ToTensor(),
                transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
            ]),
            # Horizontal flip (für Seitenansichten)
            transforms.Compose([
                transforms.Resize((224, 224)),
                transforms.RandomHorizontalFlip(p=1.0),
                transforms.ToTensor(),
                transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225])
            ])
        ]
        
        logger.info("✅ ResNet50 Model loaded successfully")
    
    def extract_embedding(self, image: Image.Image, use_tta: bool = True) -> np.ndarray:
        """
        Extrahiert Feature-Vektoren aus Tierbildern mit Test-Time Augmentation
        
        Args:
            image: PIL Image
            use_tta: Bool - Wenn True, verwendet mehrere Augmentierungen und mittelt
        
        Returns:
            np.ndarray: 2048-dimensionaler Feature Vector (ResNet50 output)
        """
        try:
            # Bild zu RGB konvertieren (falls RGBA)
            if image.mode != 'RGB':
                image = image.convert('RGB')
            
            if use_tta:
                # Test-Time Augmentation: Mehrere Varianten des Bildes verarbeiten
                embeddings = []
                
                for transform in self.transform_tta:
                    img_tensor = transform(image).unsqueeze(0).to(self.device)
                    
                    with torch.no_grad():
                        embedding = self.model(img_tensor)
                    
                    embedding = embedding.squeeze().cpu().numpy()
                    # L2 Normalisierung
                    embedding = embedding / (np.linalg.norm(embedding) + 1e-8)
                    embeddings.append(embedding)
                
                # Mittelwert aller Augmentierungen
                embedding = np.mean(embeddings, axis=0)
                
                # Nochmal normalisieren nach Averaging
                embedding = embedding / (np.linalg.norm(embedding) + 1e-8)
            else:
                # Standard single-view
                img_tensor = self.transform(image).unsqueeze(0).to(self.device)
                
                with torch.no_grad():
                    embedding = self.model(img_tensor)
                
                embedding = embedding.squeeze().cpu().numpy()
                embedding = embedding / (np.linalg.norm(embedding) + 1e-8)
            
            return embedding.astype(np.float32)
        
        except Exception as e:
            logger.error(f"❌ Embedding extraction failed: {str(e)}")
            raise
    
    def compare_embeddings(self, emb1: np.ndarray, emb2: np.ndarray) -> float:
        """
        Berechnet Cosine Similarity zwischen zwei Embeddings
        
        Args:
            emb1: np.ndarray - Erstes Embedding
            emb2: np.ndarray - Zweites Embedding
        
        Returns:
            float: Similarity zwischen 0.0 und 1.0
        """
        # Cosine Similarity = dot product (da beide normalized sind)
        similarity = np.dot(emb1, emb2)
        
        # Sicherstellen dass Wert zwischen 0 und 1 liegt
        similarity = max(0.0, min(1.0, float(similarity)))
        
        return similarity


# Model initialisieren (global, damit es nur einmal beim Start geladen wird)
embedding_model = EmbeddingModel()

# ============================================================================
# Pydantic Models (Datentypen für Requests)
# ============================================================================

class ImagePathRequest(BaseModel):
    """Request für Embedding-Generierung mit Dateipfad"""
    image_path: str
    pet_id: Optional[int] = None


class CompareEmbeddingsRequest(BaseModel):
    """Request für Embedding-Vergleich"""
    embedding1: str  # Base64 encoded
    embedding2: str  # Base64 encoded


class EmbeddingResponse(BaseModel):
    """Response mit generiertem Embedding"""
    success: bool
    embedding: str  # Base64 encoded
    shape: list = None
    message: str = None


class ComparisonResponse(BaseModel):
    """Response mit Similarity Score"""
    success: bool
    similarity: float = None
    message: str = None

# ============================================================================
# API Endpoints
# ============================================================================

@app.get("/health")
async def health_check():
    """Health Check für Monitoring"""
    return {
        "status": "healthy",
        "model": "ResNet50",
        "device": str(embedding_model.device),
        "version": "1.0.0"
    }


@app.post("/api/generate_embedding", response_model=EmbeddingResponse)
async def generate_embedding(request: ImagePathRequest):
    """
    Generiert Embedding für ein Tierbild mit Test-Time Augmentation
    
    Args:
        request.image_path: Pfad zum Bild auf dem Dateisystem
        request.pet_id: (Optional) Pet ID für Logging
    
    Returns:
        Embedding als Base64 String (mit TTA für höhere Genauigkeit)
    """
    try:
        image_path = Path(request.image_path)
        
        if not image_path.exists():
            logger.warning(f"⚠️ Image not found: {image_path}")
            raise HTTPException(status_code=404, detail="Bild-Datei nicht gefunden")
        
        # Bild laden
        image = Image.open(image_path)
        
        # Embedding extrahieren mit TTA
        embedding = embedding_model.extract_embedding(image, use_tta=True)
        
        # Als Base64 encodieren (für DB-Speicherung)
        embedding_base64 = base64.b64encode(embedding.tobytes()).decode()
        
        logger.info(f"✅ Embedding generated with TTA for image {image_path} (pet_id: {request.pet_id})")
        
        return EmbeddingResponse(
            success=True,
            embedding=embedding_base64,
            shape=list(embedding.shape),
            message="Embedding erfolgreich generiert (mit TTA)"
        )
    
    except Exception as e:
        logger.error(f"❌ Error generating embedding: {str(e)}")
        return EmbeddingResponse(
            success=False,
            message=f"Fehler: {str(e)}"
        )


@app.post("/api/upload_and_embed")
async def upload_and_embed(file: UploadFile = File(...), pet_id: int = None):
    """
    Lädt Tierbild hoch und generiert Embedding direkt mit TTA
    
    Useful für Frontend File Upload
    """
    try:
        # Bild-Datei lesen
        contents = await file.read()
        image = Image.open(io.BytesIO(contents))
        
        # Embedding extrahieren mit TTA
        embedding = embedding_model.extract_embedding(image, use_tta=True)
        embedding_base64 = base64.b64encode(embedding.tobytes()).decode()
        
        logger.info(f"✅ File uploaded and embedded with TTA (pet_id: {pet_id})")
        
        return {
            "success": True,
            "embedding": embedding_base64,
            "shape": list(embedding.shape),
            "filename": file.filename,
            "message": "Embedding mit Test-Time Augmentation generiert"
        }
    
    except Exception as e:
        logger.error(f"❌ Upload error: {str(e)}")
        return {
            "success": False,
            "message": f"Upload fehlgeschlagen: {str(e)}"
        }


@app.post("/api/compare_embeddings", response_model=ComparisonResponse)
async def compare_embeddings(request: CompareEmbeddingsRequest):
    """
    Vergleicht zwei Embeddings mit Cosine Similarity
    
    Args:
        request.embedding1: Base64 encoded erste Embedding
        request.embedding2: Base64 encoded zweite Embedding
    
    Returns:
        Similarity Score zwischen 0.0 und 1.0
    """
    try:
        # Base64 decodieren
        emb1_bytes = base64.b64decode(request.embedding1)
        emb2_bytes = base64.b64decode(request.embedding2)
        
        # Zu numpy arrays konvertieren
        emb1 = np.frombuffer(emb1_bytes, dtype=np.float32)
        emb2 = np.frombuffer(emb2_bytes, dtype=np.float32)
        
        # Similarity berechnen
        similarity = embedding_model.compare_embeddings(emb1, emb2)
        
        logger.info(f"✅ Embeddings compared - Similarity: {similarity:.4f}")
        
        return ComparisonResponse(
            success=True,
            similarity=similarity,
            message="Vergleich erfolgreich"
        )
    
    except Exception as e:
        logger.error(f"❌ Comparison error: {str(e)}")
        return ComparisonResponse(
            success=False,
            message=f"Vergleich fehlgeschlagen: {str(e)}"
        )


@app.post("/api/batch_compare")
async def batch_compare(finder_embedding: str, pet_embeddings: list):
    """
    Vergleicht ein Finder-Embedding mit mehreren Pet-Embeddings (Batch)
    
    Für Performance-Optimierung
    """
    try:
        finder_emb_bytes = base64.b64decode(finder_embedding)
        finder_emb = np.frombuffer(finder_emb_bytes, dtype=np.float32)
        
        results = []
        for i, pet_emb_b64 in enumerate(pet_embeddings):
            pet_emb_bytes = base64.b64decode(pet_emb_b64)
            pet_emb = np.frombuffer(pet_emb_bytes, dtype=np.float32)
            
            similarity = embedding_model.compare_embeddings(finder_emb, pet_emb)
            results.append({
                "pet_index": i,
                "similarity": float(similarity)
            })
        
        # Nach Similarity sortieren
        results.sort(key=lambda x: x['similarity'], reverse=True)
        
        logger.info(f"✅ Batch comparison completed - {len(results)} pets compared")
        
        return {
            "success": True,
            "results": results,
            "count": len(results)
        }
    
    except Exception as e:
        logger.error(f"❌ Batch comparison error: {str(e)}")
        return {
            "success": False,
            "message": f"Batch-Vergleich fehlgeschlagen: {str(e)}"
        }


@app.get("/api/model_info")
async def model_info():
    """Gibt Informationen über das verwendete Modell zurück"""
    return {
        "model": "ResNet50 (pretrained on ImageNet)",
        "embedding_dimension": 2048,
        "framework": "PyTorch",
        "device": str(embedding_model.device),
        "normalization": "L2",
        "similarity_metric": "Cosine Similarity",
        "input_size": (224, 224),
        "source": "torchvision.models",
        "optimization": {
            "test_time_augmentation": True,
            "tta_views": 3,
            "threshold": 0.45,
            "description": "Verwendet Multi-View TTA für robustere Features"
        }
    }


# ============================================================================
# Startup/Shutdown Events
# ============================================================================

@app.on_event("startup")
async def startup_event():
    """Wird beim Service-Start aufgerufen"""
    logger.info("🚀 PetConnect AI Service starting...")
    logger.info(f"📊 Model: ResNet50")
    logger.info(f"💻 Device: {embedding_model.device}")


@app.on_event("shutdown")
async def shutdown_event():
    """Wird beim Service-Shutdown aufgerufen"""
    logger.info("🛑 PetConnect AI Service shutting down...")


# ============================================================================
# Main Eintrypoint
# ============================================================================

if __name__ == "__main__":
    import uvicorn
    
    print("""
    ╔═══════════════════════════════════════════════════════════╗
    ║     PetConnect - KI-Service für Bildanalyse               ║
    ║     FastAPI + PyTorch (ResNet50)                          ║
    ║     http://localhost:8002                                 ║
    ║     Dokumentation: http://localhost:8002/docs             ║
    ╚═══════════════════════════════════════════════════════════╝
    """)
    
    uvicorn.run(
        app,
        host=os.getenv("AI_HOST", "0.0.0.0"),
        port=int(os.getenv("AI_PORT", "8002")),
        log_level="info"
    )
