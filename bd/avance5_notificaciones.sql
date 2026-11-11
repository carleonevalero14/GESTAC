-- Avance 5: Centro de alertas y notificaciones

CREATE TABLE IF NOT EXISTS notificaciones (
  id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  destinatario VARCHAR(50) NOT NULL,
  tipo         VARCHAR(30) NOT NULL,
  mensaje      VARCHAR(255) NOT NULL,
  url          VARCHAR(255) NULL,
  leida        TINYINT DEFAULT 0,
  fecha        DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_notif_dest_leida ON notificaciones (destinatario, leida);
