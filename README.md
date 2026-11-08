# d11assd

Drupal 11 (perfil estándar) desarrollado con **ASDD** (Agentic Spec-Driven Development).

- Tema personalizado heredado de **Bootstrap 5**.
- Módulos personalizados con componentes (SDC).
- Especificaciones en [`specs/`](specs/README.md); reglas del flujo en [`CLAUDE.md`](CLAUDE.md).

## Puesta en marcha local

```bash
composer install
cp web/sites/example.settings.local.php web/sites/default/settings.local.php
# añadir credenciales de BD y hash_salt en settings.local.php
vendor/bin/drush site:install --existing-config -y
```

## Tests

```bash
cp phpunit.xml.dist phpunit.xml   # ajustar SIMPLETEST_DB y SIMPLETEST_BASE_URL
vendor/bin/phpunit --testsuite custom-unit
```
