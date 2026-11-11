-- Avance 4: Solicitudes de equipo
-- Tabla para que usuarios soliciten equipos y admins los resuelvan

CREATE TABLE IF NOT EXISTS solicitudes (
  id               INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitante      VARCHAR(50) NOT NULL,
  categoria        VARCHAR(50) NOT NULL,
  motivo           TEXT NOT NULL,
  estado           ENUM('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  fecha_solicitud  DATETIME DEFAULT CURRENT_TIMESTAMP,
  fecha_resolucion DATETIME NULL,
  comentario_admin TEXT NULL,
  activo_asignado  VARCHAR(50) NULL
);
