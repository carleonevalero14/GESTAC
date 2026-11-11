<#
  =====================================================================
  GESTAC — Agente CMDB para Windows
  Avance 6 — Módulo CMDB.

  Recolecta el inventario y la telemetría del equipo y los envía al
  servidor GESTAC (servicios/cmdb_ingesta.php) cada 1 minuto mediante
  una tarea programada de Windows.

  Modos de uso:
    INSTALAR_AGENTE.bat                     Instalación con doble clic (recomendado)
    powershell -File agente_cmdb.ps1 -Instalar [-Servidor 192.168.1.10]
    powershell -File agente_cmdb.ps1                (un reporte; lo ejecuta la tarea)
    powershell -File agente_cmdb.ps1 -Probar        (muestra lo que se enviaría, sin enviar)
    powershell -File agente_cmdb.ps1 -Desinstalar
  =====================================================================
#>
param(
    [switch]$Instalar,
    [switch]$Desinstalar,
    [switch]$Probar,
    [string]$Servidor = ''
)

$ErrorActionPreference = 'Stop'

# ----------------------- Configuración fija -----------------------
# Debe coincidir con cmdb_umbrales.clave_alta en la base de datos.
$ClaveAlta   = 'GESTAC-CMDB-2026'
$RutaIngesta = '/GESTAC/servicios/cmdb_ingesta.php'
$Carpeta     = Join-Path $env:ProgramData 'GestacAgente'
$RutaConfig  = Join-Path $Carpeta 'config.json'
$RutaLog     = Join-Path $Carpeta 'agente.log'
$NombreTarea = 'GESTAC_Agente_CMDB'

# ----------------------- Utilidades -----------------------

function Escribir-Log([string]$Mensaje) {
    if (-not (Test-Path $Carpeta)) { New-Item -ItemType Directory -Force -Path $Carpeta | Out-Null }
    $linea = '{0}  {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Mensaje
    Add-Content -Path $RutaLog -Value $linea -Encoding UTF8
    # El log se recorta para que no crezca sin límite (reporta cada minuto).
    if ((Get-Item $RutaLog).Length -gt 200KB) {
        $ultimas = Get-Content $RutaLog -Tail 200
        Set-Content -Path $RutaLog -Value $ultimas -Encoding UTF8
    }
}

function Es-Administrador {
    $identidad = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identidad)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Invocar-Ingesta([string]$Url, [hashtable]$Cuerpo) {
    $json = $Cuerpo | ConvertTo-Json -Depth 6
    return Invoke-RestMethod -Uri $Url -Method Post `
        -ContentType 'application/json; charset=utf-8' `
        -Body ([System.Text.Encoding]::UTF8.GetBytes($json)) -TimeoutSec 20
}

# ----------------------- Recolección de datos -----------------------

function Obtener-InfoEquipo {
    $cs   = Get-CimInstance Win32_ComputerSystem
    $os   = Get-CimInstance Win32_OperatingSystem
    $cpu  = Get-CimInstance Win32_Processor | Select-Object -First 1
    $bios = Get-CimInstance Win32_BIOS

    # Se toma el adaptador activo (el que tiene puerta de enlace).
    $nic = Get-CimInstance Win32_NetworkAdapterConfiguration -Filter 'IPEnabled=TRUE' |
           Where-Object { $_.DefaultIPGateway } | Select-Object -First 1
    $ipPrivada = ''
    $mac       = ''
    if ($nic) {
        $ipPrivada = @($nic.IPAddress | Where-Object { $_ -match '^\d{1,3}(\.\d{1,3}){3}$' })[0]
        $mac       = ($nic.MACAddress -replace ':', '-')
    }

    return @{
        hostname     = $env:COMPUTERNAME
        marca        = ('' + $cs.Manufacturer).Trim()
        modelo       = ('' + $cs.Model).Trim()
        num_serie    = ('' + $bios.SerialNumber).Trim()
        cpu          = ('' + $cpu.Name).Trim()
        ram_total_gb = [math]::Round($cs.TotalPhysicalMemory / 1GB, 1)
        so_nombre    = ('' + $os.Caption).Trim()
        so_version   = ('{0} (compilación {1})' -f $os.Version, $os.BuildNumber)
        mac          = $mac
        ip_privada   = '' + $ipPrivada
    }
}

