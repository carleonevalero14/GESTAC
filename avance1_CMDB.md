# Avance 1 — Módulo CMDB (Configuration Management Database)

**Proyecto:** GESTAC — Gestión de Activos Tecnológicos
**Fecha:** 6 de julio de 2026
**Alcance:** nuevo módulo de monitoreo automático de equipos asignados, con detección de cambios (diff) y alertas preventivas de almacenamiento, SMART, batería y disponibilidad.

---

## 1. Qué se construyó

El CMDB agrega a GESTAC un quinto módulo, **CMDB / MONITOREO**, que se alimenta solo:
un agente instalado en cada equipo manda reportes JSON periódicos; el sistema los ingiere,
actualiza el inventario vivo del equipo, guarda el histórico de telemetría, detecta cambios
contra lo último conocido y evalúa alertas preventivas con umbrales configurables.
El administrador no captura nada a mano.

### Archivos nuevos

| Capa | Archivo | Responsabilidad |
|---|---|---|
| BD | `bd/avance6_cmdb.sql` | Las 6 tablas del CMDB, FK a `activos`, umbrales y datos de demostración |
| Servicios | `servicios/cmdb_ingesta.php` | Endpoint del agente: alta con token, upsert de equipo y discos, telemetría, diff y alertas |
| Servicios | `servicios/cmdb.php` | Consultas del tablero: `listar`, `detalle`, `tendencia`, `alertas`, `cambios`, `resolver_alerta`, `exportar_excel` |
| Vistas | `HTML/cmdb.php` | Tablero: tabla de equipos monitoreados con barras de almacenamiento, búsqueda, filtro por estado y exportación |
| Vistas | `HTML/cmdb_detalle.php` | Detalle: tarjetas Hardware / Red & Energía / Cloud-Sesión, barras por unidad, alertas, tendencia y línea de tiempo de cambios |
| JS | `recursos/js/cmdb.js` | Lógica de ambas pantallas con `api.obtener()/api.enviar()`, `animarContador()` y Chart.js |
| CSS | `recursos/css/cmdb.css` | Solo los estilos propios del módulo, heredando variables de `base.css` |
| Imagen | `recursos/imagenes/cmdb.png` | Ícono del módulo (glifo plano negro, mismo estilo que `activos.png`, etc.) |

### Archivos modificados

- `servicios/sistema.php` — nuevo `?tipo=cmdb_stats` con contadores (equipos activos, en línea, con alertas), protegido con `SHOW TABLES` para no romper si el SQL aún no se ejecuta.
- `HTML/menu_admin.php` y `HTML/menu_user.php` — quinta tarjeta "CMDB / MONITOREO" en la cuadrícula de módulos (fila ancha inferior) y carga de `cmdb.css`.

> **Nota sobre la extensión de las vistas:** el requerimiento decía `cmdb.html`, pero desde el
> avance 1 de seguridad todas las pantallas protegidas del proyecto son `.php` con
> `requerirSesion()` al inicio (`inventario.php`, `ver_activo.php`, …). El módulo sigue esa
> convención real: `cmdb.php` y `cmdb_detalle.php`.

---

## 2. Reutilización del diseño existente (regla de oro)

No se definió ningún color, fuente ni componente nuevo. Inventario de lo reutilizado:

**Variables de `base.css`:** `--verde-900…--verde-100`, `--verde-boton`, `--verde-boton-hover`,
`--verde-claro-primario`, `--texto-oscuro`, `--fondo-panel`, `--sombra-principal`,
`--radio-borde`, `--fuente-base` (Open Sans). Al usar solo variables, el módulo también
respeta los temas claro/oscuro de `temas.css`.

**Componentes de `componentes.css`:** `.btn-volver`, `.btn-exportar` (también como botón
"Detalles"/"Reconocer" en su variante compacta `.btn-carta`), `.btn-eliminar` (botón
"Resolver"), `.contenedor-tabla` + `.tabla-activos` + `.celda-vacia` + `.pie-tabla`,
`.caja-busqueda`, `.desplegable`, `.chip` (extendido con `.offline` y `.baja` usando los
colores rojos/gris que ya existían en `chip.vencido` y `formulario-botones`).

**Layout de `paginas.css`:** `.contenedor`, `.encabezado`, `.titulo-pagina`, `.cuerpo-pagina`,
`.encabezado-lista`, `.titulo-lista`, `.barra-controles`, `.titulo-seccion`.

