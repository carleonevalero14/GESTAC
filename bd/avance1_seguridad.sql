-- =====================================================================
-- GESTAC — Avance 1: Seguridad de sesión y control de acceso
-- ---------------------------------------------------------------------
-- Agrega a la tabla `users` el control de intentos fallidos, el bloqueo
-- temporal de la cuenta y el registro del último acceso.
--
-- Ejecutar una sola vez sobre la base de datos GESTAC (phpMyAdmin > SQL).
-- =====================================================================

ALTER TABLE `users`
  ADD COLUMN `intentos_fallidos` INT      NOT NULL DEFAULT 0    AFTER `rol`,
  ADD COLUMN `bloqueado_hasta`   DATETIME NULL     DEFAULT NULL AFTER `intentos_fallidos`,
  ADD COLUMN `ultimo_acceso`     DATETIME NULL     DEFAULT NULL AFTER `bloqueado_hasta`;