function Obtener-Discos {
    # Estado SMART simplificado: si Windows predice falla en algún disco
    # físico, todas las unidades se marcan como ADVERTENCIA.
    $smart = 'OK'
    try {
        $estados = @(Get-CimInstance Win32_DiskDrive | ForEach-Object { '' + $_.Status })
        if ($estados | Where-Object { $_ -ne '' -and $_.ToUpper() -ne 'OK' }) { $smart = 'ADVERTENCIA' }
    } catch { }

    # DriveType 3 = discos fijos, 2 = removibles (USB): ambos se reportan
    # para que el CMDB detecte unidades conectadas o retiradas.
    $discos = @()
    foreach ($d in @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3 OR DriveType=2')) {
        if (-not $d.Size) { continue }   # lector de tarjetas vacío, etc.
        $capacidad = [math]::Round($d.Size / 1GB, 1)
        $libre     = [math]::Round($d.FreeSpace / 1GB, 1)
        $discos += @{
            unidad       = $d.DeviceID          # "C:", "D:", ...
            capacidad_gb = $capacidad
            libre_gb     = $libre
            usado_gb     = [math]::Round([math]::Max($capacidad - $libre, 0), 1)
            estado_smart = $smart
        }
    }
    return $discos
}

function Obtener-Telemetria {
    $os  = Get-CimInstance Win32_OperatingSystem
    $uso = (Get-CimInstance Win32_Processor | Measure-Object -Property LoadPercentage -Average).Average

    $telemetria = @{
        uso_cpu      = if ($null -ne $uso) { [math]::Round($uso, 1) } else { $null }
        ram_libre_gb = [math]::Round($os.FreePhysicalMemory / 1MB, 1)   # FreePhysicalMemory viene en KB
        uptime_horas = [math]::Round(((Get-Date) - $os.LastBootUpTime).TotalHours, 1)
    }

    # Salud de batería = capacidad de carga actual / capacidad de diseño.
    # En equipos de escritorio no hay batería y se envía null.
    $carga = $null; $diseno = $null
    try {
        $carga  = @(Get-CimInstance -Namespace 'root\wmi' -ClassName BatteryFullChargedCapacity -ErrorAction Stop)[0].FullChargedCapacity
        $diseno = @(Get-CimInstance -Namespace 'root\wmi' -ClassName BatteryStaticData -ErrorAction Stop)[0].DesignedCapacity
    } catch {
        # BatteryStaticData no está disponible en varios equipos (p. ej. Dell):
        # se usa powercfg como alternativa.
        try {
            $xmlTmp = Join-Path $env:TEMP 'gestac_bateria.xml'
            powercfg /batteryreport /xml /output $xmlTmp | Out-Null
            [xml]$rep = Get-Content $xmlTmp -Raw
            $bat    = @($rep.BatteryReport.Batteries.Battery)[0]
            $carga  = [double]$bat.FullChargeCapacity
            $diseno = [double]$bat.DesignCapacity
            Remove-Item $xmlTmp -Force -ErrorAction SilentlyContinue
        } catch { }
    }
    if ($diseno -gt 0 -and $carga -gt 0) {
        $telemetria.bateria_pct = [int][math]::Min([math]::Round($carga / $diseno * 100), 100)
    }

    return $telemetria
}

function Obtener-IpPublica {
    # Dato opcional: si el equipo no tiene salida a internet se envía vacío
    # y el servidor conserva el último valor conocido.
    try { return '' + (Invoke-RestMethod -Uri 'https://api.ipify.org' -TimeoutSec 3) } catch { return '' }
}

