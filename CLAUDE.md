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
- Criterios de aceptación en formato **EARS** y requisitos con prioridad
  DEBE / DEBERÍA / PODRÍA (ver `specs/README.md`).
- Subagentes en `.claude/agents/`, que se lanzan a mano en su paso:
  - `asdd-validacion` — sobre la spec en borrador, antes de pedir aprobación.
  - `asdd-qa` — tras implementar (tests, phpcs, phpstan y cobertura por requisito).
  - `asdd-seguridad` — tras QA.
  - `asdd-accesibilidad` — tras seguridad, si la spec toca HTML/Twig/CSS/JS.
- Umbrales en `.claude/rules/quality-gates.md`. Una spec solo pasa a
  `implementada` con QA, seguridad y accesibilidad sin hallazgos BLOQUEANTES ni ALTOS.
- **Accesibilidad WCAG 2.2 AA** obligatoria en el tema, módulos y componentes
  con HTML, CSS o JS: requisito RA en la spec y reglas en
  `.claude/rules/accesibilidad.md`. axe-core: `npm run a11y -- URL`
  (Node con nvm: `. ~/.nvm/nvm.sh`).
- Solo la persona aprueba una spec; ningún agente cambia el estado a `aprobada`.

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
