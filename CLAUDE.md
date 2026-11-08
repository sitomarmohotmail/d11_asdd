# d11assd — Drupal 11 con ASDD (Agentic Spec-Driven Development)

## Reglas para el agente
- **Nada de código sin spec aprobada** en `specs/` (ver `specs/README.md`).
- **Tests primero**: cada criterio de aceptación tiene al menos un test; se escriben
  antes de la implementación y deben fallar inicialmente.
- Una tarea está terminada solo si pasan los tests y `phpcs` está limpio.
- Solo se versiona código propio: `web/modules/custom`, `web/themes/custom`,
  `config/sync`, `specs/` y la raíz del proyecto. Core y contrib se instalan con Composer.
- Commits en español, referenciando la spec: `spec-001: descripción`.

## Entorno local (debug)
- URL: http://d11assd.local (Apache + PHP 8.5 + MySQL 8.4 del sistema)
- Drush: `vendor/bin/drush`
- Debug: `settings.local.php` (no versionado) + development_settings (Twig debug,
  sin caché). Xdebug a demanda en el puerto 9003 (trigger `XDEBUG_TRIGGER`).
- Configuración: `vendor/bin/drush cex` / `cim` → `config/sync`.

## Comandos de calidad
```bash
vendor/bin/phpunit --testsuite custom-unit
vendor/bin/phpunit --testsuite custom-kernel
vendor/bin/phpunit --testsuite custom-functional
vendor/bin/phpunit --testsuite contrib          # tests de módulos contribuidos
vendor/bin/phpunit web/modules/contrib/MODULO   # un contrib concreto
vendor/bin/phpcs                                # estándares Drupal en custom/
vendor/bin/phpstan analyse web/modules/custom web/themes/custom
```

## Estructura
- `web/themes/custom/` — tema hijo de `bootstrap5`
- `web/modules/custom/` — módulos propios (componentes SDC)
- Despliegue posterior: Docker (variables `DB_*`, `DRUPAL_HASH_SALT`,
  `DRUPAL_TRUSTED_HOSTS` leídas en `settings.php`).