function Construir-Reporte([string]$Token) {
    $equipo = Obtener-InfoEquipo
    $equipo.ip_publica = Obtener-IpPublica
    return @{
        token      = $Token
        equipo     = $equipo
        discos     = @(Obtener-Discos)
        telemetria = Obtener-Telemetria
    }
}

# ----------------------- Modos de operación -----------------------

function Enviar-Reporte {
    if (-not (Test-Path $RutaConfig)) {
        Write-Host 'El agente no está instalado en este equipo. Ejecuta INSTALAR_AGENTE.bat primero.'
        exit 1
    }
    $config = Get-Content $RutaConfig -Raw -Encoding UTF8 | ConvertFrom-Json
    $cuerpo = Construir-Reporte $config.token
    try {
        $r = Invocar-Ingesta $config.url $cuerpo
        if ($r.exito) {
            Escribir-Log ('Reporte enviado ({0} cambio(s), {1} alerta(s) nueva(s))' -f $r.cambios, $r.alertas_nuevas)
        } else {
            Escribir-Log ('El servidor rechazó el reporte: {0}' -f $r.mensaje)
        }
    } catch {
        # Sin red o servidor apagado: se registra y se reintenta al minuto siguiente.
        Escribir-Log ('No se pudo contactar al servidor: {0}' -f $_.Exception.Message)
    }
}

function Probar-Recoleccion {
    Write-Host '=== Datos que el agente enviaría al servidor ===' -ForegroundColor Cyan
    $cuerpo = Construir-Reporte '(token-de-prueba)'
    $cuerpo | ConvertTo-Json -Depth 6 | Write-Host
}

