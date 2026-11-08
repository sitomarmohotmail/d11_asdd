# d11assd — Drupal 11 con ASDD (Agentic Spec-Driven Development)

## Reglas para el agente
- **Nada de código sin spec aprobada** en `specs/` (ver `specs/README.md`).
- **Tests primero**: cada criterio de aceptación tiene al menos un test; se escriben
  antes de la implementación y deben fallar inicialmente.
- Una tarea está terminada solo si pasan los tests y `phpcs` está limpio.
- Solo se versiona código propio: `web/modules/custom`, `web/themes/custom`,
  `.claude/agents`, `.claude/rules`, `scripts/`,
  `config/sync`, `specs/` y la raíz del proyecto. Core y contrib se instalan con Composer.
- Commits en español, referenciando la spec: `spec-001: descripción`.

## Proceso ASDD (adopción parcial de efactory-llc/asdd)
- **Gastar pocos tokens**: cada spec tiene nivel **S / M / L** (ver
  `specs/README.md`). S sin agentes; M una pasada de `asdd-validacion`; L
  añade `asdd-seguridad` y/o `asdd-accesibilidad` solo si hay superficie de
  ataque o interacción nueva. Una pasada por agente; las correcciones se
  verifican con tests, no repitiendo el agente. Mensajes al usuario breves.
- Criterios **EARS** (uno por comportamiento) y prioridades DEBE / DEBERÍA / PODRÍA.
- QA = `scripts/check.sh --spec specs/NNN-….md [URL…]` (tests, phpcs,
  phpstan, linters CSS/JS/Twig, config:status, trazabilidad, axe + reflujo + foco). Durante el
  desarrollo, tests por grupo: `vendor/bin/phpunit --group GRUPO`.
- Hallazgos BAJOS → `specs/pendientes.md`, no se corrigen en la spec.
- **Accesibilidad WCAG 2.2 AA** en todo lo que toca HTML/Twig/CSS/JS
  (requisito RA, `.claude/rules/accesibilidad.md`). Node con nvm: `. ~/.nvm/nvm.sh`.
- Umbrales en `.claude/rules/quality-gates.md`. Solo la persona aprueba una spec.

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
vendor/bin/phpstan analyse                      # nivel 6 y rutas en phpstan.neon.dist
npm run lint                                    # stylelint + eslint + twig-cs-fixer (reglas del core)
npm run lint -- --fix                           # corrige lo automático
```

## Estructura
- `web/themes/custom/` — tema hijo de `bootstrap5`
- `web/modules/custom/` — módulos propios (componentes SDC)
- Despliegue posterior: Docker (variables `DB_*`, `DRUPAL_HASH_SALT`,
  `DRUPAL_TRUSTED_HOSTS` leídas en `settings.php`).
