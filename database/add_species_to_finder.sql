-- Füge species Spalte zur finder_reports Tabelle hinzu
ALTER TABLE finder_reports 
ADD COLUMN species ENUM('dog', 'cat') DEFAULT NULL AFTER image_path;

-- Update existing records (falls vorhanden) auf NULL
-- Diese können später manuell oder durch Re-Upload aktualisiert werden
