@echo off
chcp 65001 >nul
title GESTAC - Desinstalador del agente CMDB

rem Si no somos administrador, relanzar elevado.
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo Solicitando permisos de administrador...
    powershell -NoProfile -Command "Start-Process -FilePath '%~f0' -Verb RunAs"
    exit /b
)

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0agente_cmdb.ps1" -Desinstalar
echo.
pause
