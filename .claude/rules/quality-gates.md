# Puertas de calidad (ASDD)

Umbrales que deciden si una spec puede pasar a la fase siguiente. Los aplican
los agentes `asdd-validacion`, `asdd-qa` y `asdd-seguridad`, y también el agente
principal antes de cambiar el `estado` de una spec.

Adaptado de [efactory-llc/asdd](https://github.com/efactory-llc/asdd) (MIT).

## Severidades
| Severidad | Significado | Efecto |
|---|---|---|
| BLOQUEANTE | No se puede usar la spec o el código tal como están | Para el ciclo. La persona debe intervenir. |
| ALTA | Riesgo serio de implementar algo incorrecto o inseguro | Hay que resolverlo antes de avanzar. |
| MEDIA | Problema de calidad que provocará retrabajo | Se debería resolver. Se puede avanzar con el visto bueno de la persona. |
| BAJA | Mejora menor | Se anota para la próxima revisión. |

## 1. Puerta de validación (borrador → aprobada)
Agente: `asdd-validacion`. Informe: `specs/NNN-validacion.md`.

| Condición | Acción |
|---|---|
| Algún hallazgo BLOQUEANTE o ALTO | La spec **no** puede pasar a `aprobada` |
| Requisito DEBE sin ningún criterio que lo cubra | BLOQUEANTE |
| Criterio que no sigue un patrón EARS | ALTA |
| Solo hallazgos MEDIOS o BAJOS | Se puede aprobar; la persona decide |

La aprobación (`estado: aprobada`) **siempre la da la persona**, nunca un agente.

## 2. Puerta de deuda técnica (antes de empezar una spec)
Antes de escribir tests de una spec nueva:

| Condición | Acción |
|---|---|
| `vendor/bin/phpunit --testsuite custom-unit` con fallos | BLOQUEANTE |
| `vendor/bin/phpunit --testsuite custom-kernel` con fallos | BLOQUEANTE |
| `vendor/bin/phpunit --testsuite custom-functional` con fallos | BLOQUEANTE |

Los fallos previos se arreglan primero, en su propia spec o commit.

## 3. Puerta de QA (en-curso → revisión de seguridad)
Agente: `asdd-qa`. Informe: `specs/NNN-qa.md`.

| Condición | Acción |
|---|---|
| Algún test de las suites `custom-*` falla | BLOQUEANTE |
| Requisito DEBE sin test que lo verifique | BLOQUEANTE |
| Criterio de aceptación sin test (o sin comprobación de calidad) | BLOQUEANTE |
| `vendor/bin/phpcs` con errores | BLOQUEANTE |
| `vendor/bin/phpstan analyse web/modules/custom web/themes/custom` con errores | ALTA |
| Requisito DEBERÍA sin test | MEDIA |
| Requisito PODRÍA sin test | BAJA (informativo) |
| Tabla de trazabilidad de la spec desactualizada | MEDIA |

Decisión: **SUPERADA** (sin BLOQUEANTES ni ALTOS), **SUPERADA CON AVISOS**
(solo MEDIOS o BAJOS) o **BLOQUEADA**.

## 4. Puerta de seguridad (→ implementada)
Agente: `asdd-seguridad`. Informe: `specs/NNN-seguridad.md`.

| Condición | Acción |
|---|---|
| Secreto o credencial en el código versionado | BLOQUEANTE |
| Ruta o formulario sin control de acceso | BLOQUEANTE |
| XSS: `\|raw`, `Markup::create()` o `#markup` con datos de usuario sin filtrar | ALTA |
| SQL construido concatenando entrada del usuario | ALTA |
| Vulnerabilidad conocida en `composer audit` que afecta al proyecto | ALTA |
| Formulario o acción que cambia estado sin protección CSRF | ALTA |
| Otros riesgos con controles compensatorios | MEDIA / BAJA |

Decisión: **SUPERADA**, **SUPERADA CON AVISOS** o **BLOQUEADA**.

## Specs ya implementadas (revisión retrospectiva)
Si se pasan los agentes sobre una spec ya `implementada`, los hallazgos no
reabren el estado automáticamente: se listan en el informe y la persona decide
si se corrigen (subiendo la `version` de la spec) o se aceptan.
