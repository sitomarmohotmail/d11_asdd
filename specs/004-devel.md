---
id: "004"
titulo: Módulo de desarrollo Devel
version: 1.0
estado: aprobada
nivel: S
tipo: modulo
proyecto: devel
---

# 004 · Módulo de desarrollo Devel

## Contexto
Devel proporciona herramientas de depuración para desarrollo local: volcado de
variables (`kint`/`dpm`), información de queries SQL, regeneración de caché y
utilidades de generación de contenido. Solo se activa en entornos de desarrollo;
no se despliega en producción.

## Requisitos

- **R1** *(DEBE)* — El sistema deberá instalar `drupal/devel` mediante Composer
  como dependencia de desarrollo (`--dev`).
- **R2** *(DEBE)* — El módulo `devel` deberá estar habilitado en el entorno local.
- **R3** *(DEBERÍA)* — La configuración exportada a `config/sync` deberá reflejar
  el estado del módulo.

## Criterios de aceptación (EARS)

- **CA1** *(R1 · R2 · check.sh)* — **Cuando** se ejecuta `drush config:status`,
  **el sistema deberá** mostrar 0 diferencias, con `devel` listado en
  `core.extension.yml`.

## Fuera de alcance
- Configuración de Kint o Webprofiler (submódulos opcionales).
- Habilitación en entornos de producción o staging.

## Plan técnico

1. `composer require --dev drupal/devel`
2. `vendor/bin/drush module:enable devel`
3. `vendor/bin/drush cex` → `config/sync`

Archivos modificados/creados:
- `composer.json` / `composer.lock`
- `config/sync/core.extension.yml`
- `config/sync/devel.settings.yml` (si aplica)

## Tareas
- [x] Implementación (Composer + Drush + config export)
- [x] `drush config:status` → 0 diferencias

## Puertas
- Validación: nivel S — sin pasada de agente
- QA: `config:status` limpio; `devel` instalado y habilitado

## Trazabilidad
| Requisito | Criterio | Test |
|-----------|----------|------|
| R1 · R2 · R3 | CA1 | `check.sh` (`config:status`) |