function Instalar-Agente {
    Write-Host ''
    Write-Host '=========================================' -ForegroundColor Cyan
    Write-Host '   GESTAC — Instalador del agente CMDB'    -ForegroundColor Cyan
    Write-Host '=========================================' -ForegroundColor Cyan
    Write-Host ''

    if (-not (Es-Administrador)) {
        Write-Host 'ERROR: la instalación necesita permisos de administrador.' -ForegroundColor Red
        Write-Host 'Ejecuta INSTALAR_AGENTE.bat (se eleva solo) o abre PowerShell como administrador.'
        exit 1
    }

    # ---- 1. Servidor GESTAC ----
    $srv = $Servidor
    if ($srv -eq '') {
        $srv = Read-Host 'Nombre o IP del servidor GESTAC en la red (recomendado el nombre, ej. CESAR-HERNANDEZ)'
    }
    $srv = $srv.Trim()
    if ($srv -eq '') { Write-Host 'No se indicó servidor. Instalación cancelada.' -ForegroundColor Red; exit 1 }
    $url = "http://$srv$RutaIngesta"

    Write-Host "Probando conexión con $url ..."
    try {
        Invoke-RestMethod -Uri $url -Method Get -TimeoutSec 8 | Out-Null
    } catch {
        Write-Host ''
        Write-Host "ERROR: no se pudo llegar al servidor ($($_.Exception.Message))." -ForegroundColor Red
        Write-Host 'Revisa que:'
        Write-Host '  1. Ambos equipos estén en la misma red.'
        Write-Host '  2. Apache (XAMPP) esté iniciado en el servidor.'
        Write-Host '  3. El firewall del servidor permita el puerto 80 (ver LEEME.md).'
        exit 1
    }
    Write-Host 'Servidor localizado correctamente.' -ForegroundColor Green
    Write-Host ''

    # ---- 2. Datos del equipo ----
    $info    = Obtener-InfoEquipo
    $usuario = Read-Host "Nombre del usuario asignado a este equipo (Enter para usar '$env:USERNAME')"
    if ($usuario.Trim() -eq '') { $usuario = $env:USERNAME }
    $idActivo = Read-Host 'ID del activo en GESTAC, ej. LAP002L (Enter para omitir)'

    # ---- 3. Alta en el CMDB ----
    Write-Host ''
    Write-Host ("Registrando el equipo {0} en el CMDB ..." -f $info.hostname)
    $alta = $info + @{
        clave_alta       = $ClaveAlta
        usuario_asignado = $usuario.Trim()
        id_activo        = $idActivo.Trim()
    }
    $r = Invocar-Ingesta "$url`?accion=alta" $alta
    if (-not $r.exito) {
        Write-Host ("ERROR: el servidor rechazó el alta: {0}" -f $r.mensaje) -ForegroundColor Red
        if ($r.mensaje -like '*ya está registrado*') {
            Write-Host 'Si quieres reinstalarlo, un administrador debe eliminar el equipo del CMDB primero.'
        }
        exit 1
    }
    Write-Host ("Equipo registrado con id {0}." -f $r.id_equipo) -ForegroundColor Green

    # ---- 4. Guardar configuración y copiar el agente ----
    if (-not (Test-Path $Carpeta)) { New-Item -ItemType Directory -Force -Path $Carpeta | Out-Null }
    @{ servidor = $srv; url = $url; token = $r.token } |
        ConvertTo-Json | Set-Content -Path $RutaConfig -Encoding UTF8

    $destino = Join-Path $Carpeta 'agente_cmdb.ps1'
    if ($PSCommandPath -and ($PSCommandPath -ne $destino)) {
        Copy-Item -Path $PSCommandPath -Destination $destino -Force
    }

    # ---- 5. Tarea programada cada 1 minuto ----
    # Se usa Register-ScheduledTask (y no schtasks) para poder permitir la
    # ejecución con batería: en laptops la tarea de schtasks queda "en cola"
    # y nunca reporta si el equipo no está conectado a la corriente.
    try {
        $accion  = New-ScheduledTaskAction -Execute 'powershell.exe' `
                   -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$destino`""
        $disparo = New-ScheduledTaskTrigger -Once -At (Get-Date) `
                   -RepetitionInterval (New-TimeSpan -Minutes 1)
        $ajustes = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries `
                   -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 5)
        $usuario = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
        Register-ScheduledTask -TaskName $NombreTarea -Action $accion -Trigger $disparo `
            -Settings $ajustes -Principal $usuario -Force | Out-Null
        Start-ScheduledTask -TaskName $NombreTarea
    } catch {
        Write-Host ("ERROR: no se pudo crear la tarea programada: {0}" -f $_.Exception.Message) -ForegroundColor Red
        exit 1
    }
    Escribir-Log ("Agente instalado. Servidor: $srv — id de equipo: {0}" -f $r.id_equipo)

    Write-Host ''
    Write-Host '=========================================' -ForegroundColor Green
    Write-Host ' Agente instalado correctamente.'           -ForegroundColor Green
    Write-Host '=========================================' -ForegroundColor Green
    Write-Host " - Reporta cada 1 minuto a: $url"
    Write-Host " - Tarea programada:        $NombreTarea"
    Write-Host " - Registro de actividad:   $RutaLog"
    Write-Host ''
    Write-Host 'El equipo aparecerá como EN LÍNEA en el tablero CMDB de GESTAC en menos de un minuto.'
}

function Desinstalar-Agente {
    if (-not (Es-Administrador)) {
        Write-Host 'ERROR: la desinstalación necesita permisos de administrador.' -ForegroundColor Red
        exit 1
    }
    schtasks /Delete /TN $NombreTarea /F 2>$null | Out-Null
    if (Test-Path $Carpeta) { Remove-Item -Path $Carpeta -Recurse -Force -Confirm:$false }
    Write-Host 'Agente desinstalado: se eliminó la tarea programada y la configuración local.' -ForegroundColor Green
    Write-Host 'El equipo quedará como SIN REPORTE / OFFLINE en el tablero CMDB.'
}

# ----------------------- Punto de entrada -----------------------

if     ($Instalar)    { Instalar-Agente }
elseif ($Desinstalar) { Desinstalar-Agente }
elseif ($Probar)      { Probar-Recoleccion }
else                  { Enviar-Reporte }
