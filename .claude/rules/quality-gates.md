# Puertas de calidad (ASDD)

Adaptado de [efactory-llc/asdd](https://github.com/efactory-llc/asdd) (MIT).
Nivel de cada spec (S/M/L) y ciclo: `specs/README.md`.

## Severidades
| Severidad | Efecto |
|---|---|
| BLOQUEANTE | Para el ciclo hasta resolverlo. |
| ALTA | Se resuelve en la spec antes de avanzar. |
| MEDIA | Se resuelve en la spec o la persona acepta el riesgo. |
| BAJA | No se corrige en la spec: una línea en `specs/pendientes.md`. |

## 1. Validación (borrador → aprobada) · niveles M y L
Una pasada de `asdd-validacion`.
- Requisito DEBE sin criterio, o criterio no verificable → BLOQUEANTE.
- Criterio fuera de EARS, ambigüedad que cambia la implementación, spec con
  interfaz sin RA → ALTA.
- El agente principal comprueba en el código cada BLOQUEANTE/ALTO y lo
  corrige. Solo se repite la pasada si un BLOQUEANTE no queda cubierto por
  un test.
- Solo la persona cambia el estado a `aprobada`.

## 2. QA (→ implementada) · todos los niveles
`scripts/check.sh --spec specs/NNN-….md [URL…]` en verde:
- suites `custom-unit`, `custom-kernel`, `custom-functional` sin fallos;
- `phpcs` y `phpstan` (nivel de `phpstan.neon.dist`) sin errores;
- `drush config:status` sin diferencias;
- todos los tests de la trazabilidad existen;
- si hay interfaz: axe 0 violaciones, sin scroll horizontal y foco visible
  (≥ 3:1) en todos los elementos, a 1280 y 320 px.

Además, cada requisito DEBE tiene al menos un criterio con test. Antes de
escribir tests de una spec nueva, las suites deben estar en verde.

## 3. Seguridad · nivel L con superficie de ataque
Una pasada de `asdd-seguridad`. BLOQUEANTE: secretos versionados, ruta o
formulario sin control de acceso. ALTA: XSS, SQL sin placeholders, CSRF,
vulnerabilidad en `composer audit` que afecte al proyecto.

## 4. Accesibilidad · nivel L con interacción nueva
Una pasada de `asdd-accesibilidad` (lo automático ya lo cubre `check.sh`).
ALTA: incumplimiento WCAG 2.2 AA en código propio. Lo heredado de core o
contrib que no se pueda corregir sin sobrescribir plantillas es OBS y no
bloquea. La lista manual la confirma la persona.

## Specs ya implementadas
Revisarlas de nuevo no reabre su estado: la persona decide si se corrige
(subiendo la `version`) o se acepta.
