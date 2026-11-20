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

## Plugin para VSCode

El repositorio incluye un plugin de Claude Code en `.claude/skills/asdd-pipeline/` que muestra un panel lateral con el estado del pipeline ASSD (specs por estado, diagrama de flujo y acceso rápido al dashboard web).

Se carga automáticamente al abrir el proyecto con Claude Code. Para abrirlo manualmente escribe `/assd_dashboard` en el chat.

### Configuración (solo la primera vez)

El plugin tiene dos opciones configurables por usuario:

```bash
claude plugin configure asdd-pipeline
```

| Campo | Descripción | Ejemplo |
|---|---|---|
| `specsDir` | Ruta absoluta al directorio `specs/` del proyecto | `/home/usuario/d11assd/specs` |
| `panelUrl` | URL del panel web ASSD. Si se deja vacío, el enlace no aparece. | `https://d11assd.local/asdd_tools/index.html` |

Los valores quedan guardados por usuario y no hay que repetirlos. Si `specsDir` no se configura, el plugin lo indicará en el propio panel.

## ASSD Dashboard (`asdd_tools/`)

Dashboard web con pipeline visual, editor de specs y chat integrado con Claude.

Acceso local: `http://d11assd.local/asdd_tools/`

### Chat con Claude

El dashboard incluye un chat con la API de Anthropic con contexto del proyecto inyectado automáticamente. Requiere una API key:

```bash
echo "sk-ant-TUKEY" > asdd_tools/.api-key
```

El fichero `.api-key` está en `.gitignore` — cada desarrollador pone la suya. No hace falta ningún servidor adicional; el chat funciona directamente a través de PHP.

## Tests

```bash
cp phpunit.xml.dist phpunit.xml   # ajustar SIMPLETEST_DB y SIMPLETEST_BASE_URL
vendor/bin/phpunit --testsuite custom-unit
```
