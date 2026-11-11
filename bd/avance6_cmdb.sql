-- =====================================================
-- Avance 6: Módulo CMDB — Monitoreo automático de equipos
-- Crea las 6 tablas del CMDB, el vínculo con `activos`
-- y los umbrales configurables de alertas.
-- Ejecutar sobre la base GESTAC.
-- =====================================================

-- La FK a `activos` necesita un índice único en `id_producto`
-- (la tabla original no declara llave primaria).
CREATE UNIQUE INDEX IF NOT EXISTS idx_activos_id_producto ON activos (id_producto);

-- ===================== 1. REGISTRO MAESTRO DE EQUIPOS (CI) =====================
CREATE TABLE IF NOT EXISTS cmdb_equipos (
  id_equipo        INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hostname         VARCHAR(100) NOT NULL,
  id_activo        VARCHAR(50)  NULL,
  usuario_asignado VARCHAR(100) NULL,
  marca            VARCHAR(50)  NULL,
  modelo           VARCHAR(100) NULL,
  num_serie        VARCHAR(100) NULL,
  cpu              VARCHAR(100) NULL,
  ram_total_gb     DECIMAL(7,1) NULL,
  so_nombre        VARCHAR(80)  NULL,
  so_version       VARCHAR(80)  NULL,
  mac              VARCHAR(30)  NULL,
  ip_privada       VARCHAR(45)  NULL,
  ip_publica       VARCHAR(45)  NULL,
  ultima_conexion  DATETIME     NULL,
  estado           VARCHAR(10)  NOT NULL DEFAULT 'offline',  -- online / offline / baja
  token_agente     CHAR(64)     NOT NULL,
  fecha_alta       DATETIME     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cmdb_hostname (hostname),
  UNIQUE KEY uq_cmdb_token (token_agente),
  CONSTRAINT fk_cmdb_equipo_activo FOREIGN KEY (id_activo)
    REFERENCES activos (id_producto)
    ON UPDATE CASCADE ON DELETE SET NULL
);

-- ===================== 2. HISTÓRICO DE TELEMETRÍA =====================
CREATE TABLE IF NOT EXISTS cmdb_telemetria (
  id            INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_equipo     INT NOT NULL,
  fecha_reporte DATETIME DEFAULT CURRENT_TIMESTAMP,
  uso_cpu       DECIMAL(5,1) NULL,
  ram_libre_gb  DECIMAL(7,1) NULL,
  uptime_horas  DECIMAL(9,1) NULL,
  bateria_pct   INT          NULL,
  ip            VARCHAR(45)  NULL,
  payload_json  LONGTEXT     NULL,
  KEY idx_cmdb_tel_equipo_fecha (id_equipo, fecha_reporte),
  CONSTRAINT fk_cmdb_tel_equipo FOREIGN KEY (id_equipo)
    REFERENCES cmdb_equipos (id_equipo) ON DELETE CASCADE
);

-- ===================== 3. ESTADO ACTUAL POR UNIDAD DE DISCO =====================
CREATE TABLE IF NOT EXISTS cmdb_discos (
  id           INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_equipo    INT NOT NULL,
  unidad       VARCHAR(10)  NOT NULL,
  capacidad_gb DECIMAL(9,1) NULL,
  usado_gb     DECIMAL(9,1) NULL,
  libre_gb     DECIMAL(9,1) NULL,
  libre_pct    DECIMAL(5,1) NULL,
  estado_smart VARCHAR(20)  NULL DEFAULT 'OK',
  UNIQUE KEY uq_cmdb_disco (id_equipo, unidad),
  CONSTRAINT fk_cmdb_disco_equipo FOREIGN KEY (id_equipo)
    REFERENCES cmdb_equipos (id_equipo) ON DELETE CASCADE
);

-- ===================== 4. BITÁCORA DE CAMBIOS (DIFF) =====================
CREATE TABLE IF NOT EXISTS cmdb_cambios (
  id             INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_equipo      INT NOT NULL,
  fecha          DATETIME DEFAULT CURRENT_TIMESTAMP,
  tipo           VARCHAR(30)  NOT NULL,   -- hardware / red / software / sistema
  campo          VARCHAR(50)  NOT NULL,
  valor_anterior VARCHAR(255) NULL,
  valor_nuevo    VARCHAR(255) NULL,
  KEY idx_cmdb_cambio_equipo (id_equipo, fecha),
  CONSTRAINT fk_cmdb_cambio_equipo FOREIGN KEY (id_equipo)
    REFERENCES cmdb_equipos (id_equipo) ON DELETE CASCADE
);

-- ===================== 5. ALERTAS PREVENTIVAS =====================
CREATE TABLE IF NOT EXISTS cmdb_alertas (
  id        INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_equipo INT NOT NULL,
  fecha     DATETIME DEFAULT CURRENT_TIMESTAMP,
  tipo      VARCHAR(40)  NOT NULL,   -- almacenamiento_critico / almacenamiento_bajo / falla_smart / sin_reporte / cambio_hardware / bateria_degradada
  severidad VARCHAR(10)  NOT NULL,   -- critica / media / baja
  mensaje   VARCHAR(255) NOT NULL,
  estado    VARCHAR(12)  NOT NULL DEFAULT 'abierta',  -- abierta / reconocida / resuelta
  KEY idx_cmdb_alerta_estado (estado, id_equipo),
  CONSTRAINT fk_cmdb_alerta_equipo FOREIGN KEY (id_equipo)
    REFERENCES cmdb_equipos (id_equipo) ON DELETE CASCADE
);

-- ===================== 6. UMBRALES CONFIGURABLES =====================
CREATE TABLE IF NOT EXISTS cmdb_umbrales (
  parametro   VARCHAR(50)  NOT NULL PRIMARY KEY,
  valor       VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL
);

INSERT IGNORE INTO cmdb_umbrales (parametro, valor, descripcion) VALUES
  ('almacenamiento_critico_pct', '10', 'Porcentaje libre por debajo del cual el almacenamiento es crítico'),
  ('almacenamiento_bajo_pct',    '20', 'Porcentaje libre por debajo del cual el almacenamiento es bajo'),
  ('horas_sin_reportar',         '24', 'Horas sin recibir reporte del agente para marcar el equipo offline'),
  ('bateria_degradada_pct',      '60', 'Porcentaje de salud de batería por debajo del cual se alerta degradación'),
  ('clave_alta',   'GESTAC-CMDB-2026', 'Clave que debe presentar el agente para darse de alta y recibir su token');