**JS/patrones:** `api.obtener()`/`api.enviar()` de `peticiones.js` (ningún `fetch` directo),
`animarContador()` de `utilidades.js`, Chart.js 4.4.0 con la misma paleta `VERDES` de
`menu.js`, y el patrón de exportación a Excel (HTML con cabeceras `application/vnd.ms-excel`)
de `activos.php`.

`cmdb.css` solo agrega lo que no existía: KPIs del tablero, barras de almacenamiento,
tarjetas del detalle, lista de alertas, línea de tiempo y la tarjeta ancha del menú.
Los únicos colores "nuevos" son reutilizaciones de los que ya estaban en el proyecto
(`#9b2c2c` de `.btn-eliminar`, `#ffd4d4`/`#4d1f1f` de `.chip.vencido`, `#d9d9d9` de los
botones cancelar).

---

## 3. Modelo de datos (`bd/avance6_cmdb.sql`)

| Tabla | Contenido |
|---|---|
| `cmdb_equipos` | Registro maestro (CI): hostname único, FK `id_activo` → `activos.id_producto`, usuario, hardware, SO, red, `ultima_conexion`, `estado` (online/offline/baja) y `token_agente` único |
| `cmdb_telemetria` | Histórico de reportes: CPU, RAM libre, uptime, batería, IP y payload JSON completo |
| `cmdb_discos` | Estado actual por unidad (única por equipo+unidad): capacidad, usado, libre, % libre y estado SMART |
| `cmdb_cambios` | Bitácora del diff: tipo (hardware/red/software/sistema), campo, valor anterior y nuevo |
| `cmdb_alertas` | Alertas con severidad y ciclo de vida `abierta → reconocida → resuelta` |
| `cmdb_umbrales` | Parámetros configurables (ver §4) |

Detalles importantes:

- **La tabla `activos` original no tenía llave primaria**, por lo que el script crea primero
  `CREATE UNIQUE INDEX IF NOT EXISTS idx_activos_id_producto` para poder declarar la FK
  (con `ON DELETE SET NULL` para no perder el historial del equipo si se borra el activo).
- Todas las tablas hijas tienen FK a `cmdb_equipos` con `ON DELETE CASCADE`.
- El script es idempotente (`IF NOT EXISTS` / `INSERT IGNORE`): puede ejecutarse varias veces.
- Incluye **datos de demostración** (5 equipos vinculados a activos y usuarios reales de la
  BD, discos, telemetría, cambios y alertas) para que el tablero se vea poblado sin agentes.

---

## 4. Comportamiento funcional

### Ingesta (`servicios/cmdb_ingesta.php`, sin sesión: autentica por token)

1. **Alta** (`POST ?accion=alta`): valida la clave de alta (`clave_alta` en `cmdb_umbrales`),
   registra el equipo (validando que el `id_activo` exista antes de ligar la FK) y devuelve
   un token de 64 caracteres generado con `random_bytes()`.
2. **Reporte** (`POST` con `{token, equipo, discos, telemetria}`):
   - valida el token y rechaza equipos dados de baja;
   - hace *diff* de campos sensibles (hostname, usuario, CPU, RAM, SO, versión, MAC, IP) y de
     discos (unidad agregada/retirada/cambiada) contra lo guardado → inserta en `cmdb_cambios`;
   - actualiza el equipo (`ultima_conexion = NOW()`, `estado = 'online'`) y hace upsert de
     `cmdb_discos` (elimina unidades retiradas);
   - inserta la fila de telemetría con el payload JSON completo;
   - evalúa alertas y responde `{cambios, alertas_nuevas}`.

### Alertas preventivas (umbrales configurables en `cmdb_umbrales`)

| Tipo | Severidad | Umbral por defecto |
|---|---|---|
| `almacenamiento_critico` | crítica | < 10 % libre |
| `almacenamiento_bajo` | media | < 20 % libre |
| `falla_smart` | crítica | SMART ≠ OK |
| `sin_reporte` | media | > 24 h sin reportar (marca el equipo offline) |
| `cambio_hardware` | media | cualquier diff de tipo hardware |
| `bateria_degradada` | media | salud < 60 % |

- **Sin duplicados:** no se abre una alerta si ya existe una abierta/reconocida del mismo tipo
  (por unidad, en el caso de almacenamiento).
