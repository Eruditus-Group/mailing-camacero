-- boletines.sql — Importar en phpMyAdmin (Hostinger)
CREATE TABLE IF NOT EXISTS boletines (
  fecha VARCHAR(80) NOT NULL,
  fecha_iso DATE NOT NULL,
  hora VARCHAR(20) NOT NULL DEFAULT '',
  html LONGTEXT NOT NULL,
  actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (fecha),
  KEY idx_iso (fecha_iso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;