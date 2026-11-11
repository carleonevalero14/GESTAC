# GESTAC — Agente CMDB

Agente para Windows que registra el equipo en el CMDB de GESTAC y reporta
su estado **cada 1 minuto** a través de la red local: hardware, sistema
operativo, red, discos (espacio y SMART), uso de CPU/RAM, uptime y salud
de batería. Con estos datos el servidor detecta cambios de hardware y
genera alertas preventivas.

## Archivos

| Archivo | Función |
|---|---|
| `INSTALAR_AGENTE.bat` | Instalación con doble clic (pide permisos de administrador solo) |
| `DESINSTALAR_AGENTE.bat` | Quita el agente del equipo |
| `agente_cmdb.ps1` | El agente en sí (lo ejecuta la tarea programada cada minuto) |

## 1. Preparar el servidor (una sola vez)

En la máquina donde corre XAMPP con GESTAC:

1. Ejecutar el script `bd/avance6_cmdb.sql` sobre la base de datos GESTAC.
2. Iniciar Apache y MySQL en XAMPP.
3. Permitir el puerto 80 en el firewall de Windows para que otros equipos
   de la red puedan llegar a Apache (CMD como administrador):

   ```
   netsh advfirewall firewall add rule name="Apache GESTAC" dir=in action=allow protocol=TCP localport=80
   ```

4. Anotar el **nombre del equipo servidor** (`hostname` en CMD, por ejemplo
   `CESAR-HERNANDEZ`). Se recomienda usar el nombre y no la IP: el nombre
   sigue funcionando aunque el router asigne otra IP o se cambie de red.
   (La IP con `ipconfig` también sirve, pero puede cambiar.)

## 2. Instalar el agente en otra laptop

1. Copiar la carpeta `agente` completa a la otra laptop (USB, correo, etc.).
2. Verificar que la laptop esté **en la misma red** que el servidor
   (se puede probar abriendo `http://NOMBRE-DEL-SERVIDOR/GESTAC` en el navegador).
3. Doble clic en `INSTALAR_AGENTE.bat` y aceptar el aviso de administrador.
4. Responder tres preguntas:
   - **Nombre (o IP) del servidor** (el del paso 1.4, ej. `CESAR-HERNANDEZ`).
   - **Usuario asignado** al equipo (Enter usa el usuario de Windows).
   - **ID del activo** en GESTAC, ej. `LAP002L` (Enter para omitir; sirve
     para vincular el equipo con el inventario de activos).

Eso es todo: el instalador registra el equipo en el CMDB, recibe su token
único y crea la tarea programada `GESTAC_Agente_CMDB` que reporta cada
minuto en segundo plano (aunque nadie tenga sesión iniciada). En menos de
un minuto el equipo aparece **en línea** en el tablero CMDB.

## 3. Verificar que funciona

- En GESTAC: menú **CMDB** → el equipo aparece con su última conexión.
- En la laptop: revisar el registro `C:\ProgramData\GestacAgente\agente.log`
  (debe decir «Reporte enviado» cada minuto).
- Probar la recolección sin enviar nada:

  ```
  powershell -ExecutionPolicy Bypass -File agente_cmdb.ps1 -Probar
  ```

## 4. Desinstalar

Doble clic en `DESINSTALAR_AGENTE.bat`. Elimina la tarea programada y la
configuración local; el equipo quedará *offline* en el tablero hasta que
un administrador lo dé de baja.

## Notas técnicas

- La autenticación es por **token por equipo** (64 caracteres, generado en
  el alta); la clave de alta vive en la tabla `cmdb_umbrales`
  (`clave_alta`, por defecto `GESTAC-CMDB-2026`).
- El agente guarda su configuración en `C:\ProgramData\GestacAgente\config.json`.
- Si el servidor está apagado o no hay red, el agente solo lo anota en el
  log y reintenta al minuto siguiente; no acumula errores.
- Para que funcione **fuera de la red local** a futuro solo hay que exponer
  el servidor (IP pública, VPN o túnel) y reinstalar el agente apuntando a
  esa dirección: el protocolo ya es HTTP + JSON y no depende de la LAN.
- Si el equipo «ya está registrado» al instalar, un administrador debe
  eliminar la fila correspondiente en `cmdb_equipos` antes de reinstalar.