- **Autorresolución:** si la unidad recupera espacio o el equipo vuelve a reportar, sus
  alertas de almacenamiento / `sin_reporte` se marcan `resuelta` automáticamente.
- **Integración con el resto de GESTAC:** las alertas críticas generan una notificación en el
  centro de notificaciones de cada administrador (tabla `notificaciones`) y todo evento
  relevante queda en la tabla `logs` vía `registrarLog()`.
- La detección de equipos sin reportar se ejecuta al consultar el tablero
  (`marcarEquiposSinReporte()`), sin necesidad de un cron.

### Tablero y detalle

- **Tablero:** tabla de equipos monitoreados con barra de almacenamiento de la peor unidad,
  chip de estado, última conexión y botón "Detalles"; búsqueda en vivo, filtro por estado y
  exportación a Excel. Se refresca solo cada 60 s.
- **Detalle:** tarjetas Hardware / Red & Energía / Cloud-Sesión, barras por unidad (C:, D:…)
  con chip SMART, sección "⚠ Alertas preventivas" del equipo (con Reconocer/Resolver para
  administradores), gráfica de tendencia (CPU y RAM libre de los últimos 30 reportes) y
  línea de tiempo de cambios.

### Roles

- Cualquier usuario con sesión puede **consultar** tablero y detalle (patrón de las páginas
  `ver_*`); el módulo aparece también en `menu_user.php`.
- **Reconocer/resolver alertas es exclusivo de administradores**: el servicio lo valida con
  `esAdmin()` y las vistas ocultan los botones a usuarios normales
  (`window.CMDB_ES_ADMIN` inyectado por PHP).
- El `token_agente` nunca viaja al frontend (se elimina de la respuesta de `detalle`).

---

## 5. Cómo probar

1. Ejecutar el script (idempotente):
   `C:\xampp\mysql\bin\mysql.exe -u root gestac < bd\avance6_cmdb.sql`
2. Entrar al menú → tarjeta **CMDB / MONITOREO** (fila inferior).
3. Simular un agente (los datos demo ya pueblan el tablero, esto agrega uno vivo):

```bash
# Alta (devuelve el token del equipo)
curl -X POST "http://localhost/GESTAC/servicios/cmdb_ingesta.php?accion=alta" \
  -H "Content-Type: application/json" \
  -d '{"clave_alta":"GESTAC-CMDB-2026","hostname":"MI-EQUIPO","id_activo":"LAP010D","usuario_asignado":"Nombre Apellido","cpu":"Intel Core i5","ram_total_gb":8,"so_nombre":"Windows 11 Pro","ip_privada":"192.168.1.200"}'

# Reporte periódico (usar el token devuelto)
curl -X POST "http://localhost/GESTAC/servicios/cmdb_ingesta.php" \
  -H "Content-Type: application/json" \
  -d '{"token":"<TOKEN>","equipo":{"ram_total_gb":16,"ip_privada":"192.168.1.201"},"discos":[{"unidad":"C:","capacidad_gb":476,"libre_gb":30.5,"estado_smart":"OK"}],"telemetria":{"uso_cpu":37.2,"ram_libre_gb":2.1,"uptime_horas":12.5,"bateria_pct":45}}'
```

---

## 6. Pruebas realizadas

- `php -l` sin errores en los 7 archivos PHP nuevos/modificados.
- Script SQL aplicado sobre la BD `gestac`: 6 tablas creadas, FK activa, 5 equipos demo.
- Ingesta probada de extremo a extremo con `curl`:
  - alta → token devuelto y log registrado;
  - primer reporte (disco al 6.4 %, batería 45 %) → 2 alertas creadas (crítica + media) y
    notificación enviada al administrador;
  - segundo reporte (RAM 8→16 GB, IP nueva, disco D: agregado) → 3 cambios en la bitácora y
    1 alerta de cambio de hardware, **sin duplicar** las alertas anteriores.
- Servicio del tablero probado (sesión simulada): `listar` (con KPIs), `detalle`, `tendencia`,
  `alertas`, `cambios`, `resolver_alerta` (cambio de estado verificado) y `exportar_excel`.
- `sistema.php?tipo=cmdb_stats` → `{"equipos":5,"en_linea":4,"con_alertas":4}`.
- Los datos de prueba de la ingesta se eliminaron; la BD queda con los datos demo del script.
